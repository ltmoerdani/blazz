<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - PHASE-3 Performance Optimization
     */
    public function up(): void
    {
        // Optimize chats table - primary bottleneck identified
        Schema::table('chats', function (Blueprint $table) {
            // Composite index untuk timeline queries (most common operation)
            $table->index(['workspace_id', 'created_at', 'type'], 'idx_chat_timeline_performance');
            
            // Participants lookup optimization
            $table->index(['workspace_id', 'contact_id', 'status'], 'idx_chat_participants_opt');
            
            // Media queries optimization
            $table->index(['media_id', 'created_at'], 'idx_chat_media_timeline');
        });

        // Optimize workspaces table - dashboard performance
        Schema::table('workspaces', function (Blueprint $table) {
            // Multi-tenant performance index
            $table->index(['created_by', 'created_at'], 'idx_org_creator_timeline');
            
            // Status-based queries
            if (!Schema::hasColumn('workspaces', 'status')) {
                $table->string('status', 20)->default('active')->after('name');
            }
            $table->index(['status', 'created_at'], 'idx_org_status_performance');
        });

        // Optimize teams table - workspace membership queries
        Schema::table('teams', function (Blueprint $table) {
            // Covering index untuk workspace-user relationships
            $table->index(['workspace_id', 'user_id', 'role', 'created_at'], 'idx_team_membership_complete');
        });

        // Optimize users table - authentication and search
        Schema::table('users', function (Blueprint $table) {
            // Email verification timeline
            $table->index(['email_verified_at', 'created_at'], 'idx_user_verification_timeline');
            
            // Role-based filtering
            $table->index(['role', 'created_at'], 'idx_user_role_timeline');
        });

        // Add database performance monitoring table
        Schema::create('query_performance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query_hash', 32)->index(); // MD5 hash of normalized query
            $table->text('query_sql');
            $table->decimal('execution_time', 10, 6); // Microsecond precision
            $table->integer('rows_examined');
            $table->integer('rows_sent');
            $table->string('connection_name', 50);
            $table->string('controller_action', 255)->nullable();
            $table->json('query_bindings')->nullable();
            $table->timestamp('executed_at');
            
            $table->index(['execution_time', 'executed_at'], 'idx_slow_queries');
            $table->index(['query_hash', 'executed_at'], 'idx_query_frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropIndex('idx_chat_timeline_performance');
            $table->dropIndex('idx_chat_participants_opt');
            $table->dropIndex('idx_chat_media_timeline');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropIndex('idx_org_creator_timeline');
            $table->dropIndex('idx_org_status_performance');
            if (Schema::hasColumn('workspaces', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex('idx_team_membership_complete');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_user_verification_timeline');
            $table->dropIndex('idx_user_role_timeline');
        });

        Schema::dropIfExists('query_performance_logs');
    }
};
