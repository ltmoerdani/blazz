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
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->onDelete('cascade');
            $table->string('session_id')->unique(); // Unique session identifier for Node.js service
            $table->string('phone_number', 50)->nullable();
            $table->enum('provider_type', ['meta', 'webjs'])->default('webjs');
            $table->enum('status', ['qr_scanning', 'authenticated', 'connected', 'disconnected', 'failed'])->default('qr_scanning');
            $table->text('qr_code')->nullable(); // Base64 encoded QR code
            $table->longText('session_data')->nullable(); // Encrypted session data (5-10MB)
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->json('metadata')->nullable(); // Statistics, health metrics, etc.
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['workspace_id', 'status']);
            $table->index(['session_id', 'status']);
            $table->index(['provider_type', 'is_active']);
            $table->index(['workspace_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
};
