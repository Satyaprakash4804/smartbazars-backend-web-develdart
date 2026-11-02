<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WholesaleOrder extends Model
{
    public const STATUSES = ['placed', 'confirmed', 'dispatched', 'delivered', 'cancelled'];

    protected $fillable = ['seller_id', 'subtotal', 'coins_used', 'coin_value', 'amount_payable', 'payment_method', 'status', 'vendor_note', 'admin_note'];
    protected $casts = ['subtotal' => 'decimal:2', 'coin_value' => 'decimal:2', 'amount_payable' => 'decimal:2'];

    public function seller() { return $this->belongsTo(Seller::class); }
    public function items() { return $this->hasMany(WholesaleOrderItem::class); }
}
