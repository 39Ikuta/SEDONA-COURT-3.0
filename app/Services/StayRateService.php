<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Folio;
use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StayRateService
{
    public function __construct(private AuditService $audit) {}

    public function transfer(Folio $folio, Room $room, Request $request): void
    {
        $this->apply($folio, $room, $folio->rate_tier, (int) $folio->extra_hours, $request, 'folio.room_transferred');
    }

    public function convert(Folio $folio, string $tier, Request $request): void
    {
        $this->apply($folio, $folio->room, $tier, 0, $request, 'folio.package_converted');
    }

    public function assertReservationAvailable(Room $room, CarbonInterface $arrival, CarbonInterface $departure, ?int $bookingId = null): void
    {
        if ($departure->gt($arrival) && Booking::hasConflict($room->id, $arrival->toDateTimeString(), $departure->toDateTimeString(), $bookingId)) {
            throw ValidationException::withMessages(['room_id' => 'This stay would overlap an existing reservation. Choose another room or a shorter stay.']);
        }
    }

    private function apply(Folio $folio, Room $room, string $tier, int $extraHours, Request $request, string $event): void
    {
        $checkout = $folio->checked_in_at->copy()->addHours($room->getTierHours($tier) + $extraHours);
        $this->assertReservationAvailable($room, now(), $checkout, $folio->booking_id);
        $before = $folio->only(['room_id', 'rate_tier', 'room_charge', 'extra_hours', 'expected_checkout_at', 'net_total']);
        $folio->room_id = $room->id;
        $folio->setRelation('room', $room);
        $folio->rate_tier = $tier;
        $folio->room_charge = $room->getRateForTier($tier);
        $folio->extra_hours = $extraHours;
        $folio->expected_checkout_at = $checkout;
        $folio->recalculate();
        $this->audit->record($request, $event, $folio, [
            'before' => $before,
            'after' => $folio->only(array_keys($before)),
            'reason' => $request->input('reason', 'Room transfer'),
            'operation_key' => $request->input('operation_key'),
        ]);
    }
}
