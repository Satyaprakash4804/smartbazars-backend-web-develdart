<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Audit trail for every upline / downline change made from Admin > Network Mapping.
        Schema::create('network_mapping_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('entity_type', 20);            // agent | vendor | worker
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_label', 150)->nullable();
            $table->unsignedBigInteger('from_id')->nullable();   // old parent (agent / vendor id)
            $table->string('from_label', 150)->nullable();
            $table->unsignedBigInteger('to_id')->nullable();     // new parent
            $table->string('to_label', 150)->nullable();
            $table->unsignedInteger('affected')->default(0);     // agents re-pathed (sub-tree size)
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });

        // Speeds up sub-tree moves (path LIKE '/1/4/%') and tree loading.
        Schema::table('network_members', function (Blueprint $table) {
            $table->index(['type', 'parent_id'], 'nm_type_parent_idx');
            $table->index('path', 'nm_path_idx');
        });
    }

    public function down(): void
    {
        Schema::table('network_members', function (Blueprint $table) {
            $table->dropIndex('nm_type_parent_idx');
            $table->dropIndex('nm_path_idx');
        });
        Schema::dropIfExists('network_mapping_logs');
    }
};
