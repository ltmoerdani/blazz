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
            // Add source_session_id to track which WhatsApp session created this contact
            $table->unsignedBigInteger('source_session_id')->nullable()->after('workspace_id');

            // Add source_type to distinguish between 'meta' and 'webjs'
            if (!Schema::hasColumn('contacts', 'source_type')) {
                $table->string('source_type', 20)->default('meta')->after('source_session_id');
            }

            // Add foreign key constraint
            $table->foreign('source_session_id')
                  ->references('id')
                  ->on('whatsapp_sessions')
                  ->onDelete('set null');

            // Add index for performance
            $table->index(['workspace_id', 'source_session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['source_session_id']);
            $table->dropIndex(['workspace_id', 'source_session_id']);
            $table->dropColumn(['source_session_id', 'source_type']);
        });
    }
};
