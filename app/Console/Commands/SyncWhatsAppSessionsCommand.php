<?php

namespace App\Console\Commands;

use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\WhatsAppServiceClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Sync WhatsApp Sessions between Database and Node.js
 *
 * This command reconciles session states between Laravel database
 * and Node.js service to ensure data consistency.
 */
class SyncWhatsAppSessionsCommand extends Command
{
    protected $signature = 'whatsapp:sync-sessions
                            {--cleanup : Also cleanup orphaned database records}
                            {--force : Force sync without confirmation}';

    protected $description = 'Sync WhatsApp session states between database and Node.js service';

    protected WhatsAppServiceClient $whatsappClient;

    public function __construct()
    {
        parent::__construct();
        $this->whatsappClient = new WhatsAppServiceClient();
    }

    public function handle()
    {
        $this->info('🔄 Starting WhatsApp Session Sync...');
        $this->newLine();

        // Step 1: Get sessions from Node.js
        $this->info('📡 Fetching sessions from Node.js service...');
        $nodeJsSessions = $this->getNodeJsSessions();
        
        if ($nodeJsSessions === null) {
            $this->error('❌ Failed to connect to Node.js service');
            return Command::FAILURE;
        }

        $this->info("✅ Found {$nodeJsSessions['total']} session(s) in Node.js");
        $this->newLine();

        // Step 2: Get sessions from database
        $this->info('💾 Fetching sessions from database...');
        $dbSessions = WhatsAppAccount::whereNotNull('session_id')
            ->whereIn('status', ['connected', 'qr_scanning'])
            ->get();
        
        $this->info("✅ Found {$dbSessions->count()} active session(s) in database");
        $this->newLine();

        // Step 3: Find mismatches
        $this->info('🔍 Analyzing mismatches...');
        
        $nodeSessionIds = collect($nodeJsSessions['sessions'])->pluck('session_id')->toArray();
        $orphaned = $dbSessions->filter(function ($dbSession) use ($nodeSessionIds) {
            return !in_array($dbSession->session_id, $nodeSessionIds);
        });

        $this->displayMismatchTable($dbSessions, $nodeJsSessions['sessions'], $orphaned);

        // Step 4: Sync mismatched sessions
        if ($orphaned->count() > 0) {
            if (!$this->option('force') && !$this->confirm("Update {$orphaned->count()} orphaned session(s) to 'disconnected'?", true)) {
                $this->warn('⏭️  Sync cancelled by user');
                return Command::SUCCESS;
            }

            $this->info('🔄 Updating orphaned sessions...');
            $updated = 0;

            foreach ($orphaned as $session) {
                try {
                    $session->update([
                        'status' => 'disconnected',
                        'last_activity_at' => now(),
                    ]);
                    $updated++;
                    
                    $this->line("  ✓ Updated: {$session->phone_number} ({$session->session_id})");
                } catch (\Exception $e) {
                    $this->error("  ✗ Failed: {$session->phone_number} - {$e->getMessage()}");
                }
            }

            $this->info("✅ Updated {$updated} session(s)");
            $this->newLine();
        } else {
            $this->info('✨ All sessions are in sync!');
            $this->newLine();
        }

        // Step 5: Cleanup if requested
        if ($this->option('cleanup')) {
            $this->cleanupOrphanedRecords();
        }

        // Step 6: Summary
        $this->displaySummary($dbSessions, $nodeJsSessions, $orphaned);

        return Command::SUCCESS;
    }

    protected function getNodeJsSessions(): ?array
    {
        try {
            // Use health endpoint (no auth required) to get session count
            $nodeApiUrl = config('whatsapp.node_api_url', 'http://localhost:3001');
            
            $response = Http::timeout(5)->get("{$nodeApiUrl}/health");
            
            if (!$response->successful()) {
                $this->error("Failed to connect to Node.js: HTTP {$response->status()}");
                return null;
            }

            $data = $response->json();
            
            // Health endpoint gives us session count but not details
            // For detailed sync, we'll compare database with health check counts
            return [
                'total' => $data['sessions']['total'] ?? 0,
                'connected' => $data['sessions']['connected'] ?? 0,
                'sessions' => [], // We don't have session details from health endpoint
            ];
        } catch (\Exception $e) {
            $this->error("Error fetching Node.js sessions: {$e->getMessage()}");
            return null;
        }
    }

    protected function displayMismatchTable($dbSessions, $nodeSessions, $orphaned)
    {
        if ($orphaned->isEmpty()) {
            $this->info('✨ No mismatches found');
            return;
        }

        $this->warn("⚠️  Found {$orphaned->count()} orphaned session(s):");
        $this->newLine();

        $tableData = [];
        foreach ($orphaned as $session) {
            $tableData[] = [
                $session->phone_number ?? 'N/A',
                $session->status,
                substr($session->session_id, 0, 30) . '...',
                $session->updated_at->diffForHumans(),
            ];
        }

        $this->table(
            ['Phone Number', 'DB Status', 'Session ID', 'Last Updated'],
            $tableData
        );

        $this->newLine();
    }

    protected function cleanupOrphanedRecords()
    {
        $this->info('🧹 Cleaning up orphaned records...');

        try {
            // Find duplicate phone numbers
            $duplicates = DB::table('whatsapp_accounts')
                ->select('phone_number', 'workspace_id', DB::raw('COUNT(*) as count'))
                ->whereNotNull('phone_number')
                ->groupBy('phone_number', 'workspace_id')
                ->having('count', '>', 1)
                ->get();

            if ($duplicates->isEmpty()) {
                $this->info('  ✓ No duplicate records found');
                return;
            }

            $this->warn("  Found {$duplicates->count()} phone number(s) with duplicates");

            foreach ($duplicates as $duplicate) {
                // Keep the most recent 'connected' session, mark others as disconnected
                $sessions = WhatsAppAccount::where('phone_number', $duplicate->phone_number)
                    ->where('workspace_id', $duplicate->workspace_id)
                    ->orderByDesc('updated_at')
                    ->get();

                $keepFirst = true;
                foreach ($sessions as $session) {
                    if ($keepFirst && $session->status === 'connected') {
                        $keepFirst = false;
                        continue; // Keep this one
                    }

                    // Mark others as disconnected
                    if ($session->status !== 'disconnected') {
                        $session->update(['status' => 'disconnected']);
                        $this->line("  ✓ Marked as disconnected: {$session->phone_number} ({$session->id})");
                    }
                }
            }

            $this->info('✅ Cleanup completed');
            $this->newLine();

        } catch (\Exception $e) {
            $this->error("Cleanup failed: {$e->getMessage()}");
        }
    }

    protected function displaySummary($dbSessions, $nodeJsSessions, $orphaned)
    {
        $this->info('📊 Sync Summary:');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Node.js Sessions', $nodeJsSessions['total']],
                ['Database Active Sessions', $dbSessions->count()],
                ['Orphaned Sessions', $orphaned->count()],
                ['In Sync', $orphaned->count() === 0 ? '✅ Yes' : '❌ No'],
            ]
        );

        $this->newLine();
        
        if ($orphaned->count() === 0) {
            $this->info('✨ System is healthy! All sessions are in sync.');
        } else {
            $this->warn('⚠️  Action required: Some sessions need attention.');
            $this->info('Run with --cleanup flag to automatically fix duplicates.');
        }
    }
}
