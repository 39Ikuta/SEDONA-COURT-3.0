@extends('layouts.app')

@section('title', 'Front Desk Operations Matrix')
@section('subtitle', 'Front Desk Operations | 32-Room Visual Operations Grid')


@section('content')
<div class="px-2 pt-2 space-y-3">

    <!-- KPI Summary Row -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 px-1 text-xs">
        <div class="bg-white p-2.5 border-l-4 border-slate-700 shadow-sm flex items-center justify-between">
            <span class="font-bold text-slate-600 uppercase text-[11px]">Total Rooms</span>
            <span class="font-mono font-bold text-base text-slate-900">{{ $totalRooms }}</span>
        </div>
        <div class="bg-white p-2.5 border-l-4 border-available shadow-sm flex items-center justify-between">
            <span class="font-bold text-emerald-800 uppercase text-[11px]">Available</span>
            <span class="font-mono font-bold text-base text-available">{{ $availableRooms }}</span>
        </div>
        <div class="bg-white p-2.5 border-l-4 border-occupied shadow-sm flex items-center justify-between">
            <span class="font-bold text-rose-800 uppercase text-[11px]">Occupied</span>
            <span class="font-mono font-bold text-base text-occupied">{{ $occupiedRooms }}</span>
        </div>
        <div class="bg-white p-2.5 border-l-4 border-classic shadow-sm flex items-center justify-between">
            <span class="font-bold text-amber-800 uppercase text-[11px]">Occupancy</span>
            <span class="font-mono font-bold text-base text-amber-700">{{ $occupancyRate }}%</span>
        </div>
    </div>

    @if(auth()->check() && auth()->user()->hasAnyRole(['cashier', 'front_desk']))
        <!-- Cashier personal shift performance (own sales only — never global) -->
        <div class="mx-1 bg-white border border-slate-200 shadow-sm px-3 py-2 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-2 min-w-0">
                <span class="pill shrink-0" style="background-color:#421A2B;color:#fff;border-color:#341421;">My Shift</span>
                <span class="font-bold text-slate-500 uppercase text-[10px] tracking-wider shrink-0 hidden md:inline">{{ $myShiftLabel ?? '' }}</span>
                <span class="font-mono font-black text-sm text-brand whitespace-nowrap">₱{{ number_format($myShiftRevenue ?? 0, 2) }}</span>
                <span class="text-[11px] text-slate-500 whitespace-nowrap">{{ $myCheckIns ?? 0 }} check-ins &bull; {{ $myOrdersCount ?? 0 }} orders</span>
            </div>
            <div class="flex flex-wrap items-center justify-start sm:justify-end gap-1.5">
                <span class="pill pill-available shrink-0"><span class="pill-dot"></span>{{ $availableRooms }} Available</span>
                <span class="pill pill-occupied shrink-0"><span class="pill-dot"></span>{{ $occupiedRooms }} Occupied</span>
                <span class="text-[11px] font-semibold text-slate-500 whitespace-nowrap shrink-0">{{ $occupancyRate }}% Occ.</span>
            </div>
        </div>
    @endif

    <!-- Admin / Owner Pending Force Checkout Loss Resolution Banner -->
    @if(count($pendingForceCheckouts) > 0 && auth()->user()->hasAnyRole(['admin', 'owner']))
        <div class="bg-amber-50 border border-amber-400 p-3 mx-1 text-xs">
            <div class="font-bold text-amber-900 mb-1">
                ATTENTION: Pending Force Checkout Requests ({{ count($pendingForceCheckouts) }})
            </div>
            <div class="space-y-1.5">
                @foreach($pendingForceCheckouts as $pfc)
                    <div class="flex items-center justify-between bg-white p-2 border border-amber-300">
                        <div>
                            <strong>Room {{ $pfc->room->number ?? 'N/A' }}</strong> &bull;
                            Guest: {{ $pfc->guest->name ?? 'Guest' }} &bull;
                            Unsettled Total: <strong class="font-mono text-red-700">₱{{ number_format($pfc->net_total, 2) }}</strong> &bull;
                            Reason: <span class="italic text-slate-700">{{ $pfc->force_reason ?? 'Skip-out' }}</span>
                        </div>
                        <form method="POST" action="{{ route('admin.force_checkout.approve', $pfc->id) }}" onsubmit="return confirm('Approve Force Checkout? This will write off ₱{{ number_format($pfc->net_total, 2) }} as an audited loss slip.')">
                            @csrf
                            <button type="submit" class="px-3 py-1 bg-rose-700 hover:bg-rose-800 text-white font-bold text-xs rounded">
                                Approve Loss Slip (FCE-######)
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- ACTIVE CHECKOUT ALARM BANNER (Multi-Room Audio Trigger) -->
    <div id="activeAlarmBanner" class="hidden bg-purple-900 border-2 border-purple-500 text-white p-3 mx-1 shadow-lg rounded-lg">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center space-x-2 font-black text-xs md:text-sm">
                <span class="text-base" id="alarmBannerIcon">⚠️</span>
                <span id="alarmBannerTitle">AUDIBLE CHECKOUT ALARM: GUEST STAY OVERDUE DETECTED</span>
            </div>
            <button onclick="if(window.sedonaAudio) window.sedonaAudio.stopAllAlarms()" class="px-3 py-1 bg-purple-950 hover:bg-black text-white text-[11px] font-bold rounded border border-purple-400 shadow-sm">
                Stop Alarm Sound
            </button>
        </div>
        <div id="alarmBannerRoomsList" class="space-y-1.5">
            <!-- Dynamically populated by SedonaAudioEngine -->
        </div>
    </div>

    <!-- Main 2-Column Matrix -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">

        <!-- LEFT COLUMN: AVAILABLE ROOMS -->
        <div class="lg:col-span-4">
            <div class="hms-card">
                <div class="hms-card-header border-b pb-2 mb-2 flex items-center justify-between">
                    <span class="text-available font-bold text-sm">AVAILABLE ROOMS</span>
                    <span class="text-xs text-slate-500 font-normal">({{ $rooms->where('status', 'available')->where('is_staff_quarters', false)->count() }} Ready)</span>
                </div>

                <div class="overflow-x-auto max-h-[700px] overflow-y-auto">
                    <table class="hms-table">
                        <thead class="sticky top-0 z-10 bg-slate-100">
                            <tr>
                                <th style="width: 75px;">ACTION</th>
                                <th style="width: 70px;">ROOM</th>
                                <th style="width: 45px;">FL</th>
                                <th>ROOM TYPE</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rooms->where('status', 'available') as $room)
                                @if($room->is_staff_quarters)
                                    <tr class="bg-indigo-50/70">
                                        <td class="space-x-1 whitespace-nowrap">
                                            <span class="px-2 py-0.5 bg-indigo-700 text-white text-[10px] font-bold rounded">Staff</span>
                                            @php $staffFolio = $room->activeFolio ?? $room->getOrCreateStaffFolio(); @endphp
                                            <button onclick="openAddOnModal({{ $staffFolio->id }}, '{{ $room->number }} (Staff Quarters)')" class="px-2 py-0.5 bg-[#0284c7] hover:bg-[#0369a1] text-white text-[10px] font-bold rounded shadow-sm">
                                                + Add On
                                            </button>
                                        </td>
                                        <td class="font-bold text-indigo-950 font-mono">{{ $room->number }}</td>
                                        <td class="text-slate-500 font-mono text-[11px]">{{ $room->floor }}</td>
                                        <td class="text-left pl-2 text-indigo-900 font-medium">Permanent Employee Quarters</td>
                                    </tr>
                                @else
                                    <tr>
                                        <td>
                                            <button onclick="openCheckInModal({{ $room->id }}, '{{ $room->number }}', '{{ $room->type }}', {{ $room->base_rate_3h }}, {{ $room->base_rate_12h }}, {{ $room->base_rate_24h }}, {{ $room->base_rate_promo }})" class="btn-checkin">
                                                Check In
                                            </button>
                                        </td>
                                        <td class="font-bold text-slate-900 font-mono text-sm">{{ $room->number }}</td>
                                        <td class="text-slate-500 font-mono text-[11px]">{{ $room->floor }}</td>
                                        <td class="text-left pl-2 font-medium">
                                            @php
                                                $roomUpper = strtoupper($room->type ?? '');
                                                $roomPill = str_contains($roomUpper, 'VIP') || str_contains($roomUpper, 'SUITE') ? 'pill-vip' : (str_contains($roomUpper, 'PREMIUM') ? 'pill-premium' : 'pill-classic');
                                            @endphp
                                            <span class="pill {{ $roomPill }}">{{ $room->type }}</span>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-slate-400">No rooms available under this filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: OCCUPIED ROOMS -->
        <div class="lg:col-span-8">
            <div class="hms-card">
                <div class="hms-card-header border-b pb-2 mb-2 flex items-center justify-between">
                    <span class="text-occupied font-bold text-sm">OCCUPIED ROOMS</span>
                    <span class="text-xs text-slate-500 font-normal">({{ $rooms->where('status', 'occupied')->count() }} Active Guests)</span>
                </div>

                <div class="overflow-x-auto max-h-[700px] overflow-y-auto">
                    <table class="hms-table">
                        <thead class="sticky top-0 z-10 bg-slate-100">
                            <tr>
                                <th style="min-width: 175px;">PRIMARY ACTIONS</th>
                                <th style="width: 55px;">ROOM</th>
                                <th style="width: 80px;">DATE</th>
                                <th style="width: 85px;">CHECK-IN TIME</th>
                                <th style="width: 45px;">PAX</th>
                                <th style="width: 60px;">STAY</th>
                                <th style="width: 110px;">REMAINING</th>
                                <th style="width: 85px;">STATUS</th>
                                <th style="min-width: 220px;">FOLIO & SERVICE ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rooms->where('status', 'occupied') as $room)
                                @php
                                    $folio = $room->activeFolio;
                                @endphp
                                @if($folio)
                                    <tr data-room-id="{{ $room->id }}"
                                        data-room-number="{{ $room->number }}"
                                        data-guest-name="{{ $folio->guest->name ?? 'Guest' }}"
                                        data-checkout-time="{{ $folio->expected_checkout_at->toIso8601String() }}"
                                        data-folio-id="{{ $folio->id }}"
                                        data-status="{{ $room->status }}">
                                        
                                        <!-- Primary Actions -->
                                        <td class="space-x-1 whitespace-nowrap text-left">
                                            <a href="{{ route('checkout.process', $folio->id) }}" class="btn-occupied font-bold">
                                                Occupied
                                            </a>
                                            <button onclick="openXtendModal({{ $folio->id }}, '{{ $room->number }}')" class="btn-xtend font-bold">
                                                Xtend
                                            </button>
                                            <button onclick="openDiscountModal({{ $folio->id }}, '{{ $room->number }}', '{{ $room->type }}', '{{ $folio->rate_tier }}', '{{ $folio->discount_type ?? 'NONE' }}', '{{ $folio->discount_id_ref ?? '' }}')" class="btn-discount font-bold">
                                                Discount
                                            </button>
                                            <button onclick="openTransferModal({{ $folio->id }}, '{{ $room->number }}')" class="btn-transfer font-bold">
                                                Transfer
                                            </button>
                                        </td>

                                        <!-- Room Number -->
                                        <td class="font-bold font-mono text-sm text-slate-900">
                                            {{ $room->number }}
                                        </td>

                                        <!-- Check In Date -->
                                        <td class="font-mono text-[11px]">{{ $folio->checked_in_at->format('m/d/Y') }}</td>

                                        <!-- Check In Time -->
                                        <td class="font-mono text-[11px]">{{ $folio->checked_in_at->format('h:i A') }}</td>

                                        <!-- No of Guest -->
                                        <td class="font-bold">{{ $folio->guest->headcount ?? 2 }}</td>

                                        <!-- Stay Tier -->
                                        <td class="font-bold font-mono">{{ strtoupper($folio->rate_tier) }}</td>

                                        <!-- Live Reactive Stay Countdown Timer -->
                                        <td class="stay-countdown-timer font-mono text-[11px]">
                                            Calculating...
                                        </td>

                                        <!-- Status Badge -->
                                        <td>
                                            <span class="pill pill-occupied">
                                                <span class="pill-dot"></span>OCCUPIED
                                            </span>
                                        </td>

                                        <!-- Folio & Service Actions -->
                                        <td class="space-x-1 whitespace-nowrap text-right">
                                            <button onclick="openAddOnModal({{ $folio->id }}, '{{ $room->number }}')" class="btn-addon font-bold">
                                                Add On
                                            </button>
                                            <a href="{{ route('folios.orderslip', $folio->id) }}" target="_blank" class="btn-orderslip font-bold">
                                                Order Slip
                                            </a>
                                            <a href="{{ route('pos.index') }}" class="btn-xorder font-bold">
                                                X Order
                                            </a>
                                            <button onclick="openForceCheckoutModal({{ $folio->id }}, '{{ $room->number }}', '{{ number_format($folio->net_total, 2) }}')" class="btn-force font-bold" title="Force Checkout Request">
                                                Force
                                            </button>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="9" class="py-8 text-center text-slate-400">No rooms currently occupied.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>

@include('dashboard.partials.folio-modals')
@endsection
