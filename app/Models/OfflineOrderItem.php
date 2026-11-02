<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineOrderItem extends Model
{
    protected $fillable = [
        'offline_order_id', 'store_product_variant_id', 'product_id', 'title', 'sku',
        'quantity', 'unit_price', 'subtotal', 'admin_commission_amount', 'cashback_coins', 'is_welcome_kit', 'welcome_kit_id',
    ];
    protected $casts = ['unit_price' => 'decimal:2', 'subtotal' => 'decimal:2', 'admin_commission_amount' => 'decimal:2', 'is_welcome_kit' => 'boolean'];

    public function order(): BelongsTo { return $this->belongsTo(OfflineOrder::class, 'offline_order_id'); }
}
