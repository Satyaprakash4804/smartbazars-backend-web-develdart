<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BJO / BSO get two balances:
 *   wallet_balance        = EARNINGS (commissions, withdrawable)            [existing column]
 *   order_wallet_balance  = ORDER WALLET (recharged online / by request, or topped up from earnings) — pays customer orders
 * Online recharge through Razorpay is stored in network_wallet_recharges with the gateway ids.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('network_members', function (Blueprint $t) {
            $t->decimal('order_wallet_balance', 12, 2)->default(0)->after('wallet_balance');
        });

        Schema::table('network_wallet_ledger', function (Blueprint $t) {
            $t->string('wallet', 10)->default('earnings')->after('type');      // earnings | order
            $t->index(['network_member_id', 'wallet']);
        });
        DB::table('network_wallet_ledger')->whereIn('reference_type', ['offline_order', 'recharge'])->update(['wallet' => 'order']);

        Schema::table('network_wallet_recharges', function (Blueprint $t) {
            $t->string('gateway', 20)->nullable()->after('method');            // razorpay
            $t->string('gateway_order_id', 64)->nullable()->after('gateway');
            $t->string('gateway_payment_id', 64)->nullable()->unique()->after('gateway_order_id');
            $t->index('gateway_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('network_wallet_recharges', function (Blueprint $t) {
            $t->dropIndex(['gateway_order_id']);
            $t->dropUnique(['gateway_payment_id']);
            $t->dropColumn(['gateway', 'gateway_order_id', 'gateway_payment_id']);
        });
        Schema::table('network_wallet_ledger', function (Blueprint $t) {
            $t->dropIndex(['network_member_id', 'wallet']);
            $t->dropColumn('wallet');
        });
        Schema::table('network_members', fn(Blueprint $t) => $t->dropColumn('order_wallet_balance'));
    }
};
