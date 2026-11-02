<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Joining fee (Razorpay) + public "Apply now" for Agents / Workers.
 *  - network_registration_requests: new status `pending_payment`, source, documents, payment info
 *  - network_fee_payments: one row per fee (application, ID creation, vendor joining)
 * Joining fees are NEVER split as commission — nothing here touches network_commissions.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('network_registration_requests', function (Blueprint $table) {
            // enum -> string so we can add `pending_payment` on every DB driver
            $table->string('status', 30)->default('pending_admin')->change();
        });

        Schema::table('network_registration_requests', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('source', 20)->default('internal')->after('type'); // public|admin|agent|vendor|internal
            $table->json('documents')->nullable()->after('meta');             // private-disk paths
            $table->string('payment_status', 20)->default('not_required')->after('status'); // not_required|pending|paid|waived
            $table->decimal('fee_amount', 10, 2)->nullable()->after('payment_status');
            $table->boolean('auto_approve')->default(false)->after('fee_amount'); // admin-created: create account once paid
            $table->unsignedBigInteger('reapplied_from_id')->nullable()->after('auto_approve');
        });

        Schema::create('network_fee_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('purpose', 30);                       // application|id_creation|vendor_joining
            $table->foreignId('registration_request_id')->nullable()->constrained('network_registration_requests')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->string('payer_name')->nullable();
            $table->string('payer_email')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('status', 20)->default('pending');    // pending|paid|waived|refunded
            $table->string('razorpay_order_id')->nullable()->index();
            $table->string('razorpay_payment_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->string('return_url', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['status', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_fee_payments');
        Schema::table('network_registration_requests', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'source', 'documents', 'payment_status', 'fee_amount', 'auto_approve', 'reapplied_from_id']);
        });
    }
};
