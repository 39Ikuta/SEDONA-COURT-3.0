<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'user_id',
        'voucher_number',
        'expense_date',
        'category',
        'description',
        'amount',
        'payment_source',
        'receipt_reference',
        'status',
        'notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generateVoucherNumber(): string
    {
        $dateStr = date('Ymd');
        $count = self::whereDate('created_at', today())->count() + 1;
        return 'EXP-' . $dateStr . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}
