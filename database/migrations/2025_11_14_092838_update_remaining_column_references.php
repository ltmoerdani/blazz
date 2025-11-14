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
        Schema::table('contact_accounts', function (Blueprint $table) {
            $table->renameColumn('whatsapp_session_id', 'whatsapp_account_id');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->renameColumn('whatsapp_session_id', 'whatsapp_account_id');
        });

        Schema::table('campaign_logs', function (Blueprint $table) {
            $table->renameColumn('whatsapp_session_id', 'whatsapp_account_id');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->renameColumn('whatsapp_session_id', 'whatsapp_account_id');
        });

        Schema::table('whatsapp_groups', function (Blueprint $table) {
            $table->renameColumn('whatsapp_session_id', 'whatsapp_account_id');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->renameColumn('source_session_id', 'source_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse all column renames
        Schema::table('contact_accounts', function (Blueprint $table) {
            $table->renameColumn('whatsapp_account_id', 'whatsapp_session_id');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->renameColumn('whatsapp_account_id', 'whatsapp_session_id');
        });

        Schema::table('campaign_logs', function (Blueprint $table) {
            $table->renameColumn('whatsapp_account_id', 'whatsapp_session_id');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->renameColumn('whatsapp_account_id', 'whatsapp_session_id');
        });

        Schema::table('whatsapp_groups', function (Blueprint $table) {
            $table->renameColumn('whatsapp_account_id', 'whatsapp_session_id');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->renameColumn('source_account_id', 'source_session_id');
        });
    }
};
