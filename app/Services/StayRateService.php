<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\Room;
use Illuminate\Http\Request;

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

    private function apply(Folio $folio, Room $room, string $tier, int $extraHours, Request $request, string $event): void
    {
        $before = $folio->only(['room_id', 'rate_tier', 'room_charge', 'extra_hours', 'expected_checkout_at', 'net_total']);
        $folio->room_id = $room->id;
        $folio->setRelation('room', $room);
        $folio->rate_tier = $tier;
        $folio->room_charge = $room->getRateForTier($tier);
        $folio->extra_hours = $extraHours;
        $folio->expected_checkout_at = $folio->checked_in_at->copy()->addHours($room->getTierHours($tier) + $extraHours);
        $folio->recalculate();
        $this->audit->record($request, $event, $folio, [
            'before' => $before,
            'after' => $folio->only(array_keys($before)),
            'reason' => $request->input('reason', 'Room transfer'),
            'operation_key' => $request->input('operation_key'),
        ]);
    }
}
