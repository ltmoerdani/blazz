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
use App\Services\WhatsApp\MessageSendingService;
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

    private MessageSendingService $messageService;
    private MediaProcessingService $mediaService;
    private TemplateManagementService $templateService;
    private ?AutoReplyService $autoReplyService;
    private $workspaceId;

    public function __construct(
        $workspaceId,
        MessageSendingService $messageService,
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

            // Mark messages as read
            Chat::where('contact_id', $contact->id)
                ->where('type', 'inbound')
                ->whereNull('deleted_at')
                ->where('is_read', 0)
                ->update(['is_read' => 1]);

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
                            'from' => $contacts->firstItem(),
                            'last_page' => $contacts->lastPage(),
                            'per_page' => $contacts->perPage(),
                            'to' => $contacts->lastItem(),
                            'total' => $contacts->total(),
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
                        'from' => $contacts->firstItem(),
                        'last_page' => $contacts->lastPage(),
                        'per_page' => $contacts->perPage(),
                        'to' => $contacts->lastItem(),
                        'total' => $contacts->total(),
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

    /**
     * New method to replace deprecated getChatList
     * Retrieves chat list with filters and pagination
     */
    public function getChatListWithFilters($request, $uuid = null, $searchTerm = null, $sessionId = null)
    {
        // @deprecated Use getChatList method directly
        /** @noinspection PhpDeprecationInspection */
        return $this->getChatList($request, $uuid, $searchTerm, $sessionId);
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
        // OLD: Code removed during dependency injection migration
        // NEW: Use injected services
        if($request->type === 'text'){
            return $this->messageService->sendMessage($request->uuid, $request->message, Auth::id());
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

            return $this->messageService->sendMedia($request->uuid, $request->type, $fileName, $mediaFilePath, $mediaUrl, $location);
        }
    }

    public function sendTemplateMessage(object $request, $uuid)
    {
        // OLD: Keep for reference during transition
        /*
        if(!$this->whatsappService) {
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->message = 'WhatsApp service not available';
            return $responseObject;
        }
        */
        $template = Template::where('uuid', $request->template)->first();
        $contact = Contact::where('uuid', $uuid)->first();
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
                            $uploadedFile = $file->store('uploads/media/sent/' . $this->workspaceId, 's3');
                            /** @var \Illuminate\Filesystem\FilesystemAdapter $s3Disk */
                            $s3Disk = Storage::disk('s3');
                            $mediaFilePath = $s3Disk->url($uploadedFile);
            
                            $mediaUrl = $mediaFilePath;
                        }

                        $contentType = $this->getContentTypeFromUrl($mediaUrl);
                        $mediaSize = $this->getMediaSizeInBytesFromUrl($mediaUrl);

                        //save media
                        $chatMedia = new ChatMedia;
                        $chatMedia->name = $fileName;
                        $chatMedia->location = $storage == 'aws' ? 'amazon' : 'local';
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

        //Build Template to send
        $template = $this->buildTemplate($template->name, $template->language, json_decode(json_encode($metadata)), $contact);

        // NEW: Use injected service
        return $this->messageService->sendTemplateMessage($contact->uuid, $template, Auth::id(), null, $mediaId);
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
            event(new NewChatEvent($contact->id, [
                'type' => 'text',
                'message' => $message['text']['body'],
                'message_id' => $message['id'],
            ]));

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
            event(new NewChatEvent($contact->id, [
                'type' => $message['type'],
                'message_id' => $message['id'],
                'media_url' => $message[$message['type']]['url'] ?? null,
            ]));

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
            event(new NewChatEvent($contact->id, [
                'type' => 'interactive',
                'message_id' => $message['id'],
                'interactive_type' => $message['interactive']['type'] ?? null,
            ]));

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
            event(new NewChatEvent($contact->id, [
                'type' => 'button',
                'message_id' => $message['id'],
                'button_text' => $message['button']['text'] ?? null,
            ]));

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
            event(new NewChatEvent($contact->id, [
                'type' => 'location',
                'message_id' => $message['id'],
                'latitude' => $message['location']['latitude'] ?? null,
                'longitude' => $message['location']['longitude'] ?? null,
            ]));

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
            event(new NewChatEvent($contact->id, [
                'type' => 'contacts',
                'message_id' => $message['id'],
                'contacts_count' => count($message['contacts'] ?? []),
            ]));

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
}
