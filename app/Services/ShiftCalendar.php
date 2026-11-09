<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ShiftCalendar
{
    public static function start(string $date): CarbonImmutable
    {
        $cutover = config('sedona.shift_cutover_date');
        $configured = is_string($cutover) && $cutover !== '';
        if ($configured && (! preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $cutover, $parts)
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]))) {
            throw new \InvalidArgumentException('SEDONA_SHIFT_CUTOVER_DATE must be a valid YYYY-MM-DD date.');
        }
        $newSchedule = $configured && $date >= $cutover;

        return CarbonImmutable::parse($date, 'Asia/Manila')->setTime($newSchedule ? 5 : 6, $newSchedule ? 0 : 30);
    }

    public static function date(?CarbonInterface $at = null): string
    {
        $local = CarbonImmutable::instance($at ?? now())->setTimezone('Asia/Manila');

        return ($local->lt(self::start($local->toDateString())) ? $local->subDay() : $local)->toDateString();
    }

    public static function shift(?CarbonInterface $at = null): string
    {
        $at ??= now();

        return $at->lt(self::start(self::date($at))->addHours(12)) ? 'day' : 'night';
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    public static function bounds(string $date, string $shift = 'all'): array
    {
        $start = self::start($date);
        $next = self::start($start->addDay()->toDateString());
        $evening = $start->addHours(12);

        return match ($shift) {
            'day' => [$start, $evening],
            'night' => [$evening, $next],
            default => [$start, $next],
        };
    }

    public static function label(string $date, string $shift = 'all'): string
    {
        [$start, $end] = self::bounds($date, $shift);

        return $start->format('g:i A').'–'.($start->isSameDay($end) ? '' : 'next ').$end->format('g:i A');
    }
}
