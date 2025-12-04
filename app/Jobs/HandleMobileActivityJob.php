<?php

namespace App\Jobs;

use App\Services\Campaign\CampaignConflictResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HandleMobileActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public array $backoff = [5, 30, 60];

    protected int $workspaceId;
    protected string $sessionId;
    protected string $deviceType;

    public function __construct(
        int $workspaceId,
        string $sessionId,
        string $deviceType
    ) {
        $this->workspaceId = $workspaceId;
        $this->sessionId = $sessionId;
        $this->deviceType = $deviceType;
        $this->onQueue(config('campaign.mobile_conflict.queue', 'campaign-conflict'));
    }

    public function handle(): void
    {
        Log::info('Processing mobile activity', [
            'workspace_id' => $this->workspaceId,
            'session_id' => $this->sessionId,
            'device_type' => $this->deviceType,
        ]);

        $resolver = new CampaignConflictResolver($this->workspaceId);
        $result = $resolver->pauseAllCampaigns($this->sessionId, $this->deviceType);

        if (!$result->success && !isset($result->data['skipped'])) {
            throw new \Exception($result->message);
        }

        Log::info('Mobile activity processed', [
            'workspace_id' => $this->workspaceId,
            'paused_count' => $result->data['paused_count'] ?? 0,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('HandleMobileActivityJob failed', [
            'workspace_id' => $this->workspaceId,
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
        ]);
    }
}