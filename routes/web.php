<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public / Guest Routes
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

// Quick 1-Click Login helper for testing
Route::get('/quick-login/{email}', [AuthController::class, 'quickLogin'])->name('quick.login');

// Public Lobby Kiosk route (also accessible authenticated)
Route::get('/lobby-kiosk', [DashboardController::class, 'kioskView'])->name('kiosk.view');

// Authenticated HMS Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard (Exact 32-Room Visual Operations Matrix)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/rooms/{room}/status', [RoomController::class, 'updateStatus'])->name('rooms.status');
    Route::post('/rooms/{room}/toggle-maintenance', [RoomController::class, 'toggleMaintenance'])->name('rooms.maintenance.toggle');

    // Check-in
    Route::post('/check-in', [CheckInController::class, 'store'])->name('checkin.store');

    // Exact Process Checkout Screen (Matching old HMS)
    Route::get('/checkout/{folio}', [CheckInController::class, 'showCheckout'])->name('checkout.process');
    Route::post('/checkout/{folio}', [CheckInController::class, 'processCheckout'])->name('checkout.submit');

    // Printables: Receipt, Gate Pass, Deposit Slip, Deposit Refund, Statement of Account (Billing), Loss Slip, Order Slip
    Route::get('/folios/{folio}/receipt', [CheckInController::class, 'showReceipt'])->name('folios.receipt');
    Route::get('/folios/{folio}/gate-pass', [CheckInController::class, 'showGatePass'])->name('folios.gate_pass');
    Route::get('/folios/{folio}/deposit-slip', [CheckInController::class, 'showDepositSlip'])->name('folios.deposit_slip');
    Route::get('/folios/{folio}/deposit-refund', [CheckInController::class, 'showDepositRefund'])->name('folios.deposit_refund');
    Route::get('/folios/{folio}/billing', [CheckInController::class, 'showBillingStatement'])->name('folios.billing');
    Route::get('/folios/{folio}/loss-slip', [CheckInController::class, 'showLossSlip'])->name('folios.loss_slip');
    Route::get('/folios/{folio}/order-slip', [PosController::class, 'showOrderSlip'])->name('folios.orderslip');

    // Extend Stay, Discount, Add-Ons, Room Transfer, Force Checkout
    Route::post('/folios/{folio}/extend', [CheckInController::class, 'extendStay'])->name('folios.extend');
    Route::post('/folios/{folio}/discount', [CheckInController::class, 'applyDiscount'])->name('folios.discount');
    Route::post('/folios/{folio}/transfer', [CheckInController::class, 'transferRoom'])->name('folios.transfer');
    Route::post('/folios/{folio}/force-checkout', [CheckInController::class, 'requestForceCheckout'])->name('folios.force_checkout');
    Route::post('/folios/{folio}/add-on', [PosController::class, 'addQuickAddOn'])->name('folios.addon');
    Route::post('/folios/{folio}/deposit', [CheckInController::class, 'updateDeposit'])->name('folios.deposit');


    // File Maintenance & Room Catalog
    Route::get('/rooms', [DashboardController::class, 'index'])->name('rooms.index');

    // Reservations / Bookings
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

    // Point of Sale, Dining & Kitchen Display System (KDS)
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/orders', [PosController::class, 'storeOrder'])->name('pos.order.store');
    Route::post('/pos/orders/{order}/status', [PosController::class, 'updateStatus'])->name('pos.order.status');
    Route::get('/kitchen-view', [PosController::class, 'kitchenView'])->name('kitchen.view');
    Route::get('/kitchen-tv', [PosController::class, 'kitchenTvView'])->name('kitchen.tv');

    // Shifts & Cash Drawer (Turnover, Cash Count & Shift Expenses)
    Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::get('/shifts/{shift}/remittance-slip', [ShiftController::class, 'showRemittanceSlip'])->name('shifts.remittance_slip');
    Route::post('/shifts/open', [ShiftController::class, 'openShift'])->name('shifts.open');
    Route::post('/shifts/expenses', [ShiftController::class, 'storeExpense'])->name('shifts.expense.store');
    Route::post('/shifts/{shift}/close', [ShiftController::class, 'closeShift'])->name('shifts.close');

    // Reports & Financial BI (admin/owner only — cashiers see weekly sales strip on dashboard)
    Route::middleware(['role:admin|owner|manager'])->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    });

    // Inventory Management (Stock Control, Shift Recount, Depletion Ledger)
    Route::middleware(['role:cashier|front_desk|admin|owner|manager'])->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory/recount', [InventoryController::class, 'batchRecount'])->name('inventory.recount');
        Route::post('/inventory/{item}/stock', [InventoryController::class, 'updateStock'])->name('inventory.update-stock');
        Route::get('/inventory/export-csv', [InventoryController::class, 'exportCsv'])->name('inventory.export-csv');
    });

    // ═══════════════════════════════════════════════════════════════════════
    // Admin & Owner Subsystems (Strictly Role-Gated: 403 for Cashier & Kitchen)
    // ═══════════════════════════════════════════════════════════════════════
    Route::middleware(['role:admin|owner'])->prefix('admin')->name('admin.')->group(function () {
        // Master Pricing & Rates Editor
        Route::get('/pricing', [RoomController::class, 'pricingIndex'])->name('pricing.index');
        Route::post('/pricing', [RoomController::class, 'updatePricing'])->name('pricing.update');
        // POS Catalog Item Management
        Route::post('/pricing/items', [RoomController::class, 'storeItem'])->name('pricing.items.store');
        Route::delete('/pricing/items/{posItem}', [RoomController::class, 'destroyItem'])->name('pricing.items.destroy');

        // Force Checkout Loss Slip Approvals
        Route::post('/force-checkouts/{folio}/approve', [CheckInController::class, 'approveForceCheckout'])->name('force_checkout.approve');

        // Executive Accounting & Financials
        Route::prefix('accounting')->name('accounting.')->group(function () {
            Route::get('/pnl', [AccountingController::class, 'pnl'])->name('pnl');
            Route::get('/pnl/export-csv', [AccountingController::class, 'exportCsv'])->name('pnl.csv');
            Route::get('/expenses', [AccountingController::class, 'expensesIndex'])->name('expenses');
            Route::post('/expenses', [AccountingController::class, 'storeExpense'])->name('expenses.store');
            Route::delete('/expenses/{expense}', [AccountingController::class, 'deleteExpense'])->name('expenses.delete');
            Route::get('/ledger', [AccountingController::class, 'ledgerIndex'])->name('ledger');
            Route::get('/shifts', [AccountingController::class, 'shiftsIndex'])->name('shifts');
            Route::get('/losses', [AccountingController::class, 'lossesIndex'])->name('losses');
        });

        // Staff Accounts & Security Directory (Index for Admin|Owner, Modification strictly Owner)
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::middleware(['role:owner'])->group(function () {
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        });
    });
});
