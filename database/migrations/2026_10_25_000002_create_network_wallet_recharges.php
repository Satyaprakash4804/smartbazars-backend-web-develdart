<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('network_wallet_recharges', function (Blueprint $t) {
            $t->id();
            $t->foreignId('network_member_id')->constrained('network_members')->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->string('method', 20)->default('upi');            // upi | bank | cash | other
            $t->string('reference', 80);                         // UTR / transaction id
            $t->string('proof_file', 255)->nullable();           // payment screenshot (private disk)
            $t->string('note', 255)->nullable();
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->string('admin_note', 255)->nullable();
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
            $t->index('network_member_id');
            $t->unique(['network_member_id', 'reference'], 'nwr_member_ref_unique');   // same UTR cannot be submitted twice
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_wallet_recharges');
    }
};
