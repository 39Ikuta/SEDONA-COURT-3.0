<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') | {{ config('app.name', 'Sedona Court Executive PMS') }}</title>

    <!-- Google Fonts: Inter for clean enterprise PMS typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">

    <!-- Tailwind / Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #4b5563;
            color: #1f2937;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* Brand top bar: Deep Maroon/Burgundy */
        .hms-top-nav {
            background-color: #421A2B;
            color: #ffffff;
            font-size: 13px;
            font-weight: 600;
            border-bottom: 2px solid #341421;
        }
        .hms-top-nav .nav-link,
        .hms-top-nav .nav-trigger {
            color: #ffffff;
            text-decoration: none;
            padding: 9px 13px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: background 0.15s;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }
        .hms-top-nav .nav-link:hover,
        .hms-top-nav .nav-trigger:hover,
        .hms-top-nav .nav-link.active {
            background-color: #341421;
        }
        .hms-top-nav .nav-menu a {
            color: #ffffff;
            text-decoration: none;
            padding: 9px 13px;
            display: block;
            transition: background 0.15s;
            white-space: nowrap;
        }
        .hms-top-nav .nav-menu a:hover,
        .hms-top-nav .nav-menu a.active {
            background-color: #341421;
        }

        /* Slim page header (replaces dark grey sub-nav) */
        .hms-sub-nav {
            background-color: #ffffff;
            color: #1f2937;
            font-size: 12px;
            padding: 8px 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        /* Container Card */
        .hms-card {
            background-color: #ffffff;
            border-top: 3px solid #421A2B;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.06);
            margin: 10px;
            padding: 14px;
            border-radius: 2px;
        }

        .hms-card-header {
            color: #421A2B;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 0.02em;
        }

        /* High-Density PMS Tables */
        .hms-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
        }
        .hms-table th {
            background-color: #f3f4f6;
            color: #111827;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10.5px;
            padding: 7px 6px;
            border: 1px solid #d1d5db;
            text-align: center;
            letter-spacing: 0.03em;
        }
        .hms-table td {
            padding: 5px 6px;
            border: 1px solid #e5e7eb;
            text-align: center;
            vertical-align: middle;
            color: #1f2937;
        }
        .hms-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .hms-table tbody tr:hover {
            background-color: #f3f4f6;
        }

        /* Action Buttons — status-aligned */
        .btn-checkin {
            background-color: #10B981;
            color: #ffffff;
            border: 1px solid #0B8A66;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-checkin:hover { background-color: #0B7A55; }

        .btn-occupied {
            background-color: #EF4444;
            color: #ffffff;
            border: 1px solid #DC2626;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-occupied:hover { background-color: #DC2626; }

        .btn-xtend, .btn-discount, .btn-transfer, .btn-addon, .btn-orderslip, .btn-xorder {
            background-color: #0284c7;
            color: #ffffff;
            border: 1px solid #0369a1;
            padding: 3px 7px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-xtend:hover, .btn-discount:hover, .btn-transfer:hover, .btn-addon:hover, .btn-orderslip:hover, .btn-xorder:hover {
            background-color: #0369a1;
        }

        .btn-force {
            background-color: #4b5563;
            color: #ffffff;
            border: 1px solid #374151;
            padding: 3px 7px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-force:hover { background-color: #1f2937; }

        /* Room-type + status pills (pill-only, no card accents) */
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 1px 8px;
            border-radius: 9999px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border: 1px solid transparent;
            white-space: nowrap;
        }
        .pill-vip { background-color: #F3E8FF; color: #5B21B6; border-color: #DDD6FE; }
        .pill-premium { background-color: #3B82F6; color: #ffffff; border-color: #2563EB; }
        .pill-classic { background-color: #F59E0B; color: #ffffff; border-color: #D97706; }
        .pill-occupied { background-color: #FEE2E2; color: #B91C1C; border-color: #FECACA; }
        .pill-available { background-color: #D1FAE5; color: #065F46; border-color: #A7F3D0; }
        .pill-dot { width: 6px; height: 6px; border-radius: 9999px; background-color: currentColor; display: inline-block; }

        /* Form Controls */
        .form-control-hms {
            border: 1px solid #9ca3af;
            padding: 4px 8px;
            font-size: 12px;
            width: 100%;
            box-sizing: border-box;
            background-color: #ffffff;
            border-radius: 2px;
            color: #111827;
        }
        .form-control-hms:focus {
            border-color: #421A2B;
            outline: none;
            box-shadow: 0 0 0 2px rgba(66, 26, 43, 0.15);
        }
        .form-control-hms[readonly] {
            background-color: #f3f4f6;
            text-align: right;
            font-weight: 600;
        }
        .section-bar-grey {
            background-color: #6b7280;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 8px;
            margin: 8px 0 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* Owner tab bar (light, executive) */
        .owner-tabs {
            background-color: #faf8f4;
            border-bottom: 1px solid #e8e2d6;
        }
        .owner-tabs .exec-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 600;
            color: #6b6257;
            text-decoration: none;
            border: 1px solid transparent;
            background: transparent;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.15s;
        }
        .owner-tabs .exec-tab:hover {
            background-color: #f1ece2;
        }
        .owner-tabs .exec-tab.active {
            background-color: #f6e8e8;
            color: #421A2B;
            border-color: #e3c9c9;
            font-weight: 700;
        }
        .owner-tabs .tab-menu a {
            display: block;
            padding: 9px 13px;
            color: #334155;
            text-decoration: none;
            white-space: nowrap;
            font-size: 12px;
            font-weight: 600;
        }
        .owner-tabs .tab-menu a:hover {
            background-color: #f1f5f9;
        }
    </style>
    @stack('styles')
</head>
<body class="min-h-screen flex flex-col">

    <!-- Brand Navigation Bar (Deep Maroon) -->
    <header class="hms-top-nav sticky top-0 z-40 overflow-visible">
        <div class="flex items-center justify-between px-3 gap-2">
            <div class="flex items-center gap-1 min-w-0">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 pr-2 py-1.5 shrink-0" aria-label="Sedona home">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded bg-white/10 border border-white/20 font-black text-sm">S</span>
                    <span class="leading-tight">
                        <span class="block font-black tracking-wide text-[13px]">SEDONA</span>
                        <span class="block text-[9px] font-semibold text-white/70 tracking-widest uppercase">Court PMS</span>
                    </span>
                </a>

                <button type="button" id="primaryNavToggle" onclick="togglePrimaryNav()" class="md:hidden px-2 py-2 text-white" aria-expanded="false" aria-controls="primaryNav" aria-label="Toggle navigation">
                    ☰
                </button>

                <nav id="primaryNav" class="hidden md:flex items-center flex-wrap" aria-label="Primary">
                    @if(auth()->check() && auth()->user()->hasRole('kitchen'))
                        <a href="{{ route('kitchen.view') }}" class="nav-link {{ request()->routeIs('kitchen.view') ? 'active' : '' }}">
                            Kitchen KDS
                        </a>
                        <a href="{{ route('kitchen.tv') }}" target="_blank" class="nav-link">
                            Kitchen TV ↗
                        </a>
                    @elseif(auth()->check() && auth()->user()->hasRole('customer_display'))
                        <a href="{{ route('kiosk.view') }}" class="nav-link {{ request()->routeIs('kiosk.view') ? 'active' : '' }}">
                            Lobby Kiosk Display
                        </a>
                    @elseif(auth()->check() && auth()->user()->hasAnyRole(['cashier', 'front_desk']))
                        {{-- Cashier: minimal 5-item flat nav, no dropdowns, no kitchen/display/reports --}}
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard', 'rooms.*', 'checkout.*', 'folios.*', 'checkin.*') ? 'active' : '' }}">
                            Front Desk
                        </a>
                        <a href="{{ route('bookings.index') }}" class="nav-link {{ request()->routeIs('bookings.*') ? 'active' : '' }}">
                            Reservations
                        </a>
                        <a href="{{ route('pos.index') }}" class="nav-link {{ request()->routeIs('pos.*') ? 'active' : '' }}">
                            POS
                        </a>
                        <a href="{{ route('shifts.index') }}" class="nav-link {{ request()->routeIs('shifts.*') ? 'active' : '' }}">
                            Shifts &amp; Drawer
                        </a>
                        <a href="{{ route('inventory.index') }}" class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                            Stock
                        </a>
                    @else
                        {{-- Owner / admin / manager: navigate via the light executive tab bar below --}}
                        <span class="hidden md:inline px-3 py-2 text-[11px] font-semibold uppercase tracking-widest text-white/50">Executive View</span>
                    @endif
                </nav>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <div class="text-xs text-white/90 hidden lg:flex items-center space-x-3 py-1 font-mono">
                    <button type="button" id="soundToggleBtn" onclick="if(window.sedonaAudio) window.sedonaAudio.toggleMute()" class="flex items-center space-x-1 px-2 py-0.5 rounded text-[11px] font-bold bg-white/10 text-white hover:bg-white/20 transition border border-white/20" title="Toggle Sound Alarms & Chimes">
                        <span id="soundToggleIcon">🔊</span>
                        <span id="soundToggleText">Sound ON</span>
                    </button>
                    <span class="text-white/40">•</span>
                    <span id="live-hms-time">--:-- --</span>
                </div>

                <div class="relative shrink-0" data-dropdown>
                    <button type="button" onclick="toggleNavDropdown(event)" aria-expanded="false" aria-haspopup="true" class="flex items-center gap-2 pl-1.5 pr-2 py-1 rounded bg-white/10 hover:bg-white/20 border border-white/20 text-white text-xs font-semibold max-w-[180px]">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#421A2B] font-black text-[11px] shrink-0">
                            {{ mb_strtoupper(mb_substr(auth()->user()->name ?? 'C', 0, 1)) }}
                        </span>
                        <span class="hidden sm:block truncate">{{ auth()->user()->name ?? 'Cashier' }}</span>
                        <span class="text-[9px] text-white/70 shrink-0">▼</span>
                    </button>
                    <div class="nav-menu hidden absolute top-full right-0 mt-2 w-52 bg-white shadow-2xl z-[60] text-xs border border-slate-200 rounded-md overflow-hidden" role="menu">
                        <div class="px-3 py-2.5 border-b border-slate-100 bg-slate-50">
                            <div class="font-bold text-slate-900 truncate leading-tight">{{ auth()->user()->name ?? 'Cashier' }}</div>
                            <div class="text-[10px] uppercase tracking-wider text-slate-500 mt-0.5">{{ auth()->user()->getRoleNames()->first() ?? 'cashier' }}</div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="p-1">
                            @csrf
                            <button type="submit" class="w-full text-left px-3 py-2 rounded text-slate-700 hover:bg-slate-100 text-xs font-semibold" role="menuitem">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Mobile panel mirrors desktop links (CSS-only toggle, no duplicate routes) --}}
        <nav id="primaryNavMobile" class="hidden md:hidden border-t border-white/10 px-3 pb-3" aria-label="Primary mobile">
            @if(auth()->check() && auth()->user()->hasAnyRole(['cashier', 'front_desk']))
                <a href="{{ route('dashboard') }}" class="block py-2 text-white font-semibold">Front Desk</a>
                <a href="{{ route('bookings.index') }}" class="block py-2 text-white font-semibold">Reservations</a>
                <a href="{{ route('pos.index') }}" class="block py-2 text-white font-semibold">POS</a>
                <a href="{{ route('shifts.index') }}" class="block py-2 text-white font-semibold">Shifts &amp; Drawer</a>
                <a href="{{ route('inventory.index') }}" class="block py-2 text-white font-semibold">Stock</a>
            @else
                <a href="{{ route('dashboard') }}" class="block py-2 text-white font-semibold">Frontdesk</a>
                <a href="{{ route('bookings.index') }}" class="block py-2 text-white font-semibold">Bookings</a>
                <a href="{{ route('pos.index') }}" class="block py-2 text-white font-semibold">POS Catalog</a>
                <a href="{{ route('admin.accounting.ledger') }}" class="block py-2 text-white font-semibold">Ledger</a>
                <a href="{{ route('shifts.index') }}" class="block py-2 text-white font-semibold">Shift Settlement</a>
                <a href="{{ route('reports.index') }}" class="block py-2 text-white font-semibold">Reports &amp; Audits</a>
                <a href="{{ route('admin.pricing.index') }}" class="block py-2 text-white font-semibold">System Settings — Pricing</a>
                <a href="{{ route('admin.users.index') }}" class="block py-2 text-white font-semibold">System Settings — Staff</a>
            @endif
        </nav>
    </header>

    @if(auth()->check() && auth()->user()->hasAnyRole(['owner', 'admin', 'manager']))
    <!-- Executive tab bar (owner/admin) -->
    <nav class="owner-tabs sticky top-[49px] z-30 hidden md:block" aria-label="Executive">
        <div class="flex items-center gap-1 px-3 py-2">
            <a href="{{ route('dashboard') }}" class="exec-tab {{ request()->routeIs('dashboard', 'rooms.*', 'checkout.*', 'folios.*', 'checkin.*') ? 'active' : '' }}">
                <span>▦</span> Frontdesk
            </a>
            <div class="relative" data-dropdown>
                <button type="button" class="exec-tab" aria-expanded="false" aria-haspopup="true" onclick="toggleNavDropdown(event)">
                    <span>🔔</span> Notifications
                    @isset($notifCount)
                        @if(($notifCount ?? 0) > 0)
                            <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-[#EF4444] text-white text-[10px] font-black">{{ $notifCount }}</span>
                        @endif
                    @endisset
                </button>
                <div class="tab-menu nav-menu hidden absolute left-0 top-full mt-1 w-72 bg-white shadow-2xl z-[60] border border-slate-200 rounded-md overflow-hidden" role="menu">
                    <div class="px-3 py-2 border-b border-slate-100 bg-slate-50 font-bold text-slate-700 text-xs">Attention needed</div>
                    @isset($notifItems)
                        @forelse($notifItems as $ni)
                            <a href="{{ $ni['url'] }}" role="menuitem"><span class="font-black">{{ $ni['count'] }}</span> {{ $ni['label'] }}</a>
                        @empty
                            <div class="px-3 py-3 text-slate-400 text-xs">All clear — nothing pending.</div>
                        @endisset
                    @else
                        <a href="{{ route('dashboard') }}" role="menuitem">Open Frontdesk board</a>
                    @endisset
                </div>
            </div>
            <a href="{{ route('bookings.index') }}" class="exec-tab {{ request()->routeIs('bookings.*') ? 'active' : '' }}">
                <span>📅</span> Bookings
            </a>
            <a href="{{ route('pos.index') }}" class="exec-tab {{ request()->routeIs('pos.*') ? 'active' : '' }}">
                <span>🛒</span> POS Catalog
            </a>
            <a href="{{ route('admin.accounting.ledger') }}" class="exec-tab {{ request()->routeIs('admin.accounting.ledger') ? 'active' : '' }}">
                <span>📖</span> Ledger
            </a>
            <a href="{{ route('shifts.index') }}" class="exec-tab {{ request()->routeIs('shifts.*') ? 'active' : '' }}">
                <span>💼</span> Shift Settlement
            </a>
            <div class="relative" data-dropdown>
                <button type="button" class="exec-tab {{ request()->routeIs('reports.*', 'admin.accounting.*') ? 'active' : '' }}" aria-expanded="false" aria-haspopup="true" onclick="toggleNavDropdown(event)">
                    <span>📊</span> Reports &amp; Audits <span class="text-[9px]">▼</span>
                </button>
                <div class="tab-menu nav-menu hidden absolute left-0 top-full mt-1 w-64 bg-white shadow-2xl z-[60] border border-slate-200 rounded-md overflow-hidden" role="menu">
                    <a href="{{ route('reports.index') }}" role="menuitem">Weekly Sales &amp; Occupancy</a>
                    <a href="{{ route('admin.accounting.pnl') }}" role="menuitem">Executive P&amp;L</a>
                    <a href="{{ route('admin.accounting.expenses') }}" role="menuitem">Expenses &amp; Petty Cash</a>
                    <a href="{{ route('admin.accounting.shifts') }}" role="menuitem">Shift Reconciliations</a>
                    <a href="{{ route('admin.accounting.losses') }}" role="menuitem">Loss Slips (FCE-######)</a>
                </div>
            </div>
            <div class="ml-auto relative" data-dropdown>
                <button type="button" class="exec-tab {{ request()->routeIs('admin.pricing.*', 'admin.users.*') ? 'active' : '' }}" aria-expanded="false" aria-haspopup="true" onclick="toggleNavDropdown(event)">
                    <span>⚙</span> System Settings
                </button>
                <div class="tab-menu nav-menu hidden absolute right-0 top-full mt-1 w-60 bg-white shadow-2xl z-[60] border border-slate-200 rounded-md overflow-hidden" role="menu">
                    <a href="{{ route('admin.pricing.index') }}" role="menuitem">Master Pricing Editor</a>
                    <a href="{{ route('admin.users.index') }}" role="menuitem">Staff Accounts &amp; RBAC</a>
                    <a href="{{ route('kitchen.view') }}" role="menuitem">Kitchen KDS</a>
                    <a href="{{ route('kiosk.view') }}" target="_blank" role="menuitem">Lobby Display ↗</a>
                </div>
            </div>
        </div>
    </nav>
    @endif

    <!-- Slim Page Header -->
    <div class="hms-sub-nav relative z-0 flex items-center justify-between gap-3">
        <div class="font-semibold text-xs text-slate-800 uppercase tracking-wide truncate">
            @yield('subtitle', 'Sedona Court Executive PMS')
        </div>
        <div class="shrink-0">
            @yield('top_action')
        </div>
    </div>

    <!-- Flash Messages -->
    <div class="px-3 pt-2">
        @if(session('success'))
            <div class="p-2 mb-2 bg-emerald-50 border border-emerald-500 text-emerald-900 text-xs rounded font-medium flex items-center justify-between">
                <div>
                    <div><strong>SUCCESS:</strong> {{ session('success') }}</div>
                    @if(session('settled_folio_id'))
                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                            <a href="{{ route('folios.receipt', session('settled_folio_id')) }}" target="_blank" class="px-2 py-0.5 bg-brand hover:bg-[#341421] text-white font-bold text-[10px] rounded shadow-sm inline-flex items-center gap-1">
                                🖨️ Official Receipt (80mm)
                            </a>
                            <a href="{{ route('folios.gate_pass', session('settled_folio_id')) }}" target="_blank" class="px-2 py-0.5 bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-[10px] rounded shadow-sm inline-flex items-center gap-1">
                                🚪 Exit Gate Pass (80mm)
                            </a>
                            <a href="{{ route('folios.deposit_refund', session('settled_folio_id')) }}" target="_blank" class="px-2 py-0.5 bg-slate-700 hover:bg-slate-800 text-white font-bold text-[10px] rounded shadow-sm inline-flex items-center gap-1">
                                💵 Deposit Refund Slip
                            </a>
                            <a href="{{ route('folios.billing', session('settled_folio_id')) }}" target="_blank" class="px-2 py-0.5 bg-slate-700 hover:bg-slate-800 text-white font-bold text-[10px] rounded shadow-sm inline-flex items-center gap-1">
                                📄 Statement of Account
                            </a>
                        </div>
                    @endif
                </div>
                <button onclick="this.closest('div.bg-emerald-50').remove()" class="text-emerald-700 font-bold ml-3 text-base">&times;</button>
            </div>
        @endif
        @if(session('error'))
            <div class="p-2 mb-2 bg-rose-50 border border-rose-500 text-rose-900 text-xs rounded font-medium flex items-center justify-between">
                <span><strong>ERROR:</strong> {{ session('error') }}</span>
                <button onclick="this.parentElement.remove()" class="text-rose-700 font-bold">&times;</button>
            </div>
        @endif
        @if(session('info'))
            <div class="p-2 mb-2 bg-blue-50 border border-blue-500 text-blue-900 text-xs rounded font-medium flex items-center justify-between">
                <span><strong>NOTICE:</strong> {{ session('info') }}</span>
                <button onclick="this.parentElement.remove()" class="text-blue-700 font-bold">&times;</button>
            </div>
        @endif
    </div>

    <!-- Main Content Area -->
    <main class="flex-1 pb-6">
        @yield('content')
    </main>

    <script>
        function togglePrimaryNav() {
            const nav = document.getElementById('primaryNavMobile');
            const btn = document.getElementById('primaryNavToggle');
            if (!nav) return;
            const isHidden = nav.classList.contains('hidden');
            nav.classList.toggle('hidden', !isHidden);
            if (btn) btn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
            // Desktop nav stays md:flex; mobile panel is separate.
        }

        function toggleNavDropdown(event) {
            event.stopPropagation();
            const wrapper = event.currentTarget.closest('[data-dropdown]');
            if (!wrapper) return;
            const menu = wrapper.querySelector('.nav-menu');
            const trigger = wrapper.querySelector('[aria-expanded]');
            const isHidden = menu.classList.contains('hidden');
            closeAllNavDropdowns(wrapper);
            menu.classList.toggle('hidden', !isHidden);
            if (trigger) trigger.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        }

        function closeAllNavDropdowns(except) {
            document.querySelectorAll('[data-dropdown] .nav-menu').forEach((menu) => {
                const wrapper = menu.closest('[data-dropdown]');
                if (wrapper !== except) {
                    menu.classList.add('hidden');
                    const trigger = wrapper.querySelector('[aria-expanded]');
                    if (trigger) trigger.setAttribute('aria-expanded', 'false');
                }
            });
        }

        document.addEventListener('click', () => closeAllNavDropdowns(null));
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeAllNavDropdowns(null);
        });

        function updateHmsClock() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
            const dateStr = now.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });
            const el = document.getElementById('live-hms-time');
            if (el) el.textContent = `${dateStr} ${timeStr}`;
        }
        setInterval(updateHmsClock, 1000);
        updateHmsClock();

        document.addEventListener('DOMContentLoaded', () => {
            if (window.sedonaAudio) {
                window.sedonaAudio.updateAudioButtonState();
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
