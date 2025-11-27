<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rename whatsapp_sessions table to whatsapp_accounts as per migration plan:
     * WhatsApp Sessions → Accounts Renaming Guide
     */
    public function up(): void
    {
        Schema::rename('whatsapp_sessions', 'whatsapp_accounts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('whatsapp_accounts', 'whatsapp_sessions');
    }
};
