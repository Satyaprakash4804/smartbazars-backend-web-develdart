<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleted agents / workers / vendors are archived, not erased: the row stays (soft delete) with a snapshot of who they
 * were, so wallet balance, commissions and withdrawal history are never lost.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('network_members', function (Blueprint $table) {
            if (!Schema::hasColumn('network_members', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at', 'nm_deleted_at_idx');
            }
            if (!Schema::hasColumn('network_members', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable();
            }
            if (!Schema::hasColumn('network_members', 'deleted_snapshot')) {
                $table->text('deleted_snapshot')->nullable();
            }
        });

        Schema::table('sellers', function (Blueprint $table) {
            if (!Schema::hasColumn('sellers', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable();
            }
            if (!Schema::hasColumn('sellers', 'deleted_snapshot')) {
                $table->text('deleted_snapshot')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('network_members', function (Blueprint $table) {
            $table->dropIndex('nm_deleted_at_idx');
            $table->dropColumn(['deleted_at', 'deleted_by', 'deleted_snapshot']);
        });
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn(['deleted_by', 'deleted_snapshot']);
        });
    }
};
