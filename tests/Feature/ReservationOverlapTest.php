<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReservationOverlapTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['2026-10-09 06:00:00'])]
    #[TestWith(['2026-10-09 12:00:00'])]
    public function test_reservation_accepts_an_adjacent_slot(string $arrival): void
    {
        [$cashier, $room] = $this->scheduledReservation();

        $this->actingAs($cashier)->post(route('bookings.store'), [
            'room_id' => $room->id,
            'guest_name' => 'Adjacent reservation',
            'headcount' => 2,
            'rate_tier' => '3h',
            'arrival_at' => $arrival,
        ])->assertRedirect(route('bookings.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseHas('bookings', ['guest_name' => 'Adjacent reservation', 'arrival_at' => $arrival, 'status' => 'scheduled']);
    }

    #[TestWith(['2026-10-09 06:00:01', '3h'])]
    #[TestWith(['2026-10-09 11:59:59', '3h'])]
    #[TestWith(['2026-10-09 09:00:00', '3h'])]
    #[TestWith(['2026-10-09 08:00:00', '6h'])]
    public function test_reservation_rejects_actual_overlap_without_creating_a_record(string $arrival, string $tier): void
    {
        [$cashier, $room] = $this->scheduledReservation();

        $this->actingAs($cashier)->post(route('bookings.store'), [
            'room_id' => $room->id,
            'guest_name' => 'Overlapping reservation',
            'headcount' => 2,
            'rate_tier' => $tier,
            'arrival_at' => $arrival,
        ])->assertSessionHasErrors(['arrival_at' => 'This room is already reserved for the selected date/time.']);

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseMissing('bookings', ['guest_name' => 'Overlapping reservation']);
    }

    public function test_cancelled_reservation_does_not_block_the_same_slot(): void
    {
        [$cashier, $room] = $this->scheduledReservation();
        Booking::firstOrFail()->update(['status' => 'cancelled']);

        $this->actingAs($cashier)->post(route('bookings.store'), [
            'room_id' => $room->id,
            'guest_name' => 'Replacement reservation',
            'headcount' => 2,
            'rate_tier' => '3h',
            'arrival_at' => '2026-10-09 09:00:00',
        ])->assertRedirect(route('bookings.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', ['guest_name' => 'Replacement reservation', 'arrival_at' => '2026-10-09 09:00:00', 'status' => 'scheduled']);
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_reservation_rejects_a_slot_overlapping_a_guest_already_in_house(): void
    {
        [$cashier, $room] = $this->scheduledReservation();
        $this->travelTo('2026-10-09 10:00:00');
        Booking::firstOrFail()->update(['status' => 'checked_in']);
        $room->update(['status' => 'occupied']);
        Folio::create(['room_id' => $room->id, 'guest_id' => Guest::create(['name' => 'In-house guest'])->id,
            'user_id' => $cashier->id, 'transaction_id' => Folio::generateTransactionId(), 'rate_tier' => '3h',
            'checked_in_at' => '2026-10-09 09:00:00', 'expected_checkout_at' => '2026-10-09 12:00:00', 'status' => 'active']);

        $this->actingAs($cashier)->post(route('bookings.store'), ['room_id' => $room->id, 'guest_name' => 'Overbooked guest',
            'headcount' => 2, 'rate_tier' => '3h', 'arrival_at' => '2026-10-09 10:00:00'])->assertSessionHasErrors('arrival_at');

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_reservation_accepts_a_slot_after_the_current_guest_is_due_to_leave(): void
    {
        [$cashier, $room] = $this->scheduledReservation();
        $this->travelTo('2026-10-09 10:00:00');
        Booking::firstOrFail()->update(['status' => 'checked_in']);
        $room->update(['status' => 'occupied']);
        Folio::create(['room_id' => $room->id, 'guest_id' => Guest::create(['name' => 'In-house guest'])->id,
            'user_id' => $cashier->id, 'transaction_id' => Folio::generateTransactionId(), 'rate_tier' => '3h',
            'checked_in_at' => '2026-10-09 09:00:00', 'expected_checkout_at' => '2026-10-09 12:00:00', 'status' => 'active']);

        $this->actingAs($cashier)->post(route('bookings.store'), ['room_id' => $room->id, 'guest_name' => 'Next guest',
            'headcount' => 2, 'rate_tier' => '3h', 'arrival_at' => '2026-10-09 12:00:00'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_pagination_keeps_the_selected_reservation_status(): void
    {
        [$cashier, $room] = $this->scheduledReservation();
        for ($index = 0; $index < 16; $index++) {
            Booking::create(['room_id' => $room->id, 'user_id' => $cashier->id, 'guest_name' => 'Cancelled reservation '.$index,
                'headcount' => 1, 'rate_tier' => '3h', 'arrival_at' => '2026-10-09 13:00:00', 'departure_at' => '2026-10-09 16:00:00', 'status' => 'cancelled']);
        }
        $response = $this->actingAs($cashier)->get(route('bookings.index', ['status' => 'all']))->assertOk();
        $nextPage = $response->viewData('bookings')->url(2);

        $this->get($nextPage)->assertOk()->assertViewHas('status', 'all')
            ->assertViewHas('bookings', fn ($bookings): bool => $bookings->total() === 17 && $bookings->count() === 2);
    }

    /** @return array{User, Room} */
    private function scheduledReservation(): array
    {
        $this->travelTo('2026-10-08 12:00:00');
        $cashier = User::factory()->create();
        $cashier->givePermissionTo(Permission::findOrCreate('manage_folios', 'web'));
        $room = Room::create(['number' => '1', 'floor' => 1, 'name' => 'Reservation room', 'type' => 'Classic Room', 'status' => 'available']);
        Booking::create([
            'room_id' => $room->id, 'user_id' => $cashier->id, 'guest_name' => 'Existing reservation',
            'headcount' => 2, 'rate_tier' => '3h', 'arrival_at' => '2026-10-09 09:00:00',
            'departure_at' => '2026-10-09 12:00:00', 'status' => 'scheduled',
        ]);

        return [$cashier, $room];
    }
}
