<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Strategy: Zero-downtime migration with nullable columns
     *
     * Steps:
     * 1. Add nullable columns to chats table (no default = no immediate impact)
     * 2. Create whatsapp_groups table with all constraints
     * 3. Backfill will be done separately via artisan command
     * 4. Indexes will be added in separate migration (after backfill)
     *
     * @return void
     */
    public function up()
    {
        // Step 1: Add provider and chat type columns to chats table
        // NOTE: All columns nullable to avoid blocking writes during migration
        Schema::table('chats', function (Blueprint $table) {
            // Provider type: 'meta' (existing Meta API) or 'webjs' (WhatsApp Web.js)
            $table->string('provider_type', 20)
                ->nullable()
                ->after('status')
                ->comment('Provider: meta | webjs');

            // Chat type: 'private' (1-on-1) or 'group' (group chat)
            $table->enum('chat_type', ['private', 'group'])
                ->nullable()
                ->after('provider_type')
                ->comment('Chat type: private contact or group');

            // Foreign key to whatsapp_groups (NULL for private chats)
            $table->unsignedBigInteger('group_id')
                ->nullable()
                ->after('contact_id')
                ->comment('FK to whatsapp_groups for group chats');

            // NOTE: Foreign key constraint will be added in Step 3 after groups table created
        });

        // Step 2: Create whatsapp_groups table
        Schema::create('whatsapp_groups', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();

            // Relationships
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('whatsapp_session_id');

            // WhatsApp identifiers
            $table->string('group_jid')->unique()->comment('WhatsApp group identifier (e.g., 1234567890-1234567890@g.us)');

            // Group metadata
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('owner_phone', 50)->nullable()->comment('Group creator phone number');

            // Participants (JSON array)
            // Format: [{"phone": "+6281234567890", "name": "John Doe", "isAdmin": true, "joinedAt": "2025-10-20T10:30:00Z"}]
            $table->json('participants')->comment('[{phone, name, isAdmin, joinedAt}]');

            // Group settings
            $table->string('invite_code')->nullable();
            $table->json('settings')->nullable()->comment('{messagesAdminsOnly, editInfoAdminsOnly}');

            // Timestamps
            $table->timestamp('group_created_at')->nullable()->comment('When group was created on WhatsApp');
            $table->timestamps();

            // Indexes for fast lookups
            $table->index(['workspace_id'], 'idx_groups_workspace');
            $table->index(['whatsapp_session_id'], 'idx_groups_session');
            $table->index(['workspace_id', 'whatsapp_session_id'], 'idx_groups_workspace_session');

            // Foreign key constraints
            $table->foreign('workspace_id')
                ->references('id')
                ->on('workspaces')
                ->onDelete('cascade');

            $table->foreign('whatsapp_session_id')
                ->references('id')
                ->on('whatsapp_sessions')
                ->onDelete('cascade');
        });

        // Step 3: Add foreign key from chats to whatsapp_groups
        // (Now safe since whatsapp_groups table exists)
        Schema::table('chats', function (Blueprint $table) {
            $table->foreign('group_id', 'fk_chats_group_id')
                ->references('id')
                ->on('whatsapp_groups')
                ->onDelete('set null'); // If group deleted, keep chat but set group_id = NULL
        });
    }

    /**
     * Reverse the migrations.
     *
     * Rollback Plan:
     * 1. Drop foreign key constraint first
     * 2. Drop whatsapp_groups table
     * 3. Drop columns from chats table
     *
     * @return void
     */
    public function down()
    {
        // Step 1: Drop foreign key from chats table
        Schema::table('chats', function (Blueprint $table) {
            $table->dropForeign('fk_chats_group_id');
        });

        // Step 2: Drop whatsapp_groups table (cascade will handle related data)
        Schema::dropIfExists('whatsapp_groups');

        // Step 3: Drop columns from chats table
        Schema::table('chats', function (Blueprint $table) {
            $table->dropColumn([
                'provider_type',
                'chat_type',
                'group_id',
            ]);
        });
    }
};
