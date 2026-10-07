<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Room extends Model
{
    protected $fillable = [
        'number', 'floor', 'name', 'type', 'status',
        'is_staff_quarters',
        'base_rate_3h', 'base_rate_6h', 'base_rate_12h',
        'base_rate_24h', 'base_rate_promo',
        'notes',
    ];

    protected $casts = [
        'is_staff_quarters' => 'boolean',
        'base_rate_3h'      => 'decimal:2',
        'base_rate_6h'      => 'decimal:2',
        'base_rate_12h'     => 'decimal:2',
        'base_rate_24h'     => 'decimal:2',
        'base_rate_promo'   => 'decimal:2',
    ];

    // ── Relationships ───────────────────────────────────────────────

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class);
    }

    public function activeFolio(): HasOne
    {
        return $this->hasOne(Folio::class)
                    ->where('status', 'active')
                    ->latestOfMany();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isOccupied(): bool
    {
        return $this->status === 'occupied';
    }

    public function getRateForTier(string $tier): float
    {
        return match ($tier) {
            '3h'    => (float) $this->base_rate_3h,
            '6h'    => (float) $this->base_rate_6h,
            '12h'   => (float) $this->base_rate_12h,
            '24h'   => (float) $this->base_rate_24h,
            'promo' => (float) $this->base_rate_promo,
            default => 0.0,
        };
    }

    public function getTierHours(string $tier): int
    {
        return match ($tier) {
            '3h'    => 3,
            '6h'    => 6,
            '12h'   => 12,
            '24h'   => 24,
            'promo' => 12,
            default => 0,
        };
    }

    // ── Scopes ──────────────────────────────────────────────────────

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeByFloor($query, int $floor)
    {
        return $query->where('floor', $floor);
    }

    /**
     * Retrieve or auto-create an active Staff Folio for Employee Quarters orders.
     */
    public function getOrCreateStaffFolio(): Folio
    {
        $folio = $this->activeFolio;
        if ($folio) {
            return $folio;
        }

        $guest = Guest::firstOrCreate(
            ['contact' => 'STAFF-QUARTERS-12'],
            ['name' => 'Permanent Employee Quarters', 'headcount' => 2, 'is_senior' => false, 'is_pwd' => false]
        );

        return Folio::create([
            'room_id' => $this->id,
            'guest_id' => $guest->id,
            'user_id' => \Illuminate\Support\Facades\Auth::id() ?? 1,
            'transaction_id' => 'STAFF-12-' . strtoupper(substr(uniqid(), -4)),
            'rate_tier' => 'promo',
            'checked_in_at' => now(),
            'expected_checkout_at' => now()->addYear(),
            'status' => 'active',
            'room_charge' => 0.00,
            'surcharge_total' => 0.00,
            'pos_total' => 0.00,
            'gross_total' => 0.00,
            'net_total' => 0.00,
            'pos_items' => [],
        ]);
    }
}
