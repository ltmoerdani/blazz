<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use App\Models\Chat;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * WhatsApp Session Health Service
 *
 * Monitors and maintains the health of WhatsApp sessions.
 * Detects issues and triggers auto-reconnection when needed.
 */
class SessionHealthService
{
    protected WhatsAppServiceClient $whatsappClient;
    
    // Health score thresholds
    const HEALTH_SCORE_EXCELLENT = 90;
    const HEALTH_SCORE_GOOD = 70;
    const HEALTH_SCORE_WARNING = 50;
    const HEALTH_SCORE_CRITICAL = 30;
    
    // Auto-reconnect threshold
    const AUTO_RECONNECT_THRESHOLD = 40;
    
    // Timeouts
    const MAX_IDLE_MINUTES = 30;
    const MAX_NO_MESSAGES_HOURS = 24;
    const MAX_FAILED_MESSAGES = 5;

    public function __construct()
    {
        $this->whatsappClient = new WhatsAppServiceClient();
    }

    /**
     * Check session health comprehensively
     *
     * @param WhatsAppAccount $account
     * @return array
     */
    public function checkSessionHealth(WhatsAppAccount $account): array
    {
        $issues = [];
        $healthScore = 100;
        
        try {
            // 1. Check if session exists in Node.js service
            $sessionStatus = $this->checkNodeJsSessionStatus($account);
            
            if (!$sessionStatus['exists']) {
                $healthScore -= 100;
                $issues[] = 'Session not found in Node.js service';
            } elseif ($sessionStatus['status'] !== 'connected') {
                $healthScore -= 50;
                $issues[] = "Session status: {$sessionStatus['status']}";
            }

            // 2. Check session activity (last message time)
            $lastActivity = $this->checkLastActivity($account);
            if ($lastActivity['minutes_idle'] > self::MAX_IDLE_MINUTES) {
                $healthScore -= 20;
                $issues[] = "Idle for {$lastActivity['minutes_idle']} minutes";
            }

            // 3. Check for recent failed messages
            $failedMessages = $this->checkFailedMessages($account);
            if ($failedMessages['count'] >= self::MAX_FAILED_MESSAGES) {
                $healthScore -= 30;
                $issues[] = "{$failedMessages['count']} failed messages in last hour";
            }

            // 4. Check database vs Node.js status mismatch
            if ($account->status === 'connected' && 
                (!$sessionStatus['exists'] || $sessionStatus['status'] !== 'connected')) {
                $healthScore -= 40;
                $issues[] = 'Database/Node.js status mismatch';
            }

            // 5. Check session crash detection
            $crashDetection = $this->detectSessionCrash($account);
            if ($crashDetection['likely_crashed']) {
                $healthScore -= 50;
                $issues[] = 'Session likely crashed: ' . $crashDetection['reason'];
            }

            // Ensure health score is between 0-100
            $healthScore = max(0, min(100, $healthScore));

            return [
                'is_healthy' => $healthScore >= self::HEALTH_SCORE_GOOD,
                'health_score' => $healthScore,
                'health_status' => $this->getHealthStatus($healthScore),
                'issues' => $issues,
                'should_reconnect' => $healthScore < self::AUTO_RECONNECT_THRESHOLD,
                'details' => [
                    'session_status' => $sessionStatus,
                    'last_activity' => $lastActivity,
                    'failed_messages' => $failedMessages,
                    'crash_detection' => $crashDetection,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to check session health', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'is_healthy' => false,
                'health_score' => 0,
                'health_status' => 'unknown',
                'issues' => ['Health check failed: ' . $e->getMessage()],
                'should_reconnect' => false,
            ];
        }
    }

    /**
     * Check session status in Node.js service
     */
    protected function checkNodeJsSessionStatus(WhatsAppAccount $account): array
    {
        try {
            $result = $this->whatsappClient->getSessionStatus(
                $account->workspace_id,
                $account->uuid
            );

            if ($result['success'] && isset($result['data']['status'])) {
                return [
                    'exists' => true,
                    'status' => $result['data']['status'],
                    'phone_number' => $result['data']['phone_number'] ?? null,
                    'last_activity' => $result['data']['last_activity'] ?? null,
                ];
            }

            return ['exists' => false, 'status' => 'unknown'];

        } catch (\Exception $e) {
            Log::debug('Could not check Node.js session status', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return ['exists' => false, 'status' => 'error'];
        }
    }

    /**
     * Check last activity of session
     */
    protected function checkLastActivity(WhatsAppAccount $account): array
    {
        $lastChat = Chat::where('whatsapp_account_id', $account->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastChat) {
            return [
                'has_activity' => false,
                'minutes_idle' => 99999,
                'last_message_at' => null,
            ];
        }

        $minutesIdle = Carbon::parse($lastChat->created_at)->diffInMinutes(now());

        return [
            'has_activity' => true,
            'minutes_idle' => $minutesIdle,
            'last_message_at' => $lastChat->created_at,
        ];
    }

    /**
     * Check for failed messages
     */
    protected function checkFailedMessages(WhatsAppAccount $account): array
    {
        $failedCount = Chat::where('whatsapp_account_id', $account->id)
            ->where('type', 'outbound')
            ->where('message_status', 'failed')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return [
            'count' => $failedCount,
            'threshold_exceeded' => $failedCount >= self::MAX_FAILED_MESSAGES,
        ];
    }

    /**
     * Detect if session has crashed
     */
    protected function detectSessionCrash(WhatsAppAccount $account): array
    {
        // Check for specific error patterns in recent failed messages
        $recentFailedChats = Chat::where('whatsapp_account_id', $account->id)
            ->where('message_status', 'failed')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->limit(5)
            ->get();

        $crashIndicators = [
            'Session closed',
            'page has been closed',
            'Evaluation failed',
            'Protocol error',
            'Connection closed',
        ];

        foreach ($recentFailedChats as $chat) {
            $metadata = is_string($chat->metadata) ? json_decode($chat->metadata, true) : $chat->metadata;
            $errorMessage = $metadata['error'] ?? $metadata['nodejs_response']['error'] ?? '';

            foreach ($crashIndicators as $indicator) {
                if (stripos($errorMessage, $indicator) !== false) {
                    return [
                        'likely_crashed' => true,
                        'reason' => "Error pattern detected: {$indicator}",
                        'last_error' => $errorMessage,
                    ];
                }
            }
        }

        return [
            'likely_crashed' => false,
            'reason' => null,
        ];
    }

    /**
     * Get health status label
     */
    protected function getHealthStatus(int $healthScore): string
    {
        if ($healthScore >= self::HEALTH_SCORE_EXCELLENT) {
            return 'excellent';
        } elseif ($healthScore >= self::HEALTH_SCORE_GOOD) {
            return 'good';
        } elseif ($healthScore >= self::HEALTH_SCORE_WARNING) {
            return 'warning';
        } elseif ($healthScore >= self::HEALTH_SCORE_CRITICAL) {
            return 'critical';
        } else {
            return 'failed';
        }
    }

    /**
     * Auto-reconnect unhealthy session
     */
    public function autoReconnect(WhatsAppAccount $account): array
    {
        try {
            Log::info('🔄 Starting auto-reconnect', [
                'account_id' => $account->id,
                'phone_number' => $account->phone_number,
            ]);

            // 1. Disconnect existing session
            try {
                $this->whatsappClient->disconnectSession(
                    $account->workspace_id,
                    $account->uuid
                );
                
                Log::info('✅ Old session disconnected', [
                    'account_id' => $account->id,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to disconnect old session (may not exist)', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // 2. Update database status
            $account->update([
                'status' => 'reconnecting',
                'last_reconnect_attempt_at' => now(),
            ]);

            // 3. Create new session
            $result = $this->whatsappClient->createSession(
                $account->workspace_id,
                $account->uuid,
                ['auto_reconnect' => true]
            );

            if ($result['success']) {
                Log::info('✅ Auto-reconnect successful', [
                    'account_id' => $account->id,
                    'session_id' => $result['data']['session_id'] ?? null,
                ]);

                $account->update([
                    'session_id' => $result['data']['session_id'] ?? $account->session_id,
                    'status' => 'qr_scanning',
                    'health_score' => 50, // Reset to moderate health
                ]);

                return [
                    'success' => true,
                    'message' => 'Auto-reconnect initiated. Please scan QR code.',
                    'requires_qr_scan' => true,
                ];
            } else {
                Log::error('❌ Auto-reconnect failed', [
                    'account_id' => $account->id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                $account->update([
                    'status' => 'reconnect_failed',
                ]);

                return [
                    'success' => false,
                    'message' => 'Auto-reconnect failed: ' . ($result['error'] ?? 'Unknown error'),
                ];
            }

        } catch (\Exception $e) {
            Log::error('❌ Auto-reconnect exception', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Auto-reconnect failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get health summary for all sessions
     */
    public function getHealthSummary(): array
    {
        $accounts = WhatsAppAccount::whereNotNull('session_id')->get();
        
        $summary = [
            'total' => $accounts->count(),
            'excellent' => 0,
            'good' => 0,
            'warning' => 0,
            'critical' => 0,
            'failed' => 0,
            'average_health_score' => 0,
        ];

        $totalHealthScore = 0;

        foreach ($accounts as $account) {
            $healthCheck = $this->checkSessionHealth($account);
            $status = $healthCheck['health_status'];
            
            $summary[$status]++;
            $totalHealthScore += $healthCheck['health_score'];
        }

        if ($accounts->count() > 0) {
            $summary['average_health_score'] = round($totalHealthScore / $accounts->count(), 2);
        }

        return $summary;
    }
}
