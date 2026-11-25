@if($depositReconciliation && ($depositReconciliation['openingCashHeld'] > 0 || $depositReconciliation['closingCashHeld'] > 0 || $depositReconciliation['closingGcashHeld'] > 0 || $depositReconciliation['cashReceived'] > 0 || $depositReconciliation['cashApplied'] > 0 || $depositReconciliation['cashRefunded'] > 0 || $depositReconciliation['unverifiedHeld'] > 0 || $depositReconciliation['unverifiedApplied'] > 0 || $depositReconciliation['unverifiedRefunds'] > 0))
<section class="rounded-xl border border-amber-200 bg-amber-50 p-4 mb-4" aria-label="Guest deposit handover">
    <h3 class="font-semibold text-amber-950">Guest deposit handover</h3>
    <p class="mt-1 text-xs text-slate-600">Keep pending guest deposits separate from the money-count form. Applied cash deposits belong to the cashier who settles the bill.</p>
    <dl class="mt-3 grid grid-cols-1 gap-3 text-xs sm:grid-cols-3">
        <div><dt>Cash deposits received {{ ($propertyAccess ?? false) ? 'in this drawer' : 'by me' }}</dt><dd class="font-mono font-bold">₱{{ number_format($depositReconciliation['cashReceived'], 2) }}</dd></div>
        <div><dt>Cash deposits applied to {{ ($propertyAccess ?? false) ? 'settled bills' : 'my settled bills' }}</dt><dd class="font-mono font-bold">₱{{ number_format($depositReconciliation['cashApplied'], 2) }}</dd></div>
        <div><dt>Deposit refunds paid in cash</dt><dd class="font-mono font-bold">₱{{ number_format($depositReconciliation['cashRefunded'], 2) }}</dd></div>
    </dl>
    <div class="mt-3 grid grid-cols-1 gap-3 border-t border-amber-200 pt-3 text-sm sm:grid-cols-2">
        <div><span>Shared guest cash held at start</span><strong class="block font-mono">₱{{ number_format($depositReconciliation['openingCashHeld'], 2) }}</strong></div>
        <div><span>Shared guest cash still held</span><strong class="block font-mono">₱{{ number_format($depositReconciliation['closingCashHeld'], 2) }}</strong></div>
    </div>
    <p class="mt-2 text-xs text-slate-600">These shared guest balances pass with the room handover. They are separate from each cashier's own collections.</p>
    @if(count($depositReconciliation['rooms']) > 0)
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead><tr><th class="py-2">Current room / stay</th><th class="py-2 text-right">Cash held</th><th class="py-2 text-right">GCash held</th><th class="py-2 text-right">Unverified</th></tr></thead>
            <tbody>
            @foreach($depositReconciliation['rooms'] as $depositRoom)
                <tr class="border-t border-amber-200"><td class="py-2">{{ $depositRoom['room'] }}<small class="block break-all">{{ $depositRoom['folio'] }}</small></td><td class="py-2 text-right font-mono">₱{{ number_format($depositRoom['cash'], 2) }}</td><td class="py-2 text-right font-mono">₱{{ number_format($depositRoom['gcash'], 2) }}</td><td class="py-2 text-right font-mono">₱{{ number_format($depositRoom['unverified'], 2) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
    @if($depositReconciliation['cashReleasedByRefund'] !== $depositReconciliation['cashRefunded'])
        <p class="mt-2 text-xs text-slate-700">Cash/GCash refund exchange adjustment: ₱{{ number_format($depositReconciliation['cashReleasedByRefund'] - $depositReconciliation['cashRefunded'], 2) }}. Refunding through a different payment method changes the cash available for the form.</p>
    @endif
    @if($depositReconciliation['unverifiedHeld'] > 0 || $depositReconciliation['unverifiedApplied'] > 0 || $depositReconciliation['unverifiedRefunds'] > 0)
        <p class="mt-2 text-xs font-semibold text-amber-950">Older deposits need admin/owner verification. Unverified held: ₱{{ number_format($depositReconciliation['unverifiedHeld'], 2) }} · applied: ₱{{ number_format($depositReconciliation['unverifiedApplied'], 2) }} · refunded: ₱{{ number_format($depositReconciliation['unverifiedRefunds'], 2) }}. Unverified deposits are not added to settled cash. Refund payouts remain recorded.</p>
    @endif
</section>
@endif
