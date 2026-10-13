<?php

namespace Tests\Feature;

use App\Models\Folio;
use App\Models\Guest;
use App\Models\OrderItem;
use App\Models\PosItem;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestStayWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(12, 0));
    }

    public function test_scti_numbers_are_sequential_and_existing_numbers_are_preserved(): void
    {
        $actor = $this->actor();
        $first = $this->folio($actor);
        $second = $this->folio($actor);

        $this->assertSame('SCTI-000001', $first->transaction_id);
        $this->assertSame('SCTI-000002', $second->transaction_id);
        $first->update(['status' => 'voided']);
        $this->assertSame('SCTI-000003', $this->folio($actor)->transaction_id);
        $this->assertSame('SCTI-000001', $first->fresh()->transaction_id);
    }

    public function test_sequence_initialization_skips_historical_receipt_numbers(): void
    {
        $actor = $this->actor();
        $old = $this->folio($actor);
        $old->update(['transaction_id' => 'SCTI-923456']);
        $migration = require database_path('migrations/2026_10_13_072852_create_document_sequences_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame('SCTI-923457', $this->folio($actor)->transaction_id);
        $this->assertSame('SCTI-923456', $old->fresh()->transaction_id);
    }

    public function test_receipt_reprints_keep_the_original_number_without_consuming_the_sequence(): void
    {
        $actor = $this->actor();
        $folio = $this->folio($actor);
        $folio->update(['status' => 'checked_out', 'checked_out_at' => now()]);
        $this->actingAs($actor)->post(route('folios.receipt.print', $folio), ['operation_key' => (string) Str::uuid()])->assertOk()->assertSee('SCTI-000001');
        $this->post(route('folios.receipt.print', $folio), ['operation_key' => (string) Str::uuid()])->assertOk()->assertSee('SCTI-000001');

        $this->assertSame('SCTI-000002', $this->folio($actor)->transaction_id);
        $this->assertSame('SCTI-000001', $folio->fresh()->transaction_id);
    }

    public function test_failed_number_allocation_rolls_back_with_the_stay_transaction(): void
    {
        DB::beginTransaction();
        $this->assertSame('SCTI-000001', Folio::generateTransactionId());
        DB::rollBack();

        $this->assertSame('SCTI-000001', Folio::generateTransactionId());
        $this->assertSame('SCTI-000002', Folio::generateTransactionId());
    }

    public function test_late_entry_uses_actual_arrival_and_records_the_reason_and_operator(): void
    {
        $actor = $this->actor();
        $room = $this->room('available');
        $this->actingAs($actor)->post(route('checkin.store'), [
            'room_id' => $room->id, 'guest_name' => 'Earlier Arrival', 'headcount' => 2, 'rate_tier' => '3h',
            'checked_in_at' => now()->toIso8601String(), 'record_earlier_arrival' => 1,
            'actual_checked_in_at' => '2026-10-13T11:00', 'late_entry_reason' => 'Guest arrived while the computer was restarting.',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('folios', ['room_id' => $room->id, 'checked_in_at' => '2026-10-13 11:00:00', 'expected_checkout_at' => '2026-10-13 14:00:00']);
        $audit = DB::table('audit_logs')->where('event', 'folio.late_checkin_recorded')->first();
        $this->assertSame($actor->id, (int) $audit->user_id);
        $values = json_decode($audit->new_values, true);
        $this->assertSame('Guest arrived while the computer was restarting.', $values['reason']);
        $this->assertSame(now()->toIso8601String(), $values['recorded_at']);
    }

    public function test_late_entry_requires_an_arrival_and_reason_and_rejects_future_arrivals(): void
    {
        $actor = $this->actor();
        $room = $this->room('available');
        $payload = ['room_id' => $room->id, 'headcount' => 2, 'rate_tier' => '3h', 'record_earlier_arrival' => 1];
        $this->actingAs($actor)->post(route('checkin.store'), $payload)->assertSessionHasErrors(['actual_checked_in_at', 'late_entry_reason']);
        $this->post(route('checkin.store'), $payload + ['actual_checked_in_at' => now()->addHour()->toIso8601String(), 'late_entry_reason' => 'Late entry'])->assertSessionHasErrors('actual_checked_in_at');

        $this->assertDatabaseCount('folios', 0);
        $this->assertSame('available', $room->fresh()->status);
    }

    public function test_late_entry_cannot_overlap_a_previous_recorded_stay(): void
    {
        $actor = $this->actor();
        $previous = $this->folio($actor);
        $previous->update(['checked_in_at' => '2026-10-13 08:00:00', 'checked_out_at' => '2026-10-13 11:30:00', 'status' => 'checked_out']);
        $previous->room->update(['status' => 'available']);

        $this->actingAs($actor)->post(route('checkin.store'), [
            'room_id' => $previous->room_id, 'headcount' => 2, 'rate_tier' => '3h', 'record_earlier_arrival' => 1,
            'actual_checked_in_at' => '2026-10-13T11:00', 'late_entry_reason' => 'Late entry',
        ])->assertSessionHasErrors('actual_checked_in_at');

        $this->assertDatabaseCount('folios', 1);
        $this->assertDatabaseCount('guests', 1);
        $this->assertSame('available', $previous->room->fresh()->status);
    }

    public function test_multi_item_addons_aggregate_duplicates_and_retries_create_one_order(): void
    {
        $actor = $this->actor();
        $folio = $this->folio($actor);
        $first = $this->item('Water', 25);
        $second = $this->item('Guest Kit', 50);
        $payload = ['operation_key' => (string) Str::uuid(), 'items' => [
            ['id' => $first->id, 'qty' => 1], ['id' => $first->id, 'qty' => 2], ['id' => $second->id, 'qty' => 2],
        ]];

        $this->actingAs($actor)->post(route('folios.addon', $folio), $payload)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->post(route('folios.addon', $folio), $payload)->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertSame(7, $first->fresh()->stock_quantity);
        $this->assertSame(8, $second->fresh()->stock_quantity);
        $this->assertSame('175.00', $folio->fresh()->pos_total);
        $this->assertDatabaseHas('orders', ['folio_id' => $folio->id, 'total' => 175]);
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'pos.room_charge')->count());
    }

    public function test_one_unavailable_addon_rolls_back_the_entire_cart(): void
    {
        $actor = $this->actor();
        $folio = $this->folio($actor);
        $first = $this->item('Water', 25);
        $second = $this->item('Unavailable Kit', 50);
        $second->update(['stock_quantity' => 0, 'is_available' => false]);

        $this->actingAs($actor)->post(route('folios.addon', $folio), ['operation_key' => (string) Str::uuid(), 'items' => [
            ['id' => $first->id, 'qty' => 1], ['id' => $second->id, 'qty' => 1],
        ]])->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $first->fresh()->stock_quantity);
        $this->assertSame([], $folio->fresh()->pos_items);
    }

    public function test_order_history_follows_the_stay_after_transfer_and_keeps_legacy_addons(): void
    {
        $actor = $this->actor();
        $folio = $this->folio($actor);
        $folio->update(['pos_items' => [['name' => 'Legacy Pillow', 'qty' => 1, 'price' => 100, 'subtotal' => 100]]]);
        $item = $this->item('Current Guest Water', 25);
        $this->actingAs($actor)->post(route('folios.addon', $folio), [
            'operation_key' => (string) Str::uuid(), 'pos_item_id' => $item->id, 'quantity' => 2,
        ])->assertSessionHas('success');
        $other = $this->folio($actor);
        $otherItem = $this->item('Other Guest Snack', 10);
        $this->post(route('folios.addon', $other), ['operation_key' => (string) Str::uuid(), 'pos_item_id' => $otherItem->id, 'quantity' => 1]);
        $target = $this->room('available');
        $this->post(route('folios.transfer', $folio), ['target_room_id' => $target->id])->assertSessionHas('success');

        $response = $this->get('/folios/'.$folio->id.'/orders')->assertOk()
            ->assertSee('Current Guest Water')->assertSee('Legacy Pillow');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $history = (new \DOMXPath($document))->query('//section[@id="previousGuestOrders"]')->item(0);
        $this->assertStringNotContainsString('Other Guest Snack', $history->textContent);

        $this->assertSame('150.00', $folio->fresh()->pos_total);
    }

    public function test_authorized_order_correction_is_audited_and_retry_safe(): void
    {
        $actor = $this->actor('admin');
        [$folio, $item, $line] = $this->orderFixture($actor);
        $item->update(['price' => 99]);
        $payload = ['operation_key' => (string) Str::uuid(), 'expected_quantity' => 3, 'quantity' => 1, 'reason' => 'Two items were entered by mistake.'];

        $this->actingAs($actor)->post('/folios/'.$folio->id.'/orders/'.$line->id.'/correct', $payload)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->post('/folios/'.$folio->id.'/orders/'.$line->id.'/correct', $payload)->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('order_items', ['id' => $line->id, 'quantity' => 1, 'unit_price' => 25, 'subtotal' => 25]);
        $this->assertSame('25.00', $folio->fresh()->pos_total);
        $this->assertSame(7, $item->fresh()->stock_quantity, 'Delivered items must not silently return to inventory.');
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'pos.order_corrected')->count());
    }

    public function test_cancelling_an_unprepared_dish_returns_its_stock_and_ingredients(): void
    {
        $actor = $this->actor('admin');
        $folio = $this->folio($actor);
        $egg = $this->item('Egg (Fried/Boiled)', 20, 'Kitchen Extras');
        $dish = $this->item('Tapsilog', 160, 'Breakfast');
        $dish->update(['kitchen_hours_only' => true]);
        $this->actingAs($actor)->post(route('folios.addon', $folio), ['operation_key' => (string) Str::uuid(), 'pos_item_id' => $dish->id, 'quantity' => 2])->assertSessionHas('success');
        $line = OrderItem::firstOrFail();

        $this->post('/folios/'.$folio->id.'/orders/'.$line->id.'/correct', ['operation_key' => (string) Str::uuid(), 'expected_quantity' => 2, 'quantity' => 0, 'reason' => 'Guest cancelled before preparation.'])->assertSessionHasNoErrors();

        $this->assertSame(10, $dish->fresh()->stock_quantity);
        $this->assertSame(10, $egg->fresh()->stock_quantity);
        $this->assertSame('cancelled', $line->order->fresh()->status);
        $this->assertSame('0.00', $folio->fresh()->pos_total);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_cashier_cannot_correct_saved_orders(): void
    {
        $actor = $this->actor();
        [$folio, $item, $line] = $this->orderFixture($actor);

        $this->actingAs($actor)->post('/folios/'.$folio->id.'/orders/'.$line->id.'/correct', ['operation_key' => (string) Str::uuid(), 'expected_quantity' => 3, 'quantity' => 0, 'reason' => 'Cancel'])->assertForbidden();

        $this->assertSame(3, (int) $line->fresh()->quantity);
        $this->assertSame('75.00', $folio->fresh()->pos_total);
    }

    public function test_stale_corrections_and_quantity_increases_do_not_change_the_bill(): void
    {
        $actor = $this->actor('admin');
        [$folio, $item, $line] = $this->orderFixture($actor);
        $url = route('folios.orders.correct', [$folio, $line]);
        $this->actingAs($actor)->post($url, ['operation_key' => (string) Str::uuid(), 'expected_quantity' => 2, 'quantity' => 0, 'reason' => 'Stale request'])->assertConflict();
        $this->post($url, ['operation_key' => (string) Str::uuid(), 'expected_quantity' => 3, 'quantity' => 4, 'reason' => 'Another item'])->assertSessionHasErrors('quantity');

        $this->assertSame(3, $line->fresh()->quantity);
        $this->assertSame('75.00', $folio->fresh()->pos_total);
        $this->assertSame(7, $item->fresh()->stock_quantity);
        $this->assertSame(0, DB::table('audit_logs')->where('event', 'pos.order_corrected')->count());
    }

    public function test_order_correction_cannot_touch_another_stay_or_a_settled_bill(): void
    {
        $actor = $this->actor('admin');
        [$folio, $item, $line] = $this->orderFixture($actor);
        $other = $this->folio($actor);
        $payload = ['operation_key' => (string) Str::uuid(), 'expected_quantity' => 3, 'quantity' => 0, 'reason' => 'Cancel'];

        $this->actingAs($actor)->post('/folios/'.$other->id.'/orders/'.$line->id.'/correct', $payload)->assertNotFound();
        $folio->update(['status' => 'checked_out']);
        $this->post('/folios/'.$folio->id.'/orders/'.$line->id.'/correct', $payload)->assertConflict();

        $this->assertSame(3, (int) $line->fresh()->quantity);
    }

    #[TestWith(['12h', 1195, '2026-10-13 20:00:00'])]
    #[TestWith(['24h', 2100, '2026-10-14 08:00:00'])]
    public function test_package_conversion_rerates_from_check_in_and_retries_are_safe(string $tier, int $rate, string $checkout): void
    {
        $actor = $this->actor();
        $folio = $this->folio($actor);
        $folio->update(['checked_in_at' => '2026-10-13 08:00:00', 'extra_hours' => 2]);
        $payload = ['operation_key' => (string) Str::uuid(), 'rate_tier' => $tier, 'reason' => 'Guest requested a longer package.'];

        $this->actingAs($actor)->post('/folios/'.$folio->id.'/convert', $payload)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->post('/folios/'.$folio->id.'/convert', $payload)->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('folios', ['id' => $folio->id, 'rate_tier' => $tier, 'room_charge' => $rate, 'extra_hours' => 0, 'expected_checkout_at' => $checkout]);
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'folio.package_converted')->count());
        $audit = json_decode(DB::table('audit_logs')->where('event', 'folio.package_converted')->value('new_values'), true);
        $this->assertSame('3h', $audit['before']['rate_tier']);
        $this->assertSame(2, $audit['before']['extra_hours']);
    }

    public function test_invalid_or_settled_package_conversion_does_not_change_the_stay(): void
    {
        $actor = $this->actor();
        $folio = $this->folio($actor);

        $this->actingAs($actor)->post('/folios/'.$folio->id.'/convert', ['operation_key' => (string) Str::uuid(), 'rate_tier' => '6h', 'reason' => 'Invalid package'])->assertSessionHasErrors('rate_tier');
        $folio->update(['status' => 'checked_out']);
        $this->post('/folios/'.$folio->id.'/convert', ['operation_key' => (string) Str::uuid(), 'rate_tier' => '12h', 'reason' => 'Longer stay'])->assertConflict();
        $this->assertSame('3h', $folio->fresh()->rate_tier);
    }

    private function actor(string $role = 'cashier'): User
    {
        $actor = User::factory()->create();
        $actor->assignRole(Role::findOrCreate($role, 'web'));
        foreach (['manage_folios', 'view_dashboard', 'manage_shifts', 'view_all_shifts'] as $permission) {
            $actor->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $actor;
    }

    private function room(string $status = 'occupied'): Room
    {
        return Room::create(['number' => (string) (Room::count() + 1), 'floor' => 1, 'name' => 'Classic', 'type' => 'Classic Room', 'status' => $status, 'base_rate_3h' => 395, 'base_rate_6h' => 785, 'base_rate_12h' => 1195, 'base_rate_24h' => 2100, 'base_rate_promo' => 995]);
    }

    private function folio(User $actor): Folio
    {
        return Folio::create(['room_id' => $this->room()->id, 'guest_id' => Guest::create(['name' => 'Guest '.Str::random(6), 'headcount' => 1])->id, 'user_id' => $actor->id, 'transaction_id' => Folio::generateTransactionId(), 'rate_tier' => '3h', 'checked_in_at' => now(), 'expected_checkout_at' => now()->addHours(3), 'room_charge' => 395, 'status' => 'active', 'pos_items' => []]);
    }

    private function item(string $name, int $price, string $category = 'Services'): PosItem
    {
        return PosItem::create(['name' => $name, 'category' => $category, 'price' => $price, 'is_tracked' => true, 'is_available' => true, 'stock_quantity' => 10, 'is_retired' => false]);
    }

    /** @return array{Folio, PosItem, OrderItem} */
    private function orderFixture(User $actor): array
    {
        $folio = $this->folio($actor);
        $item = $this->item('Water', 25);
        $this->actingAs($actor)->post(route('folios.addon', $folio), ['operation_key' => (string) Str::uuid(), 'pos_item_id' => $item->id, 'quantity' => 3])->assertSessionHas('success');

        return [$folio, $item, OrderItem::firstOrFail()];
    }
}
