<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\CampaignLogRetry;
use App\Models\Contact;
use App\Models\WhatsAppAccount;
use App\Models\Workspace;
use App\Models\Setting;
use App\Services\WhatsApp\MessageService;
use App\Services\WhatsApp\ProviderSelectionService;
use App\Traits\HasUuid;
use App\Traits\TemplateTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, TemplateTrait, SerializesModels;

    public $timeout = 3600; // 1 hour
    public $tries = 3;
    public $backoff = [60, 180, 600]; // Progressive backoff: 1m, 3m, 10m
    public $retryAfter = 60; // Rate limiting

    private $workspaceId;
    private MessageService $messageService;
    private ProviderSelectionService $providerService;
    private ?WhatsAppAccount $selectedAccount = null;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Campaign|int $campaign,
        ?ProviderSelectionService $providerService = null
    ) {
        $this->providerService = $providerService ?? app(ProviderSelectionService::class);
        $this->onQueue('whatsapp-campaign');
    }

    /**
     * Handle failed job
     */
    public function failed(\Throwable $exception)
    {
        Log::error('SendCampaignJob failed permanently', [
            'job' => self::class,
            'campaign_id' => $this->campaign instanceof Campaign ? $this->campaign->id : $this->campaign,
            'error' => $exception->getMessage()
        ]);
    }

    /**
     * Handle single campaign processing (when campaign ID is passed)
     */
    public function handle(): void
    {
        try {
            Log::info('SendCampaignJob started', [
                'campaign_input' => is_int($this->campaign) ? "ID: {$this->campaign}" : "Object: {$this->campaign->id}",
                'queue' => $this->queue
            ]);

            // Resolve campaign if passed as ID
            if (is_int($this->campaign)) {
                $this->campaign = Campaign::find($this->campaign);
                
                if (!$this->campaign) {
                    Log::error('Campaign not found', ['campaign_id' => $this->campaign]);
                    return;
                }
            }

            Log::info('Campaign resolved', [
                'campaign_id' => $this->campaign->id,
                'campaign_uuid' => $this->campaign->uuid,
                'status' => $this->campaign->status,
                'campaign_type' => $this->campaign->campaign_type
            ]);

            // Handle both single campaign and batch processing
            if (isset($this->campaign)) {
                $this->processSingleCampaign($this->campaign);
            } else {
                $this->processBatchCampaigns();
            }

            Log::info('SendCampaignJob completed', [
                'campaign_id' => $this->campaign->id
            ]);
        } catch (\Exception $e) {
            Log::error('Error in campaign job: ' . $e->getMessage(), [
                'campaign_id' => $this->campaign?->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark campaign as failed if single campaign processing
            if ($this->campaign) {
                $this->campaign->markAsFailed($e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Process batch campaigns (legacy functionality)
     */
    private function processBatchCampaigns(): void
    {
        Campaign::whereIn('status', ['scheduled', 'ongoing'])
            ->with('workspace')
            ->whereNull('deleted_at')
            ->cursor()
            ->each(function ($campaign) {
                if ($this->shouldProcessCampaign($campaign)) {
                    $this->processCampaign($campaign);
                }
            });
    }

    /**
     * Process single campaign
     */
    private function processSingleCampaign(Campaign $campaign): void
    {
        if (!$this->shouldProcessCampaign($campaign)) {
            return;
        }

        $this->processCampaign($campaign);
    }

    /**
     * Check if campaign should be processed based on schedule and status
     */
    private function shouldProcessCampaign(Campaign $campaign): bool
    {
        // Check campaign status
        if (!in_array($campaign->status, ['scheduled', 'ongoing'])) {
            return false;
        }

        // Check if it's time to process scheduled campaigns
        if ($campaign->status === 'scheduled') {
            $timezone = $this->getCampaignTimezone($campaign);
            $scheduledAt = Carbon::parse($campaign->scheduled_at, 'UTC')->timezone($timezone);

            return $scheduledAt->lte(Carbon::now($timezone));
        }

        return true; // Ongoing campaigns should be processed
    }

    /**
     * Get campaign timezone
     */
    private function getCampaignTimezone(Campaign $campaign): string
    {
        $timezone = 'UTC';

        if ($campaign->workspace) {
            $metadata = json_decode($campaign->workspace->metadata ?? '{}', true);
            $timezone = $metadata['timezone'] ?? $timezone;
        }

        return $timezone;
    }

    protected function processCampaign(Campaign $campaign)
    {
        try {
            Log::info('Processing campaign', [
                'campaign_id' => $campaign->id,
                'status' => $campaign->status,
                'workspace_id' => $campaign->workspace_id
            ]);

            // Initialize MessageService with campaign's workspace ID
            $this->messageService = new MessageService($campaign->workspace_id);

            // Select the best WhatsApp session for this campaign
            $this->selectedAccount = $this->providerService->selectBestAccount($campaign);

            if (!$this->selectedAccount) {
                Log::error('No suitable WhatsApp session found for campaign', [
                    'campaign_id' => $campaign->id,
                    'campaign_type' => $campaign->campaign_type,
                    'preferred_provider' => $campaign->preferred_provider
                ]);

                $campaign->markAsFailed('No suitable WhatsApp session available');
                return;
            }

            Log::info('WhatsApp session selected', [
                'campaign_id' => $campaign->id,
                'session_id' => $this->selectedAccount->id,
                'session_phone' => $this->selectedAccount->phone_number,
                'provider_type' => $this->selectedAccount->provider_type
            ]);

            // Update campaign with selected session
            $campaign->update([
                'whatsapp_account_id' => $this->selectedAccount->id
            ]);

            if ($campaign->status === 'scheduled') {
                Log::info('Campaign is scheduled, creating logs', ['campaign_id' => $campaign->id]);
                $this->processPendingCampaign($campaign);
            } elseif ($campaign->status === 'ongoing') {
                Log::info('Campaign is ongoing, sending messages', ['campaign_id' => $campaign->id]);
                $this->sendOngoingCampaignMessages($campaign);
            } else {
                Log::warning('Campaign has unexpected status', [
                    'campaign_id' => $campaign->id,
                    'status' => $campaign->status
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to process campaign: ' . $e->getMessage(), [
                'campaign_id' => $campaign->id,
                'session_id' => $this->selectedAccount?->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            $campaign->markAsFailed('Campaign processing failed: ' . $e->getMessage());
        }
    }

    protected function processPendingCampaign(Campaign $campaign)
    {
        $contacts = $this->getContactsForCampaign($campaign);

        // Create campaign logs for new contacts (if any)
        $newLogsCreated = $this->createCampaignLogs($campaign, $contacts);
        
        Log::info('Campaign logs check', [
            'campaign_id' => $campaign->id,
            'new_logs_created' => $newLogsCreated,
            'total_contacts' => $contacts->count()
        ]);

        // Check if there are any logs (new or existing)
        $hasLogs = CampaignLog::where('campaign_id', $campaign->id)->exists();
        
        if ($hasLogs) {
            // Update campaign status to ongoing
            $this->updateCampaignStatus($campaign, 'ongoing');
            
            // Reload campaign with fresh data
            $campaign = Campaign::find($campaign->id);
            
            // Process the campaign to send messages
            $this->processCampaign($campaign);
        } else {
            Log::warning('No contacts found for campaign', [
                'campaign_id' => $campaign->id,
                'contact_group_id' => $campaign->contact_group_id
            ]);
            
            $campaign->markAsFailed('No contacts found for campaign');
        }
    }

    protected function getContactsForCampaign(Campaign $campaign)
    {
        if (empty($campaign->contact_group_id) || $campaign->contact_group_id === '0') {
            return Contact::where('workspace_id', $campaign->Workspace_id)
                ->whereNull('deleted_at')
                ->get();
        }

        return Contact::whereHas('contactGroups', function ($query) use ($campaign) {
            $query->where('contact_groups.id', $campaign->contact_group_id);
        })->whereNull('deleted_at')->get();
    }

    protected function createCampaignLogs(Campaign $campaign, $contacts)
    {
        $campaignLogs = [];
        $contactIds = $contacts->pluck('id');

        // Fetch existing logs
        $existingLogs = CampaignLog::where('campaign_id', $campaign->id)
            ->whereIn('contact_id', $contactIds)
            ->pluck('contact_id')
            ->toArray();

        // Filter out contacts that already have logs
        $newContacts = $contactIds->diff($existingLogs);

        // Prepare new campaign logs
        $campaignLogs = $newContacts->map(function ($contactId) use ($campaign) {
            return [
                'campaign_id' => $campaign->id,
                'contact_id' => $contactId,
                'created_at' => now(),
            ];
        })->toArray();

        // Insert new logs if any
        if (!empty($campaignLogs)) {
            return CampaignLog::insert($campaignLogs);
        }

        return false;
    }

    protected function updateCampaignStatus(Campaign $campaign, $status)
    {
        return Campaign::where('uuid', $campaign->uuid)->update(['status' => $status]);
    }

    protected function sendOngoingCampaignMessages(Campaign $campaign)
    {
        $this->processPendingOrRetryableLogs($campaign);

        // Check if there are no more pending campaign logs
        if (!$this->hasPendingOrRetryableLogs($campaign)) {
            $this->updateCampaignStatus($campaign, 'completed');
        }
    }

    protected function processPendingOrRetryableLogs(Campaign $campaign)
    {
        $campaign = Campaign::with('workspace')->find($campaign->id);
        $orgMetadata = json_decode($campaign->workspace->metadata ?? '{}', true);
        $retryEnabled = $orgMetadata['campaigns']['enable_resend'] ?? false;
        $retryIntervals = $orgMetadata['campaigns']['resend_intervals'] ?? [];
        $maxRetries = count($retryIntervals);

        // Process pending logs
        CampaignLog::with('campaign', 'contact')
            ->where('campaign_id', $campaign->id)
            ->where('status', '=', 'pending')
            ->chunk(500, function ($pendingCampaignLogs) {
                foreach ($pendingCampaignLogs as $campaignLog) {
                    // Skip if the log is already being processed or processed
                    if ($campaignLog->status === 'ongoing' || $campaignLog->status === 'success') {
                        continue;
                    }
                    $this->sendTemplateMessage($campaignLog);
                }
            });

        // If retry is enabled, process eligible failed logs
        if ($retryEnabled && $maxRetries > 0) {
            CampaignLog::with(['campaign', 'contact', 'retries'])
                ->where('campaign_id', $campaign->id)
                ->where('status', 'failed')
                ->chunk(500, function ($logs) use ($retryIntervals, $maxRetries) {
                    foreach ($logs as $log) {
                        $retryCount = $log->retries->count();

                        // Skip if max retries have been reached
                        if ($retryCount >= $maxRetries) {
                            continue;
                        }

                        $requiredDelay = $retryIntervals[$retryCount] ?? null;

                        // Skip if there's a retry dispatch and it's not time yet
                        $lastRetryLog = $log->retries()->latest()->first(); // Assuming the relationship is defined

                        if ($lastRetryLog) {
                            $nextEligibleTime = \Carbon\Carbon::parse($lastRetryLog->created_at)->addHours($requiredDelay);
                            if (now()->lt($nextEligibleTime)) {
                                continue; // Retry time not reached yet
                            }
                        }

                        // If the last retry or dispatched time is valid, proceed to send
                        $this->sendRetryLogTemplateMessage($log);
                    }
                });
        }
    }

    protected function hasPendingOrRetryableLogs(Campaign $campaign)
    {
        $campaign = Campaign::with('workspace')->find($campaign->id);
        $orgMetadata = json_decode($campaign->workspace->metadata ?? '{}', true);
        $retryEnabled = $orgMetadata['campaigns']['enable_resend'] ?? false;
        $retryIntervals = $orgMetadata['campaigns']['resend_intervals'] ?? [];
        $maxRetries = count($retryIntervals);

        // Check for pending logs first
        $hasPending = CampaignLog::where('status', 'pending')
            ->where('campaign_id', $campaign->id)
            ->exists();

        if ($hasPending) {
            return true;
        }

        // If retry is not enabled, return early
        if (!$retryEnabled || empty($retryIntervals)) {
            return false;
        }

        // Now check for retryable failed logs
        return CampaignLog::where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->where(function ($query) use ($maxRetries) {
                $query->whereExists(function ($sub) use ($maxRetries) {
                    $sub->select(DB::raw(1))
                        ->from('campaign_log_retries as clr')
                        ->whereColumn('clr.campaign_log_id', 'campaign_logs.id')
                        ->groupBy('clr.campaign_log_id')
                        ->havingRaw('COUNT(*) < ?', [$maxRetries]);
                });
            })
            ->exists();
    }

    protected function sendTemplateMessage(CampaignLog $campaignLog)
    {
        DB::transaction(function() use ($campaignLog) {
            // Update log to ongoing, prevents this message from being sent out again
            $log = CampaignLog::where('id', $campaignLog->id)
                              ->where('status', 'pending') // Make sure the log is still pending
                              ->lockForUpdate()
                              ->first();

            if (!$log) {
                return; // Already processed
            }

            if (!$campaignLog->contact) {
                $log->status = 'failed';
                $log->save();
                return;
            }

            $campaign = $campaignLog->campaign;
            $this->workspaceId = $campaign->workspace_id;
            $campaign_user_id = $campaign->created_by;

            // Mark log as ongoing
            $log->status = 'ongoing';
            $log->save();

            try {
                // Determine message type based on campaign type
                if ($campaign->isTemplateBased()) {
                    $messageRequest = $this->buildTemplateMessageRequest($campaign, $campaignLog->contact);
                    $responseObject = $this->messageService->sendTemplateMessage(
                        $campaignLog->contact->uuid,
                        $messageRequest,
                        $campaign_user_id,
                        $campaignLog->campaign_id,
                        $this->selectedAccount
                    );
                } else {
                    // Direct message campaign
                    $messageRequest = $this->buildDirectMessageRequest($campaign, $campaignLog->contact);
                    $responseObject = $this->messageService->sendDirectMessage(
                        $campaignLog->contact->uuid,
                        $messageRequest,
                        $campaign_user_id,
                        $campaignLog->campaign_id,
                        $this->selectedAccount
                    );
                }

                $this->updateCampaignLogStatus($campaignLog, $responseObject);

            } catch (\Exception $e) {
                Log::error('Failed to send campaign message', [
                    'campaign_log_id' => $campaignLog->id,
                    'campaign_id' => $campaign->id,
                    'contact_id' => $campaignLog->contact->id,
                    'session_id' => $this->selectedAccount?->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Try fallback account if available
                if ($this->selectedAccount) {
                    $fallbackAccounts = $this->providerService->getFallbackAccounts($campaign, $this->selectedAccount);

                    foreach ($fallbackAccounts as $fallbackAccount) {
                        /** @var WhatsAppAccount $fallbackAccount */
                        try {
                            Log::info('Attempting fallback account', [
                                'campaign_id' => $campaign->id,
                                'primary_account_id' => $this->selectedAccount->id,
                                'fallback_account_id' => $fallbackAccount->id,
                                'provider_type' => $fallbackAccount->provider_type
                            ]);

                            // Update session and retry
                            $this->selectedAccount = $fallbackAccount;
                            $campaign->update(['whatsapp_account_id' => $fallbackAccount->id]);

                            if ($campaign->isTemplateBased()) {
                                $messageRequest = $this->buildTemplateMessageRequest($campaign, $campaignLog->contact);
                                $responseObject = $this->messageService->sendTemplateMessage(
                                    $campaignLog->contact->uuid,
                                    $messageRequest,
                                    $campaign_user_id,
                                    $campaignLog->campaign_id,
                                    $this->selectedAccount
                                );
                            } else {
                                $messageRequest = $this->buildDirectMessageRequest($campaign, $campaignLog->contact);
                                $responseObject = $this->messageService->sendDirectMessage(
                                    $campaignLog->contact->uuid,
                                    $messageRequest,
                                    $campaign_user_id,
                                    $campaignLog->campaign_id,
                                    $this->selectedAccount
                                );
                            }

                            $this->updateCampaignLogStatus($campaignLog, $responseObject);
                            return; // Success with fallback

                        } catch (\Exception $fallbackException) {
                            Log::warning('Fallback account also failed', [
                                'campaign_id' => $campaign->id,
                                'fallback_account_id' => $fallbackAccount->id,
                                'error' => $fallbackException->getMessage()
                            ]);
                            continue; // Try next fallback
                        }
                    }
                }

                // All attempts failed
                $log->status = 'failed';
                $log->save();
            }
        });
    }

    protected function sendRetryLogTemplateMessage(CampaignLog $campaignLog)
    {
        DB::transaction(function() use ($campaignLog) {
            //Update log to ongoing, prevents this message from being sent out again
            $log = CampaignLog::where('id', $campaignLog->id)
                              ->where('status', 'failed')
                              ->lockForUpdate()
                              ->first();

            if ($log) {
                if (!$campaignLog->contact) {
                    $log->status = 'failed';
                    $log->save();

                } else {
                    $campaign_user_id = Campaign::find($log->campaign_id)?->created_by;
                    $retryLog = new CampaignLogRetry();
                    $retryLog->campaign_log_id = $campaignLog->id;
                    $retryLog->status = 'ongoing';
                    $retryLog->save();

                    // OLD: Keep for reference during transition
                    /*
                    //Set workspace Id & initialize whatsapp service
                    $this->workspaceId = $campaignLog->campaign->Workspace_id;
                    $this->initializeWhatsappService();
                    $template = $this->buildTemplateRequest($campaignLog->campaign_id, $campaignLog->contact);
                    $responseObject = $this->whatsappService->sendTemplateMessage($campaignLog->contact->uuid, $template, $campaign_user_id, $campaignLog->campaign_id);
                    */

                    // NEW: Use injected service
                    $this->workspaceId = $campaignLog->campaign->workspace_id;
                    $this->messageService = new MessageService($this->workspaceId);
                    $template = $this->buildTemplateRequest($campaignLog->campaign_id, $campaignLog->contact);
                    $responseObject = $this->messageService->sendTemplateMessage($campaignLog->contact->uuid, $template, $campaign_user_id, $campaignLog->campaign_id);
                    $successStatus = ($responseObject->success === true) ? 'success' : 'failed';

                    $retryLog->chat_id = $responseObject->data->chat->id ?? null;
                    $retryLog->status = $successStatus;

                    // Clean metadata
                    unset($responseObject->success);
                    if (property_exists($responseObject, 'data') && property_exists($responseObject->data, 'chat')) {
                        unset($responseObject->data->chat);
                    }

                    $retryLog->metadata = json_encode($responseObject);
                    $retryLog->save();

                    // Update the retry_count on the original campaign log
                    $log = CampaignLog::find($campaignLog->id);
                    $log->retry_count += 1;
                    $log->status = $successStatus;
                    $log->save();

                    //If this is the last retry send contact to failed group
                    $orgMetadata = json_decode($campaignLog->campaign->workspace->metadata ?? '{}', true);
                    $retryIntervals = $orgMetadata['campaigns']['resend_intervals'] ?? [];
                    $maxRetries = count($retryIntervals);

                    if ($log->status === 'failed' && $log->retry_count >= $maxRetries) {
                        $this->addContactToFailedGroup($campaignLog);
                    }
                }
            }
        });
    }

    protected function updateCampaignLogStatus(CampaignLog $campaignLog, $responseObject)
    {
        $log = CampaignLog::find($campaignLog->id);

        // Update campaign log status based on the response object
        $log->chat_id = $responseObject->data->chat->id ?? null;
        $log->status = ($responseObject->success === true) ? 'success' : 'failed';
        unset($responseObject->success);
        if (property_exists($responseObject, 'data') && property_exists($responseObject->data, 'chat')) {
            unset($responseObject->data->chat);
        }
        $log->metadata = json_encode($responseObject);
        $log->updated_at = now();
        $log->save();
    }

    
    /**
     * Build template message request for WhatsApp API
     */
    protected function buildTemplateMessageRequest(Campaign $campaign, Contact $contact): array
    {
        $template = $campaign->template;
        if (!$template) {
            throw new \Exception('Template not found for template-based campaign');
        }

        // Parse campaign metadata for dynamic content
        $campaignMetadata = json_decode($campaign->metadata ?? '{}', true);

        $messageRequest = [
            'template_name' => $template->name,
            'language' => [
                'code' => $template->language ?? 'en_US'
            ],
            'components' => []
        ];

        // Build header component
        if ($template->header_type !== 'none') {
            $headerComponent = [
                'type' => 'header',
                'parameters' => []
            ];

            if ($template->header_type === 'text') {
                $headerComponent['parameters'][] = [
                    'type' => 'text',
                    'text' => $this->replaceVariables($template->header_text, $contact)
                ];
            } elseif (in_array($template->header_type, ['image', 'document', 'video'])) {
                $headerComponent['parameters'][] = [
                    'type' => $template->header_type,
                    'media' => $campaignMetadata['header']['parameters'][0]['value'] ?? $template->header_media
                ];
            }

            $messageRequest['components'][] = $headerComponent;
        }

        // Build body component
        $bodyComponent = [
            'type' => 'body',
            'parameters' => []
        ];

        // Replace variables in body text
        $bodyText = $this->replaceVariables($template->body_text, $contact);
        $bodyComponent['parameters'][] = [
            'type' => 'text',
            'text' => $bodyText
        ];

        $messageRequest['components'][] = $bodyComponent;

        // Build footer component if exists
        if ($template->footer_text) {
            $messageRequest['components'][] = [
                'type' => 'footer',
                'text' => $this->replaceVariables($template->footer_text, $contact)
            ];
        }

        // Build buttons component if exists
        if (!empty($template->buttons_data)) {
            $buttonComponent = [
                'type' => 'buttons',
                'buttons' => []
            ];

            foreach ($template->buttons_data as $button) {
                $buttonData = [
                    'type' => $button['type'],
                    'text' => $this->replaceVariables($button['text'], $contact)
                ];

                if ($button['type'] === 'url') {
                    $buttonData['url'] = $button['url'];
                } elseif ($button['type'] === 'phone_number') {
                    $buttonData['phone_number'] = $button['phone_number'];
                }

                $buttonComponent['buttons'][] = $buttonData;
            }

            $messageRequest['components'][] = $buttonComponent;
        }

        return $messageRequest;
    }

    /**
     * Build direct message request for WhatsApp API
     */
    protected function buildDirectMessageRequest(Campaign $campaign, Contact $contact): array
    {
        $messageContent = $campaign->getResolvedMessageContent();

        $messageRequest = [
            'type' => 'message',
            'content' => []
        ];

        // Build header
        if ($messageContent['header_type'] !== 'text' && $messageContent['header_media']) {
            $messageRequest['content']['header'] = [
                'type' => $messageContent['header_type'],
                'media' => $messageContent['header_media']
            ];
        } elseif ($messageContent['header_text']) {
            $messageRequest['content']['header'] = [
                'type' => 'text',
                'text' => $this->replaceVariables($messageContent['header_text'], $contact)
            ];
        }

        // Build body (required)
        $messageRequest['content']['body'] = [
            'text' => $this->replaceVariables($messageContent['body_text'], $contact)
        ];

        // Build footer
        if ($messageContent['footer_text']) {
            $messageRequest['content']['footer'] = [
                'text' => $this->replaceVariables($messageContent['footer_text'], $contact)
            ];
        }

        // Build buttons
        if (!empty($messageContent['buttons_data'])) {
            $messageRequest['content']['buttons'] = [];
            foreach ($messageContent['buttons_data'] as $button) {
                $buttonData = [
                    'type' => $button['type'],
                    'text' => $this->replaceVariables($button['text'], $contact)
                ];

                if ($button['type'] === 'url') {
                    $buttonData['url'] = $button['url'];
                } elseif ($button['type'] === 'phone_number') {
                    $buttonData['phone_number'] = $button['phone_number'];
                }

                $messageRequest['content']['buttons'][] = $buttonData;
            }
        }

        return $messageRequest;
    }

    /**
     * Replace variables in message text with contact data
     */
    protected function replaceVariables(string $text, Contact $contact): string
    {
        $variables = [
            '{{first_name}}' => $contact->first_name ?? '',
            '{{last_name}}' => $contact->last_name ?? '',
            '{{full_name}}' => trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')),
            '{{phone}}' => $contact->phone ?? '',
            '{{email}}' => $contact->email ?? '',
            '{{company}}' => $contact->company ?? '',
            '{{position}}' => $contact->position ?? '',
        ];

        return str_replace(array_keys($variables), array_values($variables), $text);
    }

    /**
     * Move contact to failed group after max retries
     */
    protected function addContactToFailedGroup($campaignLog)
    {
        $campaignSettings = json_decode($campaignLog->campaign->workspace->metadata, true)['campaigns'] ?? [];

        if (!empty($campaignSettings['move_failed_contacts_to_group'])) {
            $groupUuid = $campaignSettings['failed_campaign_group'];
            $failedGroupId = DB::table('contact_groups')->where('uuid', $groupUuid)->value('id');

            // Check if the group exists in the contact_groups table by UUID
            if (!$failedGroupId) {
                Log::warning('Failed to move contact: Group with UUID ' . $groupUuid . ' does not exist.');
            }

            // Remove all groups for the contact
            DB::table('contact_contact_group')
                ->where('contact_id', $campaignLog->contact_id)
                ->delete();

             // Add contact to the failed group
             DB::table('contact_contact_group')->insert([
                'contact_id' => $campaignLog->contact_id,
                'contact_group_id' => $failedGroupId, // Use the group ID here
            ]);
        }
    }
}
