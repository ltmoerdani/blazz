<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Media Storage Enhancement Migration for Campaigns
 * 
 * This migration enhances the chat_media table to support:
 * - UUID for secure public references
 * - Multiple file versions (original, compressed, thumbnail, webp)
 * - Processing status tracking for async operations
 * - Workspace isolation (multi-tenancy)
 * - Metadata JSON for dimensions, compression info, etc.
 * 
 * @see docs/campaign/media/01-technical-specification.md
 * @verified 2025-12-03 from live database scan
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_media', function (Blueprint $table) {
            // 1. Add UUID (generate for existing records)
            $table->uuid('uuid')->after('id')->nullable();
            
            // 2. Rename path to original_path
            $table->renameColumn('path', 'original_path');
        });
        
        // Separate schema call for new columns (after rename)
        Schema::table('chat_media', function (Blueprint $table) {
            // 3. Add new path columns for multiple versions
            $table->string('compressed_path', 512)->nullable()->after('original_path');
            $table->string('thumbnail_path', 512)->nullable()->after('compressed_path');
            $table->string('webp_path', 512)->nullable()->after('thumbnail_path');
            $table->string('cdn_url', 512)->nullable()->after('webp_path');
            
            // 4. Add processing columns for async operations
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])
                  ->default('completed') // Existing records are considered completed
                  ->after('cdn_url');
            $table->timestamp('processed_at')->nullable()->after('processing_status');
            $table->text('processing_error')->nullable()->after('processed_at');
            
            // 5. Add metadata JSON for dimensions, compression info, etc.
            $table->json('metadata')->nullable()->after('processing_error');
            
            // 6. Add workspace_id for multi-tenancy
            $table->unsignedBigInteger('workspace_id')->nullable()->after('metadata');
            
            // 7. Add timestamps
            $table->timestamp('updated_at')->nullable()->after('created_at');
            $table->softDeletes();
        });
        
        // Generate UUIDs for existing records
        DB::table('chat_media')->whereNull('uuid')->orderBy('id')->each(function ($media) {
            DB::table('chat_media')
                ->where('id', $media->id)
                ->update(['uuid' => Str::uuid()->toString()]);
        });
        
        // Make UUID required and unique after populating
        Schema::table('chat_media', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
        
        // Change size from varchar to bigint
        // Note: Existing data may have string values like "1024" - they convert automatically
        DB::statement('ALTER TABLE chat_media MODIFY size BIGINT UNSIGNED NOT NULL DEFAULT 0');
        
        // Update location enum - IMPORTANT: Keep 'amazon' for backward compatibility
        // Current live DB has: enum('local','amazon')
        // Step 1: Add new values while preserving 'amazon'
        DB::statement("ALTER TABLE chat_media MODIFY location ENUM('local', 'amazon', 's3', 's3_cdn') DEFAULT 'local'");
        
        // Add indexes for performance
        Schema::table('chat_media', function (Blueprint $table) {
            $table->index(['workspace_id'], 'idx_chat_media_workspace');
            $table->index(['processing_status', 'created_at'], 'idx_chat_media_processing');
            $table->index(['type', 'workspace_id'], 'idx_chat_media_type_workspace');
        });
        
        // Note: Foreign key will be added after data migration populates workspace_id
        // Run: php artisan media:migrate-workspace to populate workspace_id from related tables
    }

    public function down(): void
    {
        Schema::table('chat_media', function (Blueprint $table) {
            // Remove indexes
            $table->dropIndex('idx_chat_media_workspace');
            $table->dropIndex('idx_chat_media_processing');
            $table->dropIndex('idx_chat_media_type_workspace');
            
            // Remove soft delete
            $table->dropSoftDeletes();
            
            // Remove columns
            $table->dropColumn([
                'uuid',
                'compressed_path',
                'thumbnail_path',
                'webp_path',
                'cdn_url',
                'processing_status',
                'processed_at',
                'processing_error',
                'metadata',
                'workspace_id',
                'updated_at',
            ]);
            
            // Rename back
            $table->renameColumn('original_path', 'path');
        });
        
        // Revert size to varchar
        DB::statement('ALTER TABLE chat_media MODIFY size VARCHAR(128) NOT NULL');
        
        // Revert location enum
        DB::statement("ALTER TABLE chat_media MODIFY location ENUM('local', 'amazon') DEFAULT 'local'");
    }
};
