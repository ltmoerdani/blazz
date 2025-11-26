<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add columns for session cleanup tracking and health monitoring.
     * All columns are nullable to ensure backward compatibility.
     */
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            // Track last cleanup operation
            $table->timestamp('last_cleanup_at')->nullable()->after('last_connected_at')
                ->comment('Timestamp of last cleanup check');
            
            // Track session restoration attempts
            $table->integer('session_restore_count')->default(0)->after('last_cleanup_at')
                ->comment('Number of times session was restored from storage');
            
            // Health score (0-100) for monitoring
            $table->tinyInteger('health_score')->default(100)->after('session_restore_count')
                ->comment('Session health score 0-100 (for monitoring)');
            
            // Add composite index for stale session detection
            $table->index(['status', 'last_activity_at'], 'idx_stale_detection');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropIndex('idx_stale_detection');
            $table->dropColumn(['last_cleanup_at', 'session_restore_count', 'health_score']);
        });
    }
};
