<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_configured_cashier_can_open_operational_pages_but_cannot_open_admin_pages(): void
    {
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        config(['sedona.continuous_shift_enabled' => true, 'sedona.shift_handover_enabled' => false]);
        $cashiers = User::role('cashier')->where('is_active', true)->get();
        $this->assertNotEmpty($cashiers);
        foreach ($cashiers as $cashier) {
            $this->actingAs($cashier);
            foreach (['dashboard', 'pos.index', 'bookings.index', 'inventory.index', 'shifts.index', 'transactions.index', 'transactions.audit', 'approvals.index'] as $page) {
                $this->get(route($page))->assertOk();
            }
            foreach (['admin.users.index', 'admin.pricing.index', 'admin.discounts.index', 'admin.check-in-times.index', 'admin.login-history.index',
                'admin.finance.index', 'admin.finance.setup', 'admin.accounting.pnl', 'admin.accounting.ledger', 'reports.index'] as $page) {
                $this->get(route($page))->assertForbidden();
            }
        }
    }

    public function test_deactivating_a_logged_in_cashier_blocks_the_next_operation_and_signs_them_out(): void
    {
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $cashier = User::role('cashier')->firstOrFail();
        $this->actingAs($cashier)->get(route('dashboard'))->assertOk();
        $cashier->update(['is_active' => false]);

        $this->post(route('checkin.store'), [])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('folios', ['user_id' => $cashier->id, 'status' => 'active', 'room_charge' => 395]);
    }
}
