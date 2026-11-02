<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SmartBazars v1.4
 *  - vendor referral code + "verification mail sent" marker
 *  - customer can be referred by a vendor (QR) as well as by a worker
 *  - commission source "repurchase" (every order of a referred customer)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            if (!Schema::hasColumn('sellers', 'referral_code')) {
                $table->string('referral_code', 20)->nullable()->unique()->after('is_house_store');
            }
            if (!Schema::hasColumn('sellers', 'verified_mail_sent_at')) {
                $table->timestamp('verified_mail_sent_at')->nullable()->after('referral_code');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'referred_by_seller_id')) {
                $table->unsignedBigInteger('referred_by_seller_id')->nullable()->after('referred_by_worker_id');
                $table->index('referred_by_seller_id');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE network_commissions MODIFY source ENUM('vendor_sale','welcome_kit','repurchase','repurchase_order') NOT NULL");
        }

        // give every existing vendor a referral code
        foreach (DB::table('sellers')->whereNull('referral_code')->pluck('id') as $id) {
            DB::table('sellers')->where('id', $id)->update(['referral_code' => self::code()]);
        }
    }

    protected static function code(): string
    {
        do {
            $c = 'SBV' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while (DB::table('sellers')->where('referral_code', $c)->exists());
        return $c;
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("DELETE FROM network_commissions WHERE source IN ('repurchase','repurchase_order')");
            DB::statement("ALTER TABLE network_commissions MODIFY source ENUM('vendor_sale','welcome_kit') NOT NULL");
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['referred_by_seller_id']);
            $table->dropColumn('referred_by_seller_id');
        });
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn(['referral_code', 'verified_mail_sent_at']);
        });
    }
};
