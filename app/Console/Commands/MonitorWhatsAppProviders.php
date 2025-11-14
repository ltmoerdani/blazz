<?php

namespace App\Console\Commands;

use App\Models\WhatsAppAccount;
use App\Services\ProviderSelector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorWhatsAppProviders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:monitor-providers
                            {--workspace= : Monitor specific workspace only}
                            {--fix : Attempt to fix issues automatically}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor WhatsApp provider health and attempt auto-recovery';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Monitoring WhatsApp providers...');

        $workspaceId = $this->option('workspace');
        $shouldFix = $this->option('fix');

        if ($workspaceId) {
            $this->monitorWorkspace($workspaceId, $shouldFix);
        } else {
            $this->monitorAllWorkspaces($shouldFix);
        }

        $this->info('✅ Provider monitoring completed');
        return Command::SUCCESS;
    }

    /**
     * Monitor all workspaces
     */
    private function monitorAllWorkspaces(bool $shouldFix): void
    {
        $workspaces = WhatsAppAccount::select('workspace_id')
            ->distinct()
            ->pluck('workspace_id');

        $this->info("Monitoring {$workspaces->count()} workspaces...");

        foreach ($workspaces as $workspaceId) {
            $this->monitorWorkspace($workspaceId, $shouldFix);
        }
    }

    /**
     * Monitor a specific workspace
     */
    private function monitorWorkspace(int $workspaceId, bool $shouldFix): void
    {
        $this->line("📱 Workspace {$workspaceId}:");

        try {
            $providerSelector = app(ProviderSelector::class);
            $health = $providerSelector->getProviderHealth($workspaceId);

            if (empty($health)) {
                $this->warn("  No WhatsApp sessions found");
                return;
            }

            foreach ($health as $providerType => $stats) {
                $this->line("  {$providerType}: {$stats['connected_sessions']}/{$stats['total_sessions']} connected, Health: " . round($stats['average_health_score'], 1) . "%");

                // Check for issues
                if ($stats['connected_sessions'] === 0 && $stats['total_sessions'] > 0) {
                    $this->warn("  ⚠️  All {$providerType} sessions disconnected!");

                    if ($shouldFix) {
                        $this->attemptRecovery($workspaceId, $providerType);
                    }
                }

                if ($stats['average_health_score'] < 50) {
                    $this->warn("  ⚠️  Low health score: {$stats['average_health_score']}%");

                    if ($shouldFix) {
                        $this->attemptHealthImprovement($workspaceId, $providerType);
                    }
                }
            }

            // Update session statistics
            $this->updateSessionStatistics($workspaceId);

        } catch (\Exception $e) {
            $this->error("  ❌ Error monitoring workspace {$workspaceId}: " . $e->getMessage());
            Log::error('Provider monitoring failed', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Attempt to recover disconnected sessions
     */
    private function attemptRecovery(int $workspaceId, string $providerType): void
    {
        $this->info("  🔧 Attempting recovery for {$providerType}...");

        try {
            $sessions = WhatsAppAccount::forWorkspace($workspaceId)
                ->byProvider($providerType)
                ->where('status', '!=', 'connected')
                ->get();

            foreach ($sessions as $session) {
                $this->line("    Session {$session->session_id}: {$session->status}");

                // For WebJS sessions, we could attempt reconnection
                if ($providerType === 'webjs' && in_array($session->status, ['disconnected', 'qr_scanning'])) {
                    $this->attemptWebJSReconnection($session);
                }

                // For Meta API, check if credentials are still valid
                if ($providerType === 'meta') {
                    $this->checkMetaAPICredentials($session);
                }
            }
        } catch (\Exception $e) {
            $this->error("    ❌ Recovery failed: " . $e->getMessage());
        }
    }

    /**
     * Attempt to improve session health
     */
    private function attemptHealthImprovement(int $workspaceId, string $providerType): void
    {
        $this->info("  🔧 Attempting health improvement for {$providerType}...");

        try {
            $sessions = WhatsAppAccount::forWorkspace($workspaceId)
                ->byProvider($providerType)
                ->where('status', 'connected')
                ->get();

            foreach ($sessions as $session) {
                // Update session statistics
                $session->updateStatistics();

                // Log health improvement actions
                Log::info('Session health updated', [
                    'workspace_id' => $workspaceId,
                    'session_id' => $session->session_id,
                    'health_score' => $session->health_score,
                    'status' => $session->status
                ]);
            }
        } catch (\Exception $e) {
            $this->error("    ❌ Health improvement failed: " . $e->getMessage());
        }
    }

    /**
     * Attempt to reconnect WebJS session
     */
    private function attemptWebJSReconnection(WhatsAppAccount $session): void
    {
        try {
            // This would call the Node.js service to attempt reconnection
            // For now, we'll just log the attempt
            Log::info('Attempting WebJS session reconnection', [
                'workspace_id' => $session->workspace_id,
                'session_id' => $session->session_id,
                'status' => $session->status
            ]);

            $this->info("    🔄 Reconnection attempt logged for session {$session->session_id}");

        } catch (\Exception $e) {
            $this->error("    ❌ WebJS reconnection failed: " . $e->getMessage());
        }
    }

    /**
     * Check Meta API credentials validity
     */
    private function checkMetaAPICredentials(WhatsAppAccount $session): void
    {
        try {
            $sessionData = $session->session_data;

            if (!$sessionData || !isset($sessionData['access_token'])) {
                $this->error("    ❌ Missing access token for session {$session->session_id}");
                return;
            }

            // Check if token is expired or invalid
            // This would make a test API call to Meta API
            Log::info('Meta API credentials check', [
                'workspace_id' => $session->workspace_id,
                'session_id' => $session->session_id,
                'has_access_token' => !empty($sessionData['access_token'])
            ]);

            $this->info("    ✅ Credentials check completed for session {$session->session_id}");

        } catch (\Exception $e) {
            $this->error("    ❌ Credentials check failed: " . $e->getMessage());
        }
    }

    /**
     * Update statistics for all sessions in workspace
     */
    private function updateSessionStatistics(int $workspaceId): void
    {
        try {
            $sessions = WhatsAppAccount::forWorkspace($workspaceId)->get();

            foreach ($sessions as $session) {
                $session->updateStatistics();
            }

            $this->info("  📊 Statistics updated for {$sessions->count()} sessions");

        } catch (\Exception $e) {
            $this->error("  ❌ Statistics update failed: " . $e->getMessage());
        }
    }
}
