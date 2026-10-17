<?php

namespace Tests\Feature;

use App\Models\Folio;
use App\Models\Guest;
use App\Models\Room;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingPrintTimeLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-10-17 12:00:00');
    }

    public function test_preview_does_not_lock_and_print_request_locks_once_even_if_retried(): void
    {
        $actor = $this->actor('cashier');
        $folio = $this->stay($actor);
        $payload = $this->key();
        $this->actingAs($actor)->get(route('folios.billing', $folio))->assertOk()
            ->assertSee(route('folios.billing.print', $folio), false)->assertDontSee('class="billing-print-authorized"', false);
        $this->assertFalse($folio->fresh()->isStayTimeLocked());

        $this->post(route('folios.billing.print', $folio), $payload)->assertOk()->assertViewHas('printRequested', true)
            ->assertSee('class="billing-print-authorized"', false);
        $this->post(route('folios.billing.print', $folio), $payload)->assertOk();

        $this->assertSame('2026-10-17 12:00:00', $folio->fresh()->billing_printed_at->toDateTimeString());
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'billing.print_requested')->count());
        $audit = DB::table('audit_logs')->where('event', 'billing.print_requested')->first();
        $this->assertSame($actor->id, $audit->user_id);
        $this->assertFalse(json_decode($audit->new_values, true)['physical_print_confirmed']);
        $this->assertNull($folio->fresh()->receipt_number);
        $this->travel(2)->minutes();
        $this->post(route('folios.billing.print', $folio), $this->key())->assertOk()->assertViewHas('receiptCopy', 2);
        $this->assertSame('2026-10-17 12:00:00', $folio->fresh()->billing_printed_at->toDateTimeString());
    }

    #[TestWith(['cashier'])]
    #[TestWith(['front_desk'])]
    #[TestWith(['admin'])]
    #[TestWith(['owner'])]
    #[TestWith(['manager'])]
    public function test_all_operating_roles_cannot_extend_or_convert_a_printed_stay(string $role): void
    {
        $actor = $this->actor($role);
        $folio = $this->stay($actor);
        $this->actingAs($actor)->post(route('folios.billing.print', $folio), $this->key())->assertOk();
        $before = $folio->fresh()->getAttributes();

        $this->post(route('folios.extend', $folio), $this->key() + ['hours' => 1])->assertSessionHasErrors('stay_time');
        foreach (['12h', '24h'] as $tier) {
            $this->post(route('folios.convert', $folio), $this->key() + ['rate_tier' => $tier, 'reason' => 'Guest requested conversion'])->assertSessionHasErrors('stay_time');
        }

        $this->assertSame($before, $folio->fresh()->getAttributes());
    }

    public function test_admin_cannot_submit_a_time_correction_form_opened_before_printing(): void
    {
        $admin = $this->actor('admin');
        $folio = $this->stay($admin);
        $data = $this->key() + ['checked_in_at' => '2026-10-17T10:30', 'expected_checked_in_at' => $folio->checked_in_at->toIso8601String(), 'reason' => 'RA declared earlier'];
        $this->actingAs($admin)->post(route('folios.billing.print', $folio), $this->key())->assertOk();

        $this->post(route('admin.check-in-times.update', $folio), $data)->assertSessionHasErrors('stay_time');

        $this->assertSame('2026-10-17 11:00:00', $folio->fresh()->checked_in_at->toDateTimeString());
        $this->get(route('admin.check-in-times.index'))->assertOk()->assertSee('Stay times and package are locked.')
            ->assertDontSee('name="checked_in_at"', false);
        $this->get(route('dashboard'))->assertViewHas('folioExtensionOptions', fn ($options): bool => $options[$folio->id]['timeLocked'] && $options[$folio->id]['conversions'] === []);
    }

    public function test_open_time_cannot_convert_after_printing(): void
    {
        $actor = $this->actor('cashier');
        $folio = $this->stay($actor);
        $folio->update(['stay_mode' => 'open']);
        $this->actingAs($actor)->post(route('folios.billing.print', $folio), $this->key())->assertOk();

        $this->post(route('folios.convert', $folio), $this->key() + ['rate_tier' => '12h', 'reason' => 'Change Open Time'])->assertSessionHasErrors('stay_time');

        $this->assertSame('open', $folio->fresh()->stay_mode);
    }

    public function test_deposit_collection_and_checkout_keep_working_with_normal_overstay_billing(): void
    {
        $actor = $this->actor('cashier');
        $folio = $this->stay($actor);
        Shift::create(['opened_by' => $actor->id, 'shift_type' => 'day', 'shift_date' => today(), 'opened_at' => now(), 'opening_float' => 0]);
        $this->actingAs($actor)->post(route('folios.billing.print', $folio), $this->key())->assertOk();
        $this->post(route('folios.deposit.collect', $folio), $this->key() + ['amount' => 100, 'payment_method' => 'cash'])->assertSessionHasNoErrors();
        $this->travelTo('2026-10-17 16:10:00');

        $this->post(route('checkout.submit', $folio), $this->key() + ['payment_method' => 'cash', 'cash_tendered' => 1000])->assertSessionHasNoErrors();

        $folio->refresh();
        $this->assertSame('checked_out', $folio->status);
        $this->assertSame(2, $folio->overtime_hours);
        $this->assertSame('655.00', $folio->gross_total);
        $this->assertSame('555.00', $folio->net_total);
        $this->assertSame('2026-10-17 16:10:00', $folio->checked_out_at->toDateTimeString());
        $this->assertNotNull($folio->receipt_number);
    }

    public function test_print_validation_and_audit_failure_do_not_leave_a_lock(): void
    {
        $actor = $this->actor('cashier');
        $folio = $this->stay($actor);
        $this->actingAs($actor)->post(route('folios.billing.print', $folio), [])->assertSessionHasErrors('operation_key');
        $this->post(route('folios.billing.print', $folio), $this->key() + ['room_charge' => 1])->assertSessionHasErrors('room_charge');
        $this->assertFalse($folio->fresh()->isStayTimeLocked());
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with($query->sql, 'insert into "audit_logs"')) {
                throw new RuntimeException('Simulated print audit failure.');
            }
        });
        try {
            $this->post(route('folios.billing.print', $folio), $this->key())->assertServerError();
        } finally {
            Event::forget(QueryExecuted::class);
        }
        $this->assertFalse($folio->fresh()->isStayTimeLocked());
        $this->assertDatabaseCount('financial_operations', 0);
        $this->assertSame(0, DB::table('audit_logs')->where('event', 'billing.print_requested')->count());
    }

    public function test_invalid_accounts_and_cancelled_stays_cannot_issue_a_billing_print(): void
    {
        $actor = $this->actor('cashier');
        $folio = $this->stay($actor);
        $kitchen = User::factory()->create()->assignRole(Role::findOrCreate('kitchen', 'web'));
        $this->actingAs($kitchen)->post(route('folios.billing.print', $folio), $this->key())->assertForbidden();
        $folio->update(['status' => 'voided']);
        $this->actingAs($actor)->post(route('folios.billing.print', $folio), $this->key())->assertConflict();
        $this->assertFalse($folio->fresh()->isStayTimeLocked());
    }

    public function test_stale_print_parameters_redirect_to_a_clean_preview_with_an_error(): void
    {
        $actor = $this->actor('cashier');
        $folio = $this->stay($actor);
        $staleUrl = route('folios.billing', $folio).'?room_charge=1';

        $this->actingAs($actor)->from($staleUrl)->post(route('folios.billing.print', $folio).'?room_charge=1', $this->key())
            ->assertRedirect(route('folios.billing', $folio))->assertSessionHasErrors('room_charge');

        $this->withCookie(config('session.cookie'), session()->getId())->get(route('folios.billing', $folio))
            ->assertOk()->assertSee('Statement was not printed.');
        $this->assertFalse($folio->fresh()->isStayTimeLocked());
    }

    public function test_retry_of_an_extension_completed_before_print_does_not_change_locked_time(): void
    {
        $actor = $this->actor('cashier');
        $folio = $this->stay($actor);
        $data = $this->key() + ['hours' => 1];
        $this->actingAs($actor)->post(route('folios.extend', $folio), $data)->assertSessionHasNoErrors();
        $this->post(route('folios.billing.print', $folio), $this->key())->assertOk();

        $this->post(route('folios.extend', $folio), $data)->assertSessionHasNoErrors();

        $this->assertSame(1, $folio->fresh()->extra_hours);
        $this->assertSame('2026-10-17 15:00:00', $folio->fresh()->expected_checkout_at->toDateTimeString());
    }

    private function actor(string $role): User
    {
        $actor = User::factory()->create()->assignRole(Role::findOrCreate($role, 'web'));
        foreach (['manage_folios', 'view_dashboard', 'record_payments', 'manage_shifts'] as $permission) {
            $actor->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $actor;
    }

    private function stay(User $actor): Folio
    {
        $room = Room::create(['number' => '1', 'floor' => 1, 'name' => 'Classic', 'type' => 'Classic Room', 'status' => 'occupied', 'base_rate_3h' => 395, 'base_rate_12h' => 1195, 'base_rate_24h' => 2100]);

        return Folio::create(['room_id' => $room->id, 'guest_id' => Guest::create(['name' => 'Print guest', 'headcount' => 2])->id,
            'user_id' => $actor->id, 'transaction_id' => Folio::generateTransactionId(), 'rate_tier' => '3h', 'status' => 'active', 'room_charge' => 395,
            'checked_in_at' => '2026-10-17 11:00:00', 'expected_checkout_at' => '2026-10-17 14:00:00']);
    }

    /** @return array{operation_key: string} */
    private function key(): array
    {
        return ['operation_key' => (string) Str::uuid()];
    }
}
