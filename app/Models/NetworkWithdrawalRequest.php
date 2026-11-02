<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkWithdrawalRequest extends Model
{
    protected $fillable = [
        'network_member_id', 'amount', 'payout_details', 'status', 'admin_note', 'transaction_reference', 'processed_at',
    ];

    protected $casts = ['payout_details' => 'array', 'amount' => 'decimal:2', 'processed_at' => 'datetime'];

    public function member(): BelongsTo { return $this->belongsTo(NetworkMember::class, 'network_member_id')->withTrashed(); }
}
