<?php

namespace App\Jobs;

use App\Models\ChatMedia;
use App\Services\Media\MediaStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Process Campaign Media Job
 * 
 * Processes uploaded campaign media files asynchronously including:
 * - Image compression and optimization
 * - Thumbnail generation
 * - WebP conversion
 * - Video processing (if FFmpeg available)
 */
class ProcessCampaignMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * The media ID to process.
     */
    protected int $mediaId;

    /**
     * Processing options.
     */
    protected array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(int $mediaId, array $options = [])
    {
        $this->mediaId = $mediaId;
        $this->options = array_merge([
            'compress' => true,
            'thumbnail' => true,
            'webp' => true,
        ], $options);

        // Set queue based on media type or priority
        $this->onQueue(config('media.queue.name', 'media'));
    }

    /**
     * Execute the job.
     */
    public function handle(MediaStorageService $mediaStorageService): void
    {
        $media = ChatMedia::find($this->mediaId);

        if (!$media) {
            Log::warning('[ProcessCampaignMediaJob] Media not found', [
                'media_id' => $this->mediaId,
            ]);
            return;
        }

        // Skip if already processed or currently processing
        if ($media->processing_status === ChatMedia::STATUS_COMPLETED) {
            Log::debug('[ProcessCampaignMediaJob] Media already processed', [
                'media_id' => $this->mediaId,
            ]);
            return;
        }

        if ($media->processing_status === ChatMedia::STATUS_PROCESSING) {
            Log::debug('[ProcessCampaignMediaJob] Media is currently being processed', [
                'media_id' => $this->mediaId,
            ]);
            return;
        }

        Log::info('[ProcessCampaignMediaJob] Starting media processing', [
            'media_id' => $this->mediaId,
            'type' => $media->type,
            'size' => $media->size,
        ]);

        try {
            $mediaStorageService->processMedia($media, $this->options);

            Log::info('[ProcessCampaignMediaJob] Media processing completed', [
                'media_id' => $this->mediaId,
            ]);

        } catch (\Exception $e) {
            Log::error('[ProcessCampaignMediaJob] Media processing failed', [
                'media_id' => $this->mediaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark as failed
            $media->markAsFailed($e->getMessage());

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $media = ChatMedia::find($this->mediaId);

        if ($media) {
            $media->markAsFailed("Job failed after {$this->tries} attempts: " . $exception->getMessage());
        }

        Log::error('[ProcessCampaignMediaJob] Job failed permanently', [
            'media_id' => $this->mediaId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(1);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'media-processing',
            "media:{$this->mediaId}",
        ];
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return "process_media_{$this->mediaId}";
    }
}
