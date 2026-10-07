<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folio extends Model
{
    protected $fillable = [
        'room_id', 'guest_id', 'user_id', 'booking_id',
        'transaction_id', 'rate_tier',
        'checked_in_at', 'expected_checkout_at', 'checked_out_at',
        'status',
        'extra_persons', 'extra_bedding', 'extra_towels', 'extra_hours',
        'pos_items',
        'room_charge', 'surcharge_total', 'pos_total',
        'security_deposit', 'deposit_payment_method', 'deposit_status',
        'deposit_collected_at', 'deposit_refunded_at', 'deposit_refunded_amount',
        'gross_total', 'discount_amount', 'discount_type', 'discount_id_ref', 'senior_pwd_discount', 'net_total',
        'payment_method', 'cash_tendered', 'gcash_amount',
        'gcash_reference', 'change_due',
        'force_checkout', 'force_reason', 'force_by',
    ];

    protected $casts = [
        'pos_items'                => 'array',
        'checked_in_at'            => 'datetime',
        'expected_checkout_at'     => 'datetime',
        'checked_out_at'           => 'datetime',
        'deposit_collected_at'     => 'datetime',
        'deposit_refunded_at'      => 'datetime',
        'senior_pwd_discount'      => 'boolean',
        'force_checkout'           => 'boolean',
        'room_charge'              => 'decimal:2',
        'surcharge_total'          => 'decimal:2',
        'security_deposit'         => 'decimal:2',
        'deposit_refunded_amount'  => 'decimal:2',
        'pos_total'                => 'decimal:2',
        'gross_total'              => 'decimal:2',
        'discount_amount'          => 'decimal:2',
        'net_total'                => 'decimal:2',
        'cash_tendered'            => 'decimal:2',
        'gcash_amount'             => 'decimal:2',
        'change_due'               => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function forcedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'force_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'active' && now()->gt($this->expected_checkout_at);
    }

    public function remainingSeconds(): int
    {
        if (!$this->isActive()) return 0;
        return max(0, now()->diffInSeconds($this->expected_checkout_at, false));
    }

    /**
     * Recalculate all totals and persist them.
     * Call after any surcharge or POS addition.
     */
    public function recalculate(): void
    {
        $posTotal = collect($this->pos_items ?? [])->sum(fn($i) => $i['subtotal'] ?? 0);

        $surchargeRate  = config('sedona.extra_person_rate', 150);
        $beddingRate    = config('sedona.extra_bedding_rate', 100);
        $towelRate      = config('sedona.extra_towel_rate', 50);
        $extraHourRate  = config('sedona.extra_hour_rate', 100);

        $surchargeTotal =
            ($this->extra_persons  * $surchargeRate) +
            ($this->extra_bedding  * $beddingRate)   +
            ($this->extra_towels   * $towelRate)      +
            ($this->extra_hours    * $extraHourRate);

        $gross = $this->room_charge + $surchargeTotal + $posTotal;

        $discount = $this->senior_pwd_discount
            ? round($gross * 0.20, 2)
            : 0;

        $this->surcharge_total = $surchargeTotal;
        $this->pos_total       = $posTotal;
        $this->gross_total     = $gross;
        $this->discount_amount = $discount;
        $this->net_total       = $gross - $discount;
        $this->save();
    }

    /**
     * Generate a unique SCTI-###### transaction ID.
     */
    public static function generateTransactionId(): string
    {
        do {
            $id = 'SCTI-' . str_pad(random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('transaction_id', $id)->exists());

        return $id;
    }
}
