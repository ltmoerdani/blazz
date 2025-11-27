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
        // Add unique index to prevent duplicate contacts per workspace
        Schema::table('contacts', function (Blueprint $table) {
            // Add unique compound index
            $table->unique(['workspace_id', 'phone'], 'contacts_workspace_phone_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique('contacts_workspace_phone_unique');
            // Restore the old non-unique index
            $table->index(['workspace_id', 'phone'], 'contacts_workspace_id_phone_index');
        });
    }
};
