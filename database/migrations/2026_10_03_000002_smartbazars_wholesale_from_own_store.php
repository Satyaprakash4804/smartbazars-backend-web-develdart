<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** v1.5.1: bulk products can be SmartBazars own-store products (stock shared with the own store). */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('wholesale_products', function (Blueprint $table) {
            if (!Schema::hasColumn('wholesale_products', 'store_product_variant_id')) {
                $table->unsignedBigInteger('store_product_variant_id')->nullable()->after('product_id');
                $table->unique('store_product_variant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wholesale_products', function (Blueprint $table) {
            $table->dropUnique(['store_product_variant_id']);
            $table->dropColumn('store_product_variant_id');
        });
    }
};
