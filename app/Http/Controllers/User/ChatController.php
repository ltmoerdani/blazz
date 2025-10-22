<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller as BaseController;
use App\Models\AutoReply;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\workspace;
use App\Models\WhatsAppSession; // NEW: For session filter dropdown (TASK-FE-1)
use App\Services\ChatService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class ChatController extends BaseController
{
    private function chatService()
    {
        return new ChatService(session()->get('current_workspace'));
    }

    public function index(Request $request, $uuid = null)
    {
        // NEW: Support session filter (TASK-FE-1)
        $sessionId = $request->query('session_id');

        return $this->chatService()->getChatList(
            $request,
            $uuid,
            $request->query('search'),
            $sessionId
        );
    }

    public function updateChatSortDirection(Request $request)
    {
        $request->session()->put('chat_sort_direction', $request->sort);

        return Redirect::back();
    }

    public function sendMessage(Request $request)
    {
        return $this->chatService()->sendMessage($request);
    }

    public function sendTemplateMessage(Request $request, $uuid)
    {
        $res = $this->chatService()->sendTemplateMessage($request, $uuid);

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
        $this->chatService()->clearContactChat($uuid);

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
        $messages = $this->chatService()->getChatMessages($contactId, $page);
        
        return response()->json($messages);
    }
}
