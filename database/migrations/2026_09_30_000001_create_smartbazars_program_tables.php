<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SmartBazars program: Agent network, vendor workers, welcome kit, user card,
 * cashback coins and partner commissions.
 */
return new class extends Migration {
    public function up(): void
    {
        // ---------- Network members (Agents + Vendor workers) ----------
        Schema::create('network_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['agent', 'worker']);
            // Agent upline (only for agents). NULL = works directly under admin/company
            $table->foreignId('parent_id')->nullable()->constrained('network_members')->nullOnDelete();
            // Vendor a worker works for (only for workers)
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->unsignedInteger('level')->default(1);          // 1 = directly under admin
            $table->string('path', 1000)->default('/');         // materialised path e.g. /1/5/9/
            $table->string('referral_code', 20)->unique();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->string('created_by_type', 20)->default('admin'); // admin|agent|seller
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->decimal('wallet_balance', 12, 2)->default(0);
            $table->decimal('total_earned', 12, 2)->default(0);
            $table->timestamps();
            $table->index(['type', 'status']);
        });

        // ---------- Registration requests (approval workflow) ----------
        Schema::create('network_registration_requests', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['agent', 'vendor', 'worker']);
            $table->string('name');
            $table->string('email');
            $table->string('mobile', 20);
            $table->json('meta')->nullable();                   // business name, city etc.
            // who raised the request
            $table->foreignId('requested_by_member_id')->nullable()->constrained('network_members')->nullOnDelete();
            $table->foreignId('requested_by_seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            // for worker requests: the vendor they will work under
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            // pending_seller -> pending_admin -> approved | rejected
            $table->enum('status', ['pending_seller', 'pending_admin', 'approved', 'rejected'])->default('pending_admin');
            $table->timestamp('seller_approved_at')->nullable();
            $table->timestamp('admin_approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->unsignedBigInteger('created_user_id')->nullable();
            $table->timestamps();
            $table->index(['type', 'status']);
        });

        // ---------- Commission ledger ----------
        Schema::create('network_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_member_id')->nullable()->constrained('network_members')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete(); // vendor share of kit
            $table->enum('source', ['vendor_sale', 'welcome_kit']);
            $table->unsignedBigInteger('source_id');             // order_item_id | welcome_kit_purchase_id
            $table->string('role', 30);                          // direct_agent|upline_agent|worker|vendor|vendor_agent|vendor_agent_upline
            $table->decimal('base_amount', 12, 2)->default(0);
            $table->decimal('rate', 8, 4)->nullable();           // % for sales
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['credited', 'reversed'])->default('credited');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->unique(['source', 'source_id', 'role'], 'nc_unique_source_role');
            $table->index(['network_member_id', 'status']);
        });

        Schema::create('network_withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_member_id')->constrained('network_members')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->json('payout_details')->nullable();          // UPI / bank
            $table->enum('status', ['pending', 'paid', 'rejected'])->default('pending');
            $table->string('admin_note')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        // ---------- Existing tables ----------
        // Agents & workers log in to the /partner panel => new access_panel value
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY access_panel ENUM('web','admin','seller','partner') NOT NULL DEFAULT 'web'");
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'coin_balance')) {
                $table->unsignedInteger('coin_balance')->default(0)->after('reward_points');
            }
            $table->unsignedBigInteger('referred_by_worker_id')->nullable()->after('coin_balance');
            $table->timestamp('welcome_kit_purchased_at')->nullable()->after('referred_by_worker_id');
        });

        Schema::table('sellers', function (Blueprint $table) {
            $table->unsignedBigInteger('agent_member_id')->nullable()->after('user_id');
            $table->boolean('is_house_store')->default(false)->after('agent_member_id');
            $table->index('agent_member_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('cashback_coins')->default(0);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('coins_used')->default(0);
            $table->decimal('coin_discount', 12, 2)->default(0);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('cashback_coins')->default(0);   // snapshot: coins * qty
            $table->decimal('coin_discount', 12, 2)->default(0);     // share of order coin discount
        });

        // ---------- Cashback coins ----------
        Schema::create('coin_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['earn', 'redeem', 'refund', 'reverse', 'adjust']);
            $table->integer('coins');                             // +credit / -debit
            $table->unsignedInteger('balance_after');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->unique(['type', 'order_item_id'], 'coin_tx_type_item_unique');
        });

        // ---------- Welcome kit ----------
        Schema::create('welcome_kits', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(250);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('welcome_kit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('welcome_kit_id')->constrained('welcome_kits')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('item_name');                          // shown to customer
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('welcome_kit_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('welcome_kit_id')->nullable()->constrained('welcome_kits')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 30)->default('wallet');
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();
            $table->foreignId('worker_member_id')->nullable()->constrained('network_members')->nullOnDelete();
            $table->string('referral_code_used', 20)->nullable();
            $table->enum('status', ['paid', 'processing', 'dispatched', 'delivered', 'cancelled'])->default('paid');
            $table->unsignedBigInteger('address_id')->nullable();
            $table->json('address_snapshot')->nullable();
            $table->json('items_snapshot')->nullable();
            $table->boolean('commission_distributed')->default(false);
            $table->string('admin_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('user_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('welcome_kit_purchase_id')->nullable()->constrained('welcome_kit_purchases')->nullOnDelete();
            $table->string('card_number', 20)->unique();
            $table->date('issued_at');
            $table->date('valid_upto');
            $table->enum('status', ['active', 'blocked'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_cards');
        Schema::dropIfExists('welcome_kit_purchases');
        Schema::dropIfExists('welcome_kit_items');
        Schema::dropIfExists('welcome_kits');
        Schema::dropIfExists('coin_transactions');
        Schema::table('order_items', fn(Blueprint $t) => $t->dropColumn(['cashback_coins', 'coin_discount']));
        Schema::table('orders', fn(Blueprint $t) => $t->dropColumn(['coins_used', 'coin_discount']));
        Schema::table('products', fn(Blueprint $t) => $t->dropColumn('cashback_coins'));
        Schema::table('sellers', function (Blueprint $t) {
            $t->dropIndex(['agent_member_id']);
            $t->dropColumn(['agent_member_id', 'is_house_store']);
        });
        Schema::table('users', fn(Blueprint $t) => $t->dropColumn(['coin_balance', 'referred_by_worker_id', 'welcome_kit_purchased_at']));
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE users SET access_panel = 'web' WHERE access_panel = 'partner'");
            DB::statement("ALTER TABLE users MODIFY access_panel ENUM('web','admin','seller') NOT NULL DEFAULT 'web'");
        }
        Schema::dropIfExists('network_withdrawal_requests');
        Schema::dropIfExists('network_commissions');
        Schema::dropIfExists('network_registration_requests');
        Schema::dropIfExists('network_members');
    }
};
