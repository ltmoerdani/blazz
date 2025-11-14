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
        $workspaceId = Auth::user()->current_workspace_id;
        return $this->getChatService($workspaceId)->getChatListWithFilters($request, $uuid, $request->query('search'));
    }

    public function updateChatSortDirection(Request $request)
    {
        $request->session()->put('chat_sort_direction', $request->sort);

        return Redirect::back();
    }

    public function sendMessage(Request $request)
    {
        $workspaceId = Auth::user()->current_workspace_id;
        return $this->getChatService($workspaceId)->sendMessage($request);
    }

    public function sendTemplateMessage(Request $request, $uuid)
    {
        $workspaceId = Auth::user()->current_workspace_id;
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
        $workspaceId = Auth::user()->current_workspace_id;
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
        $workspaceId = Auth::user()->current_workspace_id;
        $messages = $this->getChatService($workspaceId)->getChatMessages($contactId, $page);
        
        return response()->json($messages);
    }
}