<?php

namespace App\Jobs;

use App\Jobs\RetryCampaignLogJob;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\CampaignLogRetry;
use App\Models\workspace;
use App\Services\WhatsappService;
use App\Services\WhatsApp\MessageSendingService;
use App\Traits\TemplateTrait;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessSingleCampaignLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TemplateTrait, Batchable;

    private $campaignLog;
    private $workspaceId;
    private MessageSendingService $messageService;

    public $timeout = 300; // 5 minutes
    public $tries = 3;
    public $backoff = [15, 45, 120]; // Progressive backoff: 15s, 45s, 2m
    public $retryAfter = 30; // Rate limiting

    public function __construct(
        CampaignLog $campaignLog,
        MessageSendingService $messageService
    ) {
        $this->campaignLog = $campaignLog;
        $this->messageService = $messageService;
    }

    public function handle()
    {
        try {
            DB::transaction(function() {
                // Lock the log for update to prevent duplicate processing
                $lockedLog = CampaignLog::where('id', $this->campaignLog->id)
                    ->whereIn('status', ['pending', 'failed'])
                    ->lockForUpdate()
                    ->first();

                if ($lockedLog) {
                    $campaign_user_id = Campaign::find($this->campaignLog->campaign_id)?->created_by;
                    $lockedLog->status = 'ongoing';
                    $lockedLog->save();

                    // OLD: Keep for reference during transition
                    /*
                    $this->workspaceId = $this->campaignLog->campaign->Workspace_id;
                    $this->initializeWhatsappService();
                    $template = $this->buildTemplateRequest($this->campaignLog->campaign_id, $this->campaignLog->contact);
                    */

                    // NEW: Use injected service
                    $this->workspaceId = $this->campaignLog->campaign->Workspace_id;
                    $template = $this->buildTemplateRequest($this->campaignLog->campaign_id, $this->campaignLog->contact);
                    $responseObject = $this->messageService->sendTemplateMessage(
                        $this->campaignLog->contact->uuid,
                        $template,
                        $campaign_user_id,
                        $this->campaignLog->campaign_id
                    );

                    $this->updateLogStatus($lockedLog, $responseObject);
                }
            });
        } catch (\Exception $e) {
            Log::error('Error processing campaign log ' . $this->campaignLog->id . ': ' . $e->getMessage());
            throw $e;
        }
    }

    protected function updateLogStatus(CampaignLog $log, $responseObject)
    {
        $log->chat_id = $responseObject->data->chat->id ?? null;
        $log->status = ($responseObject->success === true) ? 'success' : 'failed';

        // Clean up response object
        unset($responseObject->success);
        if (property_exists($responseObject, 'data') && property_exists($responseObject->data, 'chat')) {
            unset($responseObject->data->chat);
        }

        $log->metadata = json_encode($responseObject);
        $log->updated_at = now();
        $log->save();

        // Check if campaign is completed
        $this->checkAndUpdateCampaignStatus($log->campaign_id);
    }

    protected function checkAndUpdateCampaignStatus($campaignId)
    {
        $campaign = Campaign::find($campaignId);
        $orgMetadata = json_decode($campaign->workspace->metadata ?? '{}', true);
        $retryEnabled = $orgMetadata['campaigns']['enable_resend'] ?? false;
        $retryIntervals = $orgMetadata['campaigns']['resend_intervals'] ?? [];

        $pendingOrOngoing = CampaignLog::where('campaign_id', $campaignId)
            ->whereIn('status', ['pending', 'ongoing'])
            ->exists();

        if ($pendingOrOngoing) {
            return; // Still processing logs
        }

        if ($retryEnabled) {
            $logs = CampaignLog::where('campaign_id', $campaignId)->get();

            foreach ($logs as $log) {
                $retryCount = $log->retries()->count();
                $maxRetries = count($retryIntervals);

                // Skip if already retried max times
                if ($retryCount >= $maxRetries) {
                    continue;
                }

                // If log failed and hasn't reached max retries, schedule retry
                if ($log->status === 'failed' && $retryCount < $maxRetries) {
                    $delayHours = $retryIntervals[$retryCount] ?? null;

                    if ($delayHours !== null) {
                        RetryCampaignLogJob::dispatch($campaign->Workspace_id, $log->id, $retryCount)->onQueue('campaign-messages')->delay(now()->addMinutes($delayHours));
                    }
                }
            }
        } else {
            // Check again after potential changes
            $unprocessed = CampaignLog::where('campaign_id', $campaignId)
                ->whereIn('status', ['pending', 'ongoing'])
                ->exists();

            if (!$unprocessed) {
                $campaign->update(['status' => 'completed']);
            }
        }
    }

    /**
     * @deprecated Use injected MessageSendingService instead
     * OLD CODE - Commented out
     */
    /*
    private function initializeWhatsappService()
    {
        $config = cache()->remember("workspace.{$this->workspaceId}.metadata", 3600, function() {
            return workspace::find($this->workspaceId)->metadata ?? [];
        });

        $workspace = workspace::where('id', $this->workspaceId)->first();
        $config = $workspace && $workspace->metadata ? json_decode($workspace->metadata, true) : [];

        $accessToken = $config['whatsapp']['access_token'] ?? null;
        $apiVersion = 'v18.0';
        $appId = $config['whatsapp']['app_id'] ?? null;
        $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
        $wabaId = $config['whatsapp']['waba_id'] ?? null;

        $this->whatsappService = new WhatsappService(
            $accessToken,
            $apiVersion,
            $appId,
            $phoneNumberId,
            $wabaId,
            $this->workspaceId
        );
    }

    private function initializeWhatsappService()
    {
        $config = workspace::where('id', $this->workspaceId)->first()->metadata;
        $config = $config ? json_decode($config, true) : [];

        $accessToken = $config['whatsapp']['access_token'] ?? null;
        $apiVersion = 'v18.0';
        $appId = $config['whatsapp']['app_id'] ?? null;
        $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
        $wabaId = $config['whatsapp']['waba_id'] ?? null;

        $this->whatsappService = new WhatsappService(
            $accessToken,
            $apiVersion,
            $appId,
            $phoneNumberId,
            $wabaId,
            $this->workspaceId
        );
    }*/
}