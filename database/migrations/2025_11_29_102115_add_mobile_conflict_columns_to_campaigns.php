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
        Schema::table('campaigns', function (Blueprint $table) {
            // Mobile conflict tracking columns
            $table->timestamp('paused_at')->nullable()->after('completed_at');
            $table->string('pause_reason', 100)->nullable()->after('paused_at');
            $table->timestamp('auto_resume_at')->nullable()->after('pause_reason');
            $table->unsignedTinyInteger('pause_count')->default(0)->after('auto_resume_at');
            $table->string('paused_by_session', 255)->nullable()->after('pause_count');

            // Indexes for performance
            $table->index(['status', 'paused_at'], 'idx_campaigns_status_paused');
            $table->index(['workspace_id', 'status'], 'idx_campaigns_workspace_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex('idx_campaigns_status_paused');
            $table->dropIndex('idx_campaigns_workspace_status');

            $table->dropColumn([
                'paused_at',
                'pause_reason',
                'auto_resume_at',
                'pause_count',
                'paused_by_session',
            ]);
        });
    }
};
