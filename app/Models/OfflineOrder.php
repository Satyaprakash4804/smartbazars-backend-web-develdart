<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfflineOrder extends Model
{
    protected $fillable = [
        'order_no', 'user_id', 'user_card_id', 'seller_id', 'channel', 'network_member_id', 'placed_by_user_id',
        'status', 'payment_mode', 'subtotal', 'kit_amount', 'delivery_charge', 'coins_used', 'coin_discount', 'wallet_paid',
        'cash_paid', 'total', 'coins_earned', 'coins_credited', 'welcome_kit_purchase_id', 'commissions_done', 'note', 'cancel_reason', 'handed_over_at', 'handed_over_by',
    ];
    protected $casts = [
        'subtotal' => 'decimal:2', 'delivery_charge' => 'decimal:2', 'coin_discount' => 'decimal:2',
        'wallet_paid' => 'decimal:2', 'cash_paid' => 'decimal:2', 'total' => 'decimal:2',
        'coins_credited' => 'boolean', 'commissions_done' => 'boolean', 'kit_amount' => 'decimal:2', 'handed_over_at' => 'datetime',
    ];

    public function items(): HasMany { return $this->hasMany(OfflineOrderItem::class); }
    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'user_id')->withTrashed(); }
    public function card(): BelongsTo { return $this->belongsTo(UserCard::class, 'user_card_id'); }
    public function seller(): BelongsTo { return $this->belongsTo(Seller::class); }
    public function member(): BelongsTo { return $this->belongsTo(NetworkMember::class, 'network_member_id'); }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending_pickup' => 'Ready for pickup',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }
}
