<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Booking;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\PaymentEntry;
use App\Models\Room;
use App\Models\Shift;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(12, 0));
        $this->cashier = User::factory()->create();
        Role::findOrCreate('cashier', 'web');
        $this->cashier->assignRole('cashier');
        foreach (['manage_folios', 'record_payments', 'manage_shifts'] as $permission) {
            $this->cashier->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        Shift::create(['opened_by' => $this->cashier->id, 'shift_type' => 'day', 'shift_date' => today(), 'opened_at' => now()]);
        $this->actingAs($this->cashier);
        $this->room = Room::create(['number' => '99', 'floor' => 1, 'name' => 'Classic', 'type' => 'Classic Room', 'status' => 'occupied', 'is_staff_quarters' => false, 'base_rate_3h' => 395, 'base_rate_6h' => 790, 'base_rate_12h' => 1195, 'base_rate_24h' => 2100, 'base_rate_promo' => 995]);
    }

    private function folio(array $attributes = []): Folio
    {
        $guest = Guest::create(['name' => 'Billing Guest', 'contact' => uniqid(), 'headcount' => 1]);

        return Folio::create(array_replace(['room_id' => $this->room->id, 'guest_id' => $guest->id, 'user_id' => $this->cashier->id, 'transaction_id' => Folio::generateTransactionId(), 'rate_tier' => '3h', 'checked_in_at' => now()->subHour(), 'expected_checkout_at' => now()->addHours(2), 'status' => 'active', 'room_charge' => 395, 'discount_type' => 'NONE', 'payment_method' => 'cash', 'pos_items' => []], $attributes));
    }

    public function test_recalculation_preserves_fixed_discount_and_all_surcharges(): void
    {
        $folio = $this->folio(['discount_type' => 'DC', 'discount_amount' => 40, 'extra_persons' => 1, 'extra_bedding' => 1, 'extra_towels' => 1, 'extra_hours' => 2, 'pos_items' => [['name' => 'Water', 'qty' => 1, 'price' => 20, 'subtotal' => 20]]]);
        $folio->recalculate();
        $this->assertEquals(1025, $folio->gross_total);
        $this->assertEquals(40, $folio->discount_amount);
        $this->assertEquals(985, $folio->net_total);
        $this->post(route('checkout.submit', $folio), ['operation_key' => (string) Str::uuid(), 'cash_tendered' => 1000])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payment_entries', ['folio_id' => $folio->id, 'kind' => 'settlement', 'cash_amount' => 985]);
    }

    public function test_stay_extension_accepts_hours_from_an_html_form(): void
    {
        $folio = $this->folio();
        $folio->recalculate();

        $this->post(route('folios.extend', $folio), ['hours' => '3'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Stay extended.');

        $this->assertDatabaseHas('folios', [
            'id' => $folio->id,
            'extra_hours' => 3,
            'expected_checkout_at' => today()->format('Y-m-d').' 17:00:00',
            'surcharge_total' => 390,
            'net_total' => 785,
        ]);
        $this->assertDatabaseHas('rooms', ['id' => $this->room->id, 'status' => 'occupied']);
    }

    public function test_stay_extension_rejects_fractional_hours_without_changing_the_folio(): void
    {
        $folio = $this->folio();
        $folio->recalculate();

        $this->post(route('folios.extend', $folio), ['hours' => '1.5'])
            ->assertSessionHasErrors('hours');

        $this->assertDatabaseHas('folios', [
            'id' => $folio->id,
            'extra_hours' => 0,
            'expected_checkout_at' => today()->format('Y-m-d').' 14:00:00',
            'surcharge_total' => 0,
            'net_total' => 395,
        ]);
    }

    public function test_overtime_rounds_immediately_and_receipt_freezes_at_checkout(): void
    {
        $folio = $this->folio(['checked_in_at' => now()->subHours(4)->subSecond(), 'extra_hours' => 1]);
        $this->post(route('checkout.submit', $folio), ['operation_key' => (string) Str::uuid(), 'cash_tendered' => 1000])->assertSessionHasNoErrors();
        $folio->refresh();
        $this->assertEquals(1, $folio->overtime_hours);
        $this->assertEquals(655, $folio->gross_total);
        $this->travel(2)->days();
        $response = $this->get(route('folios.receipt', $folio).'?room_charge=1&discount_type=senior&deposit=9999');
        $response->assertOk()->assertViewHas('grossTotal', 655)->assertViewHas('finalBalanceToPay', 655)->assertViewHas('excessHours', 1);
        $this->assertEquals($this->cashier->id, $folio->checked_out_by);
    }

    public function test_blank_insufficient_and_unreferenced_payments_leave_room_occupied(): void
    {
        $folio = $this->folio();
        foreach ([[], ['payment_method' => 'cash', 'cash_tendered' => 100], ['payment_method' => 'split', 'cash_tendered' => 100, 'gcash_amount' => 100], ['payment_method' => 'gcash', 'gcash_amount' => 395]] as $payment) {
            $this->post(route('checkout.submit', $folio), ['operation_key' => (string) Str::uuid()] + $payment)->assertSessionHasErrors();
            $this->assertEquals('active', $folio->fresh()->status);
            $this->assertEquals('occupied', $this->room->fresh()->status);
        }
        $this->assertDatabaseCount('payment_entries', 0);
    }

    public function test_checkout_replay_and_late_draft_cannot_release_new_occupant(): void
    {
        $folio = $this->folio();
        $this->post(route('checkout.submit', $folio), ['operation_key' => (string) Str::uuid(), 'cash_tendered' => 400])->assertSessionHasNoErrors();
        $this->room->refresh()->update(['status' => 'occupied']);
        $this->folio();
        $this->post(route('checkout.submit', $folio), ['operation_key' => (string) Str::uuid(), 'cash_tendered' => 400])->assertStatus(409);
        $this->postJson(route('folios.deposit', $folio), ['security_deposit' => 0])->assertStatus(409);
        $this->assertEquals('occupied', $this->room->fresh()->status);
        $this->assertDatabaseCount('payment_entries', 1);
    }

    public function test_rejected_checkout_shows_the_payment_error_and_retains_entered_tenders(): void
    {
        $folio = $this->folio(['room_charge' => 695, 'pos_items' => [['name' => 'Drink', 'qty' => 1, 'price' => 90, 'subtotal' => 90]]]);
        $checkoutUrl = route('checkout.process', $folio);
        $payment = ['payment_method' => 'split', 'cash_tendered' => 500.04, 'gcash_amount' => 300, 'gcash_reference' => ''];

        $this->from($checkoutUrl)->post(route('checkout.submit', $folio), ['operation_key' => (string) Str::uuid()] + $payment)
            ->assertRedirect($checkoutUrl)->assertSessionHasErrors('gcash_reference');

        $this->assertSame('active', $folio->fresh()->status);
        $this->assertDatabaseCount('payment_entries', 0);
        $this->withCookie(config('session.cookie'), session()->getId())->get($checkoutUrl)->assertSee('A GCash reference is required.')
            ->assertSee('value="500.04"', false)->assertSee('value="300"', false);

        $payment['gcash_reference'] = 'TEST-GCASH-REFERENCE';
        $this->post(route('checkout.submit', $folio), ['operation_key' => (string) Str::uuid()] + $payment)->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $this->assertSame('checked_out', $folio->fresh()->status);
        $this->assertDatabaseHas('payment_entries', ['folio_id' => $folio->id, 'kind' => 'settlement', 'cash_amount' => 485, 'gcash_amount' => 300]);
    }

    public function test_saved_draft_is_rendered_and_missing_fields_do_not_clear_it(): void
    {
        $folio = $this->folio(['payment_method' => 'split', 'cash_tendered' => 200, 'gcash_amount' => 195, 'gcash_reference' => 'REAL-PAYMENT']);
        $this->postJson(route('folios.deposit', $folio), ['discount_id_ref' => 'new-ref'])->assertOk();
        $this->assertEquals(200, $folio->fresh()->cash_tendered);
        $this->assertEquals('REAL-PAYMENT', $folio->fresh()->gcash_reference);
        $this->get(route('checkout.process', $folio))->assertOk()->assertSee('value="200.00"', false)->assertSee('value="REAL-PAYMENT"', false);
    }

    public function test_deposit_draft_cannot_reduce_received_money_and_remaining_refund_is_signed_money(): void
    {
        $folio = $this->folio(['room_charge' => 100]);
        $this->post(route('folios.deposit.collect', $folio), ['operation_key' => (string) Str::uuid(), 'amount' => 300, 'payment_method' => 'cash'])->assertSessionHasNoErrors();
        $this->postJson(route('folios.deposit', $folio), ['security_deposit' => 300])->assertOk();
        $this->assertEquals(200, app(BillingService::class)->calculate($folio->fresh())['remainingDeposit']);
        $this->post(route('checkout.submit', $folio), ['operation_key' => (string) Str::uuid(), 'cash_tendered' => 0])->assertSessionHasNoErrors();
        $this->post(route('folios.deposit_refund.store', $folio), ['operation_key' => (string) Str::uuid(), 'amount' => 200, 'payment_method' => 'cash'])->assertSessionHasNoErrors();
        $this->executeLatestFinancialRequest();
        $this->assertEquals(100, PaymentEntry::sum('cash_amount'));
        $this->assertEquals(200, $folio->fresh()->deposit_refunded_amount);
        $this->post(route('folios.deposit_refund.store', $folio), ['operation_key' => (string) Str::uuid(), 'amount' => 1, 'payment_method' => 'cash'])->assertSessionHasErrors('amount');
        $this->assertEquals(0, app(BillingService::class)->calculate($folio->fresh())['remainingDeposit']);
    }

    public function test_occupied_booking_cannot_bypass_availability_and_booking_fields_are_authoritative(): void
    {
        $booking = Booking::create(['room_id' => $this->room->id, 'user_id' => $this->cashier->id, 'guest_name' => 'Reserved Guest', 'guest_contact' => 'RESERVED', 'headcount' => 2, 'rate_tier' => '6h', 'arrival_at' => now(), 'departure_at' => now()->addHours(6), 'status' => 'scheduled']);
        $data = ['room_id' => $this->room->id, 'booking_id' => $booking->id, 'guest_name' => 'Tampered', 'headcount' => 1, 'rate_tier' => '3h'];
        $this->post(route('checkin.store'), $data)->assertSessionHasErrors('room_id');
        $this->room->update(['status' => 'available']);
        $this->post(route('checkin.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('guests', ['name' => 'Reserved Guest', 'contact' => 'RESERVED', 'headcount' => 2]);
        $this->assertDatabaseHas('folios', ['booking_id' => $booking->id, 'rate_tier' => '6h', 'room_charge' => 790]);
        $this->assertDatabaseCount('payment_entries', 0);
    }

    public function test_frozen_shift_rolls_back_settlement_and_deposit_collection(): void
    {
        Shift::firstOrFail()->update(['is_frozen' => true]);
        $folio = $this->folio();
        $this->post(route('checkout.submit', $folio), ['operation_key' => (string) Str::uuid(), 'cash_tendered' => 400])->assertSessionHasErrors('shift');
        $this->postJson(route('folios.deposit.collect', $folio), ['operation_key' => (string) Str::uuid(), 'amount' => 100, 'payment_method' => 'cash'])->assertUnprocessable();
        $this->assertEquals('active', $folio->fresh()->status);
        $this->assertEquals(0, $folio->fresh()->security_deposit);
        $this->assertDatabaseCount('payment_entries', 0);
    }

    public function test_fixed_discount_cannot_reduce_food_when_room_charge_is_waived(): void
    {
        $folio = $this->folio(['room_charge' => 0, 'discount_type' => 'SENIOR', 'senior_pwd_discount' => true, 'pos_items' => [['name' => 'Food', 'qty' => 1, 'subtotal' => 160]]]);
        $folio->recalculate();
        $this->assertEquals(0, $folio->discount_amount);
        $this->assertEquals(160, $folio->net_total);
    }

    public function test_force_loss_is_only_recorded_on_approval_with_a_stable_reference(): void
    {
        $folio = $this->folio(['security_deposit' => 100, 'deposit_status' => 'applied_to_bill']);
        $this->post(route('folios.force_checkout', $folio), ['operation_key' => (string) Str::uuid(), 'force_reason' => 'Guest left without paying'])->assertRedirect(route('approvals.index'));
        $this->assertDatabaseCount('payment_entries', 0);
        $this->assertNull($folio->fresh()->loss_slip_reference);
        $this->get(route('folios.loss_slip', $folio))->assertStatus(409);
        Role::findOrCreate('admin', 'web');
        $this->cashier->assignRole('admin');
        $this->executeLatestFinancialRequest();
        $folio->refresh();
        $this->assertEquals(295, $folio->net_total);
        $this->assertNotNull($folio->loss_slip_reference);
        $this->assertDatabaseHas('payment_entries', ['folio_id' => $folio->id, 'kind' => 'write_off', 'cash_amount' => 0, 'gcash_amount' => 0]);
        $this->get(route('folios.loss_slip', $folio))->assertOk()->assertSee($folio->loss_slip_reference);
        $this->get(route('folios.receipt', $folio))->assertOk()->assertSee('WRITE-OFF RECORD')->assertDontSee('CASH PAYMENT');
        $approval = ApprovalRequest::latest('id')->firstOrFail();
        $this->post(route('approvals.execute', $approval), ['operation_key' => (string) Str::uuid()])->assertSessionHasErrors('approval');
        $this->assertDatabaseCount('payment_entries', 1);
    }

    public function test_refund_summary_does_not_claim_a_refund_when_no_balance_is_due(): void
    {
        $folio = $this->folio(['security_deposit' => 395, 'deposit_status' => 'applied_to_bill']);
        $this->post(route('checkout.submit', $folio), ['operation_key' => (string) Str::uuid(), 'cash_tendered' => 0])->assertRedirect(route('dashboard'));
        $this->get(route('folios.deposit_refund', $folio))->assertOk()->assertSee('NO REFUND DUE')->assertSee('DEPOSIT REFUND SUMMARY')->assertDontSee('I acknowledge receipt');
    }

    public function test_receipt_uses_the_checkout_operator_and_refund_uses_actual_refund_operator(): void
    {
        $checkInOperator = User::factory()->create(['name' => 'Check In Operator']);
        $this->cashier->update(['name' => 'Checkout Operator']);
        $folio = $this->folio(['user_id' => $checkInOperator->id, 'security_deposit' => 500, 'deposit_status' => 'not_applied']);
        $this->post(route('checkout.submit', $folio), ['operation_key' => (string) Str::uuid(), 'cash_tendered' => 400])->assertRedirect(route('dashboard'));
        $this->get(route('folios.receipt', $folio))->assertOk()->assertSee('Checkout Operator')->assertDontSee('Check In Operator');
        $refundCashier = User::factory()->create(['name' => 'Refund Operator']);
        $refundCashier->givePermissionTo(['manage_folios', 'record_payments', 'manage_shifts']);
        Shift::firstOrFail()->update(['closed_at' => now()]);
        Shift::create(['opened_by' => $refundCashier->id, 'shift_type' => 'day', 'shift_date' => today(), 'opened_at' => now()]);
        $this->actingAs($refundCashier)->post(route('folios.deposit_refund.store', $folio), ['operation_key' => (string) Str::uuid(), 'amount' => 500, 'payment_method' => 'gcash', 'gcash_reference' => 'REFUND-OPERATOR'])->assertSessionHasNoErrors();
        $this->executeLatestFinancialRequest();
        $this->get(route('folios.deposit_refund', $folio))->assertOk()->assertSee('Refund Operator')->assertSee('TOTAL DEPOSIT REFUNDED')->assertSee('REFUNDED &amp; CLEARED', false);
    }

    public function test_promo_remains_twelve_hours_and_legacy_senior_flag_uses_fixed_table(): void
    {
        $folio = $this->folio(['rate_tier' => 'promo', 'room_charge' => 995, 'checked_in_at' => now()->subHours(12)]);
        $this->assertEquals(0, app(BillingService::class)->calculate($folio)['excessHours']);
        $this->travel(1)->seconds();
        $this->assertEquals(1, app(BillingService::class)->calculate($folio)['excessHours']);
        $senior = $this->folio(['senior_pwd_discount' => true]);
        $senior->recalculate();
        $this->assertEquals(79, $senior->discount_amount);
    }
}
