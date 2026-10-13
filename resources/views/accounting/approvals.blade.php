@extends('layouts.app')
@section('title', 'Approvals & Tasks')
@section('subtitle', 'Recorded requests, independent review, and approved execution')
@section('content')
<div class="space-y-5">
    @if($errors->any())
        <div role="alert" class="hms-card border border-rose-300 bg-rose-50 text-rose-800">
            <p class="font-bold">The request could not be saved.</p>
            <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="hms-card"><p class="text-sm text-slate-600">A different admin or owner reviews each request. Approved actions apply once to the recorded details. Monetary refunds do not return food or stock automatically.</p></div>
    <div class="grid gap-4 lg:grid-cols-2">
    @foreach(['folio_override' => 'Rate or guest discount', 'invoice_tax_treatment' => 'Statutory invoice tax basis', 'deposit_refund' => 'Return remaining guest deposit', 'pos_refund' => 'Refund a walk-in sale', 'statement_void' => 'Exclude an incorrect statement import', 'cash_drop' => 'Drop drawer cash to safe', 'float_addition' => 'Add drawer float', 'bank_deposit' => 'Deposit drawer cash to bank'] as $kind => $label)
        @continue($kind === 'statement_void' && !auth()->user()->can('review-financial'))
        @continue(in_array($kind, ['cash_drop', 'float_addition', 'bank_deposit']) && !$shift)
        <details class="hms-card" @if($kind === 'folio_override' && $selectedFolio) open @endif><summary class="cursor-pointer font-semibold">{{ $label }}</summary>
            <form method="POST" action="{{ route('approvals.store') }}" class="mt-4 grid gap-3">
                @csrf<input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="kind" value="{{ $kind }}">
                @if(in_array($kind, ['folio_override', 'deposit_refund', 'invoice_tax_treatment']))
                    <label>Guest folio<select name="source_id" required class="form-control-hms">@foreach($folios as $folio)<option value="{{ $folio->id }}" @selected($kind === 'folio_override' && $selectedFolio?->id === $folio->id)>Room {{ $folio->room?->number }} — #{{ $folio->id }} — {{ $folio->guest?->name }} — {{ $folio->status }}</option>@endforeach</select></label>
                @elseif($kind === 'pos_refund')
                    <label>Original sale payment<select name="source_id" required class="form-control-hms">@foreach($payments as $payment)<option value="{{ $payment->id }}">Payment #{{ $payment->id }} — order #{{ $payment->order_id }} — ₱{{ number_format($payment->cash_amount + $payment->gcash_amount, 2) }}</option>@endforeach</select></label>
                @elseif($kind === 'statement_void')
                    <label>Unmatched statement import<select name="source_id" required class="form-control-hms">@foreach($statements as $statement)<option value="{{ $statement->id }}">#{{ $statement->id }} {{ $statement->provider }} {{ $statement->reference }} {{ $statement->gross_amount }}</option>@endforeach</select></label>
                @else
                    <input type="hidden" name="source_id" value="{{ $shift->id }}"><p class="text-sm">Drawer #{{ $shift->id }}</p>
                @endif
                @if($kind === 'folio_override')
                    <p class="text-sm text-slate-500">Leave unchanged fields empty. Choosing a discount type with no custom amount restores that guest's recorded default. Senior and PWD discounts require an ID reference.</p>
                    <label>New room charge<input name="payload[room_charge]" type="number" step="0.01" min="0" class="form-control-hms"></label>
                    <p class="text-sm text-slate-500">Check-in time corrections are handled by RA (admin).</p>
                    <label>Guest discount<select name="payload[discount_type]" class="form-control-hms"><option value="">Unchanged</option>@foreach(['DC' => 'Sedona Card / DC', 'SENIOR' => 'Senior Citizen', 'PWD' => 'PWD', 'NONE' => 'Remove discount'] as $type => $discountLabel)<option value="{{ $type }}" @selected($selectedDiscountType === $type)>{{ $discountLabel }}</option>@endforeach</select></label>
                    <label>Discount ID reference<input name="payload[discount_id_ref]" maxlength="100" class="form-control-hms"></label>
                    @if(auth()->user()->hasAnyRole(['admin', 'owner']))
                        <label>Custom discount amount (₱, optional)<input name="payload[discount_override_amount]" type="number" step="0.01" min="0" max="500000" class="form-control-hms"></label>
                    @endif
                @elseif($kind === 'statement_void')
                    <p class="text-sm text-slate-500">The original import is retained. Matching or excluding an already matched row is blocked.</p>
                @elseif($kind === 'invoice_tax_treatment')
                    <label>Accountant-approved taxable amount, inclusive of VAT<input name="payload[taxable_amount]" type="number" step="0.01" min="0" required class="form-control-hms"></label><p class="text-sm text-slate-500">The remainder of the settled sale is exempt. Record the accountant / exemption evidence reference below.</p>
                @else
                    <label>Amount<input name="payload[amount]" type="number" step="0.01" min="0.01" required class="form-control-hms"></label>
                    @if(in_array($kind, ['deposit_refund', 'pos_refund']))
                        <label>Refund method<select name="payload[payment_method]" class="form-control-hms"><option value="cash">Cash</option><option value="gcash">GCash</option></select></label>
                        <label>GCash refund reference<input name="payload[gcash_reference]" maxlength="50" class="form-control-hms"></label>
                    @else
                        <label>Destination / source<select name="payload[destination]" class="form-control-hms">@if($kind === 'bank_deposit')<option value="bank">Bank</option>@else<option value="safe">Safe</option>@if($kind === 'float_addition')<option value="owner_funds">Owner funds</option>@endif @endif</select></label>
                        <label>Movement reference<input name="payload[reference]" required maxlength="100" class="form-control-hms"></label>
                    @endif
                @endif
                <label>Reason<textarea name="reason" required maxlength="1000" class="form-control-hms"></textarea></label>
                <label>Evidence reference<input name="evidence_reference" maxlength="255" class="form-control-hms"></label>
                <button class="btn-hms-primary">Submit request</button>
            </form>
        </details>
    @endforeach
    </div>
    <div class="hms-card overflow-x-auto"><h2 class="font-semibold mb-4">{{ auth()->user()->can('review-financial') ? 'Property review queue' : 'Your requests and outcomes' }}</h2>
        <table class="w-full text-sm text-left"><thead class="bg-slate-100"><tr><th class="p-3">Request / age</th><th class="p-3">Recorded details</th><th class="p-3">Status / review</th><th class="p-3">Action</th></tr></thead><tbody>
        @forelse($requests as $approval)
            <tr class="border-b border-slate-200"><td class="p-3">#{{ $approval->id }} {{ str_replace('_', ' ', $approval->kind) }}<p>{{ $approval->created_at->diffForHumans() }}</p><p>Requester #{{ $approval->requested_by }}</p></td>
                <td class="p-3">{{ class_basename($approval->source_type) }} #{{ $approval->source_id }}<p>{{ $approval->reason }}</p><p>Evidence: {{ $approval->evidence_reference ?? 'None recorded' }}</p><pre class="whitespace-pre-wrap text-xs">{{ json_encode($approval->payload, JSON_PRETTY_PRINT) }}</pre></td>
                <td class="p-3 font-semibold">{{ str_replace('_', ' ', $approval->status) }}<p class="font-normal">{{ $approval->review_note }}</p>@if($approval->reviewed_by)<p class="font-normal">Reviewer #{{ $approval->reviewed_by }} · {{ $approval->reviewed_at }}</p>@endif</td>
                <td class="p-3">
                    @if($approval->kind === 'folio_override' && array_key_exists('checked_in_at', $approval->payload))
                        <p class="text-slate-500">Check-in time corrections now use RA's admin panel. Cancel this old request and ask RA to correct the time.</p>
                    @elseif($approval->kind === 'handover_variance' && !config('sedona.shift_handover_enabled'))
                        <p class="text-slate-500">Handover temporarily disabled. This historical request is retained and does not block day closing.</p>
                    @elseif($approval->status === 'pending' && auth()->user()->can('review-financial') && $approval->requested_by !== auth()->id())
                        <form method="POST" action="{{ route('approvals.review', $approval) }}" class="grid gap-2">@csrf<textarea name="note" required maxlength="1000" placeholder="Review reason" class="form-control-hms"></textarea><button name="decision" value="approve" class="btn-hms-primary">Approve recorded details</button><button name="decision" value="reject" class="text-rose-700">Reject</button></form>
                    @elseif($approval->status === 'approved' && ($approval->requested_by === auth()->id() || auth()->user()->can('review-financial')))
                        <form method="POST" action="{{ route('approvals.execute', $approval) }}" class="grid gap-2">@csrf<input name="operation_key" type="hidden" value="{{ (string) \Illuminate\Support\Str::uuid() }}">@if(auth()->user()->can('review-financial'))<input name="shift_override_reason" maxlength="500" placeholder="Reason if covering another drawer" class="form-control-hms">@endif<button class="btn-hms-primary">Execute approved action</button></form>
                    @else<p class="text-slate-500">{{ $approval->status === 'pending' ? 'Awaiting another reviewer' : 'Recorded' }}</p>@endif
                    @if(in_array($approval->status, ['pending', 'approved']) && $approval->kind !== 'expense_review' && ($approval->requested_by === auth()->id() || auth()->user()->can('review-financial')))
                    <form method="POST" action="{{ route('approvals.cancel', $approval) }}" class="grid gap-2 mt-3">@csrf<input name="reason" required maxlength="1000" placeholder="Cancellation reason" class="form-control-hms"><button class="text-rose-700">Cancel unexecuted request</button></form>
                    @endif
                </td>
            </tr>
        @empty<tr><td colspan="4" class="p-6 text-slate-500">No recorded requests.</td></tr>@endforelse
        </tbody></table><div class="mt-4">{{ $requests->links() }}</div>
    </div>
    @can('record_payments')
    <div class="hms-card"><h2 class="font-semibold">Configured invoices</h2><p class="text-sm text-slate-500 mb-3">Issuance remains disabled until the owner enters approved legal, tax and numbering details.</p>
        @foreach($folios->where('status', 'checked_out')->where('force_checkout', false) as $folio)<form method="POST" action="{{ route('invoices.folio', $folio) }}" class="flex flex-wrap gap-3 items-center mb-2">@csrf<input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><span>Folio #{{ $folio->id }} · {{ $folio->guest?->name }}</span><button class="btn-hms-primary">Issue / open invoice</button></form>@endforeach
        @foreach($payments as $payment)<form method="POST" action="{{ route('invoices.order', $payment->order_id) }}" class="flex flex-wrap gap-3 items-center mb-2">@csrf<input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><span>Walk-in order #{{ $payment->order_id }}</span><button class="btn-hms-primary">Issue / open invoice</button></form>@endforeach
    </div>@endcan
</div>
@endsection
