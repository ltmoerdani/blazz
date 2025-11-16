<?php

namespace App\Jobs;

use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\SessionHealthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Monitor WhatsApp Session Health Job
 *
 * Periodically checks all WhatsApp sessions for health issues and triggers
 * auto-reconnect if needed.
 *
 * Schedule: Every 2 minutes
 * Purpose: Detect crashed/unhealthy sessions and auto-recover
 */
class MonitorWhatsAppSessionHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1; // Don't retry, will run again in 2 minutes
    public $timeout = 60;
    public $failOnTimeout = false; // Don't fail the job on timeout

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            Log::info('🏥 WhatsApp Session Health Monitor started');

            // Get all active WhatsApp accounts
            $accounts = WhatsAppAccount::whereNotNull('session_id')
                ->whereIn('status', ['connected', 'qr_scanning'])
                ->get();

            if ($accounts->isEmpty()) {
                Log::info('🏥 No active WhatsApp sessions to monitor');
                return;
            }

            Log::info('🏥 Monitoring sessions', [
                'total_accounts' => $accounts->count()
            ]);

            $healthService = new SessionHealthService();
            $results = [
                'healthy' => 0,
                'unhealthy' => 0,
                'reconnected' => 0,
                'failed' => 0,
            ];

            foreach ($accounts as $account) {
                try {
                    $healthCheck = $healthService->checkSessionHealth($account);

                    if ($healthCheck['is_healthy']) {
                        $results['healthy']++;
                        
                        // Update health score
                        $account->update([
                            'health_score' => $healthCheck['health_score'],
                            'last_health_check_at' => now(),
                        ]);
                        
                        Log::debug('✅ Session healthy', [
                            'account_id' => $account->id,
                            'phone_number' => $account->phone_number,
                            'health_score' => $healthCheck['health_score'],
                        ]);
                    } else {
                        $results['unhealthy']++;
                        
                        Log::warning('⚠️ Session unhealthy', [
                            'account_id' => $account->id,
                            'phone_number' => $account->phone_number,
                            'health_score' => $healthCheck['health_score'],
                            'issues' => $healthCheck['issues'],
                        ]);

                        // Update health score and status
                        $account->update([
                            'health_score' => $healthCheck['health_score'],
                            'last_health_check_at' => now(),
                        ]);

                        // Auto-reconnect if health is critical
                        if ($healthCheck['should_reconnect']) {
                            Log::info('🔄 Triggering auto-reconnect', [
                                'account_id' => $account->id,
                                'reason' => 'Health score below threshold',
                            ]);

                            $reconnectResult = $healthService->autoReconnect($account);
                            
                            if ($reconnectResult['success']) {
                                $results['reconnected']++;
                            } else {
                                $results['failed']++;
                            }
                        }
                    }

                } catch (\Exception $e) {
                    $results['failed']++;
                    
                    Log::error('❌ Session health check failed', [
                        'account_id' => $account->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('🏥 WhatsApp Session Health Monitor completed', $results);

            // Alert if too many unhealthy sessions
            if ($results['unhealthy'] > 0) {
                $this->notifyAdminAboutUnhealthySessions($results);
            }

        } catch (\Exception $e) {
            Log::error('❌ WhatsApp Session Health Monitor failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Notify admin about unhealthy sessions
     */
    protected function notifyAdminAboutUnhealthySessions(array $results)
    {
        // TODO: Implement notification (email, Slack, etc.)
        Log::warning('⚠️ Admin notification: Unhealthy sessions detected', $results);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception)
    {
        Log::error('❌ WhatsApp Session Health Monitor job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
