<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WholesaleOrderItem extends Model
{
    protected $fillable = ['wholesale_order_id', 'wholesale_product_id', 'title', 'unit', 'price', 'quantity', 'line_total'];
    protected $casts = ['price' => 'decimal:2', 'line_total' => 'decimal:2'];

    public function product() { return $this->belongsTo(WholesaleProduct::class, 'wholesale_product_id'); }
}
