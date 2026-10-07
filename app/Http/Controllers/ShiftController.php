<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\ShiftTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ShiftController extends Controller
{
    /**
     * Display Active Shift & Shift History.
     */
    public function index(): View
    {
        $activeShift = Shift::with(['openedBy', 'closedBy'])
            ->whereNull('closed_at')
            ->latest()
            ->first();

        $shiftExpenses = collect();
        $openingFloat = 5000.00;
        $cashRevenue = 0.00;
        $totalExpenses = 0.00;
        $expectedCash = 5000.00;

        if ($activeShift) {
            $openingFloat = (float) ($activeShift->opening_float ?? 5000.00);

            $shiftExpenses = \App\Models\Expense::where('shift_id', $activeShift->id)
                ->orWhere(function ($q) use ($activeShift) {
                    $q->whereNull('shift_id')
                      ->where('payment_source', 'cash_drawer')
                      ->where('created_at', '>=', $activeShift->opened_at);
                })
                ->latest()
                ->get();

            $totalExpenses = (float) $shiftExpenses->sum('amount');

            $folios = \App\Models\Folio::where('created_at', '>=', $activeShift->opened_at)->get();
            $cashRevenue = 0.0;
            foreach ($folios as $f) {
                if ($f->payment_method === 'cash') {
                    $cashRevenue += (float) ($f->cash_tendered > 0 ? max(0, $f->cash_tendered - $f->change_due) : $f->net_total);
                } elseif ($f->payment_method === 'split') {
                    $cashRevenue += (float) max(0, $f->cash_tendered - $f->change_due);
                }
            }

            // Also check walk-in POS orders in cash
            $posCash = (float) \App\Models\Order::where('type', 'walkin_pos')
                ->where('payment_method', 'cash')
                ->where('created_at', '>=', $activeShift->opened_at)
                ->sum('total');

            $totalCashCollected = $cashRevenue + $posCash;
            $expectedCash = max(0, $openingFloat + $totalCashCollected - $totalExpenses);
        }

        $pastShifts = Shift::with(['openedBy', 'closedBy'])
            ->whereNotNull('closed_at')
            ->latest('closed_at')
            ->paginate(10);

        return view('shifts.index', compact(
            'activeShift',
            'pastShifts',
            'shiftExpenses',
            'openingFloat',
            'cashRevenue',
            'totalExpenses',
            'expectedCash'
        ));
    }

    /**
     * Open a new Shift.
     */
    public function openShift(Request $request): RedirectResponse
    {
        $existing = Shift::whereNull('closed_at')->first();
        if ($existing) {
            return back()->with('error', 'There is already an open shift in progress.');
        }

        $validated = $request->validate([
            'opening_float' => 'nullable|numeric|min:0|max:100000',
        ]);

        $now = now();
        $type = Shift::typeForTime($now);
        $float = (float) ($validated['opening_float'] ?? 5000.00);

        Shift::create([
            'opened_by' => Auth::id() ?? 1,
            'shift_type' => $type,
            'shift_date' => $now->toDateString(),
            'opened_at' => $now,
            'opening_float' => $float,
            'is_frozen' => false,
            'room_revenue' => 0,
            'kitchen_revenue' => 0,
            'gross_revenue' => 0,
            'total_expenses' => 0,
            'cash_total' => $float,
            'gcash_total' => 0,
            'expected_cash' => $float,
            'cash_variance' => 0,
            'denomination_count' => [
                '1000' => 0, '500' => 0, '200' => 0,
                '100' => 0, '50' => 0, '20' => 0, 'coins' => 0,
            ],
            'handoff_notes' => null,
        ]);

        return redirect()->route('shifts.index')
            ->with('success', "New " . strtoupper($type) . " Shift opened successfully with ₱" . number_format($float, 2) . " float!");
    }

    /**
     * Log a Shift Operating Expense / Petty Cash Outlay from Cash Drawer.
     */
    public function storeExpense(Request $request): RedirectResponse
    {
        $activeShift = Shift::whereNull('closed_at')->latest()->first();
        if (!$activeShift) {
            return back()->with('error', 'Cannot record expense: No active shift is currently open.');
        }

        $validated = $request->validate([
            'description'       => 'required|string|max:200',
            'amount'            => 'required|numeric|min:1|max:500000',
            'category'          => 'required|string|max:50',
            'receipt_reference' => 'nullable|string|max:50',
            'notes'             => 'nullable|string|max:500',
        ]);

        $voucher = 'EXP-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        $expense = \App\Models\Expense::create([
            'shift_id'          => $activeShift->id,
            'user_id'           => Auth::id(),
            'voucher_number'    => $voucher,
            'expense_date'      => now()->toDateString(),
            'category'          => $validated['category'],
            'description'       => $validated['description'],
            'amount'            => $validated['amount'],
            'payment_source'    => 'cash_drawer',
            'receipt_reference' => $validated['receipt_reference'] ?? null,
            'status'            => 'approved',
            'notes'             => $validated['notes'] ?? 'Front Desk Shift Petty Cash Outlay',
        ]);

        $activeShift->total_expenses += (float) $validated['amount'];
        $activeShift->save();

        return redirect()->route('shifts.index')
            ->with('success', "Shift expense of ₱" . number_format($expense->amount, 2) . " ({$expense->description}) logged from cash drawer.");
    }

    /**
     * Close Active Shift and record drawer cash count & handoff notes.
     */
    public function closeShift(Request $request, Shift $shift): RedirectResponse
    {
        if ($shift->closed_at) {
            return back()->with('error', 'This shift has already been closed.');
        }

        $validated = $request->validate([
            'denominations' => 'nullable|array',
            'handoff_notes' => 'nullable|string|max:1000',
        ]);

        $denoms = $validated['denominations'] ?? [];
        $calculatedCashDrawer =
            (($denoms['1000'] ?? 0) * 1000) +
            (($denoms['500'] ?? 0) * 500) +
            (($denoms['200'] ?? 0) * 200) +
            (($denoms['100'] ?? 0) * 100) +
            (($denoms['50'] ?? 0) * 50) +
            (($denoms['20'] ?? 0) * 20) +
            (($denoms['coins'] ?? 0) * 1);

        // Compute authoritative shift financial totals
        $openingFloat = (float) ($shift->opening_float ?? 5000.00);

        $shiftExpenses = \App\Models\Expense::where('shift_id', $shift->id)
            ->orWhere(function ($q) use ($shift) {
                $q->whereNull('shift_id')
                  ->where('payment_source', 'cash_drawer')
                  ->whereBetween('created_at', [$shift->opened_at, now()]);
            })
            ->get();
        $totalExpenses = (float) $shiftExpenses->sum('amount');

        $folios = \App\Models\Folio::whereBetween('created_at', [$shift->opened_at, now()])->get();
        $cashSales = 0.0;
        $gcashSales = 0.0;
        foreach ($folios as $f) {
            if ($f->payment_method === 'cash') {
                $cashSales += (float) ($f->cash_tendered > 0 ? max(0, $f->cash_tendered - $f->change_due) : $f->net_total);
            } elseif ($f->payment_method === 'split') {
                $cashSales += (float) max(0, $f->cash_tendered - $f->change_due);
                $gcashSales += (float) $f->gcash_amount;
            } elseif ($f->payment_method === 'gcash') {
                $gcashSales += (float) $f->net_total;
            }
        }

        $posCash = (float) \App\Models\Order::where('type', 'walkin_pos')
            ->where('payment_method', 'cash')
            ->whereBetween('created_at', [$shift->opened_at, now()])
            ->sum('total');

        $totalCashRevenue = $cashSales + $posCash;
        $expectedCash = max(0, $openingFloat + $totalCashRevenue - $totalExpenses);
        $variance = $calculatedCashDrawer - $expectedCash;

        $shift->update([
            'closed_by' => Auth::id() ?? 1,
            'closed_at' => now(),
            'denomination_count' => $denoms,
            'opening_float' => $openingFloat,
            'total_expenses' => $totalExpenses,
            'cash_total' => $calculatedCashDrawer,
            'expected_cash' => $expectedCash,
            'cash_variance' => $variance,
            'handoff_notes' => $validated['handoff_notes'] ?? null,
        ]);

        $statusMsg = $variance === 0.0 ? "Exact balance" : ($variance > 0 ? "Overage: +₱" . number_format($variance, 2) : "Shortage: -₱" . number_format(abs($variance), 2));

        return redirect()->route('shifts.index')
            ->with('success', "Shift closed successfully. Counted: ₱" . number_format($calculatedCashDrawer, 2) . " | Expected: ₱" . number_format($expectedCash, 2) . " ({$statusMsg})");
    }

    /**
     * 80mm Printable Shift Remittance Slip.
     */
    public function showRemittanceSlip(Shift $shift): View
    {
        $shift->load(['openedBy', 'closedBy']);

        $shiftStart = $shift->opened_at;
        $shiftEnd = $shift->closed_at ?? now();

        $folios = \App\Models\Folio::whereBetween('created_at', [$shiftStart, $shiftEnd])->get();
        $cashSales = (float) $folios->where('payment_method', 'cash')->sum('net_total');
        $gcashSales = (float) $folios->where('payment_method', 'gcash')->sum('net_total');
        $totalRevenue = $cashSales + $gcashSales;

        // Shift Operating Expenses
        $shiftExpenses = \App\Models\Expense::where('shift_id', $shift->id)
            ->orWhere(function ($q) use ($shiftStart, $shiftEnd) {
                $q->whereNull('shift_id')
                  ->where('payment_source', 'cash_drawer')
                  ->whereBetween('created_at', [$shiftStart, $shiftEnd]);
            })
            ->get();
        $totalExpenses = (float) $shiftExpenses->sum('amount');

        $openingFloat = (float) ($shift->opening_float ?? 5000.00);
        $expectedCash = $shift->expected_cash > 0 ? (float) $shift->expected_cash : max(0, $openingFloat + $cashSales - $totalExpenses);
        $actualCash = (float) ($shift->cash_total > 0 ? $shift->cash_total : $expectedCash);
        $variance = (float) ($shift->cash_variance ?? ($actualCash - $expectedCash));

        $rooms = \App\Models\Room::all();
        $occupiedCount = $rooms->where('status', 'occupied')->count();
        $availableCount = $rooms->where('status', 'available')->count();
        $maintenanceCount = $rooms->where('status', 'maintenance')->count();

        $incomingCashier = Auth::user()->name ?? 'Next Duty Operator';

        return view('shifts.remittance_slip', compact(
            'shift',
            'cashSales',
            'gcashSales',
            'totalRevenue',
            'shiftExpenses',
            'totalExpenses',
            'openingFloat',
            'expectedCash',
            'actualCash',
            'variance',
            'occupiedCount',
            'availableCount',
            'maintenanceCount',
            'incomingCashier'
        ));
    }
}

