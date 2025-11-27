<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add optional workspace_id to security_incidents
 * 
 * Purpose: Allow both workspace-specific and system-wide incident tracking
 * Strategy: Hybrid approach (Option C) - most flexible
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
        Schema::table('security_incidents', function (Blueprint $table) {
            // Only add if column doesn't exist (may be added by previous migration)
            if (!Schema::hasColumn('security_incidents', 'workspace_id')) {
                $table->unsignedBigInteger('workspace_id')->nullable()->after('audit_id')->index();
                
                // Foreign key constraint (nullable for system-wide incidents)
                $table->foreign('workspace_id')
                    ->references('id')
                    ->on('workspaces')
                    ->onDelete('set null'); // Keep incident if workspace deleted
            }

            // Add composite indexes for workspace queries (skip if exists)
            $table->index(['workspace_id', 'severity', 'resolved', 'created_at'], 'security_workspace_severity_idx');
            $table->index(['workspace_id', 'incident_type', 'created_at'], 'security_workspace_type_idx');
        });

        Schema::table('rate_limit_violations', function (Blueprint $table) {
            // Only add if column doesn't exist
            if (!Schema::hasColumn('rate_limit_violations', 'workspace_id')) {
                $table->unsignedBigInteger('workspace_id')->nullable()->after('id')->index();
                
                // Foreign key constraint
                $table->foreign('workspace_id')
                    ->references('id')
                    ->on('workspaces')
                    ->onDelete('set null');
            }

            // Add composite index (skip if exists)
            $table->index(['workspace_id', 'ip_address', 'created_at'], 'rate_limit_workspace_ip_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_incidents', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropIndex('security_workspace_severity_idx');
            $table->dropIndex('security_workspace_type_idx');
            $table->dropColumn('workspace_id');
        });

        Schema::table('rate_limit_violations', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropIndex('rate_limit_workspace_ip_idx');
            $table->dropColumn('workspace_id');
        });
    }
};
