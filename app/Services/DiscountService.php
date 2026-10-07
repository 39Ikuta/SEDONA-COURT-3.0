<?php

namespace App\Services;

class DiscountService
{
    /**
     * Authoritative fixed discount rate table (in PHP Pesos).
     * [discount_type][normalized_room_tier][normalized_stay_duration] => float amount
     */
    public const RATES = [
        'DC' => [
            'CLASSIC' => [
                '3h'  => 40.00,
                '6h'  => 0.00,
                '12h' => 55.00,
                '24h' => 95.00,
            ],
            'PREMIUM' => [
                '3h'  => 50.00,
                '6h'  => 0.00,
                '12h' => 60.00,
                '24h' => 105.00,
            ],
            'VIP' => [
                '3h'  => 65.00,
                '6h'  => 0.00,
                '12h' => 70.00,
                '24h' => 115.00,
            ],
        ],
        'SENIOR' => [
            'CLASSIC' => [
                '3h'  => 79.00,
                '6h'  => 158.00,
                '12h' => 195.00,
                '24h' => 340.00,
            ],
            'PREMIUM' => [
                '3h'  => 99.00,
                '6h'  => 178.00,
                '12h' => 215.00,
                '24h' => 375.00,
            ],
            'VIP' => [
                '3h'  => 139.00,
                '6h'  => 218.00,
                '12h' => 255.00,
                '24h' => 460.00,
            ],
        ],
    ];

    /**
     * Normalize discount type string to canonical type: 'SENIOR', 'PWD', or 'DC'.
     */
    public static function normalizeDiscountType(?string $val): ?string
    {
        if (!$val) return null;
        $upper = strtoupper(trim($val));

        if (in_array($upper, ['SENIOR', 'SENIOR CITIZEN', 'S.', 'OSCA'])) {
            return 'SENIOR';
        }
        if (in_array($upper, ['PWD', 'PERSON WITH DISABILITY'])) {
            return 'PWD';
        }
        if (in_array($upper, ['DC', 'DISCOUNT CARD', 'DISCOUNT_CARD', 'LOYALTY'])) {
            return 'DC';
        }

        return null;
    }

    /**
     * Normalize Room Type / Name to canonical Tier: 'CLASSIC', 'PREMIUM', or 'VIP'.
     */
    public static function normalizeRoomTier(?string $roomType): string
    {
        if (!$roomType) return 'CLASSIC';
        $upper = strtoupper(trim($roomType));

        if (str_contains($upper, 'VIP') || str_contains($upper, 'SUITE')) {
            return 'VIP';
        }
        if (str_contains($upper, 'PREMIUM') || str_contains($upper, 'DELUXE')) {
            return 'PREMIUM';
        }

        return 'CLASSIC';
    }

    /**
     * Normalize duration string to canonical tier: '3h', '6h', '12h', '24h'.
     */
    public static function normalizeDuration(?string $duration): string
    {
        if (!$duration) return '3h';
        $d = strtolower(trim($duration));

        if (str_starts_with($d, '3')) return '3h';
        if (str_starts_with($d, '6')) return '6h';
        if (str_starts_with($d, '12')) return '12h';
        if (str_starts_with($d, '24')) return '24h';

        return '3h';
    }

    /**
     * Lookup authoritative fixed discount amount in Pesos.
     *
     * @param string $discountType 'SENIOR', 'PWD', 'DC'
     * @param string $roomType 'VIP Suite Room', 'Premium Room', 'Classic Room'
     * @param string $duration '3h', '6h', '12h', '24h'
     * @return float Fixed discount amount
     */
    public static function getDiscountAmount(string $discountType, string $roomType, string $duration): float
    {
        $normType = self::normalizeDiscountType($discountType);
        if (!$normType) return 0.00;

        // PWD uses same statutory schedule as Senior Citizen
        $lookupCategory = ($normType === 'PWD') ? 'SENIOR' : $normType;
        $normTier = self::normalizeRoomTier($roomType);
        $normDur = self::normalizeDuration($duration);

        return self::RATES[$lookupCategory][$normTier][$normDur] ?? 0.00;
    }

    /**
     * Get discount options matrix for a room and tier for modal preview.
     */
    public static function getOptionsForRoom(string $roomType, string $duration): array
    {
        $normTier = self::normalizeRoomTier($roomType);
        $normDur = self::normalizeDuration($duration);

        return [
            'SENIOR' => self::RATES['SENIOR'][$normTier][$normDur] ?? 0.00,
            'PWD'    => self::RATES['SENIOR'][$normTier][$normDur] ?? 0.00,
            'DC'     => self::RATES['DC'][$normTier][$normDur] ?? 0.00,
        ];
    }
}
