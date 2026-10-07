{{-- Shared folio action modals + scripts (check-in, xtend, discount, transfer, add-on, force). Included by tables + board views. --}}

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: CHECK IN -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="checkInModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 bg-black/60 hidden">
    <div class="bg-white w-full max-w-md border-t-4 border-[#421A2B] p-5 shadow-2xl">
        <div class="flex items-center justify-between border-b pb-2 mb-3">
            <h3 class="font-bold text-[#421A2B] text-sm" id="checkInModalTitle">Check In Guest</h3>
            <button onclick="closeCheckInModal()" class="text-slate-500 hover:text-black font-bold text-lg">&times;</button>
        </div>

        <form method="POST" action="{{ route('checkin.store') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="room_id" id="checkInRoomId">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Customer / Guest Name:</label>
                <input type="text" name="guest_name" placeholder="Walk-in Guest Name" class="form-control-hms">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold text-slate-700">Check-in Date & Time:</label>
                        <span class="text-[9px] uppercase font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded inline-flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Live
                        </span>
                    </div>
                    <input type="text" 
                           id="checkInDateTimeDisplay" 
                           readonly 
                           tabindex="-1" 
                           style="pointer-events: none; background-color: #f8fafc; user-select: none; border-color: #cbd5e1; text-align: center;" 
                           class="form-control-hms font-mono text-[11px] font-bold text-slate-800 cursor-not-allowed select-none !text-center w-full" 
                           value="{{ now()->format('m/d/Y h:i:s A') }}"
                           title="Check-in date & time is locked to system live clock">
                    <input type="hidden" name="checked_in_at" id="checkInDateTime" value="{{ now()->toIso8601String() }}">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">No. of Guest (Pax):</label>
                    <input type="number" name="headcount" value="2" min="1" max="8" class="form-control-hms font-bold">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Stay Duration Tier:</label>
                <select name="rate_tier" id="checkInTierSelect" onchange="updateCheckInRatePreview()" class="form-control-hms font-bold">
                    <option value="3h">3 Hours Stay</option>
                    <option value="6h">6 Hours Stay</option>
                    <option value="12h" selected>12 Hours Stay</option>
                    <option value="24h">24 Hours Stay</option>
                    <option value="promo">Midnight Promo (8pm-6am)</option>
                </select>
            </div>

            <div class="bg-slate-50 p-2.5 border border-slate-200 text-xs flex items-center justify-between">
                <span class="text-slate-600 font-semibold">Standard Room Rate:</span>
                <span class="font-mono font-bold text-sm text-[#421A2B]" id="checkInRatePreview">₱1,195.00</span>
            </div>

            <div class="flex items-center space-x-2 pt-1">
                <input type="checkbox" name="is_senior" id="isSenior" value="1" class="rounded text-red-700">
                <label for="isSenior" class="text-xs text-slate-700 cursor-pointer font-semibold">Apply Statutory Senior / PWD Discount (20%)</label>
            </div>

            <div class="border-t pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeCheckInModal()" class="px-3 py-1.5 bg-slate-200 text-slate-700 text-xs font-bold rounded">Cancel</button>
                <button type="submit" class="px-5 py-1.5 bg-[#0284c7] hover:bg-[#0369a1] text-white text-xs font-bold rounded shadow">Process Check In</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: XTEND -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="xtendModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 bg-black/60 hidden">
    <div class="bg-white w-full max-w-sm border-t-4 border-[#0284c7] p-5 shadow-2xl">
        <div class="flex items-center justify-between border-b pb-2 mb-3">
            <h3 class="font-bold text-[#0284c7] text-sm" id="xtendModalTitle">Extend Stay</h3>
            <button onclick="closeXtendModal()" class="text-slate-500 hover:text-black font-bold text-lg">&times;</button>
        </div>

        <form method="POST" id="xtendForm" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Select Hours to Extend (₱130.00/hr):</label>
                <select name="hours" class="form-control-hms font-bold">
                    <option value="1">1 Hour (+₱130.00)</option>
                    <option value="2">2 Hours (+₱260.00)</option>
                    <option value="3" selected>3 Hours (+₱390.00)</option>
                    <option value="6">6 Hours (+₱780.00)</option>
                    <option value="12">12 Hours (+₱1,560.00)</option>
                </select>
            </div>

            <div class="border-t pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeXtendModal()" class="px-3 py-1.5 bg-slate-200 text-slate-700 text-xs font-bold rounded">Cancel</button>
                <button type="submit" class="px-4 py-1.5 bg-[#0284c7] hover:bg-[#0369a1] text-white text-xs font-bold rounded">Save Extension</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: DISCOUNT -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="discountModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 bg-black/60 hidden">
    <div class="bg-white w-full max-w-sm border-t-4 border-[#0284c7] p-5 shadow-2xl">
        <div class="flex items-center justify-between border-b pb-2 mb-3">
            <h3 class="font-bold text-[#0284c7] text-sm" id="discountModalTitle">Apply Discount</h3>
            <button onclick="closeDiscountModal()" class="text-slate-500 hover:text-black font-bold text-lg">&times;</button>
        </div>

        <form method="POST" id="discountForm" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Discount Classification:</label>
                <select name="discount_type" id="discountTypeSelect" onchange="updateDiscountPreview()" class="form-control-hms font-bold text-xs">
                    <option value="none">No Discount / Clear Existing</option>
                    <option value="senior">Senior Citizen (Statutory BIR Table)</option>
                    <option value="pwd">Person with Disability - PWD (Statutory BIR Table)</option>
                    <option value="dc">Sedona Discount Card (DC Loyalty Card)</option>
                </select>
            </div>

            <div id="discountRefGroup">
                <label class="block text-xs font-bold text-slate-700 mb-1">Card / ID Reference #:</label>
                <input type="text" name="discount_id_ref" id="discountIdRef" placeholder="e.g. OSCA-2024-9912 or DC-4091" class="form-control-hms font-mono text-xs font-bold">
                <span class="text-[10px] text-slate-500">Required for official tax audit and 80mm receipt itemization.</span>
            </div>

            <div class="p-2.5 bg-slate-100 border border-slate-300 rounded flex items-center justify-between text-xs">
                <div>
                    <span class="font-bold text-slate-800 uppercase text-[10px] block">Fixed Table Deduction:</span>
                    <span class="text-slate-500 text-[10px]" id="discountTierNotice">Classic Room (3h)</span>
                </div>
                <div class="font-mono font-black text-rose-700 text-base" id="discountAmountPreview">
                    -₱0.00
                </div>
            </div>

            <div class="border-t pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeDiscountModal()" class="px-3 py-1.5 bg-slate-200 text-slate-700 text-xs font-bold rounded">Cancel</button>
                <button type="submit" class="px-4 py-1.5 bg-[#0284c7] hover:bg-[#0369a1] text-white text-xs font-bold rounded">Apply Fixed Discount</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: ROOM TRANSFER -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="transferModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 bg-black/60 hidden">
    <div class="bg-white w-full max-w-md border-t-4 border-[#0284c7] p-5 shadow-2xl">
        <div class="flex items-center justify-between border-b pb-2 mb-3">
            <h3 class="font-bold text-[#0284c7] text-sm" id="transferModalTitle">Transfer Room</h3>
            <button onclick="closeTransferModal()" class="text-slate-500 hover:text-black font-bold text-lg">&times;</button>
        </div>

        <form method="POST" id="transferForm" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Select Target Available Room:</label>
                <select name="target_room_id" required class="form-control-hms font-bold">
                    @forelse($availableRoomsList as $ar)
                        <option value="{{ $ar->id }}">Room {{ $ar->number }} &bull; Floor {{ $ar->floor }} ({{ $ar->type }})</option>
                    @empty
                        <option value="" disabled selected>No other rooms currently available</option>
                    @endforelse
                </select>
            </div>
            <p class="text-[11px] text-slate-500">
                Notice: Old room will immediately transition to <strong>Available</strong>, and all active billings & food orders will follow the guest to the target room.
            </p>

            <div class="border-t pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeTransferModal()" class="px-3 py-1.5 bg-slate-200 text-slate-700 text-xs font-bold rounded">Cancel</button>
                <button type="submit" class="px-4 py-1.5 bg-[#0284c7] hover:bg-[#0369a1] text-white text-xs font-bold rounded">Complete Transfer</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: ADD ON -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="addOnModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 bg-black/60 hidden">
    <div class="bg-white w-full max-w-md border-t-4 border-[#0284c7] p-5 shadow-2xl">
        <div class="flex items-center justify-between border-b pb-2 mb-3">
            <h3 class="font-bold text-[#0284c7] text-sm" id="addOnModalTitle">Add On Item to Folio</h3>
            <button onclick="closeAddOnModal()" class="text-slate-500 hover:text-black font-bold text-lg">&times;</button>
        </div>

        <form method="POST" id="addOnForm" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Select Menu Item / Amenity:</label>
                <select name="pos_item_id" class="form-control-hms font-bold">
                    @foreach($posItems as $pi)
                        @php
                            $stock = $pi->stock_quantity ?? 0;
                            $isOut = !$pi->is_available || ($pi->is_tracked && $stock <= 0);
                            $eggNote = $pi->requiresEgg() ? " (Uses Egg)" : "";
                            $stockBadge = $pi->is_tracked ? " [Stock: {$stock}{$eggNote}]" : "";
                        @endphp
                        <option value="{{ $pi->id }}" {{ $isOut ? 'disabled class=text-slate-400' : '' }}>
                            {{ $pi->category }}: {{ $pi->name }} (₱{{ number_format($pi->price, 2) }}){{ $stockBadge }}{{ $isOut ? ' - OUT OF STOCK' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Quantity:</label>
                <input type="number" name="quantity" value="1" min="1" max="50" class="form-control-hms font-bold font-mono">
            </div>

            <div class="border-t pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeAddOnModal()" class="px-3 py-1.5 bg-slate-200 text-slate-700 text-xs font-bold rounded">Cancel</button>
                <button type="submit" class="px-4 py-1.5 bg-[#0284c7] hover:bg-[#0369a1] text-white text-xs font-bold rounded">Save Add On</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: FORCE CHECKOUT REQUEST -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="forceCheckoutModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 bg-black/60 hidden">
    <div class="bg-white w-full max-w-md border-t-4 border-[#374151] p-5 shadow-2xl">
        <div class="flex items-center justify-between border-b pb-2 mb-3">
            <h3 class="font-bold text-slate-900 text-sm" id="forceModalTitle">Request Force Checkout</h3>
            <button onclick="closeForceCheckoutModal()" class="text-slate-500 hover:text-black font-bold text-lg">&times;</button>
        </div>

        <form method="POST" id="forceForm" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Select Incident Reason:</label>
                <select name="force_reason" class="form-control-hms font-bold">
                    <option value="Guest skipped out without settlement">Guest skipped out without settlement</option>
                    <option value="Overstay and unreachable">Overstay and unreachable</option>
                    <option value="Disputed billing charges">Disputed billing charges</option>
                    <option value="Emergency property eviction">Emergency property eviction</option>
                </select>
            </div>

            <div class="border-t pt-3 flex justify-end space-x-2">
                <button type="button" onclick="closeForceCheckoutModal()" class="px-3 py-1.5 bg-slate-200 text-slate-700 text-xs font-bold rounded">Cancel</button>
                <button type="submit" class="px-4 py-1.5 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded">Submit for Approval</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    let currentRoomRates = {};

    let checkInClockInterval = null;

    function syncCheckInLiveTime() {
        const now = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        const localIso = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
        const dtInput = document.getElementById('checkInDateTime');
        if (dtInput) dtInput.value = localIso;

        const displayEl = document.getElementById('checkInDateTimeDisplay');
        if (displayEl) {
            const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
            const dateStr = now.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });
            displayEl.value = `${dateStr} ${timeStr}`;
        }
    }

    function openCheckInModal(roomId, roomNumber, roomType, r3, r12, r24, rpromo) {
        document.getElementById('checkInRoomId').value = roomId;
        document.getElementById('checkInModalTitle').textContent = `Check In Room ${roomNumber} (${roomType})`;
        
        syncCheckInLiveTime();
        if (checkInClockInterval) clearInterval(checkInClockInterval);
        checkInClockInterval = setInterval(syncCheckInLiveTime, 1000);

        currentRoomRates = { '3h': r3, '6h': r3 * 2, '12h': r12, '24h': r24, 'promo': rpromo };
        updateCheckInRatePreview();
        document.getElementById('checkInModal').classList.remove('hidden');
    }
    function updateCheckInRatePreview() {
        const tier = document.getElementById('checkInTierSelect').value;
        const rate = currentRoomRates[tier] || 0;
        document.getElementById('checkInRatePreview').textContent = `₱${rate.toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
    }
    function closeCheckInModal() {
        if (checkInClockInterval) {
            clearInterval(checkInClockInterval);
            checkInClockInterval = null;
        }
        document.getElementById('checkInModal').classList.add('hidden');
    }

    function openXtendModal(folioId, roomNumber) {
        document.getElementById('xtendForm').action = `/folios/${folioId}/extend`;
        document.getElementById('xtendModalTitle').textContent = `Extend Stay - Room ${roomNumber}`;
        document.getElementById('xtendModal').classList.remove('hidden');
    }
    function closeXtendModal() {
        document.getElementById('xtendModal').classList.add('hidden');
    }

    let currentDiscountContext = { roomType: 'Classic Room', rateTier: '3h' };
    const DISCOUNT_RATES = {
        'DC': {
            'CLASSIC': { '3h': 40, '6h': 0, '12h': 55, '24h': 95 },
            'PREMIUM': { '3h': 50, '6h': 0, '12h': 60, '24h': 105 },
            'VIP':     { '3h': 65, '6h': 0, '12h': 70, '24h': 115 }
        },
        'SENIOR': {
            'CLASSIC': { '3h': 79,  '6h': 158, '12h': 195, '24h': 340 },
            'PREMIUM': { '3h': 99,  '6h': 178, '12h': 215, '24h': 375 },
            'VIP':     { '3h': 139, '6h': 218, '12h': 255, '24h': 460 }
        }
    };

    function openDiscountModal(folioId, roomNumber, roomType, rateTier, currentDiscountType, currentDiscountRef) {
        document.getElementById('discountForm').action = `/folios/${folioId}/discount`;
        document.getElementById('discountModalTitle').textContent = `Apply Fixed Discount - Room ${roomNumber} (${roomType})`;
        
        currentDiscountContext = { roomType: roomType || 'Classic Room', rateTier: (rateTier || '3h').toLowerCase() };
        
        const dt = (currentDiscountType || 'none').toLowerCase();
        document.getElementById('discountTypeSelect').value = (dt === 'senior' || dt === 'pwd' || dt === 'dc') ? dt : 'none';
        document.getElementById('discountIdRef').value = currentDiscountRef || '';
        
        updateDiscountPreview();
        document.getElementById('discountModal').classList.remove('hidden');
    }

    function updateDiscountPreview() {
        const type = document.getElementById('discountTypeSelect').value.toUpperCase();
        const tier = currentDiscountContext.roomType.toUpperCase().includes('VIP') ? 'VIP' : (currentDiscountContext.roomType.toUpperCase().includes('PREMIUM') ? 'PREMIUM' : 'CLASSIC');
        const dur = currentDiscountContext.rateTier.startsWith('6') ? '6h' : (currentDiscountContext.rateTier.startsWith('12') ? '12h' : (currentDiscountContext.rateTier.startsWith('24') ? '24h' : '3h'));

        document.getElementById('discountTierNotice').textContent = `${tier} Room (${dur})`;
        
        if (type === 'NONE') {
            document.getElementById('discountAmountPreview').textContent = '₱0.00';
            document.getElementById('discountRefGroup').style.opacity = '0.5';
            return;
        }

        document.getElementById('discountRefGroup').style.opacity = '1';
        const lookupCat = (type === 'PWD') ? 'SENIOR' : type;
        const amount = (DISCOUNT_RATES[lookupCat] && DISCOUNT_RATES[lookupCat][tier]) ? (DISCOUNT_RATES[lookupCat][tier][dur] || 0) : 0;
        document.getElementById('discountAmountPreview').textContent = `-₱${amount.toFixed(2)}`;
    }

    function closeDiscountModal() {
        document.getElementById('discountModal').classList.add('hidden');
    }

    function openTransferModal(folioId, roomNumber) {
        document.getElementById('transferForm').action = `/folios/${folioId}/transfer`;
        document.getElementById('transferModalTitle').textContent = `Transfer Guest from Room ${roomNumber}`;
        document.getElementById('transferModal').classList.remove('hidden');
    }
    function closeTransferModal() {
        document.getElementById('transferModal').classList.add('hidden');
    }

    function openAddOnModal(folioId, roomNumber) {
        document.getElementById('addOnForm').action = `/folios/${folioId}/add-on`;
        document.getElementById('addOnModalTitle').textContent = `Add On Item - Room ${roomNumber}`;
        document.getElementById('addOnModal').classList.remove('hidden');
    }
    function closeAddOnModal() {
        document.getElementById('addOnModal').classList.add('hidden');
    }

    function openForceCheckoutModal(folioId, roomNumber, netTotal) {
        document.getElementById('forceForm').action = `/folios/${folioId}/force-checkout`;
        document.getElementById('forceModalTitle').textContent = `Request Force Checkout - Room ${roomNumber} (₱${netTotal})`;
        document.getElementById('forceCheckoutModal').classList.remove('hidden');
    }
    function closeForceCheckoutModal() {
        document.getElementById('forceCheckoutModal').classList.add('hidden');
    }
</script>
@endpush
