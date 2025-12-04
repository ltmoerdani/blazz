<?php

namespace App\Jobs;

use App\Models\CampaignLog;
use App\Models\CampaignLogRetry;
use App\Models\Workspace;
use App\Services\WhatsappService;
use App\Services\WhatsApp\MessageSendingService;
use App\Traits\TemplateTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryCampaignLogJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TemplateTrait;

    public $timeout = 300; // 5 minutes
    public $tries = 3;
    public $backoff = [20, 60, 180]; // Progressive backoff: 20s, 1m, 3m
    public $retryAfter = 30; // Rate limiting
    
    private $workspaceId;
    private $campaignLogId;
    protected $retryIndex;
    private MessageSendingService $messageService;

    public function __construct(
        int $workspaceId,
        int $campaignLogId,
        int $retryIndex,
        MessageSendingService $messageService
    ) {
        $this->workspaceId = $workspaceId;
        $this->campaignLogId = $campaignLogId;
        $this->retryIndex = $retryIndex;
        $this->messageService = $messageService;
    }

    public function uniqueId()
    {
        return $this->campaignLogId . '-' . $this->retryIndex;
    }

    /**
     * Handle failed job
     */
    public function failed(\Throwable $exception)
    {
        Log::error('RetryCampaignLogJob failed permanently', [
            'job' => self::class,
            'workspace_id' => $this->workspaceId,
            'campaign_log_id' => $this->campaignLogId,
            'retry_index' => $this->retryIndex,
            'error' => $exception->getMessage()
        ]);
    }

    public function handle()
    {
        $log = CampaignLog::with('campaign', 'campaign.workspace', 'contact')->find($this->campaignLogId);
        $campaignSettings = json_decode($log->campaign->workspace->metadata ?? '{}', true)['campaigns'] ?? [];
        $retryIntervals = $campaignSettings['resend_intervals'] ?? [];
        $maxRetries = count($retryIntervals);
        $retryCount = $log->retries()->count();

        if (!$log || $log->status !== 'failed') {
            return;
        }

        if($log->campaign->status == 'completed') {
            return; //Don't process if the campaign has been marked as completed
        }

        if($retryCount >= $maxRetries){
            return; //Don't process if retry count limit reached
        }

        // OLD: Keep for reference during transition
        /*
        // Initialize WhatsApp service
        $this->initializeWhatsappService();
        */

        DB::beginTransaction();

        try {
            // Create retry log
            $retryLog = new CampaignLogRetry();
            $retryLog->campaign_log_id = $this->campaignLogId;
            $retryLog->status = 'ongoing';
            $retryLog->save();

            $template = $this->buildTemplateRequest($log->campaign_id, $log->contact);
            $campaign_user_id = $log->campaign->created_by;

            // NEW: Use injected service
            $response = $this->messageService->sendTemplateMessage(
                $log->contact->uuid,
                $template,
                $campaign_user_id,
                $log->campaign_id
            );

            $status = ($response->success === true) ? 'success' : 'failed';
            $retryLog->chat_id = $response->data->chat->id ?? null;
            $retryLog->status = $status;

            // Clean metadata
            unset($response->success);
            if (property_exists($response, 'data') && property_exists($response->data, 'chat')) {
                unset($response->data->chat);
            }

            $retryLog->metadata = json_encode($response);
            $retryLog->save();

            // Update the retry_count on the original campaign log
            $log->retry_count += 1;
            $log->status = $status;
            $log->save();

            // Check if it failed and we need to schedule next retry
            if ($retryLog->status === 'failed') {
                $intervals = $campaignSettings['resend_intervals'] ?? [];

                if (isset($intervals[$this->retryIndex])) {
                    if($this->retryIndex + 1 < $maxRetries){
                        $nextInterval = $intervals[$this->retryIndex + 1];
                        self::dispatch($this->workspaceId, $log->id, $this->retryIndex + 1)->onQueue('campaign-messages')->delay(now()->addMinutes($nextInterval));
                    }
                } 
                
                if (!empty($campaignSettings['move_failed_contacts_to_group']) && $retryCount >= $maxRetries) {
                    $groupUuid = $campaignSettings['failed_campaign_group'];
                    $failedGroupId = DB::table('contact_groups')->where('uuid', $groupUuid)->value('id');

                    // Check if the group exists in the contact_groups table by UUID
                    if (!$failedGroupId) {
                        Log::warning('Failed to move contact: Group with UUID ' . $groupUuid . ' does not exist.');
                        return;
                    }

                    // Remove all groups for the contact
                    DB::table('contact_contact_group')
                        ->where('contact_id', $log->contact_id)
                        ->delete();

                    // Add contact to the failed group
                    DB::table('contact_contact_group')->insert([
                        'contact_id' => $log->contact_id,
                        'contact_group_id' => $failedGroupId, // Use the group ID here
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Retry failed for campaign_log {$this->campaignLogId}: " . $e->getMessage());
        }
    }

    /**
     * @deprecated Use constructor injection instead
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
    */
}
