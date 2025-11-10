<?php

namespace App\Http\Controllers\Api\v1;

use App\Events\NewChatEvent;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Contact;
use App\Models\Chat;
use App\Models\ChatLog;
use App\Models\ChatStatusLog;
use App\Models\ChatMedia;
use App\Models\workspace;
use App\Models\Setting;
use App\Models\Template;
use App\Services\AutoReplyService;
use App\Services\ChatService;
use App\Services\WhatsApp\MessageSendingService;
use App\Services\WhatsApp\MediaProcessingService;
use App\Services\WhatsApp\TemplateManagementService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Propaganistas\LaravelPhone\PhoneNumber;
use GuzzleHttp\Exception\RequestException;

class WhatsAppWebhookController extends BaseController
{
    private ?ChatService $chatService;
    private ?AutoReplyService $autoReplyService;

    public function __construct(
        MessageSendingService $messageService,
        MediaProcessingService $mediaService,
        TemplateManagementService $templateService
    ) {
        // Initialize services with workspace ID when needed
        $this->chatService = null;
        $this->autoReplyService = null;

        Config::set('broadcasting.connections.pusher', [
            'driver' => 'pusher',
            'key' => Setting::where('key', 'pusher_app_key')->value('value'),
            'secret' => Setting::where('key', 'pusher_app_secret')->value('value'),
            'app_id' => Setting::where('key', 'pusher_app_id')->value('value'),
            'options' => [
                'cluster' => Setting::where('key', 'pusher_app_cluster')->value('value'),
            ],
        ]);
    }

    /**
     * Handle WhatsApp webhook verification
     */
    public function whatsappWebhook(Request $request)
    {
        $verifyToken = Setting::where('key', 'whatsapp_callback_token')->first()->value;

        $mode = $request->input('hub_mode');
        $token = $request->input('hub_verify_token');
        $challenge = $request->input('hub_challenge');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return Response::make($challenge, 200);
        } else {
            return Response::json(['error' => 'Forbidden'], 200);
        }
    }

    /**
     * Handle WhatsApp webhook requests
     */
    public function handle(Request $request, $identifier = null)
    {
        $workspace = $this->getWorkspaceByIdentifier($identifier);

        if (!$workspace) {
            return $this->forbiddenResponse();
        }

        return $this->handleMethod($request, $workspace);
    }

    /**
     * Handle different HTTP methods
     */
    protected function handleMethod(Request $request, workspace $workspace)
    {
        if ($request->isMethod('get')) {
            return $this->handleGetRequest($request, $workspace);
        } elseif ($request->isMethod('post')) {
            $metadata = json_decode($workspace->metadata);

            if (empty($metadata)) {
                return $this->forbiddenResponse();
            }

            return $this->handlePostRequest($request, $workspace);
        }

        return Response::json(['error' => 'Method Not Allowed'], 405);
    }

    /**
     * Handle GET requests (webhook verification)
     */
    protected function handleGetRequest(Request $request, workspace $workspace)
    {
        $verifyToken = Setting::where('key', 'whatsapp_callback_token')->value('value');
        $mode = $request->input('hub_mode');
        $token = $request->input('hub_verify_token');
        $challenge = $request->input('hub_challenge');

        if ($mode === 'subscribe' && $token && $token === $verifyToken) {
            Log::info('WhatsApp webhook verified', [
                'workspace_id' => $workspace->id,
                'identifier' => $workspace->identifier,
            ]);
            return Response::make($challenge, 200);
        }

        return $this->forbiddenResponse();
    }

    /**
     * Handle POST requests (webhook events)
     */
    protected function handlePostRequest(Request $request, workspace $workspace)
    {
        $payload = $request->getContent();

        Log::info('WhatsApp webhook received', [
            'workspace_id' => $workspace->id,
            'identifier' => $workspace->identifier,
            'payload_preview' => substr($payload, 0, 200),
        ]);

        try {
            $data = json_decode($payload, true);

            if (!$data) {
                Log::error('Invalid JSON payload', ['workspace_id' => $workspace->id]);
                return $this->forbiddenResponse();
            }

            if (isset($data['entry'])) {
                foreach ($data['entry'] as $entry) {
                    if (isset($entry['changes'])) {
                        foreach ($entry['changes'] as $change) {
                            $this->processChange($change, $workspace);
                        }
                    }
                }
            }

            return Response::json(['status' => 'success'], 200);

        } catch (Exception $e) {
            Log::error('Error processing WhatsApp webhook', [
                'error' => $e->getMessage(),
                'workspace_id' => $workspace->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return Response::json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Process webhook change event
     */
    protected function processChange($change, workspace $workspace)
    {
        try {
            if (isset($change['field'])) {
                switch ($change['field']) {
                    case 'messages':
                        $this->processMessages($change, $workspace);
                        break;
                    case 'message_template_status_update':
                        $this->processMessageTemplateStatus($change, $workspace);
                        break;
                    case 'phone_number_name_update':
                        $this->processPhoneNumberNameUpdate($change, $workspace);
                        break;
                    case 'phone_number_quality_update':
                        $this->processPhoneNumberQualityUpdate($change, $workspace);
                        break;
                    default:
                        Log::info('Unhandled webhook field', [
                            'field' => $change['field'],
                            'workspace_id' => $workspace->id,
                        ]);
                }
            }
        } catch (Exception $e) {
            Log::error('Error processing webhook change', [
                'error' => $e->getMessage(),
                'change' => $change,
                'workspace_id' => $workspace->id,
            ]);
        }
    }

    /**
     * Process message events
     */
    protected function processMessages($change, workspace $workspace)
    {
        if (isset($change['value']['messages'])) {
            foreach ($change['value']['messages'] as $message) {
                $this->processMessage($message, $change['value']['metadata'], $workspace);
            }
        } elseif (isset($change['value']['statuses'])) {
            foreach ($change['value']['statuses'] as $status) {
                $this->processMessageStatus($status, $change['value']['metadata'], $workspace);
            }
        }
    }

    /**
     * Process individual message
     */
    protected function processMessage($message, $metadata, workspace $workspace)
    {
        try {
            $chatService = $this->getChatService($workspace->id);

            if ($message['type'] === 'text') {
                $chatService->processTextMessage($message, $metadata, $workspace);
            } elseif ($message['type'] === 'image' || $message['type'] === 'video' || $message['type'] === 'audio' || $message['type'] === 'document') {
                $chatService->processMediaMessage($message, $metadata, $workspace);
            } elseif ($message['type'] === 'interactive') {
                $chatService->processInteractiveMessage($message, $metadata, $workspace);
            } elseif ($message['type'] === 'button') {
                $chatService->processButtonMessage($message, $metadata, $workspace);
            } elseif ($message['type'] === 'location') {
                $chatService->processLocationMessage($message, $metadata, $workspace);
            } elseif ($message['type'] === 'contacts') {
                $chatService->processContactsMessage($message, $metadata, $workspace);
            } else {
                Log::info('Unhandled message type', [
                    'type' => $message['type'],
                    'message_id' => $message['id'] ?? null,
                    'workspace_id' => $workspace->id,
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error processing message', [
                'error' => $e->getMessage(),
                'message_id' => $message['id'] ?? null,
                'workspace_id' => $workspace->id,
            ]);
        }
    }

    /**
     * Process message status updates
     */
    protected function processMessageStatus($status, $metadata, workspace $workspace)
    {
        try {
            $chatLog = ChatLog::where('message_id', $status['id'])
                ->where('workspace_id', $workspace->id)
                ->first();

            if ($chatLog) {
                $chatLog->update([
                    'status' => $status['status'],
                    'timestamp' => $status['timestamp'] ?? now(),
                ]);

                // Update chat status log
                ChatStatusLog::create([
                    'chat_log_id' => $chatLog->id,
                    'status' => $status['status'],
                    'timestamp' => $status['timestamp'] ?? now(),
                    'metadata' => json_encode($status),
                ]);

                // Broadcast status update
                event(new NewChatEvent($chatLog->chat_id, [
                    'type' => 'message_status',
                    'status' => $status['status'],
                    'message_id' => $status['id'],
                ]));
            }

        } catch (Exception $e) {
            Log::error('Error processing message status', [
                'error' => $e->getMessage(),
                'status' => $status,
                'workspace_id' => $workspace->id,
            ]);
        }
    }

    /**
     * Process message template status updates
     */
    protected function processMessageTemplateStatus($change, workspace $workspace)
    {
        try {
            Log::info('Message template status update', [
                'event' => $change['value']['event'] ?? 'unknown',
                'message_template_id' => $change['value']['message_template_id'] ?? null,
                'workspace_id' => $workspace->id,
            ]);

            // Update template status in database
            if (isset($change['value']['message_template_id'])) {
                $template = Template::where('template_id', $change['value']['message_template_id'])
                    ->where('workspace_id', $workspace->id)
                    ->first();

                if ($template) {
                    $template->update([
                        'status' => $change['value']['event'] ?? 'unknown',
                        'last_updated' => now(),
                    ]);
                }
            }

        } catch (Exception $e) {
            Log::error('Error processing template status', [
                'error' => $e->getMessage(),
                'change' => $change,
                'workspace_id' => $workspace->id,
            ]);
        }
    }

    /**
     * Process phone number name updates
     */
    protected function processPhoneNumberNameUpdate($change, workspace $workspace)
    {
        try {
            Log::info('Phone number name update', [
                'display_phone_number' => $change['value']['display_phone_number'] ?? null,
                'verified_name' => $change['value']['verified_name'] ?? null,
                'workspace_id' => $workspace->id,
            ]);

            // Update workspace phone number details if needed
            if (isset($change['value']['display_phone_number'])) {
                $workspace->update([
                    'phone_display_name' => $change['value']['verified_name'] ?? null,
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error processing phone number name update', [
                'error' => $e->getMessage(),
                'change' => $change,
                'workspace_id' => $workspace->id,
            ]);
        }
    }

    /**
     * Process phone number quality updates
     */
    protected function processPhoneNumberQualityUpdate($change, workspace $workspace)
    {
        try {
            Log::info('Phone number quality update', [
                'quality_rating' => $change['value']['quality_rating'] ?? null,
                'workspace_id' => $workspace->id,
            ]);

            // Update workspace phone number quality rating
            if (isset($change['value']['quality_rating'])) {
                $workspace->update([
                    'phone_quality_rating' => $change['value']['quality_rating'],
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error processing phone number quality update', [
                'error' => $e->getMessage(),
                'change' => $change,
                'workspace_id' => $workspace->id,
            ]);
        }
    }

    /**
     * Get workspace by identifier
     */
    protected function getWorkspaceByIdentifier($identifier)
    {
        return workspace::where('identifier', $identifier)->first();
    }

    /**
     * Get chat service instance
     */
    private function getChatService($workspaceId)
    {
        if (!$this->chatService) {
            $this->chatService = new ChatService(
                $workspaceId,
                app('App\Services\WhatsApp\MessageSendingService'),
                app('App\Services\WhatsApp\MediaProcessingService'),
                app('App\Services\WhatsApp\TemplateManagementService')
            );
        }
        return $this->chatService;
    }

    /**
     * Get auto reply service instance
     */
    private function getAutoReplyService($workspaceId)
    {
        if (!$this->autoReplyService) {
            $this->autoReplyService = new AutoReplyService($workspaceId);
        }
        return $this->autoReplyService;
    }

    /**
     * Return forbidden response
     */
    protected function forbiddenResponse()
    {
        return Response::json(['error' => 'Forbidden'], 403);
    }
}