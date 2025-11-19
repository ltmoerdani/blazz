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
        // Update foreign key columns in related tables
        // Note: contact_sessions, chats, and campaign_logs already have whatsapp_account_id
        // Only whatsapp_groups still needs the column rename

        Schema::table('whatsapp_groups', function (Blueprint $table) {
            $table->renameColumn('whatsapp_session_id', 'whatsapp_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse all column renames
        Schema::table('whatsapp_groups', function (Blueprint $table) {
            $table->renameColumn('whatsapp_account_id', 'whatsapp_session_id');
        });
    }
};
