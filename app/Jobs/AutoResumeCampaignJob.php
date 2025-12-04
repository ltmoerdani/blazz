<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\Campaign\CampaignConflictResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoResumeCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [10, 60, 120];

    protected int $workspaceId;
    protected int $campaignId;
    protected string $sessionId;
    protected int $attemptNumber;

    public function __construct(
        int $workspaceId,
        int $campaignId,
        string $sessionId,
        int $attemptNumber = 1
    ) {
        $this->workspaceId = $workspaceId;
        $this->campaignId = $campaignId;
        $this->sessionId = $sessionId;
        $this->attemptNumber = $attemptNumber;
        $this->onQueue(config('campaign.mobile_conflict.queue', 'campaign-conflict'));
    }

    public function handle(): void
    {
        Log::info('Checking if campaign can auto-resume', [
            'campaign_id' => $this->campaignId,
            'session_id' => $this->sessionId,
            'attempt' => $this->attemptNumber,
        ]);

        $resolver = new CampaignConflictResolver($this->workspaceId);

        // Check if campaign is still paused
        $campaign = Campaign::find($this->campaignId);
        if (!$campaign || !$campaign->isPausedForMobile()) {
            Log::info('Campaign no longer paused, skipping', [
                'campaign_id' => $this->campaignId,
            ]);
            return;
        }

        // Query last mobile activity from Node.js
        $lastActivity = $resolver->queryLastMobileActivity($this->sessionId);

        // Check if should resume
        if ($resolver->shouldResume($this->campaignId, $lastActivity)) {
            // No recent activity, safe to resume
            $result = $resolver->resumeCampaign($this->campaignId);

            Log::info('Campaign auto-resumed', [
                'campaign_id' => $this->campaignId,
                'workspace_id' => $this->workspaceId,
                'pause_duration' => $result->data['pause_duration_seconds'] ?? 0,
            ]);
        } else {
            // Still active, re-queue if under max attempts
            $maxAttempts = config('campaign.mobile_conflict.max_resume_attempts', 5);

            if ($this->attemptNumber < $maxAttempts) {
                $cooldownSeconds = $resolver->getTierCooldown($campaign->whatsapp_account_id);

                self::dispatch(
                    $this->workspaceId,
                    $this->campaignId,
                    $this->sessionId,
                    $this->attemptNumber + 1
                )->delay(now()->addSeconds($cooldownSeconds))
                 ->onQueue(config('campaign.mobile_conflict.queue', 'campaign-conflict'));

                Log::info('Mobile still active, re-queued resume', [
                    'campaign_id' => $this->campaignId,
                    'attempt' => $this->attemptNumber,
                    'next_check_in' => $cooldownSeconds,
                ]);
            } else {
                // Max attempts reached, force resume
                $resolver->resumeCampaign($this->campaignId);

                Log::warning('Max resume attempts reached, force resuming', [
                    'campaign_id' => $this->campaignId,
                    'attempts' => $this->attemptNumber,
                ]);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AutoResumeCampaignJob failed', [
            'campaign_id' => $this->campaignId,
            'workspace_id' => $this->workspaceId,
            'error' => $exception->getMessage(),
        ]);
    }
}