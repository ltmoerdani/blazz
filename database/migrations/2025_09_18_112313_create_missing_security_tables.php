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
        // Skip authentication_events and data_access_logs as they already exist
        // Create only missing security tables

        // Create security_assessments table
        if (!Schema::hasTable('security_assessments')) {
            Schema::create('security_assessments', function (Blueprint $table) {
                $table->id();
                $table->string('ip_address', 45);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('workspace_id')->nullable();
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
                $table->index('workspace_id');
            });
        }

        // Create blocked_ips table
        if (!Schema::hasTable('blocked_ips')) {
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
        }

        // Create threat_ips table
        if (!Schema::hasTable('threat_ips')) {
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
