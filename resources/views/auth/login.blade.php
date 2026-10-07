<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Terminal Sign-In | Sedona Court Executive PMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #374151;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white border-t-4 border-[#421A2B] shadow-2xl p-6 md:p-8">
        
        <!-- Header & Logo -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-[#421A2B] text-white font-black text-2xl rounded shadow-md mb-2">
                SC
            </div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 uppercase">Sedona Court</h1>
            <p class="text-[11px] uppercase tracking-wider text-slate-500 font-bold">Executive Property Management System</p>
        </div>

        @if(session('error'))
            <div class="mb-4 p-2.5 bg-rose-50 border border-rose-500 text-rose-900 text-xs font-medium rounded">
                {{ session('error') }}
            </div>
        @endif

        @if(session('info'))
            <div class="mb-4 p-2.5 bg-blue-50 border border-blue-500 text-blue-900 text-xs font-medium rounded">
                {{ session('info') }}
            </div>
        @endif

        <!-- Quick 1-Click Role Switcher (Testing & Development) -->
        <div class="mb-5 bg-slate-50 p-3 border border-slate-200">
            <div class="text-xs font-bold text-slate-700 uppercase tracking-wide mb-2 flex items-center justify-between">
                <span>Fast Role Authentication:</span>
                <span class="text-[10px] text-slate-500 font-mono">Password: password</span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <a href="{{ route('quick.login', 'pau@sedonapms.com') }}" class="p-2 bg-white border border-slate-300 hover:border-[#421A2B] hover:bg-red-50 text-left rounded transition-colors block">
                    <div class="font-bold text-slate-900">Pau (Cashier)</div>
                    <div class="text-[10px] text-slate-500">Front Desk & Billing</div>
                </a>

                <a href="{{ route('quick.login', 'kitchen1@sedonapms.com') }}" class="p-2 bg-white border border-slate-300 hover:border-[#421A2B] hover:bg-red-50 text-left rounded transition-colors block">
                    <div class="font-bold text-slate-900">Kitchen Staff</div>
                    <div class="text-[10px] text-slate-500">KDS Ticket Queue</div>
                </a>

                <a href="{{ route('quick.login', 'admin1@sedonapms.com') }}" class="p-2 bg-white border border-slate-300 hover:border-[#421A2B] hover:bg-red-50 text-left rounded transition-colors block">
                    <div class="font-bold text-slate-900">Alex (Admin 1)</div>
                    <div class="text-[10px] text-slate-500">Duty Manager & Loss</div>
                </a>

                <a href="{{ route('quick.login', 'owner@sedonapms.com') }}" class="p-2 bg-white border border-slate-300 hover:border-[#421A2B] hover:bg-red-50 text-left rounded transition-colors block">
                    <div class="font-bold text-slate-900">Sedona Owner</div>
                    <div class="text-[10px] text-slate-500">Weekly BI & Profit</div>
                </a>

                <a href="{{ route('quick.login', 'kiosk@sedonapms.com') }}" class="col-span-2 p-1.5 bg-white border border-slate-300 hover:border-[#421A2B] hover:bg-red-50 text-center rounded transition-colors block">
                    <div class="font-bold text-slate-900 text-[11px]">Lobby Customer Display Kiosk (Read-Only)</div>
                </a>
            </div>
        </div>

        <div class="relative flex items-center justify-center my-4">
            <div class="border-t border-slate-300 w-full"></div>
            <span class="bg-white px-2 text-[10px] uppercase font-bold text-slate-500 absolute">Or Enter Credentials</span>
        </div>

        <!-- Standard Credentials Form -->
        <form method="POST" action="{{ route('login.submit') }}" class="space-y-3 text-xs">
            @csrf

            <div>
                <label for="email" class="block font-bold text-slate-700 mb-1">Email / Operator ID:</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email', 'pau@sedonapms.com') }}"
                    required
                    autofocus
                    class="w-full px-3 py-2 border border-slate-400 focus:border-[#421A2B] outline-none text-slate-900 font-semibold"
                    placeholder="operator@sedonapms.com"
                >
                @error('email')
                    <p class="mt-1 text-xs text-rose-700 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block font-bold text-slate-700 mb-1">Security Password:</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    value="password"
                    required
                    class="w-full px-3 py-2 border border-slate-400 focus:border-[#421A2B] outline-none text-slate-900"
                    placeholder="••••••••"
                >
            </div>

            <div class="flex items-center justify-between text-xs pt-1">
                <label class="flex items-center space-x-1.5 text-slate-600 cursor-pointer">
                    <input type="checkbox" name="remember" class="rounded text-red-700">
                    <span>Keep terminal signed in</span>
                </label>
            </div>

            <button
                type="submit"
                class="w-full py-2.5 bg-[#421A2B] hover:bg-[#341421] text-white font-bold text-xs rounded uppercase tracking-wider shadow transition-colors"
            >
                Sign In to Terminal
            </button>
        </form>

        <div class="mt-6 pt-3 border-t border-slate-200 text-center text-[10px] text-slate-400 font-mono">
            Sedona Court PMS v2.5.0 &bull; Port 8000 &bull; Asia/Manila
        </div>
    </div>

</body>
</html>
