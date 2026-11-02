<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * SmartBazars: loads the bundled PDF (dompdf) and QR libraries from packages/smartbazars-libs
 * so no extra `composer require` is needed.
 */
class SmartBazarsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $base = base_path('packages/smartbazars-libs');
        $map = [
            'Dompdf\\' => [$base . '/dompdf/src/', $base . '/dompdf/lib/'],
            'FontLib\\' => [$base . '/php-font-lib/src/FontLib/'],
            'Svg\\' => [$base . '/php-svg-lib/src/Svg/'],
            'Masterminds\\' => [$base . '/html5/src/'],
            'Sabberworm\\CSS\\' => [$base . '/css-parser/src/'],
        ];
        spl_autoload_register(function (string $class) use ($map, $base) {
            if ($class === 'QRCode' || str_starts_with($class, 'QR')) {
                $f = $base . '/qrcode/qrcode.php';
                if (is_file($f) && !class_exists('QRCode', false)) {
                    require_once $f;
                }
                return;
            }
            foreach ($map as $prefix => $dirs) {
                if (!str_starts_with($class, $prefix)) continue;
                $rel = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                foreach ($dirs as $dir) {
                    if (is_file($dir . $rel)) {
                        require_once $dir . $rel;
                        return;
                    }
                }
            }
        });
    }

    public function boot(): void
    {
        // SmartBazars own store: its stores and products are approved automatically, so customers can
        // buy them like any vendor's products (admin manages them in the vendor panel via "Own Store Products").
        $isHouse = function ($sellerId): bool {
            static $cache = [];
            if (!$sellerId) return false;
            return $cache[$sellerId] ??= (bool)\App\Models\Seller::whereKey($sellerId)->value('is_house_store');
        };
        \App\Models\Store::saving(function (\App\Models\Store $store) use ($isHouse) {
            if ($isHouse($store->seller_id)) {
                $store->verification_status = 'approved';
                if (!$store->exists && empty($store->getAttributes()['visibility_status'] ?? null)) {
                    $store->visibility_status = 'visible';
                }
            }
        });
        // own-store product variants are offered to vendors for bulk purchase automatically
        \App\Models\StoreProductVariant::created(function (\App\Models\StoreProductVariant $spv) use ($isHouse) {
            $sellerId = \App\Models\Store::whereKey($spv->store_id)->value('seller_id');
            if ($isHouse($sellerId) && \Illuminate\Support\Facades\Schema::hasColumn('wholesale_products', 'store_product_variant_id')) {
                \Illuminate\Support\Facades\DB::afterCommit(fn() => app(\App\Services\SmartBazars\WholesaleCatalog::class)->ensureFor($spv->fresh(['productVariant.product'])));
            }
        });
        \App\Models\Product::saving(function (\App\Models\Product $product) use ($isHouse) {
            if ($isHouse($product->seller_id)) {
                $product->verification_status = 'approved';
            }
        });

        // Customer signs up with a SmartBazars worker/vendor code (app/web send referral_code, friends_code or ref)
        \App\Models\User::created(function (\App\Models\User $user) {
            try {
                $panel = $user->access_panel;
                $panel = is_object($panel) && property_exists($panel, 'value') ? $panel->value : (string)$panel;
                if (app()->runningInConsole() || $panel !== 'web') return;
                $code = request()->input('referral_code') ?? request()->input('friends_code') ?? request()->input('ref');
                if ($code && preg_match('/^SB[VW][A-Z0-9]{4,}$/i', trim($code))) {
                    app(\App\Services\SmartBazars\ReferralService::class)->linkCustomer($user, $code);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('SmartBazars referral link on signup failed: ' . $e->getMessage());
            }
        });

        // Vendor verified by admin -> email Authority Letter + QR (once)
        \App\Models\Seller::saved(function (\App\Models\Seller $seller) {
            if (app()->runningInConsole()) {
                return; // seeders/imports: use Admin → Vendor Letters & QR to send
            }
            $status = $seller->verification_status;
            $status = is_object($status) && property_exists($status, 'value') ? $status->value : (string)$status;
            if ($status === 'approved' && !$seller->verified_mail_sent_at) {
                \Illuminate\Support\Facades\DB::afterCommit(function () use ($seller) {
                    app(\App\Services\SmartBazars\DocumentService::class)->sendVendorWelcome($seller->fresh());
                });
            }
        });
    }
}
