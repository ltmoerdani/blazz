<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PHASE-3 Enterprise Security: Audit Logs & Security Incidents Tables
     * GDPR-compliant audit logging untuk comprehensive activity tracking
     */
    public function up(): void
    {
        // Audit logs table untuk comprehensive activity tracking
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->string('id', 100)->primary(); // Custom request ID
            $table->string('event_type', 50)->index(); // Type of event
            $table->string('endpoint', 100)->nullable()->index(); // Route name
            $table->string('method', 10); // HTTP method
            $table->text('url'); // Full URL
            $table->string('ip_address', 45)->index(); // IPv4/IPv6 support
            $table->text('user_agent')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('session_id', 100)->nullable()->index();
            $table->json('request_data')->nullable(); // Request details
            $table->integer('status_code')->nullable()->index();
            $table->bigInteger('response_size')->nullable(); // Response size in bytes
            $table->decimal('execution_time', 10, 3)->nullable(); // Execution time in ms
            $table->bigInteger('memory_usage')->nullable(); // Memory usage in bytes
            $table->boolean('success')->nullable()->index();
            $table->string('event_result', 20)->nullable()->index(); // success/client_error/server_error
            $table->timestamps();
            
            // Indexes untuk performance dan searching
            $table->index(['user_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['success', 'created_at']);
        });
        
        // Security incidents table untuk dedicated security monitoring
        Schema::create('security_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('audit_id', 100)->index(); // Reference ke audit_logs
            $table->string('incident_type', 50)->index(); // unauthorized_access, rate_limit_exceeded, etc.
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->index();
            $table->string('ip_address', 45)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('endpoint', 100)->nullable()->index();
            $table->json('details')->nullable(); // Additional incident details
            $table->boolean('resolved')->default(false)->index();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('audit_id')->references('id')->on('audit_logs')->onDelete('cascade');
            
                        // Indexes for security_incidents
            $table->index(['severity', 'resolved', 'created_at'], 'security_severity_resolved_created_idx');
            $table->index(['incident_type', 'created_at'], 'security_type_created_idx');
            $table->index(['ip_address', 'severity', 'created_at'], 'security_ip_severity_created_idx');
        });
        
        // Rate limiting tracking table
        Schema::create('rate_limit_violations', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('rate_limit_type', 50)->index(); // ip, user, endpoint, organization
            $table->string('endpoint', 100)->nullable()->index();
            $table->integer('attempts')->default(1);
            $table->integer('limit_threshold');
            $table->integer('window_duration'); // Window duration in seconds
            $table->timestamp('first_violation')->nullable();
            $table->timestamp('last_violation')->nullable();
            $table->boolean('blocked')->default(false)->index();
            $table->timestamp('block_expires_at')->nullable();
            $table->timestamps();
            
                        // Indexes for rate_limit_violations
            $table->index(['ip_address', 'rate_limit_type', 'created_at'], 'rate_limit_ip_type_created_idx');
            $table->index(['blocked', 'block_expires_at'], 'rate_limit_blocked_expires_idx');
            $table->index('last_violation', 'rate_limit_last_violation_idx');
        });
        
        // Authentication events table untuk detailed auth tracking
        Schema::create('authentication_events', function (Blueprint $table) {
            $table->id();
            $table->string('audit_id', 100)->nullable()->index(); // Reference ke audit_logs
            $table->enum('event_type', ['login_attempt', 'login_success', 'login_failure', 'logout', 'password_reset', 'account_locked'])->index();
            $table->string('email', 255)->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->index();
            $table->text('user_agent')->nullable();
            $table->string('failure_reason', 100)->nullable(); // invalid_credentials, account_locked, etc.
            $table->boolean('suspicious')->default(false)->index(); // Flag for suspicious activity
            $table->json('additional_data')->nullable(); // 2FA attempts, device info, etc.
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('audit_id')->references('id')->on('audit_logs')->onDelete('set null');
            
                        // Indexes for authentication_events
            $table->index(['email', 'event_type', 'created_at'], 'auth_email_type_created_idx');
            $table->index(['ip_address', 'event_type', 'created_at'], 'auth_ip_type_created_idx');
            $table->index(['suspicious', 'created_at'], 'auth_suspicious_created_idx');
            $table->index(['user_id', 'event_type', 'created_at'], 'auth_user_type_created_idx');
        });
        
        // Data access logs untuk GDPR compliance
        Schema::create('data_access_logs', function (Blueprint $table) {
            $table->id();
            $table->string('audit_id', 100)->nullable()->index(); // Reference ke audit_logs
            $table->unsignedBigInteger('user_id')->index(); // User who accessed data
            $table->unsignedBigInteger('target_user_id')->nullable()->index(); // User whose data was accessed
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('data_type', 50)->index(); // user_profile, chat_history, contact_info, etc.
            $table->string('access_type', 20)->index(); // read, export, modify, delete
            $table->string('data_source', 100)->nullable(); // Table or endpoint accessed
            $table->json('accessed_fields')->nullable(); // Specific fields accessed
            $table->string('purpose', 200)->nullable(); // Business purpose for access
            $table->boolean('consent_given')->default(false)->index(); // GDPR consent tracking
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('audit_id')->references('id')->on('audit_logs')->onDelete('set null');
            
                        // Indexes for data_access_logs
            $table->index(['target_user_id', 'data_type', 'created_at'], 'data_target_type_created_idx');
            $table->index(['organization_id', 'access_type', 'created_at'], 'data_org_access_created_idx');
            $table->index(['consent_given', 'created_at'], 'data_consent_created_idx');
            $table->index(['user_id', 'access_type', 'created_at'], 'data_user_access_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_access_logs');
        Schema::dropIfExists('authentication_events');
        Schema::dropIfExists('rate_limit_violations');
        Schema::dropIfExists('security_incidents');
        Schema::dropIfExists('audit_logs');
    }
};
