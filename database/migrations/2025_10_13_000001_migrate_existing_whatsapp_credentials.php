<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing Meta API credentials from workspaces.metadata to whatsapp_sessions table
        $workspaces = DB::table('workspaces')->get();

        foreach ($workspaces as $workspace) {
            $metadata = json_decode($workspace->metadata, true);

            // Check if workspace has WhatsApp Meta API configuration
            if (isset($metadata['whatsapp']) && isset($metadata['whatsapp']['access_token'])) {
                // Create whatsapp_sessions record for Meta API
                DB::table('whatsapp_sessions')->insert([
                    'uuid' => Str::uuid()->toString(),
                    'workspace_id' => $workspace->id,
                    'session_id' => 'meta_' . $workspace->id . '_' . time(), // Unique session ID for Meta API
                    'phone_number' => $metadata['whatsapp']['phone_number_id'] ?? null,
                    'provider_type' => 'meta',
                    'status' => 'connected', // Meta API is already connected
                    'session_data' => json_encode([
                        'access_token' => $metadata['whatsapp']['access_token'] ?? null,
                        'api_version' => $metadata['whatsapp']['api_version'] ?? 'v18.0',
                        'app_id' => $metadata['whatsapp']['app_id'] ?? null,
                        'phone_number_id' => $metadata['whatsapp']['phone_number_id'] ?? null,
                        'waba_id' => $metadata['whatsapp']['waba_id'] ?? null,
                    ]),
                    'is_primary' => true, // Set as primary since it's the existing configuration
                    'is_active' => true,
                    'last_connected_at' => now(),
                    'metadata' => json_encode([
                        'migrated_from' => 'workspace_metadata',
                        'migration_date' => now()->toISOString(),
                        'messages_sent' => 0,
                        'chats_count' => 0,
                        'last_health_check' => now()->toISOString(),
                    ]),
                    'created_by' => $workspace->created_by ?? 1, // Default to first user if not available
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Log migration
                DB::table('migration_logs')->insert([
                    'migration' => 'whatsapp_credentials_migration',
                    'workspace_id' => $workspace->id,
                    'status' => 'completed',
                    'notes' => 'Migrated Meta API credentials to whatsapp_sessions table',
                    'created_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove migrated records (we can't perfectly reverse this without data loss)
        // This is a one-way migration for existing data

        DB::table('migration_logs')
            ->where('migration', 'whatsapp_credentials_migration')
            ->delete();

        // Note: We don't delete whatsapp_sessions records in down() to prevent data loss
        // Manual cleanup would be required if rollback is needed
    }
};
