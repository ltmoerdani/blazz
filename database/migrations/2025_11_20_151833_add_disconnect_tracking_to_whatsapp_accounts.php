<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add disconnect tracking for monitoring and analytics.
     * Helps identify disconnect patterns and instance reliability issues.
     */
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            // Disconnect tracking
            $table->timestamp('disconnected_at')->nullable()->after('last_connected_at')
                ->comment('When session was disconnected');
            
            $table->enum('disconnect_reason', [
                'user_initiated',
                'instance_restart',
                'timeout',
                'error',
                'qr_expired',
                'unknown'
            ])->nullable()->after('disconnected_at')
                ->comment('Reason for disconnect');
            
            $table->text('disconnect_details')->nullable()->after('disconnect_reason')
                ->comment('Additional details about disconnect (error message, stack trace, etc.)');
            
            // Index for disconnect analytics
            $table->index(['disconnect_reason', 'disconnected_at'], 'idx_disconnect_analytics');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropIndex('idx_disconnect_analytics');
            $table->dropColumn(['disconnected_at', 'disconnect_reason', 'disconnect_details']);
        });
    }
};
