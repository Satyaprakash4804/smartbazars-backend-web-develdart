<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WelcomeKit extends Model
{
    protected $fillable = ['name', 'description', 'price', 'image', 'is_active'];
    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean'];
    protected $appends = ['image_url'];

    public function items(): HasMany { return $this->hasMany(WelcomeKitItem::class)->orderBy('sort'); }
    public function purchases(): HasMany { return $this->hasMany(WelcomeKitPurchase::class); }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public static function active(): ?self
    {
        return self::where('is_active', true)->with('items.product')->latest('id')->first();
    }
}
