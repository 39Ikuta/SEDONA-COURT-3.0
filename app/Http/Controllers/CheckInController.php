<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Order;
use App\Models\PosItem;
use App\Models\Room;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CheckInController extends Controller
{
    /**
     * Process Check-In for a room.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'guest_name' => 'nullable|string|max:255',
            'headcount' => 'required|integer|min:1',
            'rate_tier' => 'required|in:3h,6h,12h,24h,promo',
            'is_senior' => 'nullable|boolean',
            'is_pwd' => 'nullable|boolean',
            'booking_id' => 'nullable|exists:bookings,id',
            'checked_in_at' => 'nullable|date',
        ]);

        $room = Room::findOrFail($validated['room_id']);

        if ($room->status !== 'available' && !$request->has('booking_id')) {
            return back()->with('error', 'Room ' . $room->number . ' is not available for check-in.');
        }

        DB::beginTransaction();
        try {
            $guestName = $validated['guest_name'] ?: 'Guest Room ' . $room->number;

            $guest = Guest::create([
                'name' => $guestName,
                'contact' => 'WALKIN-' . time(),
                'headcount' => $validated['headcount'],
                'is_senior' => $request->boolean('is_senior'),
                'is_pwd' => $request->boolean('is_pwd'),
            ]);

            $hours = $room->getTierHours($validated['rate_tier']);
            $checkedInAt = !empty($validated['checked_in_at']) ? \Carbon\Carbon::parse($validated['checked_in_at']) : now();
            $expectedCheckoutAt = $checkedInAt->copy()->addHours($hours);
            $roomRate = $room->getRateForTier($validated['rate_tier']);

            $hasDiscount = $guest->isEligibleForDiscount();
            $discountAmount = $hasDiscount ? round($roomRate * 0.20, 2) : 0;
            $netTotal = $roomRate - $discountAmount;

            $folio = Folio::create([
                'room_id' => $room->id,
                'guest_id' => $guest->id,
                'user_id' => Auth::id() ?? 1,
                'booking_id' => $validated['booking_id'] ?? null,
                'transaction_id' => Folio::generateTransactionId(),
                'rate_tier' => $validated['rate_tier'],
                'checked_in_at' => $checkedInAt,
                'expected_checkout_at' => $expectedCheckoutAt,
                'status' => 'active',
                'extra_persons' => 0,
                'extra_bedding' => 0,
                'extra_towels' => 0,
                'extra_hours' => 0,
                'pos_items' => [],
                'room_charge' => $roomRate,
                'surcharge_total' => 0,
                'pos_total' => 0,
                'gross_total' => $roomRate,
                'discount_amount' => $discountAmount,
                'senior_pwd_discount' => $hasDiscount,
                'net_total' => $netTotal,
                'payment_method' => 'cash',
            ]);

            $room->update(['status' => 'occupied']);

            if (!empty($validated['booking_id'])) {
                Booking::where('id', $validated['booking_id'])->update(['status' => 'checked_in']);
            }

            // Update shift
            $activeShift = Shift::whereNull('closed_at')->latest()->first();
            if ($activeShift) {
                $activeShift->room_revenue += $netTotal;
                $activeShift->gross_revenue += $netTotal;
                $activeShift->cash_total += $netTotal;
                $activeShift->save();
            }

            DB::commit();

            return redirect()->route('dashboard')
                ->with('success', "Room {$room->number} checked in successfully! (Folio: {$folio->transaction_id})");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Check-in failed: ' . $e->getMessage());
        }
    }

    /**
     * Show Exact Guest Check Out Processing Page matching old HMS.
     */
    /**
     * Show Exact Guest Check Out Processing Page matching old HMS.
     */
    public function showCheckout(Folio $folio): View
    {
        $folio->load(['room', 'guest', 'orders.items']);

        $room = $folio->room;
        $guest = $folio->guest;

        $checkInTime = $folio->checked_in_at;
        $checkOutTime = now();

        $tierHours = $room ? $room->getTierHours($folio->rate_tier) : 3;
        if ($tierHours == 0) $tierHours = 3;

        $xtendHours = (int) ($folio->extra_hours ?? 0);
        $totalBookedHours = $tierHours + $xtendHours;

        $actualHoursDiff = max(1, (int) ceil($checkInTime->diffInMinutes($checkOutTime) / 60));
        $excessHours = max(0, $actualHoursDiff - $totalBookedHours);

        $addHourUnitPrice = 130; // ₱130 per excess hour in old Sedona system
        $addHoursRate = ($xtendHours + $excessHours) * $addHourUnitPrice;

        $roomRate = (float) $folio->room_charge;
        if ($roomRate <= 0 && $room) {
            $roomRate = $room->getRateForTier($folio->rate_tier);
        }

        // Add Ons from Folio pos_items and attached orders
        $addOnItems = [];
        $addOnsTotal = 0;

        if (!empty($folio->pos_items) && is_array($folio->pos_items)) {
            foreach ($folio->pos_items as $p) {
                $addOnItems[] = [
                    'name' => $p['name'] ?? 'Item',
                    'qty' => $p['qty'] ?? 1,
                    'price' => (float) ($p['price'] ?? 0),
                    'amount' => (float) ($p['subtotal'] ?? 0),
                ];
                $addOnsTotal += (float) ($p['subtotal'] ?? 0);
            }
        }

        $subTotal = $roomRate + $addHoursRate + $addOnsTotal;
        $totalCharge = $subTotal;
        $totalDiscount = (float) ($folio->discount_amount ?? 0);
        $amountToPay = max(0, $totalCharge - $totalDiscount);
        $deposit = (float) ($folio->security_deposit ?? 0.00);

        $applyDeposit = ($folio->deposit_status !== 'not_applied');
        $depositApplied = $applyDeposit ? min($deposit, $amountToPay) : 0.00;
        $remainingDeposit = $applyDeposit ? max(0, $deposit - $amountToPay) : $deposit;
        $totalAmountToPay = $applyDeposit ? max(0, $amountToPay - $deposit) : $amountToPay;

        $discountOptions = \App\Services\DiscountService::getOptionsForRoom($room->type ?? 'Classic Room', $folio->rate_tier);

        $allOccupied = Room::where('status', 'occupied')->with('activeFolio.guest')->get();

        return view('checkout.process', compact(
            'folio', 'room', 'guest', 'checkInTime', 'checkOutTime',
            'tierHours', 'xtendHours', 'totalBookedHours', 'actualHoursDiff', 'excessHours', 'addHoursRate',
            'roomRate', 'addOnItems', 'addOnsTotal', 'subTotal',
            'totalCharge', 'totalDiscount', 'discountOptions', 'amountToPay', 'deposit',
            'applyDeposit', 'depositApplied', 'remainingDeposit',
            'totalAmountToPay', 'allOccupied'
        ));
    }

    /**
     * Process Checkout Settlement & Form Submission.
     */
    public function processCheckout(Request $request, Folio $folio): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method'   => 'nullable|string|in:cash,gcash,split',
            'cash_tendered'    => 'nullable|numeric|min:0',
            'gcash_amount'     => 'nullable|numeric|min:0',
            'gcash_reference'  => 'nullable|string|max:50',
            'customer_name'    => 'nullable|string|max:150',
            'security_deposit' => 'nullable|numeric|min:0',
            'apply_deposit'    => 'nullable',
            'discount_type'    => 'nullable|string|in:none,senior,pwd,dc,NONE,SENIOR,PWD,DC',
            'discount_id_ref'  => 'nullable|string|max:100',
            'checked_in_at'    => 'nullable|date',
            'room_charge'      => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $folio->load(['room', 'guest', 'orders.items']);

            $room = $folio->room;
            $tierHours = $room ? $room->getTierHours($folio->rate_tier) : 3;
            if ($tierHours == 0) $tierHours = 3;

            $xtendHours = (int) ($folio->extra_hours ?? 0);
            $totalBookedHours = $tierHours + $xtendHours;

            if ($request->filled('checked_in_at')) {
                $checkInTime = \Carbon\Carbon::parse($request->input('checked_in_at'));
                $folio->checked_in_at = $checkInTime;
                $folio->expected_checkout_at = $checkInTime->copy()->addHours($totalBookedHours);
            } else {
                $checkInTime = $folio->checked_in_at;
            }

            $checkOutTime = now();

            $actualHoursDiff = max(1, (int) ceil($checkInTime->diffInMinutes($checkOutTime) / 60));
            $excessHours = max(0, $actualHoursDiff - $totalBookedHours);

            $addHourUnitPrice = 130;
            $surchargeTotal = ($xtendHours + $excessHours) * $addHourUnitPrice;

            if (array_key_exists('room_charge', $validated) && $validated['room_charge'] !== null) {
                $roomRate = (float) $validated['room_charge'];
                $folio->room_charge = $roomRate;
            } else {
                $roomRate = (float) $folio->room_charge;
                if ($roomRate <= 0 && $room) {
                    $roomRate = $room->getRateForTier($folio->rate_tier);
                }
            }

            $addOnsTotal = 0;
            if (!empty($folio->pos_items) && is_array($folio->pos_items)) {
                foreach ($folio->pos_items as $p) {
                    $addOnsTotal += (float) ($p['subtotal'] ?? 0);
                }
            }

            // Apply Discount if specified
            if ($request->has('discount_type')) {
                $dType = strtolower($request->input('discount_type'));
                if ($dType === 'none' || empty($dType)) {
                    $folio->discount_type = 'NONE';
                    $folio->discount_id_ref = null;
                    $folio->discount_amount = 0.00;
                    $folio->senior_pwd_discount = false;
                } else {
                    $roomType = $room->type ?? 'Classic Room';
                    $discountAmount = \App\Services\DiscountService::getDiscountAmount($dType, $roomType, $folio->rate_tier);
                    $folio->discount_type = strtoupper($dType);
                    $folio->discount_id_ref = $request->input('discount_id_ref') ?: 'ID-VERIFIED';
                    $folio->discount_amount = $discountAmount;
                    $folio->senior_pwd_discount = in_array($folio->discount_type, ['SENIOR', 'PWD']);
                }
            }

            $grossTotal = $roomRate + $surchargeTotal + $addOnsTotal;
            $authoritativeDiscount = (float) ($folio->discount_amount ?? 0);
            $amountAfterDiscount = max(0, $grossTotal - $authoritativeDiscount);

            $securityDeposit = ($request->has('security_deposit') && $request->input('security_deposit') !== null)
                ? max(0, (float) $request->input('security_deposit'))
                : (float) ($folio->security_deposit ?? 0.00);

            $applyDeposit = $request->has('apply_deposit')
                ? $request->boolean('apply_deposit')
                : ($folio->deposit_status !== 'not_applied');

            if ($applyDeposit) {
                $finalBalanceToPay = max(0, $amountAfterDiscount - $securityDeposit);
                $depositStatus = $securityDeposit > 0 ? 'applied_to_bill' : 'none';
            } else {
                $finalBalanceToPay = $amountAfterDiscount;
                $depositStatus = $securityDeposit > 0 ? 'not_applied' : 'none';
            }

            // Authoritative Payment Calculations
            $paymentMethod = $validated['payment_method'] ?? 'cash';
            $cashTendered = (float) ($validated['cash_tendered'] ?? 0);
            $gcashAmount = (float) ($validated['gcash_amount'] ?? 0);
            $gcashRef = $validated['gcash_reference'] ?? null;
            $changeDue = 0.00;

            if ($paymentMethod === 'cash') {
                $gcashAmount = 0.00;
                $changeDue = max(0, $cashTendered - $finalBalanceToPay);
            } elseif ($paymentMethod === 'gcash') {
                $gcashAmount = $finalBalanceToPay;
                $cashTendered = 0.00;
                $changeDue = 0.00;
            } elseif ($paymentMethod === 'split') {
                $remainingAfterGcash = max(0, $finalBalanceToPay - $gcashAmount);
                $changeDue = max(0, $cashTendered - $remainingAfterGcash);
            }

            $folio->checked_out_at = $checkOutTime;
            $folio->status = 'checked_out';
            $folio->extra_hours = $xtendHours + $excessHours;
            $folio->surcharge_total = $surchargeTotal;
            $folio->gross_total = $grossTotal;
            $folio->discount_amount = $authoritativeDiscount;
            $folio->net_total = $finalBalanceToPay;
            $folio->payment_method = $paymentMethod;
            $folio->cash_tendered = $cashTendered;
            $folio->gcash_amount = $gcashAmount;
            $folio->gcash_reference = $gcashRef;
            $folio->change_due = $changeDue;

            $folio->security_deposit = $securityDeposit;
            $folio->deposit_status = $depositStatus;

            $folio->save();

            if ($folio->room) {
                $folio->room->update(['status' => 'available']);
            }

            if (!empty($validated['customer_name']) && $folio->guest) {
                $folio->guest->update(['name' => $validated['customer_name']]);
            }

            // Update Shift reconciliation totals
            $activeShift = Shift::whereNull('closed_at')->latest()->first();
            if ($activeShift) {
                $netCashCollected = max(0, $cashTendered - $changeDue);
                $activeShift->cash_total += $netCashCollected;
                $activeShift->gcash_total += $gcashAmount;
                $activeShift->gross_revenue += $finalBalanceToPay;
                $activeShift->save();
            }

            DB::commit();

            return redirect()->route('dashboard')
                ->with('success', "Room {$folio->room->number} successfully checked out! Settled: ₱" . number_format($finalBalanceToPay, 2) . " (Method: " . strtoupper($paymentMethod) . ", Change: ₱" . number_format($changeDue, 2) . ")")
                ->with('settled_folio_id', $folio->id);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Checkout failed: ' . $e->getMessage());
        }
    }

    /**
     * Extend Guest Stay (Xtend button).
     */
    public function extendStay(Request $request, Folio $folio): RedirectResponse
    {
        $validated = $request->validate([
            'hours' => 'required|integer|min:1',
        ]);

        $extraHours = (int) $validated['hours'];
        $folio->extra_hours += $extraHours;
        $folio->expected_checkout_at = Carbon::parse($folio->expected_checkout_at)->addHours($extraHours);
        
        $ratePerHr = 130;
        $folio->surcharge_total += ($extraHours * $ratePerHr);
        $folio->recalculate();

        return redirect()->route('dashboard')
            ->with('success', "Room {$folio->room->number} stay extended by {$extraHours} hr(s).");
    }

    /**
     * Apply Authoritative Fixed Discount (Senior / PWD / Discount Card DC).
     */
    public function applyDiscount(Request $request, Folio $folio): RedirectResponse
    {
        $validated = $request->validate([
            'discount_type'   => 'required|in:senior,pwd,dc,none',
            'discount_id_ref' => 'nullable|string|max:100',
        ]);

        $type = strtolower($validated['discount_type']);

        if ($type === 'none') {
            $folio->discount_type = 'NONE';
            $folio->discount_id_ref = null;
            $folio->discount_amount = 0.00;
            $folio->senior_pwd_discount = false;
        } else {
            $roomType = $folio->room->type ?? 'Classic Room';
            $discountAmount = \App\Services\DiscountService::getDiscountAmount($type, $roomType, $folio->rate_tier);

            $folio->discount_type = strtoupper($type);
            $folio->discount_id_ref = $validated['discount_id_ref'] ?? 'ID-VERIFIED';
            $folio->discount_amount = $discountAmount;
            $folio->senior_pwd_discount = in_array($folio->discount_type, ['SENIOR', 'PWD']);
        }

        $folio->net_total = max(0, $folio->gross_total - $folio->discount_amount);
        $folio->save();

        $typeName = match ($folio->discount_type) {
            'SENIOR' => 'Senior Citizen (Statutory Table)',
            'PWD'    => 'PWD (Statutory Table)',
            'DC'     => 'Sedona Discount Card (DC)',
            default  => 'None',
        };

        return redirect()->route('dashboard')
            ->with('success', "{$typeName} discount of ₱" . number_format($folio->discount_amount, 2) . " applied to Room {$folio->room->number} (Ref: {$folio->discount_id_ref}).");
    }

    /**
     * Update Guest Security Deposit (In Trust).
     */
    public function updateDeposit(Request $request, Folio $folio): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'security_deposit' => 'nullable|numeric|min:0|max:100000',
            'apply_deposit'    => 'nullable',
            'payment_method'   => 'nullable|string|in:cash,gcash,split',
            'cash_tendered'    => 'nullable|numeric|min:0',
            'gcash_amount'     => 'nullable|numeric|min:0',
            'gcash_reference'  => 'nullable|string|max:50',
            'change_due'       => 'nullable|numeric|min:0',
            'discount_type'    => 'nullable|string|in:none,senior,pwd,dc,NONE,SENIOR,PWD,DC',
            'discount_id_ref'  => 'nullable|string|max:100',
            'checked_in_at'    => 'nullable|date',
            'room_charge'      => 'nullable|numeric|min:0',
        ]);

        if ($request->filled('checked_in_at')) {
            $cIn = \Carbon\Carbon::parse($request->input('checked_in_at'));
            $folio->checked_in_at = $cIn;
            $tierHours = $folio->room ? $folio->room->getTierHours($folio->rate_tier) : 3;
            if ($tierHours == 0) $tierHours = 3;
            $xtendHours = (int) ($folio->extra_hours ?? 0);
            $folio->expected_checkout_at = $cIn->copy()->addHours($tierHours + $xtendHours);
        }

        if (array_key_exists('room_charge', $validated) && $validated['room_charge'] !== null) {
            $folio->room_charge = (float) $validated['room_charge'];
        }

        if (array_key_exists('security_deposit', $validated) && $validated['security_deposit'] !== null) {
            $deposit = (float) $validated['security_deposit'];
            $folio->security_deposit = $deposit;
        }

        if ($request->has('apply_deposit')) {
            $applyDeposit = $request->boolean('apply_deposit');
            if ($folio->security_deposit > 0) {
                $folio->deposit_status = $applyDeposit ? 'applied_to_bill' : 'not_applied';
            } else {
                $folio->deposit_status = 'none';
            }
        } elseif (array_key_exists('security_deposit', $validated)) {
            if ($folio->security_deposit > 0 && $folio->deposit_status !== 'not_applied') {
                $folio->deposit_status = 'applied_to_bill';
            } elseif ($folio->security_deposit <= 0) {
                $folio->deposit_status = 'none';
            }
        }

        if ($request->has('discount_type')) {
            $dType = strtolower($request->input('discount_type'));
            if ($dType === 'none' || empty($dType)) {
                $folio->discount_type = 'NONE';
                $folio->discount_id_ref = null;
                $folio->discount_amount = 0.00;
                $folio->senior_pwd_discount = false;
            } else {
                $roomType = $folio->room->type ?? 'Classic Room';
                $discountAmount = \App\Services\DiscountService::getDiscountAmount($dType, $roomType, $folio->rate_tier);
                $folio->discount_type = strtoupper($dType);
                $folio->discount_id_ref = $request->input('discount_id_ref') ?: 'ID-VERIFIED';
                $folio->discount_amount = $discountAmount;
                $folio->senior_pwd_discount = in_array($folio->discount_type, ['SENIOR', 'PWD']);
            }
        }

        if (isset($validated['payment_method'])) {
            $folio->payment_method = $validated['payment_method'];
        }
        if (array_key_exists('cash_tendered', $validated)) {
            $folio->cash_tendered = (float) ($validated['cash_tendered'] ?? 0);
        }
        if (array_key_exists('gcash_amount', $validated)) {
            $folio->gcash_amount = (float) ($validated['gcash_amount'] ?? 0);
        }
        if (array_key_exists('gcash_reference', $validated)) {
            $folio->gcash_reference = $validated['gcash_reference'];
        }
        if (array_key_exists('change_due', $validated)) {
            $folio->change_due = (float) ($validated['change_due'] ?? 0);
        }

        $folio->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'security_deposit' => (float) $folio->security_deposit,
                'deposit_status' => $folio->deposit_status,
                'payment_method' => $folio->payment_method,
                'cash_tendered' => (float) $folio->cash_tendered,
                'gcash_amount' => (float) $folio->gcash_amount,
                'gcash_reference' => $folio->gcash_reference,
                'change_due' => (float) $folio->change_due,
                'discount_type' => $folio->discount_type,
                'discount_id_ref' => $folio->discount_id_ref,
                'discount_amount' => (float) $folio->discount_amount,
                'formatted_deposit' => number_format((float)$folio->security_deposit, 2),
            ]);
        }

        return back()->with('success', "Folio settlement details updated.");
    }


    /**
     * Transfer Guest to Another Available Room.
     */
    public function transferRoom(Request $request, Folio $folio): RedirectResponse
    {
        $validated = $request->validate([
            'target_room_id' => 'required|exists:rooms,id',
        ]);

        $targetRoom = Room::findOrFail($validated['target_room_id']);

        if ($targetRoom->id === $folio->room_id) {
            return back()->with('error', 'Target room is the same as current room.');
        }

        if ($targetRoom->status !== 'available' || $targetRoom->is_staff_quarters) {
            return back()->with('error', "Room {$targetRoom->number} is not available for transfer.");
        }

        DB::beginTransaction();
        try {
            $oldRoom = $folio->room;

            // Move folio to target room
            $folio->room_id = $targetRoom->id;
            $folio->save();

            // Move any pending orders to new room
            Order::where('folio_id', $folio->id)->update(['room_id' => $targetRoom->id]);

            // Directly release old room to available (0 cleaning state)
            if ($oldRoom) {
                $oldRoom->update(['status' => 'available']);
            }

            // Set new room to occupied
            $targetRoom->update(['status' => 'occupied']);

            DB::commit();

            return redirect()->route('dashboard')
                ->with('success', "Guest transferred successfully from Room {$oldRoom->number} to Room {$targetRoom->number}.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Transfer failed: ' . $e->getMessage());
        }
    }

    /**
     * Request Force Checkout (Cashier).
     */
    public function requestForceCheckout(Request $request, Folio $folio): RedirectResponse
    {
        $validated = $request->validate([
            'force_reason' => 'required|string|max:500',
        ]);

        $folio->force_checkout = true;
        $folio->force_reason = $validated['force_reason'];
        $folio->save();

        return redirect()->route('dashboard')
            ->with('info', "Force checkout requested for Room {$folio->room->number}. Pending manager approval.");
    }

    /**
     * Approve Force Checkout Loss Slip (Admin / Owner).
     */
    public function approveForceCheckout(Request $request, Folio $folio): RedirectResponse
    {
        DB::beginTransaction();
        try {
            $lossSlipId = 'FCE-' . str_pad(random_int(1, 999999), 6, '0', STR_PAD_LEFT);

            $uncollected = $folio->net_total;
            $folio->force_checkout = true;
            $folio->force_by = Auth::id() ?? 1;
            $folio->checked_out_at = now();
            $folio->status = 'checked_out';
            $folio->payment_method = 'cash';
            $folio->cash_tendered = 0;
            $folio->change_due = 0;
            $folio->save();

            if ($folio->room) {
                $folio->room->update(['status' => 'available']);
            }

            DB::commit();

            return redirect()->route('dashboard')
                ->with('success', "Force Checkout approved for Room {$folio->room->number}. Audited Loss Slip {$lossSlipId} generated for ₱" . number_format($uncollected, 2) . ".");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Force checkout approval failed: ' . $e->getMessage());
        }
    }

    /**
     * Official Settlement Receipt (Printable).
     */
    public function showReceipt(Request $request, Folio $folio): View
    {
        $folio->load(['room', 'guest', 'cashier', 'orders.items']);

        $room = $folio->room;
        $tierHours = $room ? $room->getTierHours($folio->rate_tier) : 3;
        if ($tierHours == 0) $tierHours = 3;

        $checkInTime = $folio->checked_in_at;
        $checkOutTime = ($folio->status === 'checked_out' && $folio->checked_out_at) ? $folio->checked_out_at : now();

        $xtendHours = (int) ($folio->extra_hours ?? 0);
        $totalBookedHours = $tierHours + $xtendHours;

        $actualHoursDiff = max(1, (int) ceil($checkInTime->diffInMinutes($checkOutTime) / 60));
        $excessHours = max(0, $actualHoursDiff - $totalBookedHours);

        $addHourUnitPrice = 130;
        $xtendRate = $xtendHours * $addHourUnitPrice;
        $excessRate = $excessHours * $addHourUnitPrice;
        $addHoursRate = ($xtendHours + $excessHours) * $addHourUnitPrice;

        $roomRate = (float) $folio->room_charge;
        if ($roomRate <= 0 && $room) {
            $roomRate = $room->getRateForTier($folio->rate_tier);
        }

        $addOnsTotal = 0;
        if (!empty($folio->pos_items) && is_array($folio->pos_items)) {
            foreach ($folio->pos_items as $p) {
                $addOnsTotal += (float) ($p['subtotal'] ?? 0);
            }
        }

        $deposit = (float) ($folio->security_deposit ?? 0.00);
        $applyDeposit = ($folio->deposit_status !== 'not_applied');

        $discountType = $folio->discount_type;
        $discountIdRef = $folio->discount_id_ref;
        $totalDiscount = (float) ($folio->discount_amount ?? 0);

        if ($request->has('discount_type')) {
            $reqDType = strtolower($request->input('discount_type'));
            if ($reqDType === 'none' || empty($reqDType)) {
                $discountType = 'NONE';
                $discountIdRef = null;
                $totalDiscount = 0.00;
            } else {
                $roomType = $room->type ?? 'Classic Room';
                $totalDiscount = \App\Services\DiscountService::getDiscountAmount($reqDType, $roomType, $folio->rate_tier);
                $discountType = strtoupper($reqDType);
                $discountIdRef = $request->input('discount_id_ref', $folio->discount_id_ref ?? 'ID-VERIFIED');
            }
        }

        if ($folio->status === 'checked_out' && !$request->has('discount_type')) {
            $grossTotal = (float) $folio->gross_total;
            $amountAfterDiscount = max(0, $grossTotal - $totalDiscount);
            $finalBalanceToPay = (float) $folio->net_total;
            $depositApplied = ($folio->deposit_status === 'applied_to_bill') ? min($deposit, $amountAfterDiscount) : 0.00;
            $remainingDeposit = ($folio->deposit_status === 'applied_to_bill') ? max(0, $deposit - $amountAfterDiscount) : $deposit;
        } else {
            $grossTotal = $roomRate + $addHoursRate + $addOnsTotal;
            $amountAfterDiscount = max(0, $grossTotal - $totalDiscount);
            if ($applyDeposit) {
                $depositApplied = min($deposit, $amountAfterDiscount);
                $remainingDeposit = max(0, $deposit - $amountAfterDiscount);
                $finalBalanceToPay = max(0, $amountAfterDiscount - $deposit);
            } else {
                $depositApplied = 0.00;
                $remainingDeposit = $deposit;
                $finalBalanceToPay = $amountAfterDiscount;
            }
        }

        return view('checkout.receipt', compact(
            'folio', 'room', 'tierHours', 'xtendHours', 'excessHours',
            'xtendRate', 'excessRate', 'addHoursRate', 'roomRate',
            'grossTotal', 'totalDiscount', 'discountType', 'discountIdRef', 'deposit', 'applyDeposit', 'depositApplied', 'remainingDeposit', 'finalBalanceToPay'
        ));
    }

    /**
     * Security Deposit Acknowledgement Slip (Printable).
     */
    public function showDepositSlip(Request $request, Folio $folio): View
    {
        $folio->load(['room', 'guest', 'cashier']);
        $depositAmount = $request->has('deposit')
            ? max(0, (float) $request->input('deposit'))
            : (float) ($folio->security_deposit ?? 0.00);

        return view('checkout.deposit_slip', compact('folio', 'depositAmount'));
    }

    /**
     * Guest Statement of Account / Interim Billing (Printable).
     */
    public function showBillingStatement(Request $request, Folio $folio): View
    {
        $folio->load(['room', 'guest', 'cashier', 'orders.items']);

        $room = $folio->room;

        $tierHours = $room ? $room->getTierHours($folio->rate_tier) : 3;
        if ($tierHours == 0) $tierHours = 3;

        $xtendHours = (int) ($folio->extra_hours ?? 0);
        $totalBookedHours = $tierHours + $xtendHours;

        if ($request->has('checked_in_at')) {
            $checkInTime = \Carbon\Carbon::parse($request->input('checked_in_at'));
        } else {
            $checkInTime = $folio->checked_in_at;
        }

        $expectedCheckoutAt = $checkInTime->copy()->addHours($totalBookedHours);

        if ($request->has('checked_out_at')) {
            $checkOutTime = \Carbon\Carbon::parse($request->input('checked_out_at'));
        } else {
            $checkOutTime = ($folio->status === 'checked_out' && $folio->checked_out_at) ? $folio->checked_out_at : now();
        }

        $actualHoursDiff = max(1, (int) ceil($checkInTime->diffInMinutes($checkOutTime) / 60));
        $excessHours = max(0, $actualHoursDiff - $totalBookedHours);

        $addHourUnitPrice = 130;
        $xtendRate = $xtendHours * $addHourUnitPrice;
        $excessRate = $excessHours * $addHourUnitPrice;
        $addHoursRate = ($xtendHours + $excessHours) * $addHourUnitPrice;

        if ($request->has('room_charge')) {
            $roomRate = max(0, (float) $request->input('room_charge'));
        } else {
            $roomRate = (float) $folio->room_charge;
            if ($roomRate <= 0 && $room) {
                $roomRate = $room->getRateForTier($folio->rate_tier);
            }
        }

        $addOnsTotal = 0;
        if (!empty($folio->pos_items) && is_array($folio->pos_items)) {
            foreach ($folio->pos_items as $p) {
                $addOnsTotal += (float) ($p['subtotal'] ?? 0);
            }
        }

        $deposit = $request->has('deposit')
            ? max(0, (float) $request->input('deposit'))
            : (float) ($folio->security_deposit ?? 0.00);

        $applyDeposit = $request->has('apply_deposit')
            ? $request->boolean('apply_deposit')
            : ($folio->deposit_status !== 'not_applied');

        $discountType = $folio->discount_type;
        $discountIdRef = $folio->discount_id_ref;
        $totalDiscount = (float) ($folio->discount_amount ?? 0);

        if ($request->has('discount_type')) {
            $reqDType = strtolower($request->input('discount_type'));
            if ($reqDType === 'none' || empty($reqDType)) {
                $discountType = 'NONE';
                $discountIdRef = null;
                $totalDiscount = 0.00;
            } else {
                $roomType = $room->type ?? 'Classic Room';
                $totalDiscount = \App\Services\DiscountService::getDiscountAmount($reqDType, $roomType, $folio->rate_tier);
                $discountType = strtoupper($reqDType);
                $discountIdRef = $request->input('discount_id_ref', $folio->discount_id_ref ?? 'ID-VERIFIED');
            }
        }

        if ($folio->status === 'checked_out' && !$request->has('deposit') && !$request->has('apply_deposit') && !$request->has('discount_type') && !$request->has('room_charge') && !$request->has('checked_in_at')) {
            $grossTotal = (float) $folio->gross_total;
            $amountAfterDiscount = max(0, $grossTotal - $totalDiscount);
            $finalBalanceToPay = (float) $folio->net_total;
            $depositApplied = ($folio->deposit_status === 'applied_to_bill') ? min($deposit, $amountAfterDiscount) : 0.00;
            $remainingDeposit = ($folio->deposit_status === 'applied_to_bill') ? max(0, $deposit - $amountAfterDiscount) : $deposit;
        } else {
            $grossTotal = ($folio->status === 'checked_out' && !$request->has('room_charge') && !$request->has('checked_in_at'))
                ? (float) $folio->gross_total
                : (float) ($roomRate + $addHoursRate + $addOnsTotal);
            $amountAfterDiscount = max(0, $grossTotal - $totalDiscount);

            if ($applyDeposit) {
                $depositApplied = min($deposit, $amountAfterDiscount);
                $remainingDeposit = max(0, $deposit - $amountAfterDiscount);
                $finalBalanceToPay = max(0, $amountAfterDiscount - $deposit);
            } else {
                $depositApplied = 0.00;
                $remainingDeposit = $deposit;
                $finalBalanceToPay = $amountAfterDiscount;
            }
        }

        $paymentMethod = $request->input('payment_method', $folio->payment_method ?? 'cash');
        $cashTendered = $request->has('cash_tendered')
            ? max(0, (float) $request->input('cash_tendered'))
            : (float) ($folio->cash_tendered ?? 0.00);
        $gcashAmount = $request->has('gcash_amount')
            ? max(0, (float) $request->input('gcash_amount'))
            : (float) ($folio->gcash_amount ?? 0.00);
        $gcashRef = $request->input('gcash_reference', $folio->gcash_reference ?? null);

        if ($request->has('change_due')) {
            $changeDue = max(0, (float) $request->input('change_due'));
        } elseif (($folio->status === 'checked_out' || (float) ($folio->change_due ?? 0) > 0) && !$request->has('cash_tendered')) {
            $changeDue = (float) ($folio->change_due ?? 0.00);
        } else {
            if ($paymentMethod === 'cash') {
                $changeDue = $cashTendered > 0 ? max(0, $cashTendered - $finalBalanceToPay) : 0.00;
            } elseif ($paymentMethod === 'gcash') {
                $changeDue = 0.00;
            } elseif ($paymentMethod === 'split') {
                $remainingAfterGcash = max(0, $finalBalanceToPay - $gcashAmount);
                $changeDue = $cashTendered > 0 ? max(0, $cashTendered - $remainingAfterGcash) : 0.00;
            } else {
                $changeDue = 0.00;
            }
        }

        return view('checkout.billing', compact(
            'folio', 'room', 'checkInTime', 'checkOutTime', 'expectedCheckoutAt',
            'tierHours', 'xtendHours', 'excessHours', 'totalBookedHours', 'actualHoursDiff',
            'xtendRate', 'excessRate', 'addHoursRate', 'roomRate',
            'addOnsTotal', 'grossTotal', 'totalDiscount', 'discountType', 'discountIdRef', 'amountAfterDiscount', 'deposit', 'applyDeposit', 'depositApplied', 'remainingDeposit', 'finalBalanceToPay',
            'paymentMethod', 'cashTendered', 'gcashAmount', 'gcashRef', 'changeDue'
        ));
    }

    /**
     * Audited Loss Slip Write-Off (FCE-######) (Printable).
     */
    public function showLossSlip(Folio $folio): View
    {
        $folio->load(['room', 'guest', 'cashier', 'forcedBy']);

        return view('checkout.loss_slip', compact('folio'));
    }

    /**
     * Printable Exit Gate Pass with Code 128 Barcode.
     */
    public function showGatePass(Folio $folio): View
    {
        $folio->load(['room', 'guest', 'cashier']);
        $ticketNo = 'GP-RM' . ($folio->room->number ?? '00') . '-' . ($folio->checked_out_at ? $folio->checked_out_at->format('mdHi') : now()->format('mdHi'));

        return view('checkout.gate_pass', compact('folio', 'ticketNo'));
    }

    /**
     * Security Deposit Refund / Resolution Slip (Printable).
     */
    public function showDepositRefund(Request $request, Folio $folio): View
    {
        $folio->load(['room', 'guest', 'cashier']);
        $depositAmount = $request->has('deposit')
            ? max(0, (float) $request->input('deposit'))
            : (float) ($folio->security_deposit ?? 0.00);

        if ($request->has('refund')) {
            $refundAmount = max(0, (float) $request->input('refund'));
            $deductions = max(0, $depositAmount - $refundAmount);
        } else {
            $applyDeposit = $request->has('apply_deposit')
                ? $request->boolean('apply_deposit')
                : ($folio->deposit_status === 'applied_to_bill');

            if ($applyDeposit && $depositAmount > 0) {
                $grossTotal = (float) $folio->gross_total;
                $disc = (float) ($folio->discount_amount ?? 0);
                $billDue = max(0, $grossTotal - $disc);
                $depositApplied = min($depositAmount, $billDue);
                $deductions = $depositApplied;
                $refundAmount = max(0, $depositAmount - $depositApplied);
            } else {
                $deductions = 0.00;
                $refundAmount = $depositAmount;
            }
        }

        return view('checkout.deposit_refund_slip', compact('folio', 'depositAmount', 'deductions', 'refundAmount'));
    }
}

