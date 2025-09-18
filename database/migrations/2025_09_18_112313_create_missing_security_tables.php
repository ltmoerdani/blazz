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
        // Create authentication_events table
        Schema::create('authentication_events', function (Blueprint $table) {
            $table->id();
            $table->string('audit_id', 100)->nullable();
            $table->enum('event_type', [
                'login_attempt',
                'login_success',
                'login_failure',
                'logout',
                'password_reset',
                'account_locked'
            ]);
            $table->string('email')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('failure_reason', 100)->nullable();
            $table->boolean('suspicious')->default(false);
            $table->json('additional_data')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['email', 'event_type', 'created_at'], 'auth_email_type_created_idx');
            $table->index(['ip_address', 'event_type', 'created_at'], 'auth_ip_type_created_idx');
            $table->index(['suspicious', 'created_at'], 'auth_suspicious_created_idx');
            $table->index(['user_id', 'event_type', 'created_at'], 'auth_user_type_created_idx');
            $table->index('audit_id');
            $table->index('event_type');
            $table->index('email');
            $table->index('user_id');
            $table->index('ip_address');
            $table->index('suspicious');
        });

        // Create data_access_logs table
        Schema::create('data_access_logs', function (Blueprint $table) {
            $table->id();
            $table->string('audit_id', 100)->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('data_type', 50);
            $table->string('access_type', 20); // read, write, update, delete, export
            $table->string('data_source', 100)->nullable(); // table name, API endpoint, etc.
            $table->json('accessed_fields')->nullable(); // specific fields accessed
            $table->string('purpose', 200)->nullable(); // reason for access
            $table->boolean('consent_given')->default(false); // GDPR compliance
            $table->timestamps();

            // Indexes
            $table->index(['target_user_id', 'data_type', 'created_at'], 'data_target_type_created_idx');
            $table->index(['organization_id', 'access_type', 'created_at'], 'data_org_access_created_idx');
            $table->index(['consent_given', 'created_at'], 'data_consent_created_idx');
            $table->index(['user_id', 'access_type', 'created_at'], 'data_user_access_created_idx');
            $table->index('audit_id');
            $table->index('user_id');
            $table->index('target_user_id');
            $table->index('organization_id');
            $table->index('data_type');
            $table->index('access_type');
            $table->index('consent_given');
        });

        // Create additional security tables
        Schema::create('security_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->integer('risk_score')->default(0);
            $table->json('threats_detected')->nullable();
            $table->json('recommendations')->nullable();
            $table->boolean('blocked')->default(false);
            $table->timestamps();

            // Indexes
            $table->index(['risk_score', 'created_at']);
            $table->index(['blocked', 'created_at']);
            $table->index('ip_address');
            $table->index('user_id');
            $table->index('organization_id');
        });

        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('reason');
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['expires_at', 'blocked_at']);
            $table->index('ip_address');
        });

        Schema::create('threat_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('threat_type', 50);
            $table->string('source', 100); // threat intelligence source
            $table->text('description')->nullable();
            $table->integer('confidence_score')->default(0); // 0-100
            $table->timestamp('first_seen')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['threat_type', 'confidence_score']);
            $table->index(['expires_at', 'last_seen']);
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('threat_ips');
        Schema::dropIfExists('blocked_ips');
        Schema::dropIfExists('security_assessments');
        Schema::dropIfExists('data_access_logs');
        Schema::dropIfExists('authentication_events');
    }
};
