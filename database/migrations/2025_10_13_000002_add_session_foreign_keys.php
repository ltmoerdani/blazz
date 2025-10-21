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
            // Add whatsapp_session_id foreign key (nullable for backward compatibility)
            $table->foreignId('whatsapp_session_id')->nullable()->after('workspace_id');

            // Foreign key constraint
            $table->foreign('whatsapp_session_id')->references('id')->on('whatsapp_sessions')->onDelete('set null');

            // Index for performance
            $table->index(['whatsapp_session_id', 'created_at']);
        });

        Schema::table('campaign_logs', function (Blueprint $table) {
            // Add whatsapp_session_id foreign key (nullable for backward compatibility)
            $table->foreignId('whatsapp_session_id')->nullable()->after('contact_id');

            // Foreign key constraint
            $table->foreign('whatsapp_session_id')->references('id')->on('whatsapp_sessions')->onDelete('set null');

            // Index for performance
            $table->index(['campaign_id', 'whatsapp_session_id']);
        });

        // Create contact_sessions junction table for tracking multi-number interactions
        Schema::create('contact_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->onDelete('cascade');
            $table->foreignId('whatsapp_session_id')->constrained('whatsapp_sessions')->onDelete('cascade');
            $table->timestamp('first_interaction_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->integer('total_messages')->default(0);
            $table->timestamps();

            // Unique constraint to prevent duplicate contact-session pairs
            $table->unique(['contact_id', 'whatsapp_session_id']);

            // Indexes for performance
            $table->index(['contact_id', 'last_interaction_at']);
            $table->index(['whatsapp_session_id', 'last_interaction_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_session_id']);
            $table->dropColumn('whatsapp_session_id');
        });

        Schema::table('campaign_logs', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_session_id']);
            $table->dropColumn('whatsapp_session_id');
        });

        Schema::dropIfExists('contact_sessions');
    }
};
