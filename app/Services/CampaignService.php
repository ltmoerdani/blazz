<?php

namespace App\Services;

use Carbon\Carbon;
use App\Jobs\SendCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\ChatMedia;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Workspace;
use App\Models\Setting;
use App\Models\Template;
use App\Models\WhatsAppAccount;
use App\Services\WhatsappService;
use App\Services\Media\MediaStorageService;
use App\Traits\TemplateTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Validator;

class CampaignService
{
    use TemplateTrait;

    public function store(object $request){
        $workspaceId = session()->get('current_workspace');

        $timezone = Setting::where('key', 'timezone')->value('value');
        $workspace = workspace::find($workspaceId);
        $workspaceMetadata = json_decode($workspace->metadata ?? '{}', true);
        $timezone = $workspaceMetadata['timezone'] ?? $timezone;

        $template = Template::where('uuid', $request->template)->first();
        $contactGroup = ContactGroup::where('uuid', $request->contacts)->first();

        try {
            DB::transaction(function () use ($request, $workspaceId, $template, $contactGroup, $timezone) {
                //Request metadata
                $mediaId = null;
                if(in_array($request->header['format'], ['IMAGE', 'DOCUMENT', 'VIDEO'])){
                    $header = $request->header;
                    
                    if ($request->header['parameters']) {
                        $metadata['header']['format'] = $header['format'];
                        $metadata['header']['parameters'] = [];
                
                        foreach ($request->header['parameters'] as $parameter) {
                            if ($parameter['selection'] === 'upload') {

                                $storage = Setting::where('key', 'storage_system')->first()->value;
                                $fileName = $parameter['value']->getClientOriginalName();
                                $fileContent = $parameter['value'];

                                if($storage === 'local'){
                                    $file = Storage::disk('local')->put('public', $fileContent);
                                    $mediaFilePath = $file;
                    
                                    $mediaUrl = rtrim(config('app.url'), '/') . '/media/' . ltrim($mediaFilePath, '/');
                                } elseif($storage === 'aws') {
                                    $file = $parameter['value'];
                                    $uploadedFile = $file->store('uploads/media/sent/' . $workspaceId, 's3');
                                    /** @var \Illuminate\Filesystem\FilesystemAdapter $s3Disk */
                                    $s3Disk = Storage::disk('s3');
                                    $mediaUrl = $s3Disk->url($uploadedFile);
                    
                                    $mediaUrl = $mediaFilePath;
                                }

                                $contentType = $this->getContentTypeFromUrl($mediaUrl);
                                $mediaSize = $this->getMediaSizeInBytesFromUrl($mediaUrl);

                                //save media
                                $chatMedia = new ChatMedia;
                                $chatMedia->name = $fileName;
                                $chatMedia->path = $mediaUrl;
                                $chatMedia->type = $contentType;
                                $chatMedia->size = $mediaSize;
                                $chatMedia->created_at = now();
                                $chatMedia->save();

                                $mediaId = $chatMedia->id;
                            } else {
                                $mediaUrl = $parameter['value'];
                            }
                
                            $metadata['header']['parameters'][] = [
                                'type' => $parameter['type'],
                                'selection' => $parameter['selection'],
                                'value' => $mediaUrl,
                            ];
                        }
                    }
                } else {
                    $metadata['header'] = $request->header;
                }

                $metadata['body'] = $request->body;
                $metadata['footer'] = $request->footer;
                $metadata['buttons'] = $request->buttons;
                $metadata['media'] = $mediaId;

                // Convert $request->time from workspace's timezone to UTC
                $scheduledAt = $request->skip_schedule ? Carbon::now('UTC') : Carbon::parse($request->time, $timezone)->setTimezone('UTC');

                //Create campaign
                $campaign = new Campaign;
                $campaign['workspace_id'] = $workspaceId;
                $campaign['name'] = $request->name;
                $campaign['template_id'] = $template->id;
                $campaign['contact_group_id'] = $request->contacts === 'all' ? 0 : $contactGroup->id;
                $campaign['metadata'] = json_encode($metadata);
                $campaign['created_by'] = Auth::user()->id;
                $campaign['status'] = 'scheduled';
                $campaign['scheduled_at'] = $scheduledAt;
                $campaign->save();
            });
        } catch (\Exception $e) {
            // Handle the exception here if needed.
            // The transaction has already been rolled back automatically.
            Log::error('Failed to store campaign', [
                'error_message' => $e->getMessage(),
                'workspace_id' => $workspaceId,
                'template' => $request->template,
                'contacts' => $request->contacts,
                'user_id' => Auth::user()->id,
                'stack_trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Create Hybrid Campaign - supports both template and direct message modes
     */
    public function createHybridCampaign(object $request): Campaign
    {
        $workspaceId = session()->get('current_workspace');

        $timezone = Setting::where('key', 'timezone')->value('value');
        $workspace = Workspace::find($workspaceId);
        $workspaceMetadata = json_decode($workspace->metadata ?? '{}', true);
        $timezone = $workspaceMetadata['timezone'] ?? $timezone;

        try {
            return DB::transaction(function () use ($request, $workspaceId, $timezone) {
                $campaignData = $this->prepareCampaignData($request, $workspaceId, $timezone);

                $campaign = Campaign::create($campaignData);

                // Log campaign creation
                Log::info('Campaign created', [
                    'campaign_id' => $campaign->id,
                    'campaign_uuid' => $campaign->uuid,
                    'campaign_type' => $campaign->campaign_type,
                    'skip_schedule' => $request->skip_schedule ?? false,
                    'status' => $campaign->status
                ]);

                // Dispatch job for immediate or scheduled processing
                if ($request->skip_schedule || (!$request->scheduled_at || $request->scheduled_at->isPast())) {
                    Log::info('Dispatching SendCampaignJob immediately', [
                        'campaign_id' => $campaign->id,
                        'reason' => $request->skip_schedule ? 'skip_schedule_enabled' : 'scheduled_time_passed'
                    ]);
                    
                    SendCampaignJob::dispatch($campaign->id)
                        ->onQueue('whatsapp-campaign');
                } else {
                    Log::info('Campaign scheduled for later processing', [
                        'campaign_id' => $campaign->id,
                        'scheduled_at' => $campaign->scheduled_at
                    ]);
                }

                return $campaign;
            });
        } catch (\Exception $e) {
            Log::error('Failed to create hybrid campaign', [
                'error_message' => $e->getMessage(),
                'workspace_id' => $workspaceId,
                'campaign_type' => $request->campaign_type ?? 'template',
                'user_id' => Auth::user()->id,
                'request_data' => $request->all(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            throw new \Exception('Failed to create campaign: ' . $e->getMessage());
        }
    }

    /**
     * Prepare campaign data based on type (template or direct)
     */
    private function prepareCampaignData(object $request, int $workspaceId, string $timezone): array
    {
        $campaignType = $request->campaign_type ?? 'template';
        $contactGroup = $this->getContactGroup($request->contacts);

        $baseData = [
            'workspace_id' => $workspaceId,
            'name' => $request->name,
            'campaign_type' => $campaignType,
            'contact_group_id' => $request->contacts === 'all' ? 0 : ($contactGroup?->id ?? 0),
            'preferred_provider' => $request->preferred_provider ?? 'webjs',
            'whatsapp_account_id' => $this->getSelectedWhatsAppAccount($request->whatsapp_account_id, $workspaceId),
            'status' => 'scheduled',
            'scheduled_at' => $this->parseScheduledTime($request),
            'created_by' => Auth::user()->id,
        ];

        if ($campaignType === 'template') {
            return $this->prepareTemplateCampaignData($request, $baseData, $workspaceId);
        } else {
            return $this->prepareDirectCampaignData($request, $baseData);
        }
    }

    /**
     * Prepare template-based campaign data
     */
    private function prepareTemplateCampaignData(object $request, array $baseData, int $workspaceId): array
    {
        $template = Template::where('uuid', $request->template)->first();
        if (!$template) {
            throw new \Exception('Template not found');
        }

        $campaignData = array_merge($baseData, [
            'template_id' => $template->id,
            'metadata' => $this->buildTemplateMetadata($request, $workspaceId)
        ]);

        return $campaignData;
    }

    /**
     * Prepare direct message campaign data
     */
    private function prepareDirectCampaignData(object $request, array $baseData): array
    {
        $buttons = $request->buttons ?? [];
        
        $campaignData = array_merge($baseData, [
            'template_id' => null, // Not required for direct campaigns
            'message_content' => $request->message_content ?? null,
            'header_type' => $request->header_type ?? 'text',
            'header_text' => $request->header_text ?? null,
            'header_media' => $this->handleDirectMediaUpload($request) ?? null,
            'body_text' => $request->body_text,
            'footer_text' => $request->footer_text ?? null,
            'buttons_data' => is_array($buttons) && count($buttons) > 0 ? $this->parseButtonsData($buttons) : null,
            'metadata' => $this->buildDirectMetadata($request)
        ]);

        return $campaignData;
    }

    /**
     * Build metadata for template campaigns
     */
    private function buildTemplateMetadata(object $request, int $workspaceId): string
    {
        $metadata = [];
        $mediaId = null;

        if (isset($request->header['format']) && in_array($request->header['format'], ['IMAGE', 'DOCUMENT', 'VIDEO'])) {
            $header = $request->header;

            if ($request->header['parameters']) {
                $metadata['header']['format'] = $header['format'];
                $metadata['header']['parameters'] = [];

                foreach ($request->header['parameters'] as $parameter) {
                    if ($parameter['selection'] === 'upload') {
                        $mediaId = $this->handleTemplateMediaUpload($parameter, $workspaceId);
                        $mediaUrl = $mediaId ? ChatMedia::find($mediaId)->path : $parameter['value'];
                    } else {
                        $mediaUrl = $parameter['value'];
                    }

                    $metadata['header']['parameters'][] = [
                        'type' => $parameter['type'],
                        'selection' => $parameter['selection'],
                        'value' => $mediaUrl,
                    ];
                }
            }
        } else {
            $metadata['header'] = $request->header ?? [];
        }

        $metadata['body'] = $request->body ?? [];
        $metadata['footer'] = $request->footer ?? [];
        $metadata['buttons'] = $request->buttons ?? [];
        $metadata['media'] = $mediaId;

        return json_encode($metadata);
    }

    /**
     * Build metadata for direct campaigns
     */
    private function buildDirectMetadata(object $request): string
    {
        $buttons = $request->buttons ?? [];
        
        $metadata = [
            'campaign_type' => 'direct',
            'header' => [
                'type' => $request->header_type ?? 'text',
                'text' => $request->header_text ?? null,
                'media' => $request->header_media ?? null,
            ],
            'body' => ['text' => $request->body_text],
            'footer' => ['text' => $request->footer_text ?? null],
            'buttons' => is_array($buttons) && count($buttons) > 0 ? $this->parseButtonsData($buttons) : [],
        ];

        return json_encode($metadata);
    }

    /**
     * Handle media upload for template campaigns
     * Now uses MediaStorageService for organized S3 storage
     */
    private function handleTemplateMediaUpload(array $parameter, int $workspaceId): ?int
    {
        if (!isset($parameter['value']) || !$parameter['value'] instanceof \Illuminate\Http\UploadedFile) {
            return null;
        }

        try {
            /** @var MediaStorageService $mediaService */
            $mediaService = app(MediaStorageService::class);
            
            $chatMedia = $mediaService->uploadForCampaign(
                $parameter['value'],
                $workspaceId,
                [
                    'campaign_uuid' => 'template-upload',
                    'usage_type' => MediaStorageService::USAGE_HEADER,
                ]
            );

            Log::info('[CampaignService] Template media uploaded via MediaStorageService', [
                'media_id' => $chatMedia->id,
                'path' => $chatMedia->original_path,
                'workspace_id' => $workspaceId,
            ]);

            return $chatMedia->id;
        } catch (\Exception $e) {
            Log::error('[CampaignService] Failed to upload template media', [
                'error' => $e->getMessage(),
                'workspace_id' => $workspaceId,
            ]);
            return null;
        }
    }

    /**
     * Handle media upload for direct campaigns
     * Now uses MediaStorageService for organized S3 storage
     */
    private function handleDirectMediaUpload(object $request): ?string
    {
        if (!isset($request->header_media) || !$request->header_media instanceof \Illuminate\Http\UploadedFile) {
            return null;
        }

        try {
            $workspaceId = session()->get('current_workspace');
            
            /** @var MediaStorageService $mediaService */
            $mediaService = app(MediaStorageService::class);
            
            $chatMedia = $mediaService->uploadForCampaign(
                $request->header_media,
                $workspaceId,
                [
                    'campaign_uuid' => 'direct-upload',
                    'usage_type' => MediaStorageService::USAGE_HEADER,
                ]
            );

            Log::info('[CampaignService] Direct campaign media uploaded via MediaStorageService', [
                'media_id' => $chatMedia->id,
                'url' => $chatMedia->url,
                'workspace_id' => $workspaceId,
            ]);

            return $chatMedia->url;
        } catch (\Exception $e) {
            Log::error('[CampaignService] Failed to upload direct campaign media', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Parse buttons data for direct campaigns
     */
    private function parseButtonsData(array $buttons): array
    {
        $parsedButtons = [];

        foreach ($buttons as $button) {
            $buttonData = [
                'type' => $button['type'] ?? 'reply',
                'text' => $button['text'] ?? '',
            ];

            // Add URL for URL buttons
            if (isset($button['url']) && !empty($button['url'])) {
                $buttonData['url'] = $button['url'];
            }

            // Add phone number for phone buttons
            if (isset($button['phone_number']) && !empty($button['phone_number'])) {
                $buttonData['phone_number'] = $button['phone_number'];
                
                // Add country code if provided
                if (isset($button['country']) && !empty($button['country'])) {
                    $buttonData['country'] = $button['country'];
                }
            }

            // Add example for copy code buttons
            if (isset($button['example']) && !empty($button['example'])) {
                $buttonData['example'] = $button['example'];
            }

            $parsedButtons[] = $buttonData;
        }

        return $parsedButtons;
    }

    /**
     * Get contact group or null for 'all' contacts
     */
    private function getContactGroup(string|int $contacts): ?ContactGroup
    {
        if ($contacts === 'all') {
            return null;
        }

        return ContactGroup::where('uuid', $contacts)->first();
    }

    /**
     * Get selected WhatsApp session ID
     */
    private function getSelectedWhatsAppAccount(?int $sessionId, int $workspaceId): ?int
    {
        if ($sessionId) {
            return $sessionId;
        }

        // Auto-select primary session or first active session
        $session = WhatsAppAccount::forWorkspace($workspaceId)
            ->active()
            ->primary()
            ->first() ?? WhatsAppAccount::forWorkspace($workspaceId)
            ->active()
            ->first();

        return $session?->id;
    }

    /**
     * Parse scheduled time from request
     */
    private function parseScheduledTime(object $request): ?Carbon
    {
        if ($request->skip_schedule) {
            return Carbon::now('UTC');
        }

        if (!$request->scheduled_at) {
            return Carbon::now('UTC');
        }

        $timezone = Setting::where('key', 'timezone')->value('value');
        $workspace = Workspace::find(session()->get('current_workspace'));
        $workspaceMetadata = json_decode($workspace->metadata ?? '{}', true);
        $timezone = $workspaceMetadata['timezone'] ?? $timezone;

        return Carbon::parse($request->scheduled_at, $timezone)->setTimezone('UTC');
    }

    /**
     * Update campaign with processed message content
     */
    public function updateCampaignMessageContent(Campaign $campaign): void
    {
        if ($campaign->isDirectMessage()) {
            // Direct campaigns already have message content
            return;
        }

        // For template campaigns, you might want to copy template content
        // to campaign fields for easier processing
        if ($campaign->template) {
            $campaign->update([
                'body_text' => $campaign->template->body_text,
                'header_type' => $campaign->template->header_type,
                'header_text' => $campaign->template->header_text,
                'header_media' => $campaign->template->header_media,
                'footer_text' => $campaign->template->footer_text,
                'buttons_data' => $campaign->template->buttons_data,
            ]);
        }
    }

    public function sendCampaign(){
        //Laravel jobs implementation
        SendCampaignJob::dispatch();
    }

    public function destroy($uuid)
    {
        Campaign::where('uuid', $uuid)->update([
            'deleted_by' => Auth::user()->id,
            'deleted_at' => now()
        ]);
    }

    private function getContentTypeFromUrl($url) {
        try {
            // Make a HEAD request to fetch headers only
            $response = Http::head($url);
    
            // Check if the Content-Type header is present
            if ($response->hasHeader('Content-Type')) {
                return $response->header('Content-Type');
            }
    
            return null;
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error fetching headers: ' . $e->getMessage());
            return null;
        }
    }

    private function getMediaSizeInBytesFromUrl($url) {
        $url = ltrim($url, '/');
        $imageContent = file_get_contents($url);
    
        if ($imageContent !== false) {
            return strlen($imageContent);
        }
    
        return null;
    }
}
