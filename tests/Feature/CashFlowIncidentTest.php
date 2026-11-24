<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Folio;
use App\Models\PaymentEntry;
use App\Models\Room;
use App\Models\Shift;
use App\Models\User;
use App\Services\BusinessDayService;
use App\Services\FinancialSummaryService;
use App\Services\ShiftCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashFlowIncidentTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['sedona.continuous_shift_enabled' => true]);
        $this->at('2026-11-09 12:00');
        ShiftCalendar::scheduleCutover('2026-11-10');
        $this->at('2026-11-20 12:00');
        ShiftCalendar::scheduleCutover('2026-11-23', '06:01');
        $this->at('2026-11-24 05:50');
        $this->cashier = User::factory()->create()->assignRole(Role::findOrCreate('cashier', 'web'));
        foreach (['manage_folios', 'record_payments', 'manage_shifts', 'view_dashboard'] as $permission) {
            $this->cashier->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        Shift::create(['opened_by' => $this->cashier->id, 'opened_at' => now()->subDays(2), 'shift_date' => '2026-11-22', 'shift_type' => 'day']);
        $this->actingAs($this->cashier);
    }

    #[TestWith(['cash', '2026-11-24 05:55', 1985.0, 0.0])]
    #[TestWith(['cash', '2026-11-24 06:01', 3330.0, 0.0])]
    #[TestWith(['gcash', '2026-11-24 06:01', 1985.0, 1345.0])]
    public function test_real_deposit_and_checkout_routes_reconcile_sales_with_the_receiving_shift(string $method, string $depositTime, float $cash, float $gcash): void
    {
        $folio = $this->stay();
        $this->at($depositTime);
        $this->deposit($folio, 1345, $method);
        $this->at('2026-11-24 07:10');
        $payload = ['operation_key' => (string) Str::uuid(), 'payment_method' => 'cash', 'cash_tendered' => 2000, 'gcash_amount' => 0, 'apply_deposit' => 1];
        $this->post(route('checkout.submit', $folio), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('checkout.submit', $folio), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('15.00', $folio->fresh()->change_due);
        $this->assertDatabaseCount('payment_entries', 2);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('myShiftRevenue', 3330.0)->assertViewHas('myShiftCashCollections', $cash)
            ->assertViewHas('myShiftMoney', fn (array $money): bool => $money['gcashSales'] === $gcash)
            ->assertSee('My Net GCash Collections')->assertSee('Collections follow the payment time');
        $this->get(route('shifts.index'))->assertOk()->assertViewHas('cashRevenue', $cash)->assertViewHas('gcashCollections', $gcash);
        $summary = app(FinancialSummaryService::class)->period(now()->setTime(6, 1), now()->setTime(18, 1), true);
        $this->assertSame($cash, $summary['cashCollections']);
        $this->assertSame($gcash, $summary['gcashCollections']);
        $this->assertSame(3330.0, $summary['netRevenue']);
    }

    #[TestWith(['2026-11-24 06:00:59', 25645.0])]
    #[TestWith(['2026-11-24 06:01:00', 26210.0])]
    public function test_565_at_cutoff_is_counted_once_and_explains_the_reported_form_difference(string $paymentTime, float $dayCash): void
    {
        $folio = $this->stay();
        $this->at($paymentTime);
        $this->deposit($folio, 565, 'cash');
        $this->at('2026-11-24 07:00');
        $this->deposit($folio, 25645, 'cash');
        $this->get(route('dashboard'))->assertOk()->assertViewHas('myShiftCashCollections', $dayCash);
        $this->get(route('shifts.index'))->assertOk()->assertViewHas('cashRevenue', $dayCash);
        $previous = app(BusinessDayService::class)->snapshot('2026-11-23');
        $current = app(BusinessDayService::class)->snapshot('2026-11-24');
        $this->assertSame((int) ($dayCash * 100), $current['cash_cents']);
        $this->assertSame(2621000, $previous['cash_cents'] + $current['cash_cents']);
        $this->assertSame('26210.00', $folio->fresh()->security_deposit);
        $this->assertDatabaseCount('payment_entries', 2);
    }

    public function test_cash_out_of_565_reconciles_received_cash_and_drawer_cash(): void
    {
        $folio = $this->stay();
        $this->at('2026-11-24 07:00');
        $this->deposit($folio, 26210, 'cash');
        $this->post(route('shifts.expense.store'), ['operation_key' => (string) Str::uuid(), 'description' => 'QA drawer expense', 'category' => 'other', 'amount' => 565])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertOk()->assertViewHas('myShiftCashCollections', 26210.0)
            ->assertViewHas('myShiftMoney', fn (array $money): bool => $money['drawerExpenses'] === 565.0 && $money['netCash'] === 25645.0)
            ->assertSee('My Cash Expenses')->assertSee('My Cash After Expenses &amp; Transfers', false)->assertSee('25,645.00');
        $this->get(route('shifts.index'))->assertOk()->assertViewHas('cashRevenue', 26210.0)->assertViewHas('expectedCash', 25645.0);
    }

    public function test_cash_breakdown_tracks_transfers_and_keeps_other_cashiers_money_private(): void
    {
        $folio = $this->stay();
        $this->at('2026-11-24 07:00');
        $this->deposit($folio, 1000, 'cash');
        $drawer = Shift::sole();
        $other = User::factory()->create();
        PaymentEntry::create(['user_id' => $other->id, 'shift_id' => $drawer->id, 'kind' => 'deposit', 'cash_amount' => 98765, 'gcash_amount' => 87654, 'posted_at' => now()]);
        CashMovement::factory()->create(['user_id' => $this->cashier->id, 'shift_id' => $drawer->id, 'amount' => -100, 'posted_at' => now()]);

        $this->get(route('dashboard'))->assertOk()->assertViewHas('myShiftCashCollections', 1000.0)
            ->assertViewHas('myShiftMoney', fn (array $money): bool => $money['gcashSales'] === 0.0 && $money['netCash'] === 900.0 && (float) $money['cashMovements']->sum('amount') === -100.0)
            ->assertSee('900.00')->assertSee('-100.00')->assertDontSee('98,765.00')->assertDontSee('87,654.00');
        $this->get(route('shifts.index'))->assertOk()->assertViewHas('expectedCash', 900.0);
    }

    public function test_no_open_drawer_hides_the_breakdown_without_errors(): void
    {
        Shift::sole()->update(['closed_at' => now()]);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('myShiftMoney', null)->assertDontSee('My shift cash breakdown');
    }

    private function at(string $time): void
    {
        $this->travelTo(CarbonImmutable::parse($time, 'Asia/Manila'));
    }

    private function stay(): Folio
    {
        $room = Room::create(['number' => '7', 'floor' => 1, 'name' => 'Classic', 'type' => 'Classic Room', 'status' => 'available', 'base_rate_3h' => 3330]);
        $this->post(route('checkin.store'), ['operation_key' => (string) Str::uuid(), 'room_id' => $room->id, 'rate_tier' => '3h', 'headcount' => 2])
            ->assertRedirect()->assertSessionHasNoErrors();

        return Folio::where('room_id', $room->id)->sole();
    }

    private function deposit(Folio $folio, float $amount, string $method): void
    {
        $this->post(route('folios.deposit.collect', $folio), ['operation_key' => (string) Str::uuid(), 'amount' => $amount, 'payment_method' => $method, 'gcash_reference' => $method === 'gcash' ? 'QA-GCASH-REF' : null])
            ->assertRedirect()->assertSessionHasNoErrors();
    }
}
