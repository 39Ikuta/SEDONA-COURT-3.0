@extends('layouts.app')

@section('title', 'Guest Orders — Room '.$folio->room->number)
@section('subtitle', $folio->transaction_id.' | '.$folio->guest->name)

@section('top_action')
    <a href="{{ route('dashboard') }}" class="bg-slate-800 text-white text-xs font-bold px-3 py-2 rounded">Return to Front Desk</a>
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-3 py-4 space-y-5">
    @if($errors->any())
        <div class="bg-rose-50 border border-rose-300 text-rose-900 p-3 rounded">
            <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="hms-card flex flex-wrap justify-between gap-3">
        <div>
            <div class="font-bold">Room {{ $folio->room->number }} — {{ $folio->guest->name }}</div>
            <div class="text-xs text-slate-600">{{ $folio->transaction_id }} · {{ strtoupper($folio->rate_tier) }} package · {{ ucfirst($folio->status) }}</div>
            <div class="text-xs text-slate-600">Check-in: {{ $folio->checked_in_at->format('m/d/Y h:i A') }} · Checkout: {{ $folio->expected_checkout_at->format('m/d/Y h:i A') }}</div>
        </div>
        <div class="font-bold">Add-ons billed: ₱{{ number_format($folio->pos_total, 2) }}</div>
    </div>

    @if(auth()->user()->hasRole('admin') && $folio->isActive() && !$folio->room->is_staff_quarters)
        <section class="hms-card" id="cancelGuestStay">
            <h2 class="hms-card-header">ADMIN: CANCEL STAY</h2>
            <p class="text-sm text-slate-600">Admins can cancel a new unpaid stay within 15 minutes of its creation. This window does not change when RA corrects the check-in time.</p>
            <p class="text-sm mt-2">Created: <strong>{{ $folio->created_at->format('m/d/Y h:i:s A') }}</strong> · Cancellation deadline: <strong>{{ $folio->created_at->copy()->addMinutes(15)->format('h:i:s A') }}</strong></p>
            @if($stayCancellationReason)
                <p class="text-sm text-amber-800 mt-3">{{ $stayCancellationReason }}</p>
            @else
                <form method="POST" action="{{ route('folios.cancel', $folio) }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <label class="text-sm font-bold grow">Reason for cancelling stay
                        <input type="text" name="reason" required maxlength="500" placeholder="Guest changed their mind" class="form-control-hms block mt-1">
                    </label>
                    <button type="submit" class="bg-rose-700 hover:bg-rose-800 text-white font-bold px-4 py-2 rounded">Cancel stay</button>
                </form>
            @endif
        </section>
    @endif

    <section class="hms-card" id="previousGuestOrders">
        <h2 class="hms-card-header">PREVIOUS ORDERS</h2>
        @forelse($folio->orders as $order)
            <div class="mt-4 border border-slate-200 rounded overflow-hidden">
                <div class="bg-slate-50 p-3 flex flex-wrap justify-between gap-2 text-xs">
                    <span class="font-bold">{{ $order->transaction_id }} · {{ ucfirst($order->status) }}</span>
                    <span>{{ $order->created_at->format('m/d/Y h:i A') }} · {{ $order->cashier?->name ?? 'Staff' }}</span>
                </div>
                @if($folio->isActive() && $order->type === 'room_charge' && $order->isNew() && $order->isWithinCancellationGrace())
                    <div class="p-3 bg-rose-50 border-b border-rose-100">
                        <p class="text-xs text-rose-900 mb-2">Unprepared orders may be cancelled until {{ $order->created_at->copy()->addMinutes(15)->format('h:i:s A') }}. Their unused stock is returned automatically.</p>
                        <form method="POST" action="{{ route('folios.orders.cancel', [$folio, $order]) }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <label class="text-xs font-bold grow">Reason for cancelling order
                                <input name="reason" type="text" required maxlength="500" class="form-control-hms block mt-1" placeholder="Guest cancelled before preparation">
                            </label>
                            <button class="bg-rose-700 hover:bg-rose-800 text-white font-bold px-3 py-2 rounded text-xs">Cancel order</button>
                        </form>
                    </div>
                @endif
                <div class="overflow-x-auto">
                    <table class="hms-table">
                        <thead><tr><th>ITEM</th><th>QUANTITY</th><th>UNIT PRICE</th><th>AMOUNT</th><th>CORRECTIONS</th></tr></thead>
                        <tbody>
                            @foreach($order->items as $line)
                                <tr>
                                    <td class="text-left">{{ $line->item_name }}</td>
                                    <td>{{ $line->quantity }} @if($line->quantity === 0)<span class="text-rose-700">Cancelled</span>@endif</td>
                                    <td>₱{{ number_format($line->unit_price, 2) }}</td>
                                    <td>₱{{ number_format($line->subtotal, 2) }}</td>
                                    <td class="text-left">
                                        @if($correction = $corrections->get($line->id))
                                            <p class="text-xs text-slate-600">{{ json_decode($correction->new_values, true)['reason'] ?? '' }} — {{ $correction->name }}</p>
                                        @endif
                                        @if($folio->isActive() && $line->quantity > 0)
                                            @can('review-financial')
                                                <details class="text-xs">
                                                    <summary class="cursor-pointer font-bold text-sky-800">Correct order</summary>
                                                    <form method="POST" action="{{ route('folios.orders.correct', [$folio, $line]) }}" class="mt-2 space-y-2">
                                                        @csrf
                                                        <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                        <input type="hidden" name="expected_quantity" value="{{ $line->quantity }}">
                                                        <label class="block">Correct quantity (0 cancels this line)
                                                            <input type="number" name="quantity" min="0" max="{{ $line->quantity }}" value="{{ $line->quantity }}" required class="form-control-hms">
                                                        </label>
                                                        <label class="block">Reason
                                                            <input type="text" name="reason" maxlength="500" required class="form-control-hms">
                                                        </label>
                                                        <p class="text-slate-500">Only unprepared items are returned to stock. Additional items must be placed as a new order.</p>
                                                        <button type="submit" class="bg-rose-700 text-white px-3 py-2 rounded font-bold">Save correction</button>
                                                    </form>
                                                </details>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><td colspan="3" class="font-bold text-right">ORDER TOTAL</td><td class="font-bold">₱{{ number_format($order->total, 2) }}</td><td></td></tr></tfoot>
                    </table>
                </div>
            </div>
        @empty
            @if(empty($legacyItems))<p class="text-slate-500 text-sm py-4">No previous orders for this stay.</p>@endif
        @endforelse

        @if(!empty($legacyItems))
            <h3 class="font-bold text-sm mt-4 mb-2">Earlier add-ons</h3>
            <div class="overflow-x-auto">
                <table class="hms-table">
                    <thead><tr><th>ITEM</th><th>QUANTITY</th><th>UNIT PRICE</th><th>AMOUNT</th></tr></thead>
                    <tbody>@foreach($legacyItems as $item)<tr>
                        <td class="text-left">{{ $item['name'] ?? 'Item' }}</td><td>{{ $item['qty'] ?? 0 }}</td>
                        <td>₱{{ number_format($item['price'] ?? 0, 2) }}</td><td>₱{{ number_format($item['subtotal'] ?? 0, 2) }}</td>
                    </tr>@endforeach</tbody>
                </table>
            </div>
        @endif
    </section>

    @if($folio->isActive())
        <section class="hms-card">
            <h2 class="hms-card-header">NEW ADD-ONS</h2>
            <form method="POST" action="{{ route('folios.addon', $folio) }}" id="guestAddOnForm" class="space-y-3">
                @csrf
                <input type="hidden" name="operation_key" value="{{ old('operation_key', (string) \Illuminate\Support\Str::uuid()) }}">
                <div class="overflow-x-auto">
                    <table class="hms-table">
                        <thead><tr><th>ITEM</th><th>QUANTITY</th><th>AMOUNT</th><th></th></tr></thead>
                        <tbody id="guestAddonRows">
                            @foreach(old('items', [['id' => '', 'qty' => 1]]) as $index => $row)
                                <tr class="addon-row">
                                    <td><select name="items[{{ $index }}][id]" data-field="id" required class="form-control-hms" aria-label="Add-on item">
                                        <option value="">Select an item</option>
                                        @foreach($catalog as $category => $items)<optgroup label="{{ $category }}">
                                            @foreach($items as $item)<option value="{{ $item->id }}" data-price="{{ $item->price }}" @selected((string)($row['id'] ?? '') === (string)$item->id)>{{ $item->name }} — ₱{{ number_format($item->price, 2) }}</option>@endforeach
                                        </optgroup>@endforeach
                                    </select></td>
                                    <td><input type="number" name="items[{{ $index }}][qty]" data-field="qty" min="1" max="65535" value="{{ $row['qty'] ?? 1 }}" required class="form-control-hms" aria-label="Add-on quantity"></td>
                                    <td><output data-line-total>₱0.00</output></td>
                                    <td><button type="button" data-remove-addon class="text-rose-700 font-bold">Remove</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap justify-between items-center gap-3">
                    <button type="button" id="addGuestAddonRow" class="border border-sky-700 text-sky-800 px-3 py-2 rounded font-bold">+ Add another item</button>
                    <span class="font-bold">New order total: <output id="guestAddonTotal">₱0.00</output></span>
                    <button type="submit" class="bg-sky-700 text-white px-4 py-2 rounded font-bold">Submit add-ons once</button>
                </div>
            </form>
        </section>

        @unless($folio->room->is_staff_quarters)
            <section class="hms-card">
                <h2 class="hms-card-header">CONVERT STAY PACKAGE</h2>
                <p class="text-sm text-slate-600 mb-3">The new package starts from the original check-in time and replaces hourly extensions. Checkout alarms and the maximum 15-minute grace period follow the new checkout time.</p>
                <form method="POST" action="{{ route('folios.convert', $folio) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <label class="text-sm">Package
                        <select name="rate_tier" id="guestPackageTier" required class="form-control-hms">
                            @foreach(['12h' => 12, '24h' => 24] as $tier => $hours)
                                <option value="{{ $tier }}" @selected($tier === ($folio->rate_tier === '12h' ? '24h' : '12h')) data-checkout="{{ $folio->checked_in_at->copy()->addHours($hours)->format('m/d/Y h:i A') }}">{{ $hours }} hours — ₱{{ number_format($folio->room->getRateForTier($tier), 2) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm grow">Reason
                        <input type="text" name="reason" maxlength="500" required placeholder="Guest requested a longer package" class="form-control-hms">
                    </label>
                    <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded font-bold">Convert package</button>
                    <p class="w-full text-sm font-bold" id="guestPackageCheckout"></p>
                </form>
            </section>
        @endunless
    @endif
</div>
@endsection

@push('scripts')
<script>
    const guestRows = document.getElementById('guestAddonRows');
    if (guestRows) {
        const rowTemplate = guestRows.querySelector('.addon-row').cloneNode(true);
        const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
        function updateGuestAddons() {
            let total = 0;
            guestRows.querySelectorAll('.addon-row').forEach((row, index) => {
                row.querySelectorAll('[data-field]').forEach(input => { input.name = 'items[' + index + '][' + input.dataset.field + ']'; });
                const item = row.querySelector('[data-field="id"]');
                const quantity = Number(row.querySelector('[data-field="qty"]').value) || 0;
                const amount = (Number(item.selectedOptions[0]?.dataset.price) || 0) * quantity;
                row.querySelector('[data-line-total]').textContent = money.format(amount);
                total += amount;
            });
            document.getElementById('guestAddonTotal').textContent = money.format(total);
        }
        guestRows.addEventListener('input', updateGuestAddons);
        guestRows.addEventListener('change', updateGuestAddons);
        guestRows.addEventListener('click', event => {
            if (event.target.closest('[data-remove-addon]')) {
                if (guestRows.querySelectorAll('.addon-row').length > 1) {
                    event.target.closest('.addon-row').remove();
                } else {
                    guestRows.querySelector('[data-field="id"]').value = '';
                    guestRows.querySelector('[data-field="qty"]').value = 1;
                }
                updateGuestAddons();
            }
        });
        document.getElementById('addGuestAddonRow').addEventListener('click', () => {
            const row = rowTemplate.cloneNode(true);
            row.querySelector('[data-field="id"]').value = '';
            row.querySelector('[data-field="qty"]').value = 1;
            guestRows.appendChild(row);
            updateGuestAddons();
        });
        updateGuestAddons();
    }
    const packageTier = document.getElementById('guestPackageTier');
    if (packageTier) {
        const updateCheckout = () => { document.getElementById('guestPackageCheckout').textContent = 'New checkout: ' + packageTier.selectedOptions[0].dataset.checkout; };
        packageTier.addEventListener('change', updateCheckout);
        updateCheckout();
    }
</script>
@endpush
