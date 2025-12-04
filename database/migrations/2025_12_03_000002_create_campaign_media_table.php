<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign Media Pivot Table
 * 
 * Links campaigns to their media files with usage context.
 * Supports multiple media per campaign (header, body attachments, etc.)
 * 
 * @see docs/campaign/media/01-technical-specification.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('media_id');
            $table->string('usage_type', 50)->default('header'); // header, body, attachment
            $table->json('parameters')->nullable(); // WhatsApp API parameters
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('campaign_id')
                  ->references('id')
                  ->on('campaigns')
                  ->onDelete('cascade');
                  
            $table->foreign('media_id')
                  ->references('id')
                  ->on('chat_media')
                  ->onDelete('cascade');
            
            // Unique constraint: one media per usage type per campaign
            $table->unique(['campaign_id', 'media_id', 'usage_type'], 'campaign_media_unique');
            
            // Index for reverse lookup
            $table->index('media_id', 'idx_campaign_media_media');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_media');
    }
};
