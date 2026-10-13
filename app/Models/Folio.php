<?php

namespace App\Models;

use App\Services\BillingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
        'discount_schedule', 'discount_override_amount',
        'payment_method', 'cash_tendered', 'gcash_amount',
        'gcash_reference', 'change_due',
        'force_requested_by', 'force_checkout', 'force_reason', 'force_by', 'checked_out_by', 'overtime_hours', 'loss_slip_reference',
    ];

    protected $casts = [
        'pos_items' => 'array',
        'discount_schedule' => 'array',
        'discount_override_amount' => 'decimal:2',
        'checked_in_at' => 'datetime',
        'expected_checkout_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'deposit_collected_at' => 'datetime',
        'deposit_refunded_at' => 'datetime',
        'senior_pwd_discount' => 'boolean',
        'force_checkout' => 'boolean',
        'room_charge' => 'decimal:2',
        'surcharge_total' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'deposit_refunded_amount' => 'decimal:2',
        'pos_total' => 'decimal:2',
        'gross_total' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'net_total' => 'decimal:2',
        'cash_tendered' => 'decimal:2',
        'gcash_amount' => 'decimal:2',
        'change_due' => 'decimal:2',
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

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function paymentEntries(): HasMany
    {
        return $this->hasMany(PaymentEntry::class);
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
        if (! $this->isActive()) {
            return 0;
        }

        return max(0, now()->diffInSeconds($this->expected_checkout_at, false));
    }

    /**
     * Recalculate all totals and persist them.
     * Call after any surcharge or POS addition.
     */
    public function recalculate(): void
    {
        if (! $this->isActive()) {
            return;
        }
        $this->fill(app(BillingService::class)->calculate($this, includeOvertime: false)['attributes']);
        $this->save();
    }

    /**
     * Generate a unique SCTI-###### transaction ID.
     */
    public static function generateTransactionId(): string
    {
        return DB::transaction(function (): string {
            $sequence = DB::table('document_sequences')->where('name', 'scti')->lockForUpdate()->first();
            if (! $sequence) {
                throw new \RuntimeException('The SCTI sequence is missing. Run database migrations.');
            }
            $number = (int) $sequence->last_number;
            do {
                $number++;
                $id = 'SCTI-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
            } while (self::where('transaction_id', $id)->exists());
            DB::table('document_sequences')->where('name', 'scti')->update(['last_number' => $number]);

            return $id;
        }, 3);
    }
}
