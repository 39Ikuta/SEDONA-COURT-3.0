<?php

namespace Tests\Feature;

use App\Models\Booking;
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
