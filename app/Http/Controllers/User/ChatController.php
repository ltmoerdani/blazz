<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller as BaseController;
use App\Models\AutoReply;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\workspace;
use App\Services\ChatService;
use App\Services\WhatsappService;
use App\Services\WhatsApp\MessageSendingService;
use App\Services\WhatsApp\MediaProcessingService;
use App\Services\WhatsApp\TemplateManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class ChatController extends BaseController
{
    private ?ChatService $chatService;
    
    public function __construct(
        private MessageSendingService $messageService,
        private MediaProcessingService $mediaService,
        private TemplateManagementService $templateService
    ) {
        // Initialize services with workspace ID when needed
        $this->chatService = null;
    }
    
    private function getChatService($workspaceId)
    {
        if (!$this->chatService) {
            $this->chatService = new ChatService(
                $workspaceId,
                $this->messageService,
                $this->mediaService,
                $this->templateService
            );
        }
        return $this->chatService;
    }

    public function index(Request $request, $uuid = null)
    {
        $workspaceId = session()->get('current_workspace');

        // Support AJAX requests untuk SPA navigation (no page reload)
        // Only return JSON if it's explicitly an AJAX call (not Inertia)
        if ($request->ajax() && $request->header('X-Requested-With') === 'XMLHttpRequest' && !$request->header('X-Inertia')) {
            Log::info('ChatController::index - AJAX request', [
                'workspace_id' => $workspaceId,
                'uuid' => $uuid
            ]);
            
            // Return JSON response untuk AJAX
            return $this->getContactChatData($workspaceId, $uuid);
        }

        // DEBUG: Add logging for troubleshooting
        Log::info('ChatController::index called', [
            'workspace_id' => $workspaceId,
            'uuid' => $uuid,
            'search' => $request->query('search'),
            'headers' => [
                'X-Inertia' => $request->header('X-Inertia'),
                'X-Requested-With' => $request->header('X-Requested-With'),
                'Accept' => $request->header('Accept')
            ]
        ]);

        // Check data counts for debugging
        $chatCount = Chat::where('workspace_id', $workspaceId)->count();
        $contactsWithChatsCount = Contact::where('workspace_id', $workspaceId)
            ->whereHas('chats', function($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId)
                  ->whereNull('deleted_at');
            })
            ->count();

        Log::info('Chat list data counts', [
            'total_chats' => $chatCount,
            'contacts_with_chats' => $contactsWithChatsCount,
            'contacts_with_latest_chat' => Contact::where('workspace_id', $workspaceId)
                ->whereNotNull('latest_chat_created_at')
                ->count(),
        ]);

        return $this->getChatService($workspaceId)->getChatListWithFilters($request, $uuid, $request->query('search'));
    }
    
    /**
     * Get contact chat data for AJAX requests (SPA navigation)
     */
    private function getContactChatData($workspaceId, $uuid)
    {
        if (!$uuid) {
            return response()->json([
                'contact' => null,
                'chatThread' => [],
                'hasMoreMessages' => false,
                'nextPage' => 1
            ]);
        }
        
        $contact = Contact::where('uuid', $uuid)
            ->where('workspace_id', $workspaceId)
            ->first();
            
        if (!$contact) {
            return response()->json(['error' => 'Contact not found'], 404);
        }
        
        // Get chat messages
        $messages = $this->getChatService($workspaceId)->getChatMessages($contact->id, 1);
        
        return response()->json([
            'contact' => $contact,
            'chatThread' => $messages['messages'],
            'hasMoreMessages' => $messages['hasMoreMessages'],
            'nextPage' => $messages['nextPage']
        ]);
    }

    public function updateChatSortDirection(Request $request)
    {
        $request->session()->put('chat_sort_direction', $request->sort);

        return Redirect::back();
    }

    public function sendMessage(Request $request)
    {
        $workspaceId = session()->get('current_workspace');
        return $this->getChatService($workspaceId)->sendMessage($request);
    }

    public function sendTemplateMessage(Request $request, $uuid)
    {
        $workspaceId = session()->get('current_workspace');
        $res = $this->getChatService($workspaceId)->sendTemplateMessage($request, $uuid);

        return Redirect::back()->with(
            'status', [
                'type' => $res->success === true ? 'success' : 'error', 
                'message' => $res->success === true ? __('Message sent successfully!') : $res->message,
                'res' => $res
            ]
        );
    }

    public function deleteChats($uuid)
    {
        $workspaceId = session()->get('current_workspace');
        $this->getChatService($workspaceId)->clearContactChat($uuid);

        return Redirect::back()->with(
            'status', [
                'type' => 'success', 
                'message' => __('Chat cleared successfully!')
            ]
        );
    }

    public function loadMoreMessages(Request $request, $contactId)
    {
        $page = $request->query('page', 1);
        $workspaceId = session()->get('current_workspace');
        $messages = $this->getChatService($workspaceId)->getChatMessages($contactId, $page);
        
        return response()->json($messages);
    }
}