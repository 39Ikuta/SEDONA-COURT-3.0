<?php

namespace Tests\Feature;

use App\Models\Folio;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CashierDiscountWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_discount_validation_is_visible_after_returning_to_the_front_desk(): void
    {
        $folio = $this->checkInRoom29();

        $this->from(route('dashboard'))->followingRedirects()->post(route('folios.discount', $folio), [
            'discount_type' => 'senior', 'discount_id_ref' => ' ',
        ])->assertSee('Record the statutory discount ID reference.');

        $this->assertSame('NONE', $folio->fresh()->discount_type);
        $this->assertSame('0.00', $folio->fresh()->discount_amount);
    }

    #[TestWith(['senior', 'OSCA-ROOM-29'])]
    #[TestWith(['pwd', 'PWD-ROOM-29'])]
    public function test_cashier_can_save_a_documented_fixed_discount_from_checkout(string $type, string $reference): void
    {
        $folio = $this->checkInRoom29();

        $this->postJson(route('folios.deposit', $folio), [
            'discount_type' => $type, 'discount_id_ref' => $reference,
        ])->assertOk()->assertJsonPath('discount_amount', '79.00')->assertJsonPath('net_total', '316.00');

        $this->assertDatabaseHas('folios', [
            'id' => $folio->id, 'discount_type' => strtoupper($type),
            'discount_id_ref' => $reference, 'discount_amount' => 79,
        ]);
        $this->get(route('checkout.process', $folio))->assertSee($reference);
    }

    public function test_checkout_discount_without_an_id_returns_an_actionable_error_and_keeps_the_bill(): void
    {
        $folio = $this->checkInRoom29();

        $this->postJson(route('folios.deposit', $folio), [
            'discount_type' => 'pwd', 'discount_id_ref' => '',
        ])->assertUnprocessable()->assertJsonValidationErrors('discount_id_ref')
            ->assertJsonPath('errors.discount_id_ref.0', 'Record the statutory discount ID reference.');

        $this->assertSame('NONE', $folio->fresh()->discount_type);
        $this->assertSame('395.00', $folio->fresh()->net_total);
    }

    public function test_cashier_discount_change_link_selects_the_requested_guest_and_classification(): void
    {
        $folio = $this->checkInRoom29();

        $this->get(route('approvals.index', ['folio_id' => $folio->id, 'discount_type' => 'DC']))
            ->assertViewHas('selectedFolio', fn (Folio $selected): bool => $selected->is($folio))
            ->assertViewHas('selectedDiscountType', 'DC')->assertSee('Room 29');

        $this->assertDatabaseCount('approval_requests', 0);
        $this->assertSame('NONE', $folio->fresh()->discount_type);
    }

    private function checkInRoom29(): Folio
    {
        $this->withoutVite();
        $this->travelTo('2026-10-11 12:00:00');
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'pau@sedonapms.com')->firstOrFail());
        $room = Room::where('number', '29')->firstOrFail();

        $this->post(route('checkin.store'), [
            'room_id' => $room->id, 'guest_name' => 'Discount workflow guest',
            'headcount' => 2, 'rate_tier' => '3h',
        ])->assertSessionHasNoErrors();

        return Folio::where('room_id', $room->id)->firstOrFail();
    }
}
