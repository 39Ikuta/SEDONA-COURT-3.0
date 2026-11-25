<?php

namespace Tests\Feature;

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

class DepositCarryIncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_deposit_is_in_cash_collections_before_it_becomes_sales(): void
    {
        [$folio] = $this->receiveRoom29Deposit();

        $this->get(route('dashboard'))->assertViewHas('myShiftCashCollections', 2500.0)->assertViewHas('myShiftRevenue', 0.0);
        $this->get(route('shifts.index'))->assertViewHas('moneyCountCash', 0.0)
            ->assertViewHas('depositReconciliation', fn (array $deposit): bool => $deposit['cashReceived'] === 2500.0 && $deposit['closingCashHeld'] === 2500.0)
            ->assertSee('Cash for my money-count form')->assertSee('Guest deposit handover');
        $this->get(route('shifts.index'))->assertSee('SHARED DRAWER IN PROGRESS')->assertDontSee('ACTIVE DAY SHIFT IN PROGRESS');
        $this->assertSame('2500.00', $folio->fresh()->security_deposit);
    }

    #[TestWith(['cash', 2500.0, 0.0, 400.0, 0.0, '06:30'])]
    #[TestWith(['gcash', 0.0, 2500.0, 0.0, 400.0, '06:30'])]
    #[TestWith(['cash', 2500.0, 0.0, 400.0, 0.0, '05:00'])]
    #[TestWith(['gcash', 0.0, 2500.0, 0.0, 400.0, '05:00'])]
    #[TestWith(['cash', 2500.0, 0.0, 400.0, 0.0, '06:01'])]
    #[TestWith(['gcash', 0.0, 2500.0, 0.0, 400.0, '06:01'])]
    public function test_three_cashiers_receive_hold_apply_and_refund_one_deposit_without_duplicate_cash(string $method, float $receivedCash, float $receivedGcash, float $refundCash, float $refundGcash, string $schedule): void
    {
        [$folio, $drawer] = $this->receiveRoom29Deposit($method, $schedule);
        $financials = app(FinancialSummaryService::class);
        $this->get(route('dashboard'))->assertViewHas('myShiftCashCollections', $receivedCash)->assertViewHas('myShiftRevenue', 0.0);
        $this->get(route('shifts.index'))->assertViewHas('expectedCash', $receivedCash)->assertViewHas('gcashCollections', $receivedGcash);
        $this->assertSame($receivedCash, $financials->shift($drawer)['expectedCash']);
        $this->assertDatabaseCount('payment_entries', 1);

        $this->at('2026-11-25 12:00');
        $this->actingAs($this->cashier('Day cashier'));
        $this->get(route('shifts.index'))->assertViewHas('expectedCash', 0.0);
        $this->get(route('dashboard'))->assertViewHas('myShiftCashCollections', 0.0)->assertViewHas('myShiftRevenue', 0.0);
        $this->get(route('shifts.remittance_slip', $drawer))->assertViewHas('expectedCash', 0.0);
        $this->get(route('shifts.remittance_slip', $drawer))->assertViewHas('moneyCountCash', 0.0)
            ->assertViewHas('depositReconciliation', fn (array $deposit): bool => $deposit['openingCashHeld'] === $receivedCash && $deposit['closingCashHeld'] === $receivedCash);
        $this->assertSame($receivedCash, $financials->shift($drawer)['expectedCash']);
        $this->assertSame('2500.00', $folio->fresh()->security_deposit);
        $this->assertDatabaseCount('payment_entries', 1);

        $this->at('2026-11-25 19:59');
        $this->actingAs($this->cashier('Next night cashier'));
        $checkout = ['operation_key' => (string) Str::uuid(), 'apply_deposit' => true, 'payment_method' => 'cash', 'cash_tendered' => 0];
        $this->post(route('checkout.submit', $folio), $checkout)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('checkout.submit', $folio), $checkout)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('checked_out', $folio->fresh()->status);
        $this->assertSame('2100.00', $folio->fresh()->gross_total);
        $this->assertSame('0.00', $folio->fresh()->net_total);
        $this->get(route('dashboard'))->assertViewHas('myShiftCashCollections', 0.0)->assertViewHas('myShiftRevenue', 2100.0);
        $this->get(route('dashboard'))->assertViewHas('myShiftMoney', fn (array $money): bool => $money['moneyCountCash'] === $receivedCash - $refundCash);
        $this->assertDatabaseCount('payment_entries', 2);

        $this->at('2026-11-25 20:00');
        $refund = ['operation_key' => (string) Str::uuid(), 'amount' => 400, 'payment_method' => $method, 'gcash_reference' => $method === 'gcash' ? 'QA-DEPOSIT-REFUND' : null, 'reason' => 'QA return of unused deposit'];
        $this->post(route('folios.deposit_refund.store', $folio), $refund)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('folios.deposit_refund.store', $folio), $refund)->assertRedirect()->assertSessionHasNoErrors();
        $this->executeLatestFinancialRequest();

        $this->assertSame('400.00', $folio->fresh()->deposit_refunded_amount);
        $this->assertDatabaseCount('payment_entries', 3);
        $this->assertDatabaseCount('payment_refund_allocations', 1);
        $this->assertSame($receivedCash - $refundCash, (float) PaymentEntry::sum('cash_amount'));
        $this->assertSame($receivedGcash - $refundGcash, (float) PaymentEntry::sum('gcash_amount'));
        $this->assertSame($receivedCash - $refundCash, $financials->shift($drawer)['expectedCash']);
        $this->get(route('dashboard'))->assertViewHas('myShiftCashCollections', -$refundCash)->assertViewHas('myShiftRevenue', 2100.0);
        $this->get(route('shifts.index'))->assertViewHas('expectedCash', -$refundCash)->assertViewHas('gcashCollections', -$refundGcash);
        $this->get(route('shifts.remittance_slip', $drawer))->assertViewHas('expectedCash', -$refundCash);
        $this->get(route('shifts.index'))->assertViewHas('moneyCountCash', $receivedCash - $refundCash)
            ->assertViewHas('depositReconciliation', fn (array $deposit): bool => $deposit['cashApplied'] === $receivedCash - $refundCash && $deposit['closingCashHeld'] === 0.0);

        $previousDay = app(BusinessDayService::class)->snapshot('2026-11-24');
        $currentDay = app(BusinessDayService::class)->snapshot('2026-11-25');
        $this->assertSame((int) ($receivedCash * 100), $previousDay['cash_cents']);
        $this->assertSame(250000, $previousDay['closing_deposit_cents']);
        $this->assertSame(250000, $currentDay['opening_deposit_cents']);
        $this->assertSame((int) (-$refundCash * 100), $currentDay['cash_cents']);
        $this->assertSame(0, $currentDay['closing_deposit_cents']);
    }

    #[TestWith(['06:30', '06:15', 0.0, '2026-11-24'])]
    #[TestWith(['06:30', '06:30', 500.0, '2026-11-25'])]
    #[TestWith(['05:00', '06:15', 500.0, '2026-11-25'])]
    #[TestWith(['06:01', '06:15', 500.0, '2026-11-25'])]
    public function test_cutoff_changes_payment_allocation_without_adding_or_losing_cash(string $schedule, string $paymentTime, float $dayCash, string $businessDate): void
    {
        [$folio] = $this->receiveRoom29Deposit('cash', $schedule);
        $this->at('2026-11-25 '.$paymentTime);
        $this->post(route('folios.deposit.collect', $folio), ['operation_key' => (string) Str::uuid(), 'amount' => 500, 'payment_method' => 'cash'])->assertRedirect()->assertSessionHasNoErrors();

        $this->at('2026-11-25 07:00');
        $this->get(route('shifts.index'))->assertViewHas('cashRevenue', $dayCash);
        $this->assertDatabaseHas('payment_entries', ['kind' => 'deposit', 'cash_amount' => 500, 'business_date' => $businessDate]);
        $previous = app(BusinessDayService::class)->snapshot('2026-11-24');
        $current = app(BusinessDayService::class)->snapshot('2026-11-25');
        $this->assertSame((int) ($dayCash * 100), $current['cash_cents']);
        $this->assertSame(300000, $previous['cash_cents'] + $current['cash_cents']);
        $this->assertSame(300000, $current['closing_deposit_cents']);
        $this->assertDatabaseCount('payment_entries', 2);
    }

    public function test_mixed_deposits_apply_the_cash_part_once_and_hold_only_the_unused_gcash(): void
    {
        [$folio, $drawer] = $this->receiveRoom29Deposit('cash', '06:01', 1000);
        $this->post(route('folios.deposit.collect', $folio), ['operation_key' => (string) Str::uuid(), 'amount' => 1500, 'payment_method' => 'gcash', 'gcash_reference' => 'QA-MIXED-DEPOSIT'])->assertSessionHasNoErrors();
        $this->at('2026-11-25 19:59');
        $this->actingAs($this->cashier('Mixed-deposit settling cashier'));
        $this->settle($folio);

        $this->get(route('shifts.index'))->assertViewHas('moneyCountCash', 1000.0)
            ->assertViewHas('depositReconciliation', fn (array $deposit): bool => $deposit['cashApplied'] === 1000.0 && $deposit['closingCashHeld'] === 0.0 && $deposit['closingGcashHeld'] === 400.0);
        $this->refund($folio, 400, 'gcash');
        $this->get(route('shifts.remittance_slip', $drawer))->assertViewHas('moneyCountCash', 1000.0);
        $this->assertSame(1000.0, (float) PaymentEntry::sum('cash_amount'));
        $this->assertSame(1100.0, (float) PaymentEntry::sum('gcash_amount'));
        $this->assertDatabaseCount('payment_entries', 4);
    }

    #[TestWith(['cash', 'gcash', 2500.0, 2500.0, -400.0])]
    #[TestWith(['gcash', 'cash', -400.0, -400.0, 2500.0])]
    public function test_cross_tender_refunds_adjust_cash_using_the_original_deposit_source(string $depositMethod, string $refundMethod, float $moneyCount, float $cash, float $gcash): void
    {
        [$folio, $drawer] = $this->receiveRoom29Deposit($depositMethod);
        $drawer->update(['opening_float' => 1000]);
        $this->at('2026-11-25 19:59');
        $this->actingAs($this->cashier('Cross-tender refund cashier'));
        $this->settle($folio);
        $this->refund($folio, 400, $refundMethod);

        $this->get(route('shifts.index'))->assertViewHas('moneyCountCash', $moneyCount)
            ->assertViewHas('depositReconciliation', fn (array $deposit): bool => $deposit['closingCashHeld'] === 0.0 && $deposit['closingGcashHeld'] === 0.0 && $deposit['unverifiedRefunds'] === 0.0)
            ->assertSee('Cash/GCash refund exchange adjustment');
        $this->assertSame($cash, (float) PaymentEntry::sum('cash_amount'));
        $this->assertSame($gcash, (float) PaymentEntry::sum('gcash_amount'));
        $this->assertSame(1000.0 + $cash, app(FinancialSummaryService::class)->shift($drawer)['expectedCash']);
    }

    public function test_partial_refunds_release_held_cash_without_reducing_settled_cash_twice(): void
    {
        [$folio] = $this->receiveRoom29Deposit();
        $this->at('2026-11-25 19:59');
        $this->actingAs($this->cashier('Partial refund cashier'));
        $this->settle($folio);
        $this->refund($folio, 150, 'cash');
        $this->get(route('shifts.index'))->assertViewHas('moneyCountCash', 2100.0)
            ->assertViewHas('depositReconciliation', fn (array $deposit): bool => $deposit['cashRefunded'] === 150.0 && $deposit['closingCashHeld'] === 250.0);
        $this->refund($folio, 250, 'cash');

        $this->get(route('shifts.index'))->assertViewHas('moneyCountCash', 2100.0)
            ->assertViewHas('depositReconciliation', fn (array $deposit): bool => $deposit['cashReleasedByRefund'] === 400.0 && $deposit['closingCashHeld'] === 0.0);
        $this->assertSame(2100.0, (float) PaymentEntry::sum('cash_amount'));
        $this->assertDatabaseCount('payment_entries', 4);
    }

    public function test_deposit_not_applied_stays_in_handover_and_its_refund_does_not_change_the_paid_bill(): void
    {
        [$folio] = $this->receiveRoom29Deposit('cash', '06:01', 500);
        $this->at('2026-11-25 19:59');
        $this->actingAs($this->cashier('Separate payment cashier'));
        $this->settle($folio, false, 2100);
        $this->get(route('shifts.index'))->assertViewHas('moneyCountCash', 2100.0)
            ->assertViewHas('depositReconciliation', fn (array $deposit): bool => $deposit['cashApplied'] === 0.0 && $deposit['closingCashHeld'] === 500.0);
        $this->refund($folio, 500, 'cash');

        $this->get(route('shifts.index'))->assertViewHas('moneyCountCash', 2100.0)
            ->assertViewHas('depositReconciliation', fn (array $deposit): bool => $deposit['closingCashHeld'] === 0.0);
        $this->assertSame(2100.0, (float) PaymentEntry::sum('cash_amount'));
    }

    public function test_historical_handover_balances_do_not_change_after_a_later_checkout_and_refund(): void
    {
        [$folio, $drawer] = $this->receiveRoom29Deposit();
        $dayCashier = $this->cashier('Historical day cashier');
        $this->at('2026-11-25 19:59');
        $this->actingAs($this->cashier('Later settling cashier'));
        $this->settle($folio);
        $this->refund($folio, 400, 'cash');
        $this->at('2026-11-25 12:00');
        $this->actingAs($dayCashier);

        $this->get(route('shifts.remittance_slip', $drawer))->assertViewHas('moneyCountCash', 0.0)
            ->assertViewHas('depositReconciliation', fn (array $deposit): bool => $deposit['openingCashHeld'] === 2500.0 && $deposit['closingCashHeld'] === 2500.0 && $deposit['cashApplied'] === 0.0);
        $this->assertSame('400.00', $folio->fresh()->deposit_refunded_amount);
    }

    public function test_unverified_legacy_deposits_are_flagged_instead_of_inventing_cash(): void
    {
        [$folio] = $this->receiveRoom29Deposit('cash', '06:01', 1000);
        $folio->update(['security_deposit' => 2500]);
        $this->at('2026-11-25 19:59');
        $this->actingAs($this->cashier('Legacy-deposit settling cashier'));
        $this->settle($folio);

        $this->get(route('shifts.index'))->assertViewHas('moneyCountCash', 600.0)
            ->assertViewHas('depositReconciliation', fn (array $deposit): bool => $deposit['cashApplied'] === 600.0 && $deposit['unverifiedApplied'] === 1500.0 && $deposit['closingCashHeld'] === 400.0)
            ->assertSee('Unverified deposits are not added to settled cash');
        $this->assertSame(1000.0, (float) PaymentEntry::sum('cash_amount'));
    }

    public function test_admin_keeps_the_full_drawer_balance_while_pending_deposits_are_separate(): void
    {
        [$folio] = $this->receiveRoom29Deposit();
        $admin = User::factory()->create()->assignRole(Role::findOrCreate('admin', 'web'));
        foreach (['manage_shifts', 'view_all_shifts', 'record_payments'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $this->actingAs($admin)->get(route('shifts.index'))->assertViewHas('expectedCash', 2500.0)->assertViewHas('moneyCountCash', 0.0)
            ->assertViewHas('depositReconciliation', fn (array $deposit): bool => $deposit['closingCashHeld'] === 2500.0);
        $this->assertSame('2500.00', $folio->fresh()->security_deposit);
    }

    public function test_refund_by_the_following_cashier_uses_held_money_without_reducing_their_sales(): void
    {
        [$folio] = $this->receiveRoom29Deposit();
        $this->at('2026-11-25 19:59');
        $this->actingAs($this->cashier('Settling cashier'));
        $this->settle($folio);
        $this->at('2026-11-26 12:00');
        $this->actingAs($this->cashier('Following refund cashier'));
        $this->refund($folio, 400, 'cash');

        $this->get(route('shifts.index'))->assertViewHas('cashRevenue', -400.0)->assertViewHas('moneyCountCash', 0.0)
            ->assertViewHas('depositReconciliation', fn (array $deposit): bool => $deposit['openingCashHeld'] === 400.0 && $deposit['closingCashHeld'] === 0.0 && $deposit['cashApplied'] === 0.0 && $deposit['cashReleasedByRefund'] === 400.0);
        $this->assertSame(2100.0, (float) PaymentEntry::sum('cash_amount'));
    }

    public function test_money_count_deducts_real_expenses_and_approved_cash_transfers_once(): void
    {
        [$folio, $drawer] = $this->receiveRoom29Deposit();
        $this->at('2026-11-25 19:59');
        $this->actingAs($this->cashier('Cash-out cashier'));
        $this->settle($folio);
        $this->post(route('shifts.expense.store'), ['operation_key' => (string) Str::uuid(), 'description' => 'QA operational cash out', 'category' => 'other', 'amount' => 100])->assertSessionHasNoErrors();
        $this->post(route('approvals.store'), ['operation_key' => (string) Str::uuid(), 'kind' => 'cash_drop', 'source_id' => $drawer->id, 'reason' => 'QA remittance to safe', 'payload' => ['amount' => 200, 'destination' => 'safe', 'reference' => 'QA-CASH-DROP']])->assertSessionHasNoErrors();
        $this->executeLatestFinancialRequest();

        $this->get(route('shifts.index'))->assertViewHas('moneyCountCash', 1800.0)->assertViewHas('expectedCash', -300.0);
        $this->assertSame(2200.0, app(FinancialSummaryService::class)->shift($drawer)['expectedCash']);
        $this->assertDatabaseCount('cash_movements', 1);
        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_other_cashiers_settled_money_is_not_exposed_by_the_new_summary(): void
    {
        [$folio, $drawer] = $this->receiveRoom29Deposit();
        $cashier = auth()->user();
        $this->actingAs($this->cashier('Other cashier'));
        $room = Room::create(['number' => '30', 'floor' => 3, 'name' => 'Other room', 'type' => 'Classic Room', 'status' => 'available', 'base_rate_3h' => 98765]);
        $this->post(route('checkin.store'), ['operation_key' => (string) Str::uuid(), 'room_id' => $room->id, 'rate_tier' => '3h', 'headcount' => 2])->assertSessionHasNoErrors();
        $this->settle(Folio::where('room_id', $room->id)->sole(), false, 98765);

        $this->actingAs($cashier)->get(route('dashboard'))->assertViewHas('myShiftMoney', fn (array $money): bool => $money['moneyCountCash'] === 0.0 && $money['settledCashCollections'] === 0.0)
            ->assertDontSee('98,765.00');
        $this->get(route('shifts.remittance_slip', $drawer))->assertViewHas('moneyCountCash', 0.0)->assertDontSee('98,765.00');
        $this->assertSame('2500.00', $folio->fresh()->security_deposit);
    }

    private function settle(Folio $folio, bool $apply = true, float $cash = 0): void
    {
        $this->post(route('checkout.submit', $folio), ['operation_key' => (string) Str::uuid(), 'apply_deposit' => $apply, 'payment_method' => 'cash', 'cash_tendered' => $cash])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function refund(Folio $folio, float $amount, string $method): void
    {
        $this->post(route('folios.deposit_refund.store', $folio), ['operation_key' => (string) Str::uuid(), 'amount' => $amount, 'payment_method' => $method, 'gcash_reference' => $method === 'gcash' ? 'QA-REFUND' : null])->assertRedirect()->assertSessionHasNoErrors();
        $this->executeLatestFinancialRequest();
    }

    /** @return array{Folio, Shift} */
    private function receiveRoom29Deposit(string $method = 'cash', string $schedule = '06:01', float $amount = 2500): array
    {
        $this->withoutVite();
        config(['sedona.continuous_shift_enabled' => true]);
        if ($schedule !== '06:30') {
            $this->at('2026-11-09 12:00');
            ShiftCalendar::scheduleCutover('2026-11-10');
            if ($schedule === '06:01') {
                $this->at('2026-11-20 12:00');
                ShiftCalendar::scheduleCutover('2026-11-23', '06:01');
            }
        }
        $this->at('2026-11-24 20:00');
        $cashier = $this->cashier('First night cashier');
        $this->actingAs($cashier);
        $drawer = Shift::create(['opened_by' => $cashier->id, 'opened_at' => now()->subDays(2), 'shift_date' => '2026-11-22', 'shift_type' => 'day', 'opening_float' => 0]);
        $room = Room::create(['number' => '29', 'floor' => 3, 'name' => 'Classic Room 29', 'type' => 'Classic Room', 'status' => 'available', 'base_rate_24h' => 2100]);
        $this->post(route('checkin.store'), ['operation_key' => (string) Str::uuid(), 'room_id' => $room->id, 'rate_tier' => '24h', 'headcount' => 2])->assertRedirect()->assertSessionHasNoErrors();
        $folio = Folio::where('room_id', $room->id)->sole();
        $deposit = ['operation_key' => (string) Str::uuid(), 'amount' => $amount, 'payment_method' => $method, 'gcash_reference' => $method === 'gcash' ? 'QA-ROOM29-DEPOSIT' : null];
        $this->post(route('folios.deposit.collect', $folio), $deposit)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('folios.deposit.collect', $folio), $deposit)->assertRedirect()->assertSessionHasNoErrors();

        return [$folio, $drawer];
    }

    private function cashier(string $name): User
    {
        $cashier = User::factory()->create(['name' => $name])->assignRole(Role::findOrCreate('cashier', 'web'));
        foreach (['manage_folios', 'record_payments', 'manage_shifts', 'view_dashboard'] as $permission) {
            $cashier->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $cashier;
    }

    private function at(string $time): void
    {
        $this->travelTo(CarbonImmutable::parse($time, 'Asia/Manila'));
    }
}
