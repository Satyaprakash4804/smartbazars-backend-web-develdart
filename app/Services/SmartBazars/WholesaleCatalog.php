<?php

namespace App\Services\SmartBazars;

use App\Models\Seller;
use App\Models\StoreProductVariant;
use App\Models\WholesaleProduct;

/**
 * SmartBazars own-store products are offered to vendors for bulk purchase automatically.
 * Admin can then change the vendor price, min/max quantity, coins % or hide an item.
 */
class WholesaleCatalog
{
    public function houseSellerIds(): array
    {
        return Seller::where('is_house_store', true)->pluck('id')->all();
    }

    /** Create bulk entries for every own-store product variant that has none yet. Returns how many were added. */
    public function syncOwnStore(): int
    {
        $ids = $this->houseSellerIds();
        if (!$ids) return 0;
        $added = 0;
        StoreProductVariant::with(['productVariant.product', 'store'])
            ->whereHas('store', fn($q) => $q->whereIn('seller_id', $ids))
            ->whereNotIn('id', WholesaleProduct::whereNotNull('store_product_variant_id')->pluck('store_product_variant_id'))
            ->chunkById(200, function ($rows) use (&$added) {
                foreach ($rows as $spv) {
                    if ($this->ensureFor($spv)) $added++;
                }
            });
        return $added;
    }

    /** Make sure one store variant of the own store has a bulk entry (never overwrites admin edits). */
    public function ensureFor(StoreProductVariant $spv): bool
    {
        if (WholesaleProduct::where('store_product_variant_id', $spv->id)->exists()) return false;
        $product = $spv->productVariant?->product;
        if (!$product) return false;
        $variantTitle = $spv->productVariant->title ?? '';
        $title = $product->title . ($variantTitle && $variantTitle !== $product->title ? ' — ' . $variantTitle : '');
        $price = (float)$spv->price;
        $special = (float)$spv->special_price;
        WholesaleProduct::create([
            'product_id' => $product->id,
            'store_product_variant_id' => $spv->id,
            'title' => mb_substr($title, 0, 190),
            'unit' => null,
            'description' => $product->short_description,
            'mrp' => $price,
            'vendor_price' => $special > 0 && $special < $price ? $special : $price,
            'min_qty' => 1,
            'max_qty' => null,
            'stock' => null,
            'max_coin_percent' => 100,
            'is_active' => true,
        ]);
        return true;
    }
}
