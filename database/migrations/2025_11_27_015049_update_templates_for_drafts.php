<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Update templates table for Scenario A (Draft-First)
 * 
 * Purpose: Make meta_id nullable to support draft templates that are not yet
 * submitted to Meta API. This enables users to create templates without
 * requiring WhatsApp connection first.
 * 
 * @see docs/templates/template-independence-implementation.md
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // Make meta_id nullable for draft templates
            // Draft templates don't have a Meta ID until published
            $table->string('meta_id', 128)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // Revert: meta_id back to required
            // Note: This will fail if null values exist in the database
            $table->string('meta_id', 128)->nullable(false)->change();
        });
    }
};
