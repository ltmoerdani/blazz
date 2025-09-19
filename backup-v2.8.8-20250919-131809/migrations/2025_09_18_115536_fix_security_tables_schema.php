<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix P3.2 Security Tables Schema Issues
     * - audit_logs: Use request_id as primary key dengan auto-increment fallback
     * - security_incidents: Add missing organization_id column for multi-tenant logging
     */
    public function up(): void
    {
        // First, drop the foreign key constraint temporarily
        Schema::table('security_incidents', function (Blueprint $table) {
            $table->dropForeign(['audit_id']);
        });
        
        // Fix audit_logs table structure
        Schema::table('audit_logs', function (Blueprint $table) {
            // Add missing request_id column yang diexpect middleware
            if (!Schema::hasColumn('audit_logs', 'request_id')) {
                $table->string('request_id', 100)->nullable()->after('id');
                $table->index('request_id');
            }
        });
        
        // Fix security_incidents table - add organization_id for multi-tenant
        Schema::table('security_incidents', function (Blueprint $table) {
            if (!Schema::hasColumn('security_incidents', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('user_id')->index();
            }
            
            // Add additional indexes untuk performance
            $table->index(['organization_id', 'incident_type', 'created_at'], 'security_org_type_created_idx');
        });
        
        // Restore foreign key with proper constraint
        Schema::table('security_incidents', function (Blueprint $table) {
            $table->foreign('audit_id')->references('id')->on('audit_logs')->onDelete('cascade');
        });
        
        // Ensure rate_limit_violations has organization_id
        Schema::table('rate_limit_violations', function (Blueprint $table) {
            if (!Schema::hasColumn('rate_limit_violations', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('user_id')->index();
            }
        });
        
        // Fix authentication_events - ensure audit_id relationship is proper
        Schema::table('authentication_events', function (Blueprint $table) {
            if (!Schema::hasColumn('authentication_events', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('user_id')->index();
            }
        });
        
        // Fix data_access_logs - ensure all required indexes exist
        if (Schema::hasTable('data_access_logs')) {
            Schema::table('data_access_logs', function (Blueprint $table) {
                // Add composite index untuk better performance
                $table->index(['organization_id', 'data_type', 'created_at'], 'data_access_organization_created_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('audit_logs', 'request_id')) {
                $table->dropIndex(['request_id']);
                $table->dropColumn('request_id');
            }
        });
        
        Schema::table('security_incidents', function (Blueprint $table) {
            if (Schema::hasColumn('security_incidents', 'organization_id')) {
                $table->dropIndex('security_org_type_created_idx');
                $table->dropColumn('organization_id');
            }
        });
        
        Schema::table('rate_limit_violations', function (Blueprint $table) {
            if (Schema::hasColumn('rate_limit_violations', 'organization_id')) {
                $table->dropColumn('organization_id');
            }
        });
        
        Schema::table('authentication_events', function (Blueprint $table) {
            if (Schema::hasColumn('authentication_events', 'organization_id')) {
                $table->dropColumn('organization_id');
            }
        });
    }
};
