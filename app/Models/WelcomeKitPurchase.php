<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WelcomeKitPurchase extends Model
{
    protected $fillable = [
        'user_id', 'welcome_kit_id', 'amount', 'payment_method', 'wallet_transaction_id', 'worker_member_id',
        'referral_code_used', 'status', 'address_id', 'address_snapshot', 'items_snapshot',
        'commission_distributed', 'admin_note',
    ];

    protected $casts = [
        'address_snapshot' => 'array',
        'items_snapshot' => 'array',
        'commission_distributed' => 'boolean',
        'amount' => 'decimal:2',
    ];

    public const STATUSES = ['paid', 'processing', 'dispatched', 'delivered', 'cancelled'];

    public function user() { return $this->belongsTo(User::class); }
    public function kit() { return $this->belongsTo(WelcomeKit::class, 'welcome_kit_id'); }
    public function worker() { return $this->belongsTo(NetworkMember::class, 'worker_member_id'); }
    public function card() { return $this->hasOne(UserCard::class); }
}
