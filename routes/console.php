<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pms:clean-trial', function () {
    $this->info('=====================================================');
    $this->info('  SEDONA COURT PMS - RESETTING DATABASE FOR TRIAL');
    $this->info('=====================================================');

    \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

    \Illuminate\Support\Facades\DB::table('order_items')->truncate();
    \Illuminate\Support\Facades\DB::table('orders')->truncate();
    \Illuminate\Support\Facades\DB::table('folios')->truncate();
    \Illuminate\Support\Facades\DB::table('guests')->truncate();
    \Illuminate\Support\Facades\DB::table('shifts')->truncate();
    \Illuminate\Support\Facades\DB::table('expenses')->truncate();
    \Illuminate\Support\Facades\DB::table('bookings')->truncate();
    if (\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
        \Illuminate\Support\Facades\DB::table('audit_logs')->truncate();
    }

    \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

    // Reset all 32 rooms to 'available'
    \App\Models\Room::query()->update(['status' => 'available']);

    $totalRooms = \App\Models\Room::count();
    $availableRooms = \App\Models\Room::where('status', 'available')->count();
    $totalUsers = \App\Models\User::count();
    $totalItems = \App\Models\PosItem::count();

    $this->line('');
    $this->info("✓ Cleared all sample folios, guests, dining orders, shifts, and expenses.");
    $this->info("✓ Rooms reset: {$availableRooms} of {$totalRooms} rooms set to AVAILABLE.");
    $this->info("✓ Retained {$totalUsers} staff user accounts & roles (Cashiers, Admin, Owner, Kitchen, Kiosk).");
    $this->info("✓ Retained {$totalItems} master POS catalog items & tiered room rates.");
    $this->line('');
    $this->info('Database is now in a 100% CLEAN SLATE ready for real staff trial operations!');
    $this->info('=====================================================');
})->purpose('Reset database to a clean operational slate for live staff trial');

