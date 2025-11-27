<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            // Composite indexes for workspace queries - check if they exist first
            $this->addIndexIfNotExists('whatsapp_accounts', ['workspace_id', 'status'], 'idx_workspace_status');
            $this->addIndexIfNotExists('whatsapp_accounts', 'session_id', 'idx_session_id');
            $this->addIndexIfNotExists('whatsapp_accounts', 'last_activity_at', 'idx_last_activity');
            $this->addIndexIfNotExists('whatsapp_accounts', ['workspace_id', 'is_primary'], 'idx_workspace_primary');
            $this->addIndexIfNotExists('whatsapp_accounts', ['workspace_id', 'provider_type'], 'idx_workspace_provider');
        });

        Schema::table('chats', function (Blueprint $table) {
            // Composite indexes for chat queries
            $this->addIndexIfNotExists('chats', ['whatsapp_account_id', 'created_at'], 'idx_account_recent');
            $this->addIndexIfNotExists('chats', ['workspace_id', 'contact_id', 'created_at'], 'idx_workspace_contact_created');
            $this->addIndexIfNotExists('chats', ['contact_id', 'created_at'], 'idx_contact_recent');
            $this->addIndexIfNotExists('chats', ['workspace_id', 'type'], 'idx_workspace_type');
            $this->addIndexIfNotExists('chats', ['workspace_id', 'message_status'], 'idx_workspace_status');
            $this->addIndexIfNotExists('chats', ['is_read', 'created_at'], 'idx_read_created');
            $this->addIndexIfNotExists('chats', ['workspace_id', 'created_at'], 'idx_workspace_created');
        });

        Schema::table('contacts', function (Blueprint $table) {
            // Composite indexes for contact queries
            $this->addIndexIfNotExists('contacts', ['workspace_id', 'is_active'], 'idx_workspace_active');
            $this->addIndexIfNotExists('contacts', ['workspace_id', 'updated_at'], 'idx_workspace_updated');
            $this->addIndexIfNotExists('contacts', ['workspace_id', 'phone'], 'idx_workspace_phone');
            $this->addIndexIfNotExists('contacts', ['workspace_id', 'latest_chat_created_at'], 'idx_workspace_latest_chat');
            $this->addIndexIfNotExists('contacts', ['is_active', 'latest_chat_created_at'], 'idx_active_latest_chat');
        });

        Schema::table('campaign_logs', function (Blueprint $table) {
            // Composite indexes for campaign queries
            $this->addIndexIfNotExists('campaign_logs', ['workspace_id', 'status'], 'idx_campaign_workspace_status');
            $this->addIndexIfNotExists('campaign_logs', ['whatsapp_account_id', 'created_at'], 'idx_campaign_account_created');
            $this->addIndexIfNotExists('campaign_logs', ['workspace_id', 'created_at'], 'idx_campaign_workspace_created');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            // Indexes for workspace queries
            $this->addIndexIfNotExists('workspaces', 'is_active', 'idx_workspace_active_status');
            $this->addIndexIfNotExists('workspaces', ['plan_type', 'is_active'], 'idx_workspace_plan_active');
        });

        Schema::table('users', function (Blueprint $table) {
            // Indexes for user queries
            $this->addIndexIfNotExists('users', ['workspace_id', 'is_active'], 'idx_user_workspace_active');
            $this->addIndexIfNotExists('users', ['workspace_id', 'role'], 'idx_user_workspace_role');
        });
    }

    /**
     * Helper method to add index if it doesn't exist
     */
    private function addIndexIfNotExists(string $table, $columns, string $indexName): void
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

            if (empty($indexes)) {
                Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                    if (is_array($columns)) {
                        $table->index($columns, $indexName);
                    } else {
                        $table->index($columns, $indexName);
                    }
                });
            }
        } catch (\Exception $e) {
            // Ignore errors and continue
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropIndex('idx_workspace_status');
            $table->dropIndex('idx_session_id');
            $table->dropIndex('idx_last_activity');
            $table->dropIndex('idx_workspace_primary');
            $table->dropIndex('idx_workspace_provider');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->dropIndex('idx_account_recent');
            $table->dropIndex('idx_workspace_contact_created');
            $table->dropIndex('idx_contact_recent');
            $table->dropIndex('idx_workspace_type');
            $table->dropIndex('idx_workspace_status');
            $table->dropIndex('idx_read_created');
            $table->dropIndex('idx_workspace_created');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('idx_workspace_active');
            $table->dropIndex('idx_workspace_updated');
            $table->dropIndex('idx_workspace_phone');
            $table->dropIndex('idx_workspace_latest_chat');
            $table->dropIndex('idx_active_latest_chat');
        });

        Schema::table('campaign_logs', function (Blueprint $table) {
            $table->dropIndex('idx_campaign_workspace_status');
            $table->dropIndex('idx_campaign_account_created');
            $table->dropIndex('idx_campaign_workspace_created');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropIndex('idx_workspace_active_status');
            $table->dropIndex('idx_workspace_plan_active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_user_workspace_active');
            $table->dropIndex('idx_user_workspace_role');
        });
    }
};
