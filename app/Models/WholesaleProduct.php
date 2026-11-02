<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A product SmartBazars sells to vendors in bulk at a vendor price. */
class WholesaleProduct extends Model
{
    protected $fillable = ['product_id', 'store_product_variant_id', 'title', 'unit', 'description', 'image', 'mrp', 'vendor_price', 'min_qty', 'max_qty', 'stock', 'max_coin_percent', 'is_active', 'sort'];
    protected $casts = ['mrp' => 'decimal:2', 'vendor_price' => 'decimal:2', 'is_active' => 'boolean'];
    protected $appends = ['image_url', 'is_smartbazars', 'available_stock'];

    public function product() { return $this->belongsTo(Product::class); }

    /** Linked SmartBazars own-store variant (stock is shared with the own store). */
    public function storeVariant() { return $this->belongsTo(StoreProductVariant::class, 'store_product_variant_id'); }

    public function getIsSmartbazarsAttribute(): bool
    {
        return (bool)$this->store_product_variant_id;
    }

    /** null = unlimited */
    public function getAvailableStockAttribute(): ?int
    {
        if ($this->store_product_variant_id) {
            return (int)($this->storeVariant?->stock ?? 0);
        }
        return $this->stock;
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image) return asset('storage/' . $this->image);
        return $this->product?->main_image;
    }
}
