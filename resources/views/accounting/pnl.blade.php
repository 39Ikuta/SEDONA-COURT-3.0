@extends('layouts.app')

@section('title', 'Executive Profit & Loss Statement')
@section('subtitle', 'Accounting & Financial Hub | Profit & Loss Statement (P&L) & Revenue Analytics')

@section('top_action')
    <div class="flex items-center space-x-2">
        <a href="{{ route('admin.accounting.pnl.csv', ['range' => $range, 'start_date' => request('start_date'), 'end_date' => request('end_date')]) }}" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-[11px] px-3 py-1 rounded shadow-sm">
            ⤓ Export CSV Report
        </a>
        <button onclick="window.print()" class="bg-slate-700 hover:bg-slate-800 text-white font-bold text-[11px] px-3 py-1 rounded shadow-sm">
            ⎙ Print Statement
        </button>
    </div>
@endsection

@section('content')
<div class="px-3 pt-2 space-y-3 max-w-7xl mx-auto">

    <!-- Date Range Filter Bar -->
    <div class="bg-white p-2.5 border-l-4 border-[#421A2B] shadow-sm flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex items-center space-x-1">
            <span class="font-bold text-slate-700 uppercase tracking-wide mr-2">Reporting Period:</span>
            <a href="{{ route('admin.accounting.pnl', ['range' => 'today']) }}" class="px-2.5 py-1 rounded font-bold {{ $range === 'today' ? 'bg-[#421A2B] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-300' }}">Today</a>
            <a href="{{ route('admin.accounting.pnl', ['range' => 'this_week']) }}" class="px-2.5 py-1 rounded font-bold {{ $range === 'this_week' ? 'bg-[#421A2B] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-300' }}">This Week</a>
            <a href="{{ route('admin.accounting.pnl', ['range' => 'this_month']) }}" class="px-2.5 py-1 rounded font-bold {{ $range === 'this_month' ? 'bg-[#421A2B] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-300' }}">This Month</a>
            <a href="{{ route('admin.accounting.pnl', ['range' => 'last_month']) }}" class="px-2.5 py-1 rounded font-bold {{ $range === 'last_month' ? 'bg-[#421A2B] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-300' }}">Last Month</a>
        </div>

        <form method="GET" action="{{ route('admin.accounting.pnl') }}" class="flex items-center space-x-2">
            <input type="hidden" name="range" value="custom">
            <input type="date" name="start_date" value="{{ request('start_date', $startDate->toDateString()) }}" class="form-control-hms !w-32 font-mono text-xs">
            <span class="text-slate-400">to</span>
            <input type="date" name="end_date" value="{{ request('end_date', $endDate->toDateString()) }}" class="form-control-hms !w-32 font-mono text-xs">
            <button type="submit" class="px-3 py-1 bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs rounded">Filter</button>
        </form>
    </div>

    <!-- Executive KPI Row -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
        <div class="bg-white p-3 border-l-4 border-emerald-600 shadow-sm">
            <span class="font-bold text-slate-500 uppercase text-[10px] block">Gross Collections</span>
            <div class="text-xl font-mono font-black text-emerald-800 mt-0.5">₱{{ number_format($grossRevenue, 2) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">{{ $totalCheckIns }} Stays in {{ $rangeLabel }}</div>
        </div>

        <div class="bg-white p-3 border-l-4 border-rose-600 shadow-sm">
            <span class="font-bold text-slate-500 uppercase text-[10px] block">Operating Expenses (OPEX)</span>
            <div class="text-xl font-mono font-black text-rose-700 mt-0.5">₱{{ number_format($totalOpex, 2) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">{{ count($expenses) }} itemized vouchers</div>
        </div>

        <div class="bg-white p-3 border-l-4 {{ $netOperatingProfit >= 0 ? 'border-blue-600' : 'border-red-600' }} shadow-sm">
            <span class="font-bold text-slate-500 uppercase text-[10px] block">Net Operating Income (EBITDA)</span>
            <div class="text-xl font-mono font-black {{ $netOperatingProfit >= 0 ? 'text-blue-800' : 'text-red-700' }} mt-0.5">
                ₱{{ number_format($netOperatingProfit, 2) }}
            </div>
            <div class="text-[10px] text-slate-400 mt-0.5">Gross minus OPEX & Deductions</div>
        </div>

        <div class="bg-white p-3 border-l-4 border-purple-600 shadow-sm">
            <span class="font-bold text-slate-500 uppercase text-[10px] block">12% Inclusive VAT Split</span>
            <div class="text-xl font-mono font-black text-purple-800 mt-0.5">₱{{ number_format($vatOutputAmount, 2) }}</div>
            <div class="text-[10px] text-slate-400 mt-0.5">Vatable Base: ₱{{ number_format($netVatableSales, 2) }}</div>
        </div>
    </div>

    <!-- Main Two-Column P&L Ledger -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">

        <!-- Left 6 Cols: Revenue Streams & Deductions -->
        <div class="lg:col-span-6 space-y-3">
            <div class="hms-card">
                <div class="hms-card-header border-b pb-2 mb-2 flex items-center justify-between">
                    <span>1. OPERATING REVENUE STREAMS</span>
                    <span class="text-xs text-slate-500 font-mono">{{ $rangeLabel }}</span>
                </div>

                <div class="space-y-1.5 text-xs">
                    <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-200">
                        <div>
                            <div class="font-bold text-slate-900">Room Base Lodging Revenue</div>
                            <div class="text-[10px] text-slate-500">Standard 3h, 6h, 12h, 24h & Promo tier collections</div>
                        </div>
                        <span class="font-mono font-bold text-slate-900">₱{{ number_format($roomLodgingRev, 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-200">
                        <div>
                            <div class="font-bold text-slate-900">Overtime & Stay Extension Surcharges</div>
                            <div class="text-[10px] text-slate-500">Excess hours billed @ ₱130.00 / hour</div>
                        </div>
                        <span class="font-mono font-bold text-purple-800">₱{{ number_format($overtimeRev, 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-200">
                        <div>
                            <div class="font-bold text-slate-900">Food & Beverage (Dining & Refreshments)</div>
                            <div class="text-[10px] text-slate-500">In-room dining folios + Walk-in POS transactions</div>
                        </div>
                        <span class="font-mono font-bold text-emerald-800">₱{{ number_format($totalDiningRev, 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-200">
                        <div>
                            <div class="font-bold text-slate-900">Amenity & Extra Guest Surcharges</div>
                            <div class="text-[10px] text-slate-500">Extra persons (₱200), towels (₱100), bedding (₱150)</div>
                        </div>
                        <span class="font-mono font-bold text-slate-900">₱{{ number_format($surchargesRev, 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 bg-emerald-50 border border-emerald-300 font-bold mt-2">
                        <span class="text-emerald-900 uppercase">TOTAL GROSS REVENUE:</span>
                        <span class="font-mono text-base text-emerald-800">₱{{ number_format($grossRevenue, 2) }}</span>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-200">
                    <div class="text-[11px] font-bold uppercase text-slate-500 mb-2">Less: Allowances & Statutory Deductions</div>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex items-center justify-between p-2 bg-rose-50/70 border border-rose-200">
                            <div>
                                <span class="font-bold text-rose-900">Senior Citizen / PWD Discounts (20%)</span>
                                <div class="text-[10px] text-rose-600">Statutory deductions with valid government ID</div>
                            </div>
                            <span class="font-mono font-bold text-rose-700">-₱{{ number_format($seniorPwdDiscounts, 2) }}</span>
                        </div>

                        <div class="flex items-center justify-between p-2 bg-rose-50/70 border border-rose-200">
                            <div>
                                <span class="font-bold text-rose-900">Audited Loss Slips (FCE-######)</span>
                                <div class="text-[10px] text-rose-600">Approved force checkouts & skip-out write offs</div>
                            </div>
                            <span class="font-mono font-bold text-rose-700">-₱{{ number_format($lossSlips, 2) }}</span>
                        </div>

                        <div class="flex items-center justify-between p-2 bg-slate-100 border border-slate-300 font-bold">
                            <span class="text-slate-800">NET CASH & GCASH COLLECTED:</span>
                            <span class="font-mono text-slate-900">₱{{ number_format($netRevenueCollected, 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Channel Split -->
                <div class="mt-4 pt-3 border-t border-slate-200 grid grid-cols-2 gap-2 text-xs">
                    <div class="p-2 bg-slate-50 border border-slate-200">
                        <span class="font-bold text-slate-500 uppercase text-[10px] block">Physical Cash Inflow</span>
                        <span class="font-mono font-bold text-emerald-700 text-sm">₱{{ number_format($cashCollections, 2) }}</span>
                    </div>
                    <div class="p-2 bg-slate-50 border border-slate-200">
                        <span class="font-bold text-slate-500 uppercase text-[10px] block">GCash E-Wallet Inflow</span>
                        <span class="font-mono font-bold text-blue-700 text-sm">₱{{ number_format($gcashCollections, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right 6 Cols: Operating Expenses & Net Operating Income -->
        <div class="lg:col-span-6 space-y-3">
            <div class="hms-card">
                <div class="hms-card-header border-b pb-2 mb-2 flex items-center justify-between">
                    <span>2. OPERATING EXPENSES (OPEX)</span>
                    <a href="{{ route('admin.accounting.expenses') }}" class="text-xs text-blue-700 hover:underline font-semibold">+ Manage Vouchers &rarr;</a>
                </div>

                <div class="space-y-1.5 text-xs">
                    <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-200">
                        <span>Utilities (Electricity, Water, Commercial WiFi)</span>
                        <span class="font-mono font-bold text-slate-900">₱{{ number_format($expensesByCategory['utilities'], 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-200">
                        <span>Housekeeping & Commercial Laundry Linens</span>
                        <span class="font-mono font-bold text-slate-900">₱{{ number_format($expensesByCategory['laundry_linens'], 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-200">
                        <span>Repairs, AC Servicing & Property Maintenance</span>
                        <span class="font-mono font-bold text-slate-900">₱{{ number_format($expensesByCategory['maintenance_repairs'], 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-200">
                        <span>Kitchen F&B Meat & Grocery Inventory Restock</span>
                        <span class="font-mono font-bold text-slate-900">₱{{ number_format($expensesByCategory['kitchen_inventory'], 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-200">
                        <span>Staff Wages, Duty Meals & Transport Allowances</span>
                        <span class="font-mono font-bold text-slate-900">₱{{ number_format($expensesByCategory['staff_allowances'], 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-200">
                        <span>Administrative Supplies & Business Permits</span>
                        <span class="font-mono font-bold text-slate-900">₱{{ number_format($expensesByCategory['administrative'], 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 bg-rose-50 border border-rose-300 font-bold mt-2">
                        <span class="text-rose-900 uppercase">TOTAL OPERATING EXPENSES (OPEX):</span>
                        <span class="font-mono text-base text-rose-700">₱{{ number_format($totalOpex, 2) }}</span>
                    </div>
                </div>

                <!-- Final Net Operating Income Box -->
                <div class="mt-4 p-3 bg-gradient-to-r {{ $netOperatingProfit >= 0 ? 'from-emerald-50 to-emerald-100 border-emerald-400' : 'from-rose-50 to-rose-100 border-rose-400' }} border flex items-center justify-between">
                    <div>
                        <div class="font-black uppercase text-xs {{ $netOperatingProfit >= 0 ? 'text-emerald-950' : 'text-rose-950' }}">
                            NET OPERATING PROFIT (EBITDA)
                        </div>
                        <div class="text-[10px] {{ $netOperatingProfit >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            Net Collections minus Total Operating Expenses
                        </div>
                    </div>
                    <div class="text-2xl font-mono font-black {{ $netOperatingProfit >= 0 ? 'text-emerald-800' : 'text-rose-700' }}">
                        ₱{{ number_format($netOperatingProfit, 2) }}
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>
@endsection
