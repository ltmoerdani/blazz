<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * IMPORTANT: This migration should be run AFTER backfill command completes
     * (php artisan chats:backfill-provider-type)
     *
     * Purpose: Add performance indexes for optimized query performance
     * Target: getChatList query < 500ms for 50 contacts
     *
     * @return void
     */
    public function up()
    {
        Schema::table('chats', function (Blueprint $table) {
            // Index 1: Provider type filtering with timestamp ordering
            // Optimizes: SELECT * FROM chats WHERE workspace_id = X AND provider_type = 'webjs' ORDER BY created_at DESC
            $table->index(
                ['workspace_id', 'provider_type', 'created_at'],
                'idx_chats_provider_type'
            );

            // Index 2: Chat type filtering with timestamp ordering
            // Optimizes: SELECT * FROM chats WHERE workspace_id = X AND chat_type = 'group' ORDER BY created_at DESC
            $table->index(
                ['workspace_id', 'chat_type', 'created_at'],
                'idx_chats_chat_type'
            );

            // Index 3: Session-based chat filtering (most important for multi-number support)
            // Optimizes: getChatList() with session filter
            // Query: SELECT * FROM chats WHERE workspace_id = X AND whatsapp_session_id = Y ORDER BY created_at DESC
            $table->index(
                ['workspace_id', 'chat_type', 'whatsapp_session_id', 'created_at'],
                'idx_chats_type_session'
            );

            // Index 4: Combined provider and session lookup
            // Optimizes: SELECT * FROM chats WHERE workspace_id = X AND provider_type = 'webjs' AND whatsapp_session_id = Y
            $table->index(
                ['workspace_id', 'provider_type', 'whatsapp_session_id'],
                'idx_chats_provider_session'
            );

            // Index 5: Contact-based chat lookup with ordering
            // Optimizes: Find latest chat for specific contact
            // Query: SELECT * FROM chats WHERE contact_id = X ORDER BY created_at DESC LIMIT 1
            $table->index(
                ['contact_id', 'created_at'],
                'idx_chats_contact_chat'
            );

            // Index 6: Group-based chat lookup with ordering
            // Optimizes: Find latest chat for specific group
            // Query: SELECT * FROM chats WHERE group_id = X ORDER BY created_at DESC LIMIT 1
            $table->index(
                ['group_id', 'created_at'],
                'idx_chats_group_chat'
            );

            // Index 7: Comprehensive session filter (covers most getChatList queries)
            // Optimizes: Main query pattern in ChatService::getChatList()
            $table->index(
                ['workspace_id', 'whatsapp_session_id', 'created_at'],
                'idx_chats_workspace_session_created'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('chats', function (Blueprint $table) {
            // Drop all indexes in reverse order
            $table->dropIndex('idx_chats_workspace_session_created');
            $table->dropIndex('idx_chats_group_chat');
            $table->dropIndex('idx_chats_contact_chat');
            $table->dropIndex('idx_chats_provider_session');
            $table->dropIndex('idx_chats_type_session');
            $table->dropIndex('idx_chats_chat_type');
            $table->dropIndex('idx_chats_provider_type');
        });
    }
};
