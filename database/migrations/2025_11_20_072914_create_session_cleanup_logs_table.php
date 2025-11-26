<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Create audit trail table for session cleanup operations.
     * Useful for debugging and monitoring cleanup behavior.
     */
    public function up(): void
    {
        Schema::create('session_cleanup_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('whatsapp_account_id')->nullable();
            $table->string('action', 50)->comment('cleanup, remove, restore, health_check');
            $table->string('status', 20)->comment('success, failed, skipped');
            $table->text('reason')->nullable()->comment('Why this action was taken');
            $table->json('metadata')->nullable()->comment('Additional context (old status, error, etc)');
            $table->timestamps();
            
            // Indexes
            $table->index('whatsapp_account_id');
            $table->index(['action', 'status']);
            $table->index('created_at');
            
            // Foreign key (soft reference, no cascade)
            $table->foreign('whatsapp_account_id')
                ->references('id')
                ->on('whatsapp_accounts')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_cleanup_logs');
    }
};
