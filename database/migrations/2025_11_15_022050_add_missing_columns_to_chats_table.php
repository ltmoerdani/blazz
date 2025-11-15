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
            // WhatsApp message tracking
            if (!Schema::hasColumn('chats', 'message_id')) {
                $table->string('message_id', 255)->nullable()->after('contact_id');
            }
            
            // Message status columns
            if (!Schema::hasColumn('chats', 'message_status')) {
                $table->enum('message_status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending')->after('type');
            }
            if (!Schema::hasColumn('chats', 'ack_level')) {
                $table->integer('ack_level')->nullable()->after('message_status');
            }
            
            // Timestamp columns for message lifecycle
            if (!Schema::hasColumn('chats', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('created_at');
            }
            if (!Schema::hasColumn('chats', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('sent_at');
            }
            if (!Schema::hasColumn('chats', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('delivered_at');
            }
            if (!Schema::hasColumn('chats', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('read_at');
            }
            
            // Read status for unread counter
            if (!Schema::hasColumn('chats', 'is_read')) {
                $table->boolean('is_read')->default(false)->after('updated_at');
            }
            
            // Retry mechanism for failed messages
            if (!Schema::hasColumn('chats', 'retry_count')) {
                $table->integer('retry_count')->default(0)->after('is_read');
            }
            
            // Multi-session support
            if (!Schema::hasColumn('chats', 'whatsapp_account_id')) {
                $table->integer('whatsapp_account_id')->nullable()->after('workspace_id');
            }
            
            // Group chat support
            if (!Schema::hasColumn('chats', 'group_id')) {
                $table->integer('group_id')->nullable()->after('contact_id');
            }
            
            // User tracking (who sent the message from admin side)
            if (!Schema::hasColumn('chats', 'user_id')) {
                $table->integer('user_id')->nullable()->after('group_id');
            }
        });

        // Add indexes separately after all columns are created
        Schema::table('chats', function (Blueprint $table) {
            // Check if indexes exist by trying to create them
            try {
                $table->index(['workspace_id', 'contact_id', 'created_at']);
            } catch (\Exception $e) {
                // Index already exists, ignore
            }
            try {
                $table->index(['workspace_id', 'whatsapp_account_id']);
            } catch (\Exception $e) {
                // Index already exists, ignore
            }
            try {
                $table->index(['message_id']);
            } catch (\Exception $e) {
                // Index already exists, ignore
            }
            try {
                $table->index(['is_read', 'type']);
            } catch (\Exception $e) {
                // Index already exists, ignore
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            // Drop indexes first
            try {
                $table->dropIndex(['workspace_id', 'contact_id', 'created_at']);
            } catch (\Exception $e) {
                // Index doesn't exist, ignore
            }
            try {
                $table->dropIndex(['workspace_id', 'whatsapp_account_id']);
            } catch (\Exception $e) {
                // Index doesn't exist, ignore
            }
            try {
                $table->dropIndex(['message_id']);
            } catch (\Exception $e) {
                // Index doesn't exist, ignore
            }
            try {
                $table->dropIndex(['is_read', 'type']);
            } catch (\Exception $e) {
                // Index doesn't exist, ignore
            }
            
            // Drop columns
            $columnsToCheck = [
                'message_id',
                'message_status',
                'ack_level',
                'sent_at',
                'delivered_at',
                'read_at',
                'updated_at',
                'is_read',
                'retry_count',
                'whatsapp_account_id',
                'group_id',
                'user_id',
            ];
            
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('chats', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
