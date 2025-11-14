<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rename whatsapp_session_id foreign key columns to whatsapp_account_id
     * as per WhatsApp Sessions → Accounts migration plan
     */
    public function up(): void
    {
        Schema::table('campaign_logs', function (Blueprint $table) {
            $table->renameColumn('whatsapp_session_id', 'whatsapp_account_id');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->renameColumn('whatsapp_session_id', 'whatsapp_account_id');
        });

        Schema::table('contact_sessions', function (Blueprint $table) {
            $table->renameColumn('whatsapp_session_id', 'whatsapp_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_logs', function (Blueprint $table) {
            $table->renameColumn('whatsapp_account_id', 'whatsapp_session_id');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->renameColumn('whatsapp_account_id', 'whatsapp_session_id');
        });

        Schema::table('contact_sessions', function (Blueprint $table) {
            $table->renameColumn('whatsapp_account_id', 'whatsapp_session_id');
        });
    }
};
