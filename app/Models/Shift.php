<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
        'opened_by', 'closed_by', 'shift_type', 'shift_date',
        'opened_at', 'closed_at', 'is_frozen',
        'room_revenue', 'kitchen_revenue', 'gross_revenue',
        'denomination_count', 'cash_total', 'gcash_total',
        'handoff_notes',
    ];

    protected $casts = [
        'shift_date'         => 'date',
        'opened_at'          => 'datetime',
        'closed_at'          => 'datetime',
        'is_frozen'          => 'boolean',
        'denomination_count' => 'array',
        'room_revenue'       => 'decimal:2',
        'kitchen_revenue'    => 'decimal:2',
        'gross_revenue'      => 'decimal:2',
        'cash_total'         => 'decimal:2',
        'gcash_total'        => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ShiftTask::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return is_null($this->closed_at);
    }

    /**
     * Determine shift type (day/night) from a given datetime.
     */
    public static function typeForTime(\Carbon\Carbon $time): string
    {
        $hour = (int) $time->format('G');
        return ($hour >= 6 && $hour < 18) ? 'day' : 'night';
    }
}
