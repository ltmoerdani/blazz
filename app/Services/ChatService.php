<?php

namespace App\Services;

use App\Events\NewChatEvent;
use App\Http\Resources\ContactResource;
use App\Helpers\CustomHelper;
use App\Models\Addon;
use App\Models\Chat;
use App\Models\ChatLog;
use App\Models\ChatMedia;
use App\Models\ChatTicket;
use App\Models\ChatTicketLog;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactGroup;
use App\Models\workspace;
use App\Models\Setting;
use App\Models\Team;
use App\Models\Template;
use App\Models\WhatsAppAccount; // NEW: For session filter dropdown
use App\Services\SubscriptionService;
use App\Services\WhatsappService;
use App\Services\WhatsApp\MessageService;
use App\Services\WhatsApp\MediaProcessingService;
use App\Services\WhatsApp\TemplateManagementService;
use App\Services\AutoReplyService;
use App\Traits\TemplateTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Exception;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class ChatService
{
    use TemplateTrait;
    
    // Constants for repeated string literals
    const AI_ASSISTANT_MODULE = 'AI Assistant';

    private MessageService $messageService;
    private MediaProcessingService $mediaService;
    private TemplateManagementService $templateService;
    private ?AutoReplyService $autoReplyService;
    private $workspaceId;

    public function __construct(
        $workspaceId,
        MessageService $messageService,
        MediaProcessingService $mediaService,
        TemplateManagementService $templateService
    ) {
        $this->workspaceId = $workspaceId;
        $this->messageService = $messageService;
        $this->mediaService = $mediaService;
        $this->templateService = $templateService;
        $this->autoReplyService = null;
    }

    /**
     * @deprecated Use constructor injection instead
     */
    /*
    private function initializeWhatsappService()
    {
        $workspace = workspace::where('id', $this->workspaceId)->first();

        if (!$workspace) {
            $this->whatsappService = null;
            return;
        }

        $config = $workspace->metadata;
        $config = $config ? json_decode($config, true) : [];

        $accessToken = $config['whatsapp']['access_token'] ?? null;
        $apiVersion = config('graph.api_version');
        $appId = $config['whatsapp']['app_id'] ?? null;
        $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
        $wabaId = $config['whatsapp']['waba_id'] ?? null;

        $this->whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $this->workspaceId);
    }
    */

    /**
     * @deprecated Use getChatListWithFilters instead
     */
    public function getChatList($request, $uuid = null, $searchTerm = null, $sessionId = null)
    {
        return $this->getChatListWithFilters($request, $uuid, $searchTerm, $sessionId);
    }

    /**
     * New method to replace deprecated getChatList
     * Retrieves chat list with filters and pagination
     */
    public function getChatListWithFilters($request, $uuid = null, $searchTerm = null, $sessionId = null)
    {
        $role = Auth::user()->teams[0]->role;
        $contact = new Contact;
        $config = workspace::where('id', $this->workspaceId)->first();
        $agents = Team::where('workspace_id', $this->workspaceId)->get();
        $ticketState = $request->status == null ? 'all' : $request->status;
        $sortDirection = $request->session()->get('chat_sort_direction') ?? 'desc';
        $allowAgentsToViewAllChats = true;
        $ticketingActive = false;
        $aimodule = CustomHelper::isModuleEnabled(self::AI_ASSISTANT_MODULE);

        //Check if tickets module has been enabled
        if($config && $config->metadata != null){
            $settings = json_decode($config->metadata);

            if(isset($settings->tickets) && $settings->tickets->active === true){
                $ticketingActive = true;

                //Check for chats that don't have corresponding chat ticket rows
                $contacts = $contact->contactsWithChats($this->workspaceId, null, false, null, 'asc', 'owner', true, $sessionId);

                foreach($contacts as $contact){
                    ChatTicket::firstOrCreate(
                        ['contact_id' => $contact->id],
                        [
                            'assigned_to' => null,
                            'status' => 'open',
                            'updated_at' => now(),
                        ]
                    );
                }

                //Check if agents can view all chats
                $allowAgentsToViewAllChats = $settings->tickets->allow_agents_to_view_all_chats;
            }
        }

        // Retrieve the list of contacts with chats (with session filter support)
        $contacts = $contact->contactsWithChats($this->workspaceId, $searchTerm, $ticketingActive, $ticketState, $sortDirection, $role, $allowAgentsToViewAllChats, $sessionId);
        $rowCount = $contact->contactsWithChatsCount($this->workspaceId, $searchTerm, $ticketingActive, $ticketState, $sortDirection, $role, $allowAgentsToViewAllChats, $sessionId);

        $pusherSettings = Setting::whereIn('key', [
            'pusher_app_id',
            'pusher_app_key',
            'pusher_app_secret',
            'pusher_app_cluster',
        ])->pluck('value', 'key')->toArray();

        $messageTemplates = Template::where('workspace_id', $this->workspaceId)
            ->where('deleted_at', null)
            ->where('status', 'APPROVED')
            ->get();

        if ($uuid !== null) {
            $contact = Contact::with(['lastChat', 'lastInboundChat', 'notes', 'contactGroups'])
                ->where('uuid', $uuid)
                ->first();
            
            $ticket = ChatTicket::with('user')
                ->where('contact_id', $contact->id)
                ->first();

            $initialMessages = $this->getChatMessages($contact->id);

            // Mark messages as read and update contact unread counter
            $this->markContactMessagesAsRead($contact->id);

            if (request()->expectsJson()) {
                return response()->json([
                    'result' => ContactResource::collection($contacts)->response()->getData(),
                ], 200);
            } else {
                $settings = $config && $config->metadata ? json_decode($config->metadata) : null;

                //To ensure the unread message counter is updated
                $unreadMessages = Chat::where('workspace_id', $this->workspaceId)
                    ->where('type', 'inbound')
                    ->where('deleted_at', null)
                    ->where('is_read', 0)
                    ->count();

                // NEW: Get WhatsApp sessions for filter dropdown (TASK-FE-1)
                $sessions = WhatsAppAccount::where('workspace_id', $this->workspaceId)
                    ->where('status', 'connected')
                    ->select('id', 'phone_number', 'provider_type')
                    ->withCount(['chats as unread_count' => function ($query) {
                        $query->where('is_read', false)
                              ->where('type', 'inbound')
                              ->whereNull('deleted_at');
                    }])
                    ->get();

                return Inertia::render('User/Chat/Index', [
                    'title' => 'Chats',
                    'rows' => (object)[
                        'data' => ContactResource::collection($contacts)->toArray(request()),
                        'meta' => [
                            'current_page' => $contacts->currentPage(),
                            'per_page' => $contacts->perPage(),
                            'has_more_pages' => $contacts->hasMorePages(),
                        ]
                    ],
                    'simpleForm' => !CustomHelper::isModuleEnabled(self::AI_ASSISTANT_MODULE) || empty(optional($settings)->ai->ai_chat_form_active),
                    'rowCount' => $rowCount,
                    'filters' => request()->all(),
                    'pusherSettings' => $pusherSettings,
                    'workspaceId' => $this->workspaceId,
                    'state' => app()->environment(),
                    'demoNumber' => env('DEMO_NUMBER'),
                    'settings' => $config ?? (object)['metadata' => null],
                    'templates' => $messageTemplates,
                    'status' => $request->status ?? 'all',
                    'chatThread' => $initialMessages['messages'],
                    'hasMoreMessages' => $initialMessages['hasMoreMessages'],
                    'nextPage' => $initialMessages['nextPage'],
                    'contact' => $contact,
                    'fields' => ContactField::where('workspace_id', $this->workspaceId)->where('deleted_at', null)->get(),
                    'locationSettings' => $this->getLocationSettings(),
                    'ticket' => $ticket,
                    'agents' => $agents,
                    'addon' => $aimodule,
                    'chat_sort_direction' => $sortDirection,
                    'unreadMessages' => $unreadMessages,
                    'sessions' => $sessions, // NEW: WhatsApp sessions for filter (TASK-FE-1)
                    'isChatLimitReached' => SubscriptionService::isSubscriptionFeatureLimitReached($this->workspaceId, 'message_limit')
                ]);
            }
        }

        if (request()->expectsJson()) {
            return response()->json([
                'result' => ContactResource::collection($contacts)->response()->getData(),
            ], 200);
        } else {
            $settings = $config && $config->metadata ? json_decode($config->metadata) : null;

            // NEW: Get WhatsApp sessions for filter dropdown (TASK-FE-1)
            $sessions = WhatsAppAccount::where('workspace_id', $this->workspaceId)
                ->where('status', 'connected')
                ->select('id', 'phone_number', 'provider_type')
                ->withCount(['chats as unread_count' => function ($query) {
                    $query->where('is_read', false)
                          ->where('type', 'inbound')
                          ->whereNull('deleted_at');
                }])
                ->get();

            return Inertia::render('User/Chat/Index', [
                'title' => 'Chats',
                'rows' => (object)[
                    'data' => ContactResource::collection($contacts)->toArray(request()),
                    'meta' => [
                        'current_page' => $contacts->currentPage(),
                        'per_page' => $contacts->perPage(),
                        'has_more_pages' => $contacts->hasMorePages(),
                    ]
                ],
                'simpleForm' => !CustomHelper::isModuleEnabled(self::AI_ASSISTANT_MODULE) || empty(optional($settings)->ai->ai_chat_form_active),
                'rowCount' => $rowCount,
                'filters' => request()->all(),
                'pusherSettings' => $pusherSettings,
                'workspaceId' => $this->workspaceId,
                'state' => app()->environment(),
                'settings' => $config ?? (object)['metadata' => null],
                'templates' => $messageTemplates,
                'status' => $request->status ?? 'all',
                'agents' => $agents,
                'addon' => $aimodule,
                'ticket' => array(),
                'chat_sort_direction' => $sortDirection,
                'sessions' => $sessions, // NEW: WhatsApp sessions for filter (TASK-FE-1)
                'isChatLimitReached' => SubscriptionService::isSubscriptionFeatureLimitReached($this->workspaceId, 'message_limit'),
                // Add missing props for consistency with UUID route
                'contact' => null,
                'chatThread' => [],
                'hasMoreMessages' => false,
                'nextPage' => null,
                'fields' => ContactField::where('workspace_id', $this->workspaceId)->where('deleted_at', null)->get(),
                'locationSettings' => $this->getLocationSettings(),
            ]);
        }
    }

    public function handleTicketAssignment($contactId){
        $workspaceId = $this->workspaceId;
        $workspace = workspace::where('id', $this->workspaceId)->first();
        
        if (!$workspace || !$workspace->metadata) {
            return;
        }
        
        $settings = json_decode($workspace->metadata);

        // Check if ticket functionality is active
        if(isset($settings->tickets) && $settings->tickets->active === true){
            $autoassignment = $settings->tickets->auto_assignment;
            $reassignOnReopen = $settings->tickets->reassign_reopened_chats;

            // Check if a ticket already exists for the contact
            $ticket = ChatTicket::where('contact_id', $contactId)->first();

            DB::transaction(function () use ($reassignOnReopen, $autoassignment, $ticket, $contactId, $workspaceId) {
                if(!$ticket){
                    // Create a new ticket if it doesn't exist
                    $ticket = new ChatTicket;
                    $ticket->contact_id = $contactId;
                    $ticket->status = 'open';
                    $ticket->updated_at = now();

                    // Perform auto-assignment if enabled
                    if($autoassignment){
                        // Find an agent with the least number of assigned tickets
                        $agent = Team::where('workspace_id', $workspaceId)
                            ->withCount('tickets')
                            ->whereNull('deleted_at')
                            ->orderBy('tickets_count')->first();

                        // Assign the ticket to the agent with the least number of assigned tickets
                        $ticket->assigned_to = $agent->user_id;
                    } else {
                        $ticket->assigned_to = null;
                    }

                    $ticket->save();

                    $ticketId = ChatTicketLog::insertGetId([
                        'contact_id' => $contactId,
                        'description' => 'Conversation was opened',
                        'created_at' => now()
                    ]);

                    ChatLog::insert([
                        'contact_id' => $contactId,
                        'entity_type' => 'ticket',
                        'entity_id' => $ticketId,
                        'created_at' => now()
                    ]);
                } else {
                    // Reopen the ticket if it's closed and reassignment on reopen is enabled
                    if($ticket->status === 'closed'){
                        if($reassignOnReopen){
                            if($autoassignment){
                                $agent = Team::where('workspace_id', $workspaceId)
                                    ->withCount('tickets')
                                    ->whereNull('deleted_at')
                                    ->orderBy('tickets_count')
                                    ->first();

                                $ticket->assigned_to = $agent->user_id;
                            } else {
                                $ticket->assigned_to = null;
                            }
                        }

                        $ticket->status = 'open';
                        $ticket->save();

                        $ticketId = ChatTicketLog::insertGetId([
                            'contact_id' => $contactId,
                            'description' => 'Conversation was moved from closed to open',
                            'created_at' => now()
                        ]);
    
                        ChatLog::insert([
                            'contact_id' => $contactId,
                            'entity_type' => 'ticket',
                            'entity_id' => $ticketId,
                            'created_at' => now()
                        ]);
                    }
                }
            });
        }
    }

    public function sendMessage(object $request)
    {
        try {
            // NEW: Use MessageService (WebJS) instead of MessageSendingService (Meta API)
            // Handle null/empty type - default to 'text'
            $type = $request->type ?? 'text';
            
            Log::info('ChatService::sendMessage called', [
                'type' => $type,
                'has_message' => !empty($request->message),
                'has_file' => !empty($request->file('file')),
                'uuid' => $request->uuid,
            ]);
            
            // Check if this is a text message or media message
            if($type === 'text' || empty($request->file('file'))){
                $result = $this->messageService->sendMessage(
                    $request->uuid, 
                    $request->message ?? '', 
                    'text',
                    ['optimistic_id' => $request->optimistic_id ?? null]
                );
                
                Log::info('ChatService::sendMessage - MessageService result', [
                    'success' => $result->success ?? 'null',
                    'message' => $result->message ?? 'null',
                ]);
                
                return $result;
            } else {
            $storage = Setting::where('key', 'storage_system')->first()->value;
            $fileName = $request->file('file')->getClientOriginalName();
            $fileContent = $request->file('file');

            if($storage === 'local'){
                $location = 'local';
                $file = Storage::disk('local')->put('public', $fileContent);
                $mediaFilePath = $file;
                $mediaUrl = rtrim(config('app.url'), '/') . '/media/' . ltrim($mediaFilePath, '/');
            } elseif($storage === 'aws') {
                $location = 'amazon';
                $file = $request->file('file');
                $uploadedFile = $file->store('uploads/media/sent/' . $this->workspaceId, 's3');
                /** @var \Illuminate\Filesystem\FilesystemAdapter $s3Disk */
                $s3Disk = Storage::disk('s3');
                $mediaFilePath = $s3Disk->url($uploadedFile);
                $mediaUrl = $mediaFilePath;
            }

            // Build options array for MessageService
            $options = [
                'file_name' => $fileName,
                'file_path' => $mediaFilePath,
                'media_url' => $mediaUrl,
                'location' => $location,
            ];

                $result = $this->messageService->sendMessage($request->uuid, $fileName, $type, $options);
                
                Log::info('ChatService::sendMessage - Media MessageService result', [
                    'success' => $result->success ?? 'null',
                    'message' => $result->message ?? 'null',
                ]);
                
                return $result;
            }
        } catch (\Exception $e) {
            Log::error('ChatService::sendMessage - Exception caught', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return (object) [
                'success' => false,
                'message' => 'Failed to send message: ' . $e->getMessage(),
            ];
        }
    }

    public function sendTemplateMessage(object $request, $uuid)
    {
        $template = Template::where('uuid', $request->template)->first();
        $contact = Contact::where('uuid', $uuid)->first();

        if (!$template || !$contact) {
            return (object) [
                'success' => false,
                'message' => 'Template or contact not found',
            ];
        }

        // Build template data for MessageService
        $templateData = [
            'name' => $template->name,
            'language' => [
                'code' => $template->language ?? 'en_US'
            ],
            'components' => []
        ];

        // Process header if exists
        if (isset($request->header['format']) && $request->header['format'] !== 'none') {
            $headerComponent = [
                'type' => 'header',
                'parameters' => []
            ];

            if ($request->header['format'] === 'text' && isset($request->header['text'])) {
                $headerComponent['parameters'][] = [
                    'type' => 'text',
                    'text' => $request->header['text']
                ];
            } elseif (in_array($request->header['format'], ['IMAGE', 'DOCUMENT', 'VIDEO'])) {
                if (isset($request->header['parameters'])) {
                    foreach ($request->header['parameters'] as $parameter) {
                        if ($parameter['selection'] === 'upload') {
                            // Handle file upload
                            $mediaUrl = $this->processTemplateMediaUpload($parameter['value']);
                            if ($mediaUrl) {
                                $headerComponent['parameters'][] = [
                                    'type' => strtolower($request->header['format']),
                                    'image' => ['link' => $mediaUrl] // Will be adjusted based on type
                                ];
                            }
                        } else {
                            // Use existing media URL
                            $headerComponent['parameters'][] = [
                                'type' => strtolower($request->header['format']),
                                'image' => ['link' => $parameter['value']]
                            ];
                        }
                    }
                }
            }

            $templateData['components'][] = $headerComponent;
        }

        // Process body (required)
        $bodyText = '';
        if (isset($request->body['text'])) {
            $bodyText = $request->body['text'];
        } else {
            $bodyText = $template->body_text ?? '';
        }

        $templateData['components'][] = [
            'type' => 'body',
            'parameters' => [
                [
                    'type' => 'text',
                    'text' => $bodyText
                ]
            ]
        ];

        // Process footer if exists
        if (isset($request->footer['text']) && !empty($request->footer['text'])) {
            $templateData['components'][] = [
                'type' => 'footer',
                'text' => $request->footer['text']
            ];
        }

        // Process buttons if exists
        if (isset($request->buttons) && !empty($request->buttons)) {
            $buttonComponent = [
                'type' => 'buttons',
                'buttons' => []
            ];

            foreach ($request->buttons as $button) {
                $buttonData = [
                    'type' => $button['type'],
                    'text' => $button['text']
                ];

                if ($button['type'] === 'url') {
                    $buttonData['url'] = $button['url'];
                } elseif ($button['type'] === 'phone_number') {
                    $buttonData['phone_number'] = $button['phone_number'];
                }

                $buttonComponent['buttons'][] = $buttonData;
            }

            $templateData['components'][] = $buttonComponent;
        }

        // Build options
        $options = [
            'user_id' => Auth::id(),
            'template_id' => $template->id,
        ];

        // NEW: Use MessageService (WebJS) instead of MessageSendingService (Meta API)
        return $this->messageService->sendTemplateMessage($contact->uuid, $templateData, $options);
    }

    /**
     * Process template media upload
     */
    private function processTemplateMediaUpload($file)
    {
        try {
            $storage = Setting::where('key', 'storage_system')->first()->value;
            $fileName = $file->getClientOriginalName();

            if($storage === 'local'){
                $filePath = Storage::disk('local')->put('public', $file);
                return rtrim(config('app.url'), '/') . '/media/' . ltrim($filePath, '/');
            } elseif($storage === 'aws') {
                $uploadedFile = $file->store('uploads/media/sent/' . $this->workspaceId, 's3');
                /** @var \Illuminate\Filesystem\FilesystemAdapter $s3Disk */
                $s3Disk = Storage::disk('s3');
                return $s3Disk->url($uploadedFile);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to process template media upload', [
                'error' => $e->getMessage(),
                'workspace_id' => $this->workspaceId
            ]);
            return null;
        }
    }

    public function clearMessage($uuid)
    {
        Chat::where('uuid', $uuid)
            ->update([
                'deleted_by' => Auth::id(),
                'deleted_at' => now()
            ]);
    }

    public function clearContactChat($uuid)
    {
        $contact = Contact::with('lastChat')->where('uuid', $uuid)->firstOrFail();
        Chat::where('contact_id', $contact->id)->update([
            'deleted_by' => Auth::id(),
            'deleted_at' => now()
        ]);

        ChatLog::where('contact_id', $contact->id)->where('entity_type', 'chat')->update([
            'deleted_by' => Auth::id(),
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

    private function getLocationSettings(){
        // Retrieve the settings for the current workspace
        $workspace = workspace::where('id', $this->workspaceId)->first();

        if ($workspace && $workspace->metadata) {
            // Decode the JSON metadata column into an associative array
            $metadata = json_decode($workspace->metadata, true);

            if (isset($metadata['contacts'])) {
                // If the 'contacts' key exists, retrieve the 'location' value
                return $metadata['contacts']['location'];
            }
        }
        
        return null;
    }

    public function getChatMessages($contactId, $page = 1, $perPage = 10)
    {
        $chatLogs = ChatLog::where('contact_id', $contactId)
            ->where('deleted_at', null)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $chats = [];
        foreach ($chatLogs as $chatLog) {
            $chats[] = array([
                'type' => $chatLog->entity_type,
                'value' => $chatLog->relatedEntities
            ]);
        }

        // DEBUG: Log structure untuk debug double bubble issue
        Log::debug('getChatMessages structure', [
            'contact_id' => $contactId,
            'total_chat_logs' => $chatLogs->count(),
            'chats_array_count' => count($chats),
            'first_chat_structure' => isset($chats[0]) ? [
                'is_array' => is_array($chats[0]),
                'first_element_type' => isset($chats[0][0]) ? $chats[0][0]['type'] : null,
                'has_value' => isset($chats[0][0]['value']),
                'value_id' => isset($chats[0][0]['value']->id) ? $chats[0][0]['value']->id : null,
            ] : null
        ]);

        return [
            'messages' => array_reverse($chats),
            'hasMoreMessages' => $chatLogs->hasMorePages(),
            'nextPage' => $chatLogs->currentPage() + 1
        ];
    }

    /**
     * Process text message from WhatsApp webhook
     */
    public function processTextMessage($message, $metadata, $workspace)
    {
        try {
            $contact = $this->findOrCreateContact($message, $metadata, $workspace);
            $this->createChatFromMessage($contact, $message, 'text', $workspace);

            // Handle auto-reply if enabled
            $chat = Chat::where('contact_id', $contact->id)
                ->where('message_id', $message['id'])
                ->first();

            if ($chat && $this->autoReplyService) {
                $this->autoReplyService->checkAutoReply($chat, false);
            }

            // Broadcast new message event
            if ($chat) {
                $this->broadcastNewChatEvent($chat, $workspace);
            }

        } catch (Exception $e) {
            Log::error('Error processing text message', [
                'error' => $e->getMessage(),
                'message_id' => $message['id'] ?? null,
                'workspace_id' => $workspace->id,
            ]);
        }
    }

    /**
     * Process media message from WhatsApp webhook
     */
    public function processMediaMessage($message, $metadata, $workspace)
    {
        try {
            $contact = $this->findOrCreateContact($message, $metadata, $workspace);
            $this->createChatFromMessage($contact, $message, $message['type'], $workspace);

            // Handle auto-reply if enabled
            $chat = Chat::where('contact_id', $contact->id)
                ->where('message_id', $message['id'])
                ->first();

            if ($chat && $this->autoReplyService) {
                $this->autoReplyService->checkAutoReply($chat, false);
            }

            // Broadcast new message event
            if ($chat) {
                $this->broadcastNewChatEvent($chat, $workspace);
            }

        } catch (Exception $e) {
            Log::error('Error processing media message', [
                'error' => $e->getMessage(),
                'message_id' => $message['id'] ?? null,
                'workspace_id' => $workspace->id,
            ]);
        }
    }

    /**
     * Process interactive message from WhatsApp webhook
     */
    public function processInteractiveMessage($message, $metadata, $workspace)
    {
        try {
            $contact = $this->findOrCreateContact($message, $metadata, $workspace);
            $this->createChatFromMessage($contact, $message, 'interactive', $workspace);

            // Handle auto-reply if enabled
            $chat = Chat::where('contact_id', $contact->id)
                ->where('message_id', $message['id'])
                ->first();

            if ($chat && $this->autoReplyService) {
                $this->autoReplyService->checkAutoReply($chat, false);
            }

            // Broadcast new message event
            if ($chat) {
                $this->broadcastNewChatEvent($chat, $workspace);
            }

        } catch (Exception $e) {
            Log::error('Error processing interactive message', [
                'error' => $e->getMessage(),
                'message_id' => $message['id'] ?? null,
                'workspace_id' => $workspace->id,
            ]);
        }
    }

    /**
     * Process button message from WhatsApp webhook
     */
    public function processButtonMessage($message, $metadata, $workspace)
    {
        try {
            $contact = $this->findOrCreateContact($message, $metadata, $workspace);
            $this->createChatFromMessage($contact, $message, 'button', $workspace);

            // Handle auto-reply if enabled
            $chat = Chat::where('contact_id', $contact->id)
                ->where('message_id', $message['id'])
                ->first();

            if ($chat && $this->autoReplyService) {
                $this->autoReplyService->checkAutoReply($chat, false);
            }

            // Broadcast new message event
            if ($chat) {
                $this->broadcastNewChatEvent($chat, $workspace);
            }

        } catch (Exception $e) {
            Log::error('Error processing button message', [
                'error' => $e->getMessage(),
                'message_id' => $message['id'] ?? null,
                'workspace_id' => $workspace->id,
            ]);
        }
    }

    /**
     * Process location message from WhatsApp webhook
     */
    public function processLocationMessage($message, $metadata, $workspace)
    {
        try {
            $contact = $this->findOrCreateContact($message, $metadata, $workspace);
            $this->createChatFromMessage($contact, $message, 'location', $workspace);

            // Handle auto-reply if enabled
            $chat = Chat::where('contact_id', $contact->id)
                ->where('message_id', $message['id'])
                ->first();

            if ($chat && $this->autoReplyService) {
                $this->autoReplyService->checkAutoReply($chat, false);
            }

            // Broadcast new message event
            if ($chat) {
                $this->broadcastNewChatEvent($chat, $workspace);
            }

        } catch (Exception $e) {
            Log::error('Error processing location message', [
                'error' => $e->getMessage(),
                'message_id' => $message['id'] ?? null,
                'workspace_id' => $workspace->id,
            ]);
        }
    }

    /**
     * Process contacts message from WhatsApp webhook
     */
    public function processContactsMessage($message, $metadata, $workspace)
    {
        try {
            $contact = $this->findOrCreateContact($message, $metadata, $workspace);
            $this->createChatFromMessage($contact, $message, 'contacts', $workspace);

            // Handle auto-reply if enabled
            $chat = Chat::where('contact_id', $contact->id)
                ->where('message_id', $message['id'])
                ->first();

            if ($chat && $this->autoReplyService) {
                $this->autoReplyService->checkAutoReply($chat, false);
            }

            // Broadcast new message event
            if ($chat) {
                $this->broadcastNewChatEvent($chat, $workspace);
            }

        } catch (Exception $e) {
            Log::error('Error processing contacts message', [
                'error' => $e->getMessage(),
                'message_id' => $message['id'] ?? null,
                'workspace_id' => $workspace->id,
            ]);
        }
    }

    /**
     * Find or create contact from WhatsApp message
     */
    private function findOrCreateContact($message, $metadata, $workspace)
    {
        $phoneNumber = $message['from'];
        $contactName = null;

        // Try to get contact name from message if it's a new contact
        if (isset($message['contacts'][0]['name'])) {
            $contactName = $message['contacts'][0]['name']['formatted_name'] ??
                          $message['contacts'][0]['name']['full_name'] ??
                          null;
        }

        // Find existing contact by phone number
        $contact = Contact::where('phone', $phoneNumber)
            ->where('workspace_id', $workspace->id)
            ->first();

        if (!$contact) {
            // Create new contact
            $contact = new Contact();
            $contact->uuid = Str::uuid();
            $contact->workspace_id = $workspace->id;
            $contact->phone = $phoneNumber;

            if ($contactName) {
                $nameParts = explode(' ', $contactName, 2);
                $contact->first_name = $nameParts[0] ?? null;
                $contact->last_name = $nameParts[1] ?? null;
                $contact->full_name = $contactName;
            }

            $contact->save();
        }

        return $contact;
    }

    /**
     * Create chat entry from message
     */
    private function createChatFromMessage($contact, $message, $type, $workspace)
    {
        $chat = new Chat();
        $chat->uuid = Str::uuid();
        $chat->contact_id = $contact->id;
        $chat->workspace_id = $workspace->id;
        $chat->message_id = $message['id'];
        $chat->type = 'inbound';
        $chat->metadata = json_encode($message);
        $chat->created_at = $message['timestamp'] ?? now();
        $chat->save();

        // Create chat log entry
        ChatLog::create([
            'contact_id' => $contact->id,
            'entity_type' => 'chat',
            'entity_id' => $chat->id,
            'created_at' => $chat->created_at,
        ]);

        // Handle ticket assignment if enabled
        $this->handleTicketAssignment($contact->id);
    }
    
    /**
     * Broadcast NewChatEvent with proper chat structure
     * 
     * @param Chat $chat
     * @param Workspace $workspace
     * @return void
     */
    /**
     * Broadcast new chat event with complete structured data
     * Following riset best practice (Section 4.3)
     */
    public function broadcastNewChatEvent($chat, $workspace = null)
    {
        try {
            // Load all necessary relationships
            $chat->load(['contact.workspace', 'media', 'user']);
            
            // Auto-resolve workspace if not provided
            if (!$workspace) {
                $workspace = $chat->contact->workspace;
            }
            
            Log::info('🔍 Broadcasting chat event', [
                'chat_id' => $chat->id,
                'contact_id' => $chat->contact_id,
                'workspace_id' => $workspace->id
            ]);
            
            // Build complete structured message data following riset pattern
            $messageData = [
                'id' => $chat->id,
                'wam_id' => $chat->wam_id,
                'contact_id' => $chat->contact_id,
                
                // Contact information (fully structured)
                'contact' => [
                    'id' => $chat->contact->id,
                    'first_name' => $chat->contact->first_name,
                    'phone' => $chat->contact->phone,
                    'profile_picture_url' => $chat->contact->profile_picture_url,
                    'unread_messages' => $chat->contact->unread_messages,
                    'latest_chat_created_at' => $chat->contact->latest_chat_created_at,
                ],
                
                // Message details
                'type' => $chat->type, // inbound/outbound
                'message_type' => $chat->message_type, // text/image/video/document
                'message_status' => $chat->message_status,
                'body' => $chat->body, // Uses accessor untuk get body dari metadata
                'chat_type' => $chat->chat_type ?? 'private',
                
                // Media information (if exists)
                'media_id' => $chat->media_id,
                'media' => $chat->media ? [
                    'id' => $chat->media->id,
                    'url' => $chat->media->url,
                    'mime_type' => $chat->media->mime_type,
                    'file_name' => $chat->media->file_name,
                    'file_size' => $chat->media->file_size,
                ] : null,
                
                // User information (for outbound messages)
                'user_id' => $chat->user_id,
                'user' => $chat->user ? [
                    'id' => $chat->user->id,
                    'name' => $chat->user->name,
                    'avatar' => $chat->user->avatar ?? null,
                ] : null,
                
                // Timestamps (handle both Carbon and string)
                'created_at' => is_string($chat->created_at) ? $chat->created_at : $chat->created_at?->toISOString(),
                'sent_at' => is_string($chat->sent_at) ? $chat->sent_at : $chat->sent_at?->toISOString(),
                'delivered_at' => is_string($chat->delivered_at) ? $chat->delivered_at : $chat->delivered_at?->toISOString(),
                'read_at' => is_string($chat->read_at) ? $chat->read_at : $chat->read_at?->toISOString(),
                'is_read' => (bool) $chat->is_read,
                
                // Metadata
                'metadata' => $chat->metadata,
            ];
            
            Log::info('📤 Broadcasting message.received event', [
                'workspace_id' => $workspace->id,
                'contact_id' => $chat->contact_id,
                'chat_id' => $chat->id,
                'channels' => [
                    'workspace.' . $workspace->id,
                    'workspace.' . $workspace->id . '.chat.' . $chat->contact_id
                ],
                'message_type' => $chat->message_type,
                'contact_name' => $chat->contact->first_name ?? 'Unknown'
            ]);
            
            // Broadcast with new structure and contactId for specific channel
            event(new NewChatEvent($messageData, $workspace->id, $chat->contact_id));
            
            Log::info('✅ message.received event broadcasted successfully');
            
        } catch (\Exception $e) {
            Log::error('❌ Error broadcasting message.received event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chat_id' => $chat->id,
                'workspace_id' => $workspace->id
            ]);
        }
    }
    
    /**
     * Mark all inbound messages as read for a contact
     * This triggers Chat model observer to auto-decrement unread_messages counter
     * 
     * @param int $contactId
     * @return int Number of messages marked as read
     */
    public function markContactMessagesAsRead($contactId)
    {
        try {
            // Get all unread messages for this contact
            $unreadMessages = Chat::where('contact_id', $contactId)
                ->where('type', 'inbound')
                ->whereNull('deleted_at')
                ->where('is_read', 0)
                ->get();
            
            $unreadCount = $unreadMessages->count();
            
            // CRITICAL: Recalculate and sync unread_messages counter
            // This fixes data inconsistency issues
            $contact = Contact::find($contactId);
            if ($contact) {
                $actualUnreadCount = Chat::where('contact_id', $contactId)
                    ->where('type', 'inbound')
                    ->whereNull('deleted_at')
                    ->where('is_read', 0)
                    ->count();
                
                // Force sync counter with actual data
                if ($contact->unread_messages != $actualUnreadCount) {
                    Log::warning('Unread counter mismatch - fixing', [
                        'contact_id' => $contactId,
                        'stored_count' => $contact->unread_messages,
                        'actual_count' => $actualUnreadCount
                    ]);
                    
                    $contact->unread_messages = $actualUnreadCount;
                    $contact->save();
                }
            }
            
            if ($unreadCount === 0) {
                Log::debug('No unread messages to mark', [
                    'contact_id' => $contactId,
                    'counter_synced' => true
                ]);
                return 0;
            }
            
            // CRITICAL: Update each message individually to trigger observer
            // Observer in Chat model will auto-decrement contact's unread_messages
            foreach ($unreadMessages as $message) {
                $message->is_read = 1;
                $message->save(); // This triggers updating() observer
            }
            
            // Force final sync to ensure counter is 0
            if ($contact) {
                $contact->refresh();
                if ($contact->unread_messages > 0) {
                    Log::warning('Counter still > 0 after mark as read - forcing to 0', [
                        'contact_id' => $contactId,
                        'remaining' => $contact->unread_messages
                    ]);
                    $contact->unread_messages = 0;
                    $contact->save();
                }
            }
            
            Log::info('Messages marked as read', [
                'contact_id' => $contactId,
                'marked_count' => $unreadCount,
                'observer_triggered' => true,
                'final_counter' => 0
            ]);
            
            return $unreadCount;
        } catch (\Exception $e) {
            Log::error('Failed to mark messages as read', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}
