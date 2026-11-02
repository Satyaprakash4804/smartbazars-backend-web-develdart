<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('network_member_kyc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_member_id')->unique()->constrained('network_members')->cascadeOnDelete();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('zipcode', 20)->nullable();
            $table->text('aadhar_number')->nullable();   // encrypted
            $table->text('pan_number')->nullable();      // encrypted
            $table->string('qualification', 150)->nullable();
            $table->text('education_details')->nullable();
            $table->json('documents')->nullable();       // aadhar|pan|education => private-disk path
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_member_kyc');
    }
};
