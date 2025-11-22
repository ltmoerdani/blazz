<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Workspace Settings Seeder
 * 
 * Seeds default workspace settings for existing workspaces.
 * Run after migration: php artisan db:seed --class=WorkspaceSettingsSeeder
 */
class WorkspaceSettingsSeeder extends Seeder
{
    /**
     * Default workspace settings template
     */
    private array $defaultSettings = [
        // Appearance
        [
            'key' => 'workspace_name',
            'value' => null, // Will use workspace name
            'type' => 'string',
            'description' => 'Custom workspace display name',
        ],
        [
            'key' => 'workspace_logo',
            'value' => null,
            'type' => 'string',
            'description' => 'Workspace logo URL',
        ],
        [
            'key' => 'theme',
            'value' => 'light',
            'type' => 'string',
            'description' => 'UI theme (light/dark)',
        ],

        // Communication
        [
            'key' => 'default_reply_time',
            'value' => '24',
            'type' => 'integer',
            'description' => 'Default reply time in hours',
        ],
        [
            'key' => 'auto_close_inactive_chats',
            'value' => 'false',
            'type' => 'boolean',
            'description' => 'Automatically close inactive chats',
        ],
        [
            'key' => 'inactive_chat_timeout',
            'value' => '72',
            'type' => 'integer',
            'description' => 'Hours before chat is marked inactive',
        ],

        // Notifications
        [
            'key' => 'email_notifications',
            'value' => 'true',
            'type' => 'boolean',
            'description' => 'Enable email notifications',
        ],
        [
            'key' => 'push_notifications',
            'value' => 'true',
            'type' => 'boolean',
            'description' => 'Enable push notifications',
        ],

        // Features
        [
            'key' => 'enable_campaigns',
            'value' => 'true',
            'type' => 'boolean',
            'description' => 'Enable campaign features',
        ],
        [
            'key' => 'enable_auto_replies',
            'value' => 'true',
            'type' => 'boolean',
            'description' => 'Enable auto-reply features',
        ],
        [
            'key' => 'enable_templates',
            'value' => 'true',
            'type' => 'boolean',
            'description' => 'Enable message templates',
        ],

        // Limits
        [
            'key' => 'max_contacts',
            'value' => '10000',
            'type' => 'integer',
            'description' => 'Maximum number of contacts',
        ],
        [
            'key' => 'max_campaigns_per_month',
            'value' => '100',
            'type' => 'integer',
            'description' => 'Maximum campaigns per month',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all workspaces
        $workspaces = DB::table('workspaces')->get();

        if ($workspaces->isEmpty()) {
            $this->command->info('No workspaces found. Skipping workspace settings seeding.');
            return;
        }

        $this->command->info("Found {$workspaces->count()} workspaces. Seeding default settings...");

        $insertedCount = 0;

        foreach ($workspaces as $workspace) {
            foreach ($this->defaultSettings as $setting) {
                // Check if setting already exists
                $exists = DB::table('workspace_settings')
                    ->where('workspace_id', $workspace->id)
                    ->where('key', $setting['key'])
                    ->exists();

                if (!$exists) {
                    $value = $setting['value'];

                    // Use workspace name if setting is workspace_name and value is null
                    if ($setting['key'] === 'workspace_name' && $value === null) {
                        $value = $workspace->name;
                    }

                    DB::table('workspace_settings')->insert([
                        'workspace_id' => $workspace->id,
                        'key' => $setting['key'],
                        'value' => $value,
                        'type' => $setting['type'],
                        'description' => $setting['description'],
                        'is_encrypted' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $insertedCount++;
                }
            }
        }

        $this->command->info("✅ Seeded {$insertedCount} workspace settings across {$workspaces->count()} workspaces.");
    }
}
