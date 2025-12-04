<?php

namespace App\Console\Commands;

use App\Models\CampaignLog;
use App\Models\Chat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillCampaignLogChatId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaign:backfill-chat-id 
                            {--dry-run : Preview changes without applying them}
                            {--campaign-id= : Only process specific campaign ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill chat_id for campaign logs that have Chat ID stored in metadata';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $campaignId = $this->option('campaign-id');

        $this->info($dryRun ? '🔍 DRY RUN MODE - No changes will be made' : '🚀 Starting backfill...');

        // Query campaign logs with null chat_id but have metadata
        $query = CampaignLog::whereNull('chat_id')
            ->where('status', 'success')
            ->whereNotNull('metadata');

        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }

        $logs = $query->get();

        $this->info("Found {$logs->count()} campaign logs to process");

        $updated = 0;
        $failed = 0;
        $skipped = 0;

        $progressBar = $this->output->createProgressBar($logs->count());
        $progressBar->start();

        foreach ($logs as $log) {
            try {
                $metadata = json_decode($log->metadata, true);
                
                // Try to extract Chat ID from different possible locations in metadata
                $chatId = $metadata['data']['id'] 
                    ?? $metadata['data']['chat']['id'] 
                    ?? $metadata['chat_id']
                    ?? null;

                if (!$chatId) {
                    $skipped++;
                    $progressBar->advance();
                    continue;
                }

                // Verify the Chat exists
                $chat = Chat::find($chatId);
                if (!$chat) {
                    $this->warn("\n  Chat ID {$chatId} not found in database for log {$log->id}");
                    $skipped++;
                    $progressBar->advance();
                    continue;
                }

                if ($dryRun) {
                    $this->line("\n  Would update log {$log->id}: chat_id = {$chatId}");
                } else {
                    // Update the campaign log
                    $log->update(['chat_id' => $chatId]);

                    // Also update metadata to include delivery status from chat if available
                    if ($chat->message_status) {
                        $metadata['message_status'] = $chat->message_status;
                        $metadata['ack_level'] = $chat->ack_level;
                        
                        if ($chat->delivered_at) {
                            $metadata['delivered_at'] = $chat->delivered_at->toISOString();
                        }
                        if ($chat->read_at) {
                            $metadata['read_at'] = $chat->read_at->toISOString();
                        }
                        
                        $log->update(['metadata' => json_encode($metadata)]);
                    }

                    Log::info('Backfilled campaign log chat_id', [
                        'campaign_log_id' => $log->id,
                        'campaign_id' => $log->campaign_id,
                        'chat_id' => $chatId,
                        'message_status' => $chat->message_status
                    ]);
                }

                $updated++;

            } catch (\Exception $e) {
                $this->error("\n  Error processing log {$log->id}: {$e->getMessage()}");
                $failed++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('📊 Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', $logs->count()],
                ['Updated', $updated],
                ['Skipped (no Chat ID in metadata)', $skipped],
                ['Failed', $failed],
            ]
        );

        if ($dryRun && $updated > 0) {
            $this->warn('💡 Run without --dry-run to apply these changes');
        }

        if ($updated > 0 && !$dryRun) {
            $this->info('✅ Backfill completed! Campaign statistics will now include delivery tracking.');
            $this->info('💡 Run UpdateCampaignStatisticsJob to recalculate campaign stats.');
        }

        return Command::SUCCESS;
    }
}
