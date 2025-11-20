<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add session storage metadata for tracking session files in shared storage (EFS/NFS).
     * Useful for operations team to verify session file integrity and monitor storage usage.
     */
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            // Session storage metadata
            $table->string('session_storage_path', 500)->nullable()->after('session_data')
                ->comment('Path to session files in shared storage (e.g., /mnt/efs/workspace_1/session_001)');
            
            $table->bigInteger('session_file_size_bytes')->nullable()->after('session_storage_path')
                ->comment('Total size of session files in bytes');
            
            $table->timestamp('session_storage_verified_at')->nullable()->after('session_file_size_bytes')
                ->comment('Last time session files were verified to exist');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'session_storage_path',
                'session_file_size_bytes',
                'session_storage_verified_at'
            ]);
        });
    }
};
