<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Folio;
use App\Models\Order;
use App\Models\Room;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingController extends Controller
{
    /**
     * Profit & Loss (P&L) Statement & Executive BI.
     */
    public function pnl(Request $request): View
    {
        $range = $request->query('range', 'this_week');
        $customStart = $request->query('start_date');
        $customEnd = $request->query('end_date');

        [$startDate, $endDate, $rangeLabel] = $this->resolveDateRange($range, $customStart, $customEnd);

        // 1. Folios in range
        $settledFolios = Folio::with(['room', 'guest', 'cashier'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $roomLodgingRev = (float) $settledFolios->sum('room_charge');
        $overtimeRev = (float) $settledFolios->sum(fn($f) => $f->extra_hours * 130);
        $surchargesRev = (float) $settledFolios->sum(fn($f) => ($f->extra_persons * 200) + ($f->extra_towels * 100) + ($f->extra_bedding * 150));
        $folioPosRev = (float) $settledFolios->sum('pos_total');

        // Walk-in POS orders in range
        $walkinPosRev = (float) Order::where('type', 'walkin_pos')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total');

        $totalDiningRev = $folioPosRev + $walkinPosRev;
        $grossRevenue = $roomLodgingRev + $overtimeRev + $surchargesRev + $totalDiningRev;

        // Deductions & Allowances
        $seniorPwdDiscounts = (float) $settledFolios->sum('discount_amount');
        $lossSlips = (float) $settledFolios->where('force_checkout', true)->sum('net_total');

        $netRevenueCollected = max(0, $grossRevenue - $seniorPwdDiscounts - $lossSlips);

        // 12% Inclusive VAT Split
        $netVatableSales = round($grossRevenue / 1.12, 2);
        $vatOutputAmount = round($grossRevenue - $netVatableSales, 2);

        // 2. Operating Expenses in range
        $expenses = Expense::whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('status', 'approved')
            ->get();

        $expensesByCategory = [
            'utilities'            => (float) $expenses->where('category', 'utilities')->sum('amount'),
            'laundry_linens'       => (float) $expenses->where('category', 'laundry_linens')->sum('amount'),
            'maintenance_repairs'  => (float) $expenses->where('category', 'maintenance_repairs')->sum('amount'),
            'kitchen_inventory'    => (float) $expenses->where('category', 'kitchen_inventory')->sum('amount'),
            'staff_allowances'     => (float) $expenses->where('category', 'staff_allowances')->sum('amount'),
            'administrative'       => (float) $expenses->where('category', 'administrative')->sum('amount'),
            'other'                => (float) $expenses->where('category', 'other')->sum('amount'),
        ];

        $totalOpex = array_sum($expensesByCategory);
        $netOperatingProfit = $netRevenueCollected - $totalOpex;

        // KPI Counts
        $totalCheckIns = $settledFolios->count();
        $totalRooms = Room::where('is_staff_quarters', false)->count();

        // Payment mix
        $cashCollections = (float) $settledFolios->where('payment_method', 'cash')->sum('net_total') + (float) Order::where('type', 'walkin_pos')->where('payment_method', 'cash')->whereBetween('created_at', [$startDate, $endDate])->sum('total');
        $gcashCollections = (float) $settledFolios->where('payment_method', 'gcash')->sum('net_total') + (float) Order::where('type', 'walkin_pos')->where('payment_method', 'gcash')->whereBetween('created_at', [$startDate, $endDate])->sum('total');

        return view('accounting.pnl', compact(
            'range', 'rangeLabel', 'startDate', 'endDate',
            'roomLodgingRev', 'overtimeRev', 'surchargesRev', 'totalDiningRev', 'grossRevenue',
            'seniorPwdDiscounts', 'lossSlips', 'netRevenueCollected',
            'netVatableSales', 'vatOutputAmount',
            'expensesByCategory', 'totalOpex', 'netOperatingProfit',
            'totalCheckIns', 'totalRooms',
            'cashCollections', 'gcashCollections',
            'settledFolios', 'expenses'
        ));
    }

    /**
     * Operating Expenses & Petty Cash Ledger.
     */
    public function expensesIndex(Request $request): View
    {
        $selectedCategory = $request->query('category', 'all');
        $query = Expense::with('user')->latest('expense_date');

        if ($selectedCategory !== 'all') {
            $query->where('category', $selectedCategory);
        }

        $expenses = $query->paginate(20);

        $thisMonthTotal = (float) Expense::whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->where('status', 'approved')
            ->sum('amount');

        $todayTotal = (float) Expense::whereDate('expense_date', today())
            ->where('status', 'approved')
            ->sum('amount');

        $categories = [
            'utilities'           => 'Utilities (Power, Water, Internet)',
            'laundry_linens'      => 'Housekeeping & Laundry Linens',
            'maintenance_repairs' => 'Repairs & Property Maintenance',
            'kitchen_inventory'   => 'Kitchen & F&B Grocery Restock',
            'staff_allowances'    => 'Staff Wages & Duty Allowances',
            'administrative'      => 'Administrative & Government Permits',
            'other'               => 'Other Sundry Expenses',
        ];

        return view('accounting.expenses', compact(
            'expenses', 'selectedCategory', 'thisMonthTotal', 'todayTotal', 'categories'
        ));
    }

    /**
     * Store New Expense Voucher.
     */
    public function storeExpense(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'expense_date'      => 'required|date',
            'category'          => 'required|string|max:50',
            'description'       => 'required|string|max:255',
            'amount'            => 'required|numeric|min:1',
            'payment_source'    => 'required|in:cash_drawer,petty_cash,bank_transfer',
            'receipt_reference' => 'nullable|string|max:50',
            'notes'             => 'nullable|string|max:500',
        ]);

        $voucherNumber = Expense::generateVoucherNumber();

        Expense::create([
            'user_id'           => Auth::id() ?? 1,
            'voucher_number'    => $voucherNumber,
            'expense_date'      => $validated['expense_date'],
            'category'          => $validated['category'],
            'description'       => $validated['description'],
            'amount'            => $validated['amount'],
            'payment_source'    => $validated['payment_source'],
            'receipt_reference' => $validated['receipt_reference'] ?? null,
            'status'            => 'approved',
            'notes'             => $validated['notes'] ?? null,
        ]);

        return redirect()->route('admin.accounting.expenses')
            ->with('success', "Expense voucher {$voucherNumber} created for ₱" . number_format($validated['amount'], 2) . ".");
    }

    /**
     * Void or Delete Expense.
     */
    public function deleteExpense(Expense $expense): RedirectResponse
    {
        $voucher = $expense->voucher_number;
        $expense->delete();

        return redirect()->route('admin.accounting.expenses')
            ->with('success', "Expense voucher {$voucher} has been removed from ledger.");
    }

    /**
     * General Financial Audit Journal & Transaction Stream.
     */
    public function ledgerIndex(Request $request): View
    {
        $folios = Folio::with(['room', 'guest', 'cashier'])
            ->latest()
            ->take(50)
            ->get();

        $walkinOrders = Order::with('items')
            ->where('type', 'walkin_pos')
            ->latest()
            ->take(30)
            ->get();

        $expenses = Expense::with('user')
            ->latest('expense_date')
            ->take(30)
            ->get();

        return view('accounting.ledger', compact('folios', 'walkinOrders', 'expenses'));
    }

    /**
     * Shift Reconciliations & Drawer Audits.
     */
    public function shiftsIndex(): View
    {
        $shifts = Shift::with(['openedBy', 'closedBy'])
            ->latest('shift_date')
            ->paginate(20);

        return view('accounting.shifts', compact('shifts'));
    }

    /**
     * Audited Loss Slips & Bad Debt Ledger (FCE-######).
     */
    public function lossesIndex(): View
    {
        $lossFolios = Folio::with(['room', 'guest', 'cashier', 'forcedBy'])
            ->where('force_checkout', true)
            ->latest()
            ->paginate(20);

        $totalWrittenOff = (float) Folio::where('force_checkout', true)->sum('net_total');

        return view('accounting.losses', compact('lossFolios', 'totalWrittenOff'));
    }

    /**
     * Export P&L Financial Report to CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $range = $request->query('range', 'this_month');
        [$startDate, $endDate, $rangeLabel] = $this->resolveDateRange($range, $request->query('start_date'), $request->query('end_date'));

        $folios = Folio::with(['room', 'guest'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $expenses = Expense::whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('status', 'approved')
            ->get();

        $filename = 'Sedona_Court_Financial_Report_' . $startDate->format('Ymd') . '_to_' . $endDate->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($folios, $expenses, $rangeLabel, $startDate, $endDate) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['SEDONA COURT TRAVELLER\'S INN - OFFICIAL FINANCIAL STATEMENT']);
            fputcsv($handle, ['Period', $rangeLabel, 'From', $startDate->format('m/d/Y'), 'To', $endDate->format('m/d/Y')]);
            fputcsv($handle, []);

            fputcsv($handle, ['REVENUE STREAMS', 'AMOUNT (PHP)']);
            fputcsv($handle, ['Room Lodging Base Revenue', number_format($folios->sum('room_charge'), 2)]);
            fputcsv($handle, ['Overtime & Excess Surcharges (₱130/hr)', number_format($folios->sum(fn($f) => $f->extra_hours * 130), 2)]);
            fputcsv($handle, ['POS Dining & Beverage Sales', number_format($folios->sum('pos_total'), 2)]);
            fputcsv($handle, ['GROSS REVENUE', number_format($folios->sum('gross_total'), 2)]);
            fputcsv($handle, ['Less: Senior / PWD Discounts (20%)', number_format($folios->sum('discount_amount'), 2)]);
            fputcsv($handle, ['Less: Audited Loss Slips (FCE-######)', number_format($folios->where('force_checkout', true)->sum('net_total'), 2)]);
            fputcsv($handle, ['NET REVENUE COLLECTED', number_format($folios->sum('net_total'), 2)]);
            fputcsv($handle, []);

            fputcsv($handle, ['OPERATING EXPENSES (OPEX)', 'AMOUNT (PHP)']);
            foreach ($expenses->groupBy('category') as $cat => $items) {
                fputcsv($handle, [ucwords(str_replace('_', ' ', $cat)), number_format($items->sum('amount'), 2)]);
            }
            fputcsv($handle, ['TOTAL OPERATING EXPENSES', number_format($expenses->sum('amount'), 2)]);
            fputcsv($handle, []);

            $netProfit = $folios->sum('net_total') - $expenses->sum('amount');
            fputcsv($handle, ['NET OPERATING PROFIT (EBITDA)', number_format($netProfit, 2)]);

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function resolveDateRange(string $range, ?string $customStart, ?string $customEnd): array
    {
        return match ($range) {
            'today' => [
                now()->startOfDay(),
                now()->endOfDay(),
                'Today (' . now()->format('M d, Y') . ')',
            ],
            'this_week' => [
                now()->startOfWeek(),
                now()->endOfWeek(),
                'This Week (' . now()->startOfWeek()->format('M d') . ' - ' . now()->endOfWeek()->format('M d, Y') . ')',
            ],
            'this_month' => [
                now()->startOfMonth(),
                now()->endOfMonth(),
                'This Month (' . now()->format('F Y') . ')',
            ],
            'last_month' => [
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth(),
                'Last Month (' . now()->subMonth()->format('F Y') . ')',
            ],
            'custom' => [
                $customStart ? Carbon::parse($customStart)->startOfDay() : now()->startOfMonth(),
                $customEnd ? Carbon::parse($customEnd)->endOfDay() : now()->endOfDay(),
                'Custom Range (' . ($customStart ?? '') . ' to ' . ($customEnd ?? '') . ')',
            ],
            default => [
                now()->startOfWeek(),
                now()->endOfWeek(),
                'This Week',
            ],
        };
    }
}
