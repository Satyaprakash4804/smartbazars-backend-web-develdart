<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkWalletRecharge extends Model
{
    protected $fillable = ['network_member_id', 'amount', 'method', 'gateway', 'gateway_order_id', 'gateway_payment_id', 'reference', 'proof_file', 'note', 'status', 'admin_note', 'reviewed_by', 'reviewed_at'];
    protected $casts = ['amount' => 'decimal:2', 'reviewed_at' => 'datetime'];

    public function member(): BelongsTo { return $this->belongsTo(NetworkMember::class, 'network_member_id'); }
}
