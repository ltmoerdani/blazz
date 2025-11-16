<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            // Health monitoring fields
            $table->integer('health_score')->default(100)->after('status');
            $table->timestamp('last_health_check_at')->nullable()->after('health_score');
            $table->timestamp('last_reconnect_attempt_at')->nullable()->after('last_health_check_at');
            $table->integer('reconnect_attempts')->default(0)->after('last_reconnect_attempt_at');
            $table->json('health_metadata')->nullable()->after('reconnect_attempts');
            
            // Add index for health monitoring queries
            $table->index(['status', 'health_score']);
            $table->index('last_health_check_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropIndex(['status', 'health_score']);
            $table->dropIndex(['last_health_check_at']);
            
            $table->dropColumn([
                'health_score',
                'last_health_check_at',
                'last_reconnect_attempt_at',
                'reconnect_attempts',
                'health_metadata',
            ]);
        });
    }
};
