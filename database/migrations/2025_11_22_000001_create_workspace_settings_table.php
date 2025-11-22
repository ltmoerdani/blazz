<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create workspace_settings table
 * 
 * Purpose: Workspace-specific settings without breaking global settings
 * Strategy: New table approach (Option B) - safest, no breaking changes
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
        Schema::create('workspace_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('key', 100)->index();
            $table->text('value')->nullable();
            $table->string('type', 50)->default('string'); // string, json, boolean, integer
            $table->text('description')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();

            // Composite unique constraint: one key per workspace
            $table->unique(['workspace_id', 'key'], 'workspace_settings_unique');

            // Foreign key constraint
            $table->foreign('workspace_id')
                ->references('id')
                ->on('workspaces')
                ->onDelete('cascade');

            // Indexes for performance
            $table->index(['workspace_id', 'key', 'created_at'], 'workspace_settings_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_settings');
    }
};
