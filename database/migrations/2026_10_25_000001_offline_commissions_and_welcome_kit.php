<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Offline User Card purchases earn the same commissions / vendor coins as online orders,
 * and the Welcome Kit can be sold as a line on an offline order.
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE network_commissions MODIFY source ENUM('vendor_sale','welcome_kit','repurchase','repurchase_order','offline_vendor_sale','offline_repurchase','offline_repurchase_order') NOT NULL");
        }

        Schema::table('offline_order_items', function (Blueprint $t) {
            $t->decimal('admin_commission_amount', 12, 2)->default(0)->after('subtotal');
            $t->boolean('is_welcome_kit')->default(false)->after('cashback_coins');
            $t->unsignedBigInteger('welcome_kit_id')->nullable()->after('is_welcome_kit');
        });

        Schema::table('offline_orders', function (Blueprint $t) {
            $t->decimal('kit_amount', 12, 2)->default(0)->after('subtotal');          // welcome kit price inside `total`
            $t->unsignedBigInteger('welcome_kit_purchase_id')->nullable()->after('coins_credited');
            $t->boolean('commissions_done')->default(false)->after('welcome_kit_purchase_id');
        });

        if (Schema::hasTable('vendor_coin_transactions') && !Schema::hasColumn('vendor_coin_transactions', 'offline_order_id')) {
            Schema::table('vendor_coin_transactions', function (Blueprint $t) {
                $t->unsignedBigInteger('offline_order_id')->nullable()->after('order_item_id');
                $t->index('offline_order_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vendor_coin_transactions', 'offline_order_id')) {
            Schema::table('vendor_coin_transactions', function (Blueprint $t) {
                $t->dropIndex(['offline_order_id']);
                $t->dropColumn('offline_order_id');
            });
        }
        Schema::table('offline_orders', fn(Blueprint $t) => $t->dropColumn(['kit_amount', 'welcome_kit_purchase_id', 'commissions_done']));
        Schema::table('offline_order_items', fn(Blueprint $t) => $t->dropColumn(['admin_commission_amount', 'is_welcome_kit', 'welcome_kit_id']));
        if (DB::getDriverName() === 'mysql') {
            DB::statement("DELETE FROM network_commissions WHERE source IN ('offline_vendor_sale','offline_repurchase','offline_repurchase_order')");
            DB::statement("ALTER TABLE network_commissions MODIFY source ENUM('vendor_sale','welcome_kit','repurchase','repurchase_order') NOT NULL");
        }
    }
};
