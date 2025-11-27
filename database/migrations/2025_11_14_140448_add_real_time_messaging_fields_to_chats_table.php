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
        Schema::table('chats', function (Blueprint $table) {
            // Add real-time messaging fields (check if they don't exist)
            $columns = Schema::getColumnListing('chats');

            if (!in_array('whatsapp_message_id', $columns)) {
                $table->string('whatsapp_message_id', 128)->nullable()->after('wam_id');
            }
            if (!in_array('message_status', $columns)) {
                $table->enum('message_status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending')->after('status');
            }
            if (!in_array('ack_level', $columns)) {
                $table->tinyInteger('ack_level')->nullable()->comment('1=pending, 2=sent, 3=delivered, 4=read')->after('message_status');
            }
            if (!in_array('delivered_at', $columns)) {
                $table->timestamp('delivered_at')->nullable()->after('created_at');
            }
            if (!in_array('read_at', $columns)) {
                $table->timestamp('read_at')->nullable()->after('delivered_at');
            }
            if (!in_array('retry_count', $columns)) {
                $table->tinyInteger('retry_count')->default(0)->after('read_at');
            }

            // Add indexes for instant messaging performance (< 500ms)
            // Check if indexes don't exist before creating them
            if (!Schema::hasIndex('chats', 'chats_workspace_contact_index')) {
                $table->index(['workspace_id', 'contact_id'], 'chats_workspace_contact_index');
            }
            if (!Schema::hasIndex('chats', 'chats_whatsapp_message_id_index')) {
                $table->index(['whatsapp_message_id'], 'chats_whatsapp_message_id_index');
            }
            if (!Schema::hasIndex('chats', 'chats_contact_created_index')) {
                $table->index(['contact_id', 'created_at'], 'chats_contact_created_index');
            }
            if (!Schema::hasIndex('chats', 'chats_status_created_index')) {
                $table->index(['message_status', 'created_at'], 'chats_status_created_index');
            }
            if (!Schema::hasIndex('chats', 'chats_workspace_status_index')) {
                $table->index(['workspace_id', 'message_status'], 'chats_workspace_status_index');
            }
            if (!Schema::hasIndex('chats', 'chats_created_at_index')) {
                $table->index(['created_at'], 'chats_created_at_index');
            }

            // Composite index for chat list queries
            if (!Schema::hasIndex('chats', 'chats_workspace_contact_created_index')) {
                $table->index(['workspace_id', 'contact_id', 'created_at'], 'chats_workspace_contact_created_index');
            }
        });

        Schema::table('contacts', function (Blueprint $table) {
            // Add real-time messaging fields (check if they don't exist)
            $columns = Schema::getColumnListing('contacts');

            if (!in_array('last_message_at', $columns)) {
                $table->timestamp('last_message_at')->nullable()->after('latest_chat_created_at');
            }
            if (!in_array('last_activity', $columns)) {
                $table->timestamp('last_activity')->nullable()->after('last_message_at');
            }
            if (!in_array('is_online', $columns)) {
                $table->boolean('is_online')->default(false)->after('last_activity');
            }
            if (!in_array('typing_status', $columns)) {
                $table->string('typing_status', 20)->default('idle')->comment('idle, typing, recording')->after('is_online');
            }

            // Performance indexes for real-time features
            // Check if indexes don't exist before creating them
            if (!Schema::hasIndex('contacts', 'contacts_workspace_last_message_index')) {
                $table->index(['workspace_id', 'last_message_at'], 'contacts_workspace_last_message_index');
            }
            if (!Schema::hasIndex('contacts', 'contacts_workspace_online_index')) {
                $table->index(['workspace_id', 'is_online'], 'contacts_workspace_online_index');
            }
            if (!Schema::hasIndex('contacts', 'contacts_workspace_typing_index')) {
                $table->index(['workspace_id', 'typing_status'], 'contacts_workspace_typing_index');
            }
            if (!Schema::hasIndex('contacts', 'contacts_last_activity_index')) {
                $table->index(['last_activity'], 'contacts_last_activity_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            // Drop indexes safely (if they exist)
            $indexes = [
                'chats_workspace_contact_index',
                'chats_whatsapp_message_id_index',
                'chats_contact_created_index',
                'chats_status_created_index',
                'chats_workspace_status_index',
                'chats_created_at_index',
                'chats_workspace_contact_created_index'
            ];

            foreach ($indexes as $index) {
                if (Schema::hasIndex('chats', $index)) {
                    $table->dropIndex($index);
                }
            }

            // Drop columns
            $table->dropColumn(['whatsapp_message_id', 'message_status', 'ack_level', 'delivered_at', 'read_at', 'retry_count']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            // Drop indexes safely (if they exist)
            $indexes = [
                'contacts_workspace_last_message_index',
                'contacts_workspace_online_index',
                'contacts_workspace_typing_index',
                'contacts_last_activity_index'
            ];

            foreach ($indexes as $index) {
                if (Schema::hasIndex('contacts', $index)) {
                    $table->dropIndex($index);
                }
            }

            // Drop columns
            $table->dropColumn(['last_message_at', 'last_activity', 'is_online', 'typing_status']);
        });
    }
};
