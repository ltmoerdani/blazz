<?php

namespace App\Console\Commands\WhatsApp;

use App\Models\WhatsAppAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Sync Instance URLs Command
 * 
 * Verifies and synchronizes assigned_instance_url for all active WhatsApp sessions.
 * This command proactively detects and fixes instance URL mismatches that can occur
 * after server restarts, instance crashes, or session migrations.
 * 
 * Features:
 * - Scans all active sessions
 * - Verifies each session exists in its assigned instance
 * - Auto-discovers correct instance if mismatch found
 * - Updates database with correct URLs
 * - Invalidates cache for updated sessions
 * - Provides detailed reporting
 * 
 * Usage:
 *   php artisan whatsapp:sync-instance-urls
 *   php artisan whatsapp:sync-instance-urls --force
 *   php artisan whatsapp:sync-instance-urls --dry-run
 * 
 * Scheduling:
 *   Should run every 5 minutes via Laravel scheduler for proactive monitoring
 */
class SyncInstanceUrls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:sync-instance-urls 
                          {--force : Force sync even if last check was recent}
                          {--dry-run : Show what would be changed without updating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify and sync instance URLs for all active WhatsApp sessions';

    /**
     * Available Node.js instances
     *
     * @var array
     */
    protected $instances = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->instances = config('services.whatsapp.nodejs_instances', [
            'http://localhost:3001',
            'http://localhost:3002',
            'http://localhost:3003',
            'http://localhost:3004',
        ]);
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $startTime = microtime(true);
        $dryRun = $this->option('dry-run');
        
        $this->info('========================================');
        $this->info('WhatsApp Instance URL Sync');
        $this->info('========================================');
        $this->newLine();
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }
        
        // Get all active sessions
        $accounts = WhatsAppAccount::where('status', 'connected')
            ->whereNotNull('session_id')
            ->get();
        
        $this->info("Found {$accounts->count()} active session(s) to verify");
        $this->newLine();
        
        // Initialize statistics
        $stats = [
            'verified' => 0,
            'updated' => 0,
            'failed' => 0,
            'unreachable' => 0,
        ];
        
        $updates = [];
        
        // Process each account
        foreach ($accounts as $account) {
            $this->line("Checking Account #{$account->id} ({$account->phone_number})...");
            
            $result = $this->verifyAndUpdateInstance($account, $dryRun);
            $stats[$result['status']]++;
            
            if ($result['status'] === 'updated') {
                $updates[] = $result['details'];
            }
        }
        
        // Display results
        $this->newLine();
        $this->info('========================================');
        $this->info('Sync Results');
        $this->info('========================================');
        $this->newLine();
        
        $this->table(
            ['Status', 'Count'],
            [
                ['Verified (no change needed)', $stats['verified']],
                ['Updated (instance changed)', $stats['updated']],
                ['Failed (session not found)', $stats['failed']],
                ['Unreachable (check manually)', $stats['unreachable']],
            ]
        );
        
        // Show update details
        if (!empty($updates)) {
            $this->newLine();
            $this->info('Update Details:');
            $this->table(
                ['Account', 'Phone', 'Old Instance', 'New Instance'],
                $updates
            );
        }
        
        // Show warnings for failed sessions
        if ($stats['failed'] > 0 || $stats['unreachable'] > 0) {
            $this->newLine();
            $this->warn('⚠️  Some sessions require attention. Check logs for details.');
        }
        
        $executionTime = round(microtime(true) - $startTime, 2);
        
        $this->newLine();
        $this->info("Execution time: {$executionTime}s");
        
        if ($dryRun) {
            $this->newLine();
            $this->warn('DRY RUN COMPLETED - No changes were made');
            $this->info('Run without --dry-run to apply changes');
        }
        
        return Command::SUCCESS;
    }

    /**
     * Verify and update instance for a single account
     *
     * @param WhatsAppAccount $account
     * @param bool $dryRun
     * @return array
     */
    protected function verifyAndUpdateInstance($account, $dryRun)
    {
        $currentUrl = $account->assigned_instance_url;
        $sessionId = $account->session_id;
        
        // 1. First, try current assigned instance
        if ($currentUrl && $this->sessionExistsInInstance($sessionId, $currentUrl)) {
            $this->line("  ✓ Session found in assigned instance: {$currentUrl}");
            
            return [
                'status' => 'verified',
                'details' => null,
            ];
        }
        
        // 2. Current instance failed, scan all instances
        $this->warn("  ✗ Session NOT found in assigned instance: {$currentUrl}");
        $this->line("  Scanning all instances...");
        
        foreach ($this->instances as $instanceUrl) {
            if ($instanceUrl === $currentUrl) {
                continue; // Already checked
            }
            
            if ($this->sessionExistsInInstance($sessionId, $instanceUrl)) {
                $this->info("  ✓ Session found in: {$instanceUrl}");
                
                if (!$dryRun) {
                    // Update database
                    $account->update(['assigned_instance_url' => $instanceUrl]);
                    
                    // Clear cache
                    Cache::forget("whatsapp_instance:{$account->uuid}");
                    Cache::forget("whatsapp_instance:{$sessionId}");
                    
                    // Log the update
                    Log::info('Instance URL updated by sync command', [
                        'command' => 'whatsapp:sync-instance-urls',
                        'account_id' => $account->id,
                        'phone_number' => $account->phone_number,
                        'session_id' => $sessionId,
                        'old_url' => $currentUrl,
                        'new_url' => $instanceUrl,
                    ]);
                    
                    $this->info("  ✓ Database updated");
                } else {
                    $this->warn("  [DRY RUN] Would update: {$currentUrl} → {$instanceUrl}");
                }
                
                return [
                    'status' => 'updated',
                    'details' => [
                        'account' => "#{$account->id}",
                        'phone' => $account->phone_number,
                        'old_instance' => $currentUrl ?: 'null',
                        'new_instance' => $instanceUrl,
                    ],
                ];
            }
        }
        
        // 3. Not found in any instance
        $this->error("  ✗ Session not found in ANY instance");
        
        Log::alert('Session unreachable in all instances', [
            'command' => 'whatsapp:sync-instance-urls',
            'account_id' => $account->id,
            'phone_number' => $account->phone_number,
            'session_id' => $sessionId,
            'last_known_url' => $currentUrl,
            'instances_checked' => $this->instances,
        ]);
        
        return [
            'status' => 'failed',
            'details' => null,
        ];
    }

    /**
     * Check if session exists in instance
     *
     * @param string $sessionId
     * @param string $instanceUrl
     * @return bool
     */
    protected function sessionExistsInInstance($sessionId, $instanceUrl)
    {
        try {
            $apiKey = config('services.whatsapp.api_key', env('LARAVEL_API_TOKEN'));
            
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->withHeaders([
                    'X-API-Key' => $apiKey,
                ])
                ->get("{$instanceUrl}/api/sessions/{$sessionId}/status");
            
            if ($response->successful()) {
                $data = $response->json();
                
                // Check if session is in working/connected state
                $status = $data['status'] ?? $data['data']['status'] ?? null;
                
                // Consider session exists if it's in any of these states
                $validStatuses = ['WORKING', 'connected', 'SCAN_QR_CODE', 'ready', 'STARTING'];
                
                return in_array($status, $validStatuses);
            }
            
            return false;
            
        } catch (\Exception $e) {
            // Instance might be down or session doesn't exist
            Log::debug('Instance check failed during sync', [
                'session_id' => $sessionId,
                'instance_url' => $instanceUrl,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Check health of all instances
     *
     * @return array
     */
    protected function checkInstancesHealth()
    {
        $health = [];
        
        foreach ($this->instances as $instanceUrl) {
            try {
                $start = microtime(true);
                $response = Http::timeout(3)->get("{$instanceUrl}/health");
                $responseTime = round((microtime(true) - $start) * 1000, 2);
                
                $health[$instanceUrl] = [
                    'healthy' => $response->successful(),
                    'status_code' => $response->status(),
                    'response_time_ms' => $responseTime,
                ];
                
            } catch (\Exception $e) {
                $health[$instanceUrl] = [
                    'healthy' => false,
                    'status_code' => 0,
                    'response_time_ms' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $health;
    }

    /**
     * Get session distribution across instances
     *
     * @return array
     */
    protected function getSessionDistribution()
    {
        $distribution = [];
        
        foreach ($this->instances as $instanceUrl) {
            $count = WhatsAppAccount::where('assigned_instance_url', $instanceUrl)
                ->where('status', 'connected')
                ->count();
            
            $distribution[$instanceUrl] = $count;
        }
        
        return $distribution;
    }
}
