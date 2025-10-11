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
            // Add external_id column for message deduplication
            $table->string('external_id', 255)->nullable()->after('wam_id');
            
            // Add index for external_id to improve message deduplication performance
            $table->index('external_id', 'idx_chats_external_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropIndex('idx_chats_external_id');
            $table->dropColumn('external_id');
        });
    }
};
