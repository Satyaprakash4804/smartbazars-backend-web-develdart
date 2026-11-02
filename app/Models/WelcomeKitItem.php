<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WelcomeKitItem extends Model
{
    protected $fillable = ['welcome_kit_id', 'product_id', 'item_name', 'quantity', 'sort'];

    public function kit() { return $this->belongsTo(WelcomeKit::class, 'welcome_kit_id'); }
    public function product() { return $this->belongsTo(Product::class); }
}
