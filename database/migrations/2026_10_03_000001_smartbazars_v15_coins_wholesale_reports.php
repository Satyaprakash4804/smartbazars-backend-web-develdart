<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SmartBazars v1.5
 *  - vendor coin wallet: coins customers spend on a vendor's products go to that vendor
 *    (or to SmartBazars admin for own-store products); vendors transfer coins to admin for cash
 *  - wholesale (bulk) products admin sells to vendors + vendor bulk orders
 *  - daily reports of agents and workers
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            if (!Schema::hasColumn('sellers', 'coin_balance')) {
                $table->unsignedInteger('coin_balance')->default(0)->after('referral_code');
            }
        });

        // seller_id NULL = SmartBazars admin (own store / received transfers)
        if (!Schema::hasTable('vendor_coin_transactions')) Schema::create('vendor_coin_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->enum('type', ['earn', 'reverse', 'transfer_out', 'transfer_in', 'transfer_refund', 'wholesale_payment', 'adjust']);
            $table->integer('coins');
            $table->integer('balance_after')->default(0);
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('coin_transfer_id')->nullable();
            $table->unsignedBigInteger('wholesale_order_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->index(['seller_id', 'created_at']);
            $table->unique(['type', 'order_item_id'], 'vct_type_item_unique');
        });

        if (!Schema::hasTable('coin_transfers')) Schema::create('coin_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->unsignedInteger('coins');
            $table->decimal('rupee_value', 12, 2);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('vendor_note')->nullable();
            $table->string('admin_note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        if (!Schema::hasTable('wholesale_products')) Schema::create('wholesale_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('title');
            $table->string('unit', 50)->nullable();               // e.g. "Carton of 24", "25 kg bag"
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('mrp', 12, 2)->default(0);
            $table->decimal('vendor_price', 12, 2);               // price for vendors
            $table->unsignedInteger('min_qty')->default(1);
            $table->unsignedInteger('max_qty')->nullable();        // per order, null = no limit
            $table->unsignedInteger('stock')->nullable();          // null = unlimited
            $table->unsignedInteger('max_coin_percent')->default(100); // % of line that coins may pay
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        if (!Schema::hasTable('wholesale_orders')) Schema::create('wholesale_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->decimal('subtotal', 12, 2);
            $table->unsignedInteger('coins_used')->default(0);
            $table->decimal('coin_value', 12, 2)->default(0);
            $table->decimal('amount_payable', 12, 2);
            $table->enum('payment_method', ['settlement', 'offline'])->default('settlement');
            $table->enum('status', ['placed', 'confirmed', 'dispatched', 'delivered', 'cancelled'])->default('placed');
            $table->string('vendor_note')->nullable();
            $table->string('admin_note')->nullable();
            $table->timestamps();
        });

        if (!Schema::hasTable('wholesale_order_items')) Schema::create('wholesale_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wholesale_order_id')->constrained('wholesale_orders')->cascadeOnDelete();
            $table->foreignId('wholesale_product_id')->nullable()->constrained('wholesale_products')->nullOnDelete();
            $table->string('title');
            $table->string('unit', 50)->nullable();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        // India: make sure the system currency is ₹ / INR (apps showed "$" when it was left at the default)
        $row = \Illuminate\Support\Facades\DB::table('settings')->where('variable', 'system')->first();
        if ($row) {
            $v = json_decode($row->value, true);
        }
        if (!empty($row) && is_array($v)) {
            if (empty($v['currencySymbol']) || $v['currencySymbol'] === '$') $v['currencySymbol'] = '₹';
            if (empty($v['currency']) || strtoupper($v['currency']) === 'USD') $v['currency'] = 'INR';
            \Illuminate\Support\Facades\DB::table('settings')->where('variable', 'system')->update(['value' => json_encode($v, JSON_UNESCAPED_UNICODE)]);
        }

        if (!Schema::hasTable('partner_daily_reports')) Schema::create('partner_daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_member_id')->constrained('network_members')->cascadeOnDelete();
            $table->date('report_date');
            $table->text('content');
            $table->unsignedInteger('customers_met')->nullable();
            $table->unsignedInteger('kits_sold')->nullable();
            $table->timestamps();
            $table->unique(['network_member_id', 'report_date']);
            $table->index('report_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_daily_reports');
        Schema::dropIfExists('wholesale_order_items');
        Schema::dropIfExists('wholesale_orders');
        Schema::dropIfExists('wholesale_products');
        Schema::dropIfExists('coin_transfers');
        Schema::dropIfExists('vendor_coin_transactions');
        Schema::table('sellers', fn(Blueprint $t) => $t->dropColumn('coin_balance'));
    }
};
