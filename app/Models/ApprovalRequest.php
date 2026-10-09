<?php

namespace App\Models;

use Database\Factories\ApprovalRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory;

    protected $fillable = ['kind', 'source_type', 'source_id', 'requested_by', 'reviewed_by', 'executed_by', 'payload', 'source_hash', 'status', 'reason', 'evidence_reference', 'review_note', 'reviewed_at', 'executed_at'];

    protected $casts = ['payload' => 'array', 'reviewed_at' => 'datetime', 'executed_at' => 'datetime'];
}
