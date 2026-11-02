<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Online "pickup from store" orders: no delivery charge, no delivery boy, vendor completes the order at handover. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->boolean('is_pickup')->default(false)->after('fulfillment_type');
            $t->timestamp('picked_up_at')->nullable()->after('is_pickup');
            $t->index('is_pickup');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex(['is_pickup']);
            $t->dropColumn(['is_pickup', 'picked_up_at']);
        });
    }
};
