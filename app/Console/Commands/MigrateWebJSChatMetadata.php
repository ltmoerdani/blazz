<?php

namespace App\Console\Commands;

use App\Models\Chat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateWebJSChatMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chats:migrate-webjs-metadata {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate WhatsApp WebJS chat metadata to Meta API compatible format';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
        }

        // Get all WebJS chats that need migration
        $chats = Chat::where('provider_type', 'webjs')
            ->whereNotNull('metadata')
            ->get();

        if ($chats->isEmpty()) {
            $this->info('No WebJS chats found to migrate.');
            return Command::SUCCESS;
        }

        $this->info("Found {$chats->count()} WebJS chats to migrate.");

        $progressBar = $this->output->createProgressBar($chats->count());
        $progressBar->start();

        $migratedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($chats as $chat) {
            try {
                $metadata = is_string($chat->metadata) ? json_decode($chat->metadata, true) : $chat->metadata;

                // Check if already in new format (has 'text' object)
                if (isset($metadata['text']['body']) || !isset($metadata['body'])) {
                    $skippedCount++;
                    $progressBar->advance();
                    continue;
                }

                // Map message type
                $messageType = $metadata['type'] ?? 'chat';
                $metaApiType = ($messageType === 'chat') ? 'text' : $messageType;

                // Normalize to Meta API format
                $normalizedMetadata = [
                    'id' => $metadata['id'] ?? $chat->wam_id,
                    'from' => $metadata['from'] ?? '',
                    'timestamp' => $metadata['timestamp'] ?? time(),
                    'type' => $metaApiType,
                ];

                // Add message content based on type
                if ($metaApiType === 'text' && isset($metadata['body'])) {
                    $normalizedMetadata['text'] = [
                        'body' => $metadata['body']
                    ];
                } elseif ($metaApiType === 'image' && isset($metadata['body'])) {
                    $normalizedMetadata['image'] = [
                        'caption' => $metadata['body']
                    ];
                } elseif ($metaApiType === 'video' && isset($metadata['body'])) {
                    $normalizedMetadata['video'] = [
                        'caption' => $metadata['body']
                    ];
                } elseif ($metaApiType === 'document' && isset($metadata['body'])) {
                    $normalizedMetadata['document'] = [
                        'caption' => $metadata['body']
                    ];
                } elseif (isset($metadata['body'])) {
                    $normalizedMetadata['text'] = [
                        'body' => $metadata['body']
                    ];
                }

                // Preserve WebJS-specific fields
                if (isset($metadata['chat_type']) && $metadata['chat_type'] === 'group') {
                    $normalizedMetadata['_webjs'] = [
                        'chat_type' => 'group',
                        'group_id' => $metadata['group_id'] ?? null,
                        'group_name' => $metadata['group_name'] ?? null,
                        'sender_phone' => $metadata['sender_phone'] ?? null,
                        'sender_name' => $metadata['sender_name'] ?? null,
                    ];
                }

                if (!$dryRun) {
                    $chat->metadata = json_encode($normalizedMetadata);
                    $chat->save();
                }

                $migratedCount++;

            } catch (\Exception $e) {
                $this->error("Error migrating chat ID {$chat->id}: {$e->getMessage()}");
                $errorCount++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info("Migration Summary:");
        $this->table(
            ['Status', 'Count'],
            [
                ['Migrated', $migratedCount],
                ['Skipped (already migrated)', $skippedCount],
                ['Errors', $errorCount],
                ['Total', $chats->count()],
            ]
        );

        if ($dryRun) {
            $this->warn('DRY RUN completed - no changes were made. Run without --dry-run to apply changes.');
        } else {
            $this->info('Migration completed successfully!');
        }

        return Command::SUCCESS;
    }
}
