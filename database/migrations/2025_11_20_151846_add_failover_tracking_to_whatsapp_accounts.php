<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add failover/migration tracking for monitoring session migrations between instances.
     * Helps identify instance reliability issues and migration patterns.
     */
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            // Add previous_instance_index after assigned_instance_url
            // (must be after instance tracking migration has run)
            $table->tinyInteger('previous_instance_index')->nullable()->after('assigned_instance_url')
                ->comment('Previous instance index before migration (for failover tracking)');
            
            // Failover/migration tracking
            $table->integer('instance_migration_count')->default(0)->after('session_restore_count')
                ->comment('Number of times session was migrated to different instance');
            
            $table->timestamp('last_instance_migration_at')->nullable()->after('instance_migration_count')
                ->comment('Last time session was migrated to different instance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'previous_instance_index',
                'instance_migration_count',
                'last_instance_migration_at'
            ]);
        });
    }
};
