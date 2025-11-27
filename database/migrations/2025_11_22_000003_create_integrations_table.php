<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create integrations table
 * 
 * Purpose: Store payment gateway and third-party integrations per workspace
 * Previously: No migration existed (only DB::table() queries in code)
 * 
 * @see /docs/architecture/CRITICAL-ISSUES-IMPLEMENTATION-ROADMAP.md
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->unsignedBigInteger('workspace_id')->index();
            
            // Integration details
            $table->string('name', 100)->index(); // RazorPay, Coinbase, PayStack, etc.
            $table->string('provider_type', 50)->index(); // payment, storage, communication, etc.
            $table->string('status', 50)->default('inactive')->index(); // active, inactive, suspended
            
            // Configuration
            $table->json('credentials')->nullable(); // Encrypted API keys, secrets
            $table->json('settings')->nullable(); // Provider-specific settings
            $table->json('metadata')->nullable(); // Additional data
            
            // Limits and usage
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_test_mode')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->integer('usage_count')->default(0);
            
            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Composite unique: one integration type per workspace
            $table->unique(['workspace_id', 'name'], 'integrations_workspace_name_unique');

            // Foreign keys
            $table->foreign('workspace_id')
                ->references('id')
                ->on('workspaces')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indexes for common queries
            $table->index(['workspace_id', 'provider_type', 'is_active'], 'integrations_workspace_provider_idx');
            $table->index(['workspace_id', 'name', 'is_active'], 'integrations_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
