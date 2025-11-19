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
        Schema::table('contacts', function (Blueprint $table) {
            // Check and add only if column doesn't exist
            if (!Schema::hasColumn('contacts', 'last_message_at')) {
                $table->timestamp('last_message_at')->nullable()->after('latest_chat_created_at');
            }
            if (!Schema::hasColumn('contacts', 'last_activity')) {
                $table->timestamp('last_activity')->nullable()->after('last_message_at');
            }
            
            // Presence & status columns
            if (!Schema::hasColumn('contacts', 'is_online')) {
                $table->boolean('is_online')->default(false)->after('last_activity');
            }
            if (!Schema::hasColumn('contacts', 'typing_status')) {
                $table->enum('typing_status', ['idle', 'typing'])->default('idle')->after('is_online');
            }
            
            // Display & organization columns
            if (!Schema::hasColumn('contacts', 'full_name')) {
                $table->string('full_name')->nullable()->after('last_name');
            }
            if (!Schema::hasColumn('contacts', 'chat_type')) {
                $table->enum('chat_type', ['private', 'group'])->default('private')->after('email');
            }
            if (!Schema::hasColumn('contacts', 'group_name')) {
                $table->string('group_name')->nullable()->after('chat_type');
            }
            if (!Schema::hasColumn('contacts', 'participants_count')) {
                $table->integer('participants_count')->nullable()->after('group_name');
            }
            
            // Provider & session columns
            if (!Schema::hasColumn('contacts', 'provider_type')) {
                $table->enum('provider_type', ['meta', 'webjs'])->nullable()->after('participants_count');
            }
            if (!Schema::hasColumn('contacts', 'whatsapp_account_id')) {
                $table->integer('whatsapp_account_id')->nullable()->after('provider_type');
            }
            
            // Unread counter
            if (!Schema::hasColumn('contacts', 'unread_messages')) {
                $table->integer('unread_messages')->default(0)->after('whatsapp_account_id');
            }
        });

        // Add indexes separately after all columns are created
        Schema::table('contacts', function (Blueprint $table) {
            // Check if index exists by trying to create it
            try {
                $table->index('whatsapp_account_id');
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
        Schema::table('contacts', function (Blueprint $table) {
            // Drop indexes first
            try {
                $table->dropIndex(['whatsapp_account_id']);
            } catch (\Exception $e) {
                // Index doesn't exist, ignore
            }
            
            // Drop columns
            $columnsToCheck = [
                'last_message_at',
                'last_activity',
                'is_online',
                'typing_status',
                'full_name',
                'chat_type',
                'group_name',
                'participants_count',
                'provider_type',
                'whatsapp_account_id',
                'unread_messages',
            ];
            
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('contacts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
