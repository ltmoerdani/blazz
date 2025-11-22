<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Events\CampaignStatisticsUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UpdateCampaignStatisticsJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [10, 30, 60]; // Progressive backoff: 10s, 30s, 1m

    /**
     * The number of seconds after which the job should be made available again if it fails.
     *
     * @var int
     */
    public $retryAfter = 30; // Rate limiting

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $campaignId
    ) {
        $this->onQueue('campaign-stats');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Use cache lock to prevent concurrent updates for same campaign
            $lock = Cache::lock("campaign_stats_update_{$this->campaignId}", 10);

            if (!$lock->get()) {
                Log::info('Campaign stats update already in progress', [
                    'campaign_id' => $this->campaignId
                ]);
                return;
            }

            try {
                $campaign = Campaign::find($this->campaignId);

                if (!$campaign) {
                    Log::warning('Campaign not found for stats update', [
                        'campaign_id' => $this->campaignId
                    ]);
                    return;
                }

                Log::info('Updating campaign statistics', [
                    'campaign_id' => $this->campaignId,
                    'campaign_uuid' => $campaign->uuid,
                    'campaign_name' => $campaign->name
                ]);

                // Get previous counts for comparison
                $previousCounts = [
                    'sent' => $campaign->messages_sent,
                    'delivered' => $campaign->messages_delivered,
                    'read' => $campaign->messages_read,
                    'failed' => $campaign->messages_failed
                ];

                // Update performance counters using optimized aggregation query
                $campaign->updatePerformanceCounters();

                // Refresh model to get updated values
                $campaign->refresh();

                $newCounts = [
                    'sent' => $campaign->messages_sent,
                    'delivered' => $campaign->messages_delivered,
                    'read' => $campaign->messages_read,
                    'failed' => $campaign->messages_failed
                ];

                Log::info('Campaign statistics updated successfully', [
                    'campaign_id' => $this->campaignId,
                    'previous' => $previousCounts,
                    'new' => $newCounts,
                    'changes' => [
                        'sent_delta' => $newCounts['sent'] - $previousCounts['sent'],
                        'delivered_delta' => $newCounts['delivered'] - $previousCounts['delivered'],
                        'read_delta' => $newCounts['read'] - $previousCounts['read'],
                        'failed_delta' => $newCounts['failed'] - $previousCounts['failed']
                    ]
                ]);

                // Broadcast real-time update to frontend
                $statistics = [
                    'total_message_count' => $campaign->contactsCount(),
                    'total_sent_count' => $campaign->messages_sent,
                    'total_delivered_count' => $campaign->messages_delivered,
                    'total_read_count' => $campaign->messages_read,
                    'total_failed_count' => $campaign->messages_failed,
                    'pending_count' => max(0, $campaign->contactsCount() - $campaign->messages_sent - $campaign->messages_failed),
                    'updated_at' => now()->toISOString(),
                    'delivery_rate' => $campaign->messages_sent > 0 
                        ? round(($campaign->messages_delivered / $campaign->messages_sent) * 100, 2) 
                        : 0,
                    'read_rate' => $campaign->messages_delivered > 0 
                        ? round(($campaign->messages_read / $campaign->messages_delivered) * 100, 2) 
                        : 0,
                    'success_rate' => $campaign->contactsCount() > 0 
                        ? round(($campaign->messages_sent / $campaign->contactsCount()) * 100, 2) 
                        : 0
                ];

                broadcast(new CampaignStatisticsUpdated(
                    $campaign->id,
                    $campaign->workspace_id,
                    $campaign->uuid,
                    $statistics
                ));

                Log::info('Campaign statistics broadcasted', [
                    'campaign_id' => $this->campaignId,
                    'statistics' => $statistics
                ]);

            } finally {
                $lock->release();
            }

        } catch (\Exception $e) {
            Log::error('Failed to update campaign statistics', [
                'campaign_id' => $this->campaignId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('UpdateCampaignStatisticsJob failed permanently', [
            'campaign_id' => $this->campaignId,
            'attempt' => $this->attempts(),
            'exception' => [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ]
        ]);

        // Optionally notify administrators
        if ($this->attempts() >= 2) {
            Log::critical('Campaign statistics update failed multiple times', [
                'campaign_id' => $this->campaignId,
                'requires_attention' => true
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['campaign-stats', "campaign:{$this->campaignId}"];
    }
}
