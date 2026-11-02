<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinTransfer extends Model
{
    protected $fillable = ['seller_id', 'coins', 'rupee_value', 'status', 'vendor_note', 'admin_note', 'processed_at'];
    protected $casts = ['processed_at' => 'datetime', 'rupee_value' => 'decimal:2'];

    public function seller() { return $this->belongsTo(Seller::class); }
}
