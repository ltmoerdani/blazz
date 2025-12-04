<?php

namespace App\Services\Campaign;

use App\Models\Campaign;
use App\Models\WhatsappAccount;
use App\Jobs\AutoResumeCampaignJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

/**
 * CampaignConflictResolver
 *
 * Handles conflict resolution between active campaigns and mobile WhatsApp activity.
 * Pauses ENTIRE campaigns when mobile activity is detected.
 * Auto-resumes when no activity detected within tier cooldown period.
 */
class CampaignConflictResolver
{
    protected int $workspaceId;
    protected string $queue;
    protected int $maxResumeAttempts;

    public function __construct(int $workspaceId)
    {
        $this->workspaceId = $workspaceId;
        $this->queue = config('campaign.mobile_conflict.queue', 'campaign-conflict');
        $this->maxResumeAttempts = config('campaign.mobile_conflict.max_resume_attempts', 5);
    }

    /**
     * Pause all ongoing campaigns for a session
     */
    public function pauseAllCampaigns(string $sessionId, string $deviceType): object
    {
        if (!config('campaign.mobile_conflict.enabled', true)) {
            return (object) [
                'success' => true,
                'data' => ['skipped' => true, 'reason' => 'feature_disabled'],
                'message' => 'Mobile conflict detection is disabled',
            ];
        }

        $triggerDeviceTypes = config('campaign.mobile_conflict.trigger_device_types', ['android', 'ios']);
        if (!in_array($deviceType, $triggerDeviceTypes)) {
            return (object) [
                'success' => true,
                'data' => ['skipped' => true, 'reason' => 'device_type_not_trigger'],
                'message' => "Device type '{$deviceType}' does not trigger pause",
            ];
        }

        DB::beginTransaction();

        try {
            $campaigns = $this->getOngoingCampaigns($sessionId);

            if ($campaigns->isEmpty()) {
                DB::commit();
                Log::info('No ongoing campaigns to pause', [
                    'workspace_id' => $this->workspaceId,
                    'session_id' => $sessionId,
                ]);

                return (object) [
                    'success' => true,
                    'data' => ['paused_count' => 0],
                    'message' => 'No ongoing campaigns to pause',
                ];
            }

            $pausedIds = [];

            foreach ($campaigns as $campaign) {
                $campaign->pauseForMobileActivity($sessionId);
                $pausedIds[] = $campaign->id;

                // Get tier cooldown for this campaign's WhatsApp account
                $cooldownSeconds = $this->getTierCooldown($campaign->whatsapp_account_id);

                // Schedule auto-resume job
                AutoResumeCampaignJob::dispatch(
                    $this->workspaceId,
                    $campaign->id,
                    $sessionId,
                    1
                )->delay(now()->addSeconds($cooldownSeconds))
                 ->onQueue($this->queue);
            }

            DB::commit();

            Log::info('Campaigns paused for mobile activity', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $sessionId,
                'device_type' => $deviceType,
                'paused_count' => count($pausedIds),
                'campaign_ids' => $pausedIds,
            ]);

            return (object) [
                'success' => true,
                'data' => [
                    'paused_count' => count($pausedIds),
                    'campaign_ids' => $pausedIds,
                ],
                'message' => 'Paused ' . count($pausedIds) . ' campaigns',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to pause campaigns', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'data' => null,
                'message' => 'Failed to pause campaigns: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Resume a paused campaign
     */
    public function resumeCampaign(int $campaignId): object
    {
        try {
            $campaign = Campaign::where('id', $campaignId)
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if (!$campaign) {
                return (object) [
                    'success' => false,
                    'data' => null,
                    'message' => 'Campaign not found',
                ];
            }

            if (!$campaign->isPausedForMobile()) {
                return (object) [
                    'success' => true,
                    'data' => ['already_resumed' => true, 'status' => $campaign->status],
                    'message' => 'Campaign already resumed or not paused',
                ];
            }

            $pauseDuration = $campaign->paused_at ? now()->diffInSeconds($campaign->paused_at) : 0;

            $campaign->resumeFromPause();

            Log::info('Campaign resumed', [
                'campaign_id' => $campaignId,
                'workspace_id' => $this->workspaceId,
                'pause_duration_seconds' => $pauseDuration,
            ]);

            return (object) [
                'success' => true,
                'data' => [
                    'campaign_id' => $campaignId,
                    'status' => $campaign->status,
                    'pause_duration_seconds' => $pauseDuration,
                ],
                'message' => 'Campaign resumed successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to resume campaign', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'data' => null,
                'message' => 'Failed to resume: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check if campaign should resume based on last activity
     */
    public function shouldResume(int $campaignId, ?\DateTime $lastMobileActivity): bool
    {
        if ($lastMobileActivity === null) {
            return true; // No activity, safe to resume
        }

        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            return false;
        }

        $cooldownSeconds = $this->getTierCooldown($campaign->whatsapp_account_id);
        $secondsSinceActivity = now()->diffInSeconds($lastMobileActivity);

        return $secondsSinceActivity >= $cooldownSeconds;
    }

    /**
     * Get ongoing campaigns for a session
     */
    public function getOngoingCampaigns(string $sessionId): Collection
    {
        return Campaign::where('workspace_id', $this->workspaceId)
            ->whereHas('whatsappAccount', function ($query) use ($sessionId) {
                $query->where('session_id', $sessionId);
            })
            ->where('status', 'ongoing')
            ->get();
    }

    /**
     * Get tier cooldown for a WhatsApp account
     */
    public function getTierCooldown(int $whatsappAccountId): int
    {
        $account = WhatsappAccount::find($whatsappAccountId);
        $tier = $account ? ($account->tier ?? 1) : 1;

        $tierCooldowns = config('campaign.mobile_conflict.tier_cooldown', [
            1 => 60,
            2 => 45,
            3 => 30,
            4 => 20,
        ]);

        return $tierCooldowns[$tier] ?? config('campaign.mobile_conflict.default_cooldown_seconds', 30);
    }

    /**
     * Query Node.js service for last mobile activity
     */
    public function queryLastMobileActivity(string $sessionId): ?\DateTime
    {
        try {
            $nodeServiceUrl = config('services.whatsapp.url', 'http://localhost:3000');

            $response = Http::timeout(5)->get(
                "{$nodeServiceUrl}/api/internal/sessions/{$sessionId}/last-activity"
            );

            if ($response->successful()) {
                $data = $response->json('data');
                if ($data && $data['last_activity']) {
                    return new \DateTime($data['last_activity']);
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::warning('Failed to query mobile activity', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get statistics
     */
    public function getStatistics(): object
    {
        try {
            $stats = [
                'total_paused' => Campaign::where('workspace_id', $this->workspaceId)
                    ->where('status', Campaign::STATUS_PAUSED_MOBILE)
                    ->count(),

                'total_resumed_today' => Campaign::where('workspace_id', $this->workspaceId)
                    ->whereNotNull('auto_resume_at')
                    ->whereDate('auto_resume_at', today())
                    ->count(),

                'average_pause_duration' => Campaign::where('workspace_id', $this->workspaceId)
                    ->whereNotNull('paused_at')
                    ->whereNotNull('auto_resume_at')
                    ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, paused_at, auto_resume_at)) as avg_duration')
                    ->value('avg_duration') ?? 0,

                'campaigns_paused_multiple_times' => Campaign::where('workspace_id', $this->workspaceId)
                    ->where('pause_count', '>', 1)
                    ->count(),
            ];

            return (object) [
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully',
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'data' => null,
                'message' => 'Failed to get statistics: ' . $e->getMessage(),
            ];
        }
    }
}