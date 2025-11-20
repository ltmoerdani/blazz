<?php

namespace App\Console\Commands;

use App\Models\WhatsAppAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sync WhatsApp Sessions with Node.js Service
 *
 * This command syncs the database status with the actual Node.js service status.
 * Fixes the issue where database shows "connected" but Node.js has 0 sessions.
 *
 * Usage:
 *   php artisan whatsapp:sync-sessions
 *   php artisan whatsapp:sync-sessions --auto-reconnect
 */
class SyncWhatsAppSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:sync-sessions
                          {--auto-reconnect : Automatically reconnect disconnected sessions}
                          {--workspace= : Sync only specific workspace ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync WhatsApp session status between database and Node.js service';

    private $nodeServiceUrl;
    private $apiKey;

    public function __construct()
    {
        parent::__construct();
        $this->nodeServiceUrl = config('whatsapp.node_service_url');
        $this->apiKey = config('whatsapp.node_api_key');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Starting WhatsApp session synchronization...');

        try {
            // Get Node.js service health
            $nodeHealth = $this->getNodeServiceHealth();

            if (!$nodeHealth) {
                $this->error('❌ Cannot connect to Node.js service at ' . $this->nodeServiceUrl);
                return 1;
            }

            $this->info("✅ Node.js service is healthy (uptime: {$nodeHealth['uptime']}s)");
            $this->info("   Sessions in Node.js: {$nodeHealth['sessions']['total']} total, {$nodeHealth['sessions']['connected']} connected");

            // Get active sessions from Node.js
            $activeSessions = $this->getActiveNodeSessions();

            // Get all database sessions
            $query = WhatsAppAccount::query();
            
            if ($workspaceId = $this->option('workspace')) {
                $query->where('workspace_id', $workspaceId);
            }

            $dbSessions = $query->whereIn('status', ['connected', 'authenticated', 'qr_scanning'])->get();

            $this->info("\n📊 Database Analysis:");
            $this->info("   Sessions in database: {$dbSessions->count()} active");

            // Analyze mismatches
            $mismatches = 0;
            $reconnected = 0;
            $updated = 0;

            foreach ($dbSessions as $session) {
                $existsInNode = in_array($session->session_id, $activeSessions);
                
                if (!$existsInNode && $session->status === 'connected') {
                    $mismatches++;
                    
                    $this->warn("\n⚠️  Mismatch found:");
                    $this->line("   Session ID: {$session->session_id}");
                    $this->line("   Phone: {$session->phone_number}");
                    $this->line("   Database status: {$session->status}");
                    $this->line("   Node.js status: not found");

                    // Auto-reconnect if flag is set
                    if ($this->option('auto-reconnect')) {
                        $this->line("   🔄 Attempting auto-reconnect...");
                        
                        if ($this->attemptReconnect($session)) {
                            $reconnected++;
                            $this->info("   ✅ Reconnection initiated successfully");
                        } else {
                            // Update to disconnected
                            $session->update([
                                'status' => 'disconnected',
                                'metadata' => array_merge($session->metadata ?? [], [
                                    'auto_disconnected_at' => now()->toISOString(),
                                    'reason' => 'Node.js session not found during sync'
                                ])
                            ]);
                            $updated++;
                            $this->line("   📝 Updated database status to 'disconnected'");
                        }
                    } else {
                        // Just update status
                        $session->update([
                            'status' => 'disconnected',
                            'metadata' => array_merge($session->metadata ?? [], [
                                'auto_disconnected_at' => now()->toISOString(),
                                'reason' => 'Node.js session not found during sync'
                            ])
                        ]);
                        $updated++;
                        $this->line("   📝 Updated database status to 'disconnected'");
                    }
                }
            }

            // Summary
            $this->info("\n" . str_repeat('=', 60));
            $this->info('📈 Synchronization Summary:');
            $this->info("   Mismatches found: {$mismatches}");
            $this->info("   Sessions reconnected: {$reconnected}");
            $this->info("   Statuses updated: {$updated}");
            $this->info(str_repeat('=', 60));

            if ($mismatches === 0) {
                $this->info("\n✅ All sessions are in sync!");
            } else {
                $this->warn("\n⚠️  Found {$mismatches} session(s) out of sync");
                if (!$this->option('auto-reconnect')) {
                    $this->info("\n💡 Tip: Use --auto-reconnect flag to automatically reconnect disconnected sessions");
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Sync failed: {$e->getMessage()}");
            Log::error('WhatsApp session sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Get Node.js service health
     */
    private function getNodeServiceHealth(): ?array
    {
        try {
            $response = Http::timeout(5)->get("{$this->nodeServiceUrl}/health");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get Node.js health', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get active sessions from Node.js
     */
    private function getActiveNodeSessions(): array
    {
        try {
            $response = Http::timeout(10)
                ->get("{$this->nodeServiceUrl}/api/sessions", [
                    'api_key' => $this->apiKey,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $sessions = $data['sessions'] ?? [];
                
                // Extract session_id from array of objects
                return array_map(function($session) {
                    return $session['session_id'] ?? null;
                }, $sessions);
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Failed to get Node.js sessions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Attempt to reconnect a session
     */
    private function attemptReconnect(WhatsAppAccount $session): bool
    {
        try {
            // Use create endpoint instead of reconnect since session doesn't exist in Node.js
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->nodeServiceUrl}/api/sessions", [
                    'session_id' => $session->session_id,
                    'workspace_id' => $session->workspace_id,
                    'account_id' => $session->id,
                    'api_key' => $this->apiKey,
                ]);

            if ($response->successful()) {
                $session->update([
                    'status' => 'qr_scanning',
                    'metadata' => array_merge($session->metadata ?? [], [
                        'auto_reconnect_at' => now()->toISOString(),
                        'reconnect_triggered_by' => 'sync_command'
                    ])
                ]);

                Log::info('Session reconnection initiated', [
                    'session_id' => $session->session_id,
                    'workspace_id' => $session->workspace_id,
                    'response' => $response->json()
                ]);

                return true;
            }

            Log::warning('Session reconnection failed', [
                'session_id' => $session->session_id,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to reconnect session', [
                'session_id' => $session->session_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
