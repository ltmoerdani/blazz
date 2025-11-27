<?php

namespace App\Console\Commands;

use App\Models\Chat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillChatProviderType extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chats:backfill-provider-type
                            {--chunk=5000 : Number of records to process per chunk}
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill provider_type and chat_type for existing chats (default: meta & private)';

    /**
     * Execute the console command.
     *
     * Strategy: Chunk-based backfill untuk avoid long table locks
     * - Process 5000 rows per chunk (configurable)
     * - Set provider_type = 'meta' for existing chats
     * - Set chat_type = 'private' for existing chats
     * - Show progress bar for visibility
     *
     * @return int
     */
    public function handle()
    {
        $chunkSize = (int) $this->option('chunk');
        $isDryRun = $this->option('dry-run');

        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║  Backfill Chat Provider Type & Chat Type                  ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Count total chats to backfill
        $this->info('📊 Analyzing database...');

        $totalNullProvider = Chat::whereNull('provider_type')->count();
        $totalNullChatType = Chat::whereNull('chat_type')->count();
        $totalToBackfill = max($totalNullProvider, $totalNullChatType);

        if ($totalToBackfill === 0) {
            $this->info('✅ No chats need backfilling. All chats already have provider_type and chat_type set.');
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info("Found chats to backfill:");
        $this->line("  • Chats without provider_type: {$totalNullProvider}");
        $this->line("  • Chats without chat_type: {$totalNullChatType}");
        $this->line("  • Total chats to process: {$totalToBackfill}");
        $this->line("  • Chunk size: {$chunkSize} chats per batch");
        $this->newLine();

        if (!$isDryRun && !$this->confirm('Do you want to proceed with backfill?', true)) {
            $this->warn('⚠️  Backfill cancelled by user.');
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('🚀 Starting backfill process...');
        $this->newLine();

        $bar = $this->output->createProgressBar($totalToBackfill);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
        $bar->setMessage('Initializing...');

        $processed = 0;
        $updated = 0;
        $startTime = now();

        try {
            // Process in chunks to avoid long table locks
            Chat::whereNull('provider_type')
                ->orWhereNull('chat_type')
                ->chunkById($chunkSize, function ($chats) use ($bar, &$processed, &$updated, $isDryRun, $chunkSize, $startTime) {
                    $chunkStartTime = microtime(true);

                    if (!$isDryRun) {
                        // Update chunk with transaction
                        DB::transaction(function () use ($chats, &$updated) {
                            $chatIds = $chats->pluck('id')->toArray();

                            $updatedCount = Chat::whereIn('id', $chatIds)
                                ->update([
                                    'provider_type' => DB::raw("COALESCE(provider_type, 'meta')"),
                                    'chat_type' => DB::raw("COALESCE(chat_type, 'private')"),
                                ]);

                            $updated += $updatedCount;
                        });
                    } else {
                        // Dry run - just count
                        $updated += $chats->count();
                    }

                    $processed += $chats->count();
                    $chunkTime = round((microtime(true) - $chunkStartTime) * 1000, 2);

                    $bar->advance($chats->count());
                    $bar->setMessage("Processed {$processed} chats (chunk: {$chunkTime}ms)");

                    // Log progress every 10 chunks
                    if (($processed % ($chunkSize * 10)) === 0) {
                        Log::channel('whatsapp')->info('Backfill progress', [
                            'processed' => $processed,
                            'updated' => $updated,
                            'elapsed_seconds' => now()->diffInSeconds($startTime),
                        ]);
                    }
                });

            $bar->finish();

        } catch (\Exception $e) {
            $this->newLine(2);
            $this->error('❌ Backfill failed: ' . $e->getMessage());

            Log::channel('whatsapp')->error('Backfill failed', [
                'processed' => $processed,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }

        $duration = now()->diffInSeconds($startTime);
        $rate = $processed > 0 ? round($processed / max($duration, 1), 2) : 0;

        $this->newLine(2);
        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║  Backfill Completed Successfully                          ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();
        $this->line("  • Total chats processed: {$processed}");
        $this->line("  • Total chats updated: {$updated}");
        $this->line("  • Duration: {$duration} seconds");
        $this->line("  • Processing rate: {$rate} chats/second");
        $this->newLine();

        if (!$isDryRun) {
            // Verify backfill success
            $this->info('🔍 Verifying backfill...');

            $remainingNull = Chat::whereNull('provider_type')->count();
            $remainingNullType = Chat::whereNull('chat_type')->count();

            if ($remainingNull === 0 && $remainingNullType === 0) {
                $this->info('✅ Verification passed: All chats have provider_type and chat_type set.');
            } else {
                $this->warn("⚠️  Warning: {$remainingNull} chats still have NULL provider_type, {$remainingNullType} still have NULL chat_type");
                $this->warn('   You may need to run the backfill again.');
            }

            $this->newLine();
            $this->info('📌 Next Steps:');
            $this->line('  1. Run indexes migration: php artisan migrate --path=database/migrations/2025_10_22_000002_add_chat_indexes.php');
            $this->line('  2. Verify query performance: Run EXPLAIN on getChatList queries');
            $this->newLine();

            Log::channel('whatsapp')->info('Backfill completed', [
                'processed' => $processed,
                'updated' => $updated,
                'duration_seconds' => $duration,
                'rate_per_second' => $rate,
            ]);
        } else {
            $this->info('✅ Dry run completed. No changes were made.');
            $this->info('   Run without --dry-run to apply changes.');
        }

        return Command::SUCCESS;
    }
}
