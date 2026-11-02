<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinTransaction extends Model
{
    protected $fillable = ['user_id', 'type', 'coins', 'balance_after', 'order_id', 'order_item_id', 'offline_order_id', 'description'];
    protected $casts = ['coins' => 'integer', 'balance_after' => 'integer'];

    public function user() { return $this->belongsTo(User::class); }
}
