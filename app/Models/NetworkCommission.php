<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkCommission extends Model
{
    protected $fillable = [
        'network_member_id', 'seller_id', 'source', 'source_id', 'role', 'base_amount', 'rate', 'amount',
        'status', 'description',
    ];

    protected $casts = ['amount' => 'decimal:2', 'base_amount' => 'decimal:2', 'rate' => 'decimal:4'];

    public function member(): BelongsTo { return $this->belongsTo(NetworkMember::class, 'network_member_id')->withTrashed(); }
    public function seller(): BelongsTo { return $this->belongsTo(Seller::class); }
}
