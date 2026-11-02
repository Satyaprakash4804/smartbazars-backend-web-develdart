<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SmartBazars POS / offline sales.
 *  - offline_customer_profiles : extra KYC-style details for a customer whose User Card was made offline
 *  - offline_orders / items    : every counter sale or BJO-placed order (no delivery charge)
 *  - network_wallet_ledger     : debit/credit history for the BSO/BJO wallet (so paying from wallet is auditable)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('offline_customer_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->foreignId('user_card_id')->nullable()->constrained('user_cards')->nullOnDelete();
            $t->string('created_by_type', 12)->default('seller');          // seller | worker | admin
            $t->unsignedBigInteger('created_by_id')->nullable();           // seller id | network_member id | user id
            $t->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();           // vendor the card belongs to
            $t->foreignId('network_member_id')->nullable()->constrained('network_members')->nullOnDelete(); // BJO who created it
            $t->string('address_line', 255)->nullable();
            $t->string('ward_panchayat', 120);
            $t->string('block', 120);
            $t->string('district', 120);
            $t->string('state', 120);
            $t->string('pincode', 10)->nullable();
            $t->string('aadhaar_last4', 4)->nullable();                    // the full number is never stored or printed
            $t->string('aadhaar_file', 255)->nullable();                   // private disk
            $t->string('photo_file', 255)->nullable();                     // private disk
            $t->string('share_token', 48)->unique();                       // unguessable public link (WhatsApp)
            $t->timestamp('card_emailed_at')->nullable();
            $t->timestamps();
            $t->index(['seller_id', 'created_at']);
            $t->index('network_member_id');
        });

        Schema::create('offline_orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_no', 24)->unique();                         // OFL26102400001
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();       // customer
            $t->foreignId('user_card_id')->nullable()->constrained('user_cards')->nullOnDelete();
            $t->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();   // vendor / house store that sells
            $t->enum('channel', ['counter', 'worker'])->default('counter'); // counter = vendor POS, worker = BJO order
            $t->foreignId('network_member_id')->nullable()->constrained('network_members')->nullOnDelete(); // BJO who placed it
            $t->unsignedBigInteger('placed_by_user_id')->nullable();
            $t->enum('status', ['pending_pickup', 'completed', 'cancelled'])->default('completed');
            $t->string('payment_mode', 20)->default('cash');              // cash|upi|card|wallet|coins|mixed
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('delivery_charge', 12, 2)->default(0);             // always 0 for offline orders
            $t->unsignedInteger('coins_used')->default(0);
            $t->decimal('coin_discount', 12, 2)->default(0);
            $t->decimal('wallet_paid', 12, 2)->default(0);                 // BJO wallet
            $t->decimal('cash_paid', 12, 2)->default(0);                   // collected over the counter
            $t->decimal('total', 12, 2)->default(0);                       // subtotal - coin_discount
            $t->unsignedInteger('coins_earned')->default(0);
            $t->boolean('coins_credited')->default(false);
            $t->string('note', 255)->nullable();
            $t->string('cancel_reason', 255)->nullable();
            $t->timestamp('handed_over_at')->nullable();
            $t->unsignedBigInteger('handed_over_by')->nullable();
            $t->timestamps();
            $t->index(['seller_id', 'status', 'created_at']);
            $t->index(['user_id', 'created_at']);
            $t->index('network_member_id');
        });

        Schema::create('offline_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('offline_order_id')->constrained('offline_orders')->cascadeOnDelete();
            $t->unsignedBigInteger('store_product_variant_id')->nullable();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('title');
            $t->string('sku', 80)->nullable();
            $t->unsignedInteger('quantity');
            $t->decimal('unit_price', 12, 2);
            $t->decimal('subtotal', 12, 2);
            $t->unsignedInteger('cashback_coins')->default(0);            // coins x qty snapshot
            $t->timestamps();
        });

        Schema::create('network_wallet_ledger', function (Blueprint $t) {
            $t->id();
            $t->foreignId('network_member_id')->constrained('network_members')->cascadeOnDelete();
            $t->enum('type', ['credit', 'debit']);
            $t->decimal('amount', 12, 2);
            $t->decimal('balance_after', 12, 2);
            $t->string('reference_type', 40)->nullable();                  // offline_order
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->string('description')->nullable();
            $t->timestamps();
            $t->index(['network_member_id', 'created_at']);
        });

        Schema::table('coin_transactions', function (Blueprint $t) {
            if (!Schema::hasColumn('coin_transactions', 'offline_order_id')) {
                $t->unsignedBigInteger('offline_order_id')->nullable()->after('order_item_id');
                $t->index('offline_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coin_transactions', function (Blueprint $t) {
            $t->dropIndex(['offline_order_id']);
            $t->dropColumn('offline_order_id');
        });
        Schema::dropIfExists('network_wallet_ledger');
        Schema::dropIfExists('offline_order_items');
        Schema::dropIfExists('offline_orders');
        Schema::dropIfExists('offline_customer_profiles');
    }
};
