<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Coin ledger for vendors (seller_id) and SmartBazars admin (seller_id = null). */
class VendorCoinTransaction extends Model
{
    protected $fillable = ['seller_id', 'type', 'coins', 'balance_after', 'order_item_id', 'offline_order_id', 'coin_transfer_id', 'wholesale_order_id', 'description'];
    protected $casts = ['coins' => 'integer', 'balance_after' => 'integer'];

    public function seller() { return $this->belongsTo(Seller::class); }
}
