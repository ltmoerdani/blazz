<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add instance tracking fields for multi-instance workspace-sharded architecture.
     * CRITICAL: Required for routing sessions to correct WhatsApp instance.
     */
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            // Instance assignment
            $table->tinyInteger('assigned_instance_index')->nullable()->after('workspace_id')
                ->comment('Index of WhatsApp instance handling this session (0-based)');
            
            $table->string('assigned_instance_url', 255)->nullable()->after('assigned_instance_index')
                ->comment('URL of instance handling this session (e.g., http://instance-2:3001)');
            
            // Index for querying sessions by instance
            $table->index('assigned_instance_index', 'idx_instance_assignment');
        });
        
        // Backfill existing records (assume single instance at index 0)
        DB::table('whatsapp_accounts')
            ->whereNull('assigned_instance_index')
            ->update([
                'assigned_instance_index' => 0,
                'assigned_instance_url' => config('whatsapp.instances.0', 'http://localhost:3001'),
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropIndex('idx_instance_assignment');
            $table->dropColumn(['assigned_instance_index', 'assigned_instance_url']);
        });
    }
};
