<?php

namespace App\Services\WhatsApp;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\WhatsAppAccount;
use App\Models\CampaignLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * WhatsApp Webhook Service
 *
 * Handles inbound webhooks from Node.js service
 * Manages event processing, queueing, and acknowledgment
 * Provides secure endpoint validation and rate limiting
 *
 * TASK-SERVICE-6: Laravel ↔ Node.js webhook communication
 */
class WebhookService
{
    protected $workspaceId;
    protected $logger;
    protected $nodeServiceUrl;
    protected $webhookSecret;
    protected $queuePrefix = 'whatsapp_webhooks';

    public function __construct($workspaceId)
    {
        $this->workspaceId = $workspaceId;
        $this->logger = Log::channel('whatsapp');
        $this->nodeServiceUrl = config('whatsapp.node_service_url', 'http://localhost:3000');
        $this->webhookSecret = config('whatsapp.webhook_secret');
    }

    /**
     * Process incoming webhook from Node.js service
     *
     * @param array $webhookData
     * @param string $signature
     * @return object
     */
    public function processWebhook($webhookData, $signature = null)
    {
        try {
            // Validate webhook signature
            if (!$this->validateWebhookSignature($webhookData, $signature)) {
                $this->logger->warning('Invalid webhook signature', [
                    'workspace_id' => $this->workspaceId,
                    'event_type' => $webhookData['event'] ?? 'unknown',
                ]);

                return (object) [
                    'success' => false,
                    'message' => 'Invalid webhook signature',
                    'status_code' => 401,
                ];
            }

            // Validate required fields
            if (!isset($webhookData['event']) || !isset($webhookData['data'])) {
                return (object) [
                    'success' => false,
                    'message' => 'Invalid webhook structure',
                    'status_code' => 400,
                ];
            }

            $event = $webhookData['event'];
            $data = $webhookData['data'];

            // Apply rate limiting per workspace
            if (!$this->checkRateLimit($this->workspaceId, $event)) {
                return (object) [
                    'success' => false,
                    'message' => 'Rate limit exceeded',
                    'status_code' => 429,
                ];
            }

            // Log webhook receipt
            $this->logger->info('Webhook received', [
                'workspace_id' => $this->workspaceId,
                'event' => $event,
                'account_id' => $data['account_id'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'message_id' => $data['message_id'] ?? null,
            ]);

            // Process event based on type
            $result = $this->routeWebhookEvent($event, $data);

            // Send acknowledgment to Node.js service
            $this->sendAcknowledgment($event, $result);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Webhook processing failed', [
                'workspace_id' => $this->workspaceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'webhook_data' => $webhookData,
            ]);

            return (object) [
                'success' => false,
                'message' => 'Webhook processing failed: ' . $e->getMessage(),
                'status_code' => 500,
            ];
        }
    }

    /**
     * Route webhook event to appropriate handler
     *
     * @param string $event
     * @param array $data
     * @return object
     */
    protected function routeWebhookEvent($event, $data)
    {
        switch ($event) {
            case 'qr.code':
                return $this->handleQRCodeGenerated($data);

            case 'session.connected':
                return $this->handleSessionConnected($data);

            case 'session.disconnected':
                return $this->handleSessionDisconnected($data);

            case 'message.received':
                return $this->handleMessageReceived($data);

            case 'message.sent':
                return $this->handleMessageSent($data);

            case 'message.delivered':
                return $this->handleMessageDelivered($data);

            case 'message.read':
                return $this->handleMessageRead($data);

            case 'message.failed':
                return $this->handleMessageFailed($data);

            case 'contact.updated':
                return $this->handleContactUpdated($data);

            case 'chats.synced':
                return $this->handleChatsSynced($data);

            case 'contacts.synced':
                return $this->handleContactsSynced($data);

            case 'session.error':
                return $this->handleSessionError($data);

            case 'rate.limit.warning':
                return $this->handleRateLimitWarning($data);

            case 'campaign.completed':
                return $this->handleCampaignCompleted($data);

            default:
                $this->logger->warning('Unknown webhook event', [
                    'workspace_id' => $this->workspaceId,
                    'event' => $event,
                ]);

                return (object) [
                    'success' => true,
                    'message' => "Unknown event: {$event}",
                    'event_processed' => false,
                ];
        }
    }

    /**
     * Handle QR code generation event
     *
     * @param array $data
     * @return object
     */
    protected function handleQRCodeGenerated($data)
    {
        try {
            $account = WhatsAppAccount::where('session_id', $data['session_id'])
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if (!$account) {
                return (object) [
                    'success' => false,
                    'message' => 'Account not found for QR code',
                ];
            }

            $account->update([
                'qr_code' => $data['qr_code'],
                'status' => 'qr_scanning',
                'last_activity_at' => now(),
            ]);

            return (object) [
                'success' => true,
                'message' => 'QR code updated successfully',
                'data' => [
                    'account_id' => $account->id,
                    'qr_code_expires_at' => $data['expires_at'] ?? null,
                ],
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to handle QR code: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle session connection event
     *
     * @param array $data
     * @return object
     */
    protected function handleSessionConnected($data)
    {
        try {
            $account = WhatsAppAccount::where('session_id', $data['session_id'])
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if (!$account) {
                return (object) [
                    'success' => false,
                    'message' => 'Account not found for session connection',
                ];
            }

            $account->update([
                'status' => 'connected',
                'qr_code' => null,
                'connected_at' => now(),
                'last_connected_at' => now(),
                'last_activity_at' => now(),
                'phone_number' => $data['phone_number'] ?? $account->phone_number,
            ]);

            $this->logger->info('Session connected', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $account->id,
                'phone_number' => $account->phone_number,
            ]);

            return (object) [
                'success' => true,
                'message' => 'Session connected successfully',
                'data' => [
                    'account_id' => $account->id,
                    'phone_number' => $account->phone_number,
                ],
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to handle session connection: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle session disconnection event
     *
     * @param array $data
     * @return object
     */
    protected function handleSessionDisconnected($data)
    {
        try {
            $account = WhatsAppAccount::where('session_id', $data['session_id'])
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if (!$account) {
                return (object) [
                    'success' => false,
                    'message' => 'Account not found for session disconnection',
                ];
            }

            $account->update([
                'status' => 'disconnected',
                'disconnected_at' => now(),
                'last_activity_at' => now(),
            ]);

            $this->logger->info('Session disconnected', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $account->id,
                'reason' => $data['reason'] ?? 'manual',
            ]);

            return (object) [
                'success' => true,
                'message' => 'Session disconnected successfully',
                'data' => [
                    'account_id' => $account->id,
                    'reason' => $data['reason'] ?? 'manual',
                ],
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to handle session disconnection: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle incoming message event
     *
     * @param array $data
     * @return object
     */
    protected function handleMessageReceived($data)
    {
        try {
            // Find or create contact
            $contact = Contact::where('phone', $this->normalizePhoneNumber($data['from']))
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if (!$contact) {
                $contact = Contact::create([
                    'uuid' => Str::uuid(),
                    'workspace_id' => $this->workspaceId,
                    'phone' => $this->normalizePhoneNumber($data['from']),
                    'first_name' => $data['contact_name'] ?? $this->extractNameFromPhone($data['from']),
                    'last_name' => '',
                    'full_name' => $data['contact_name'] ?? $this->extractNameFromPhone($data['from']),
                    'is_active' => true,
                    'metadata' => [
                        'source' => 'whatsapp_inbound',
                        'created_from_message' => true,
                    ],
                ]);
            }

            // Find WhatsApp account
            $account = WhatsAppAccount::where('session_id', $data['session_id'])
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if (!$account) {
                return (object) [
                    'success' => false,
                    'message' => 'WhatsApp account not found',
                ];
            }

            // Create chat record
            $chat = Chat::create([
                'uuid' => $data['message_id'] ?? Str::uuid(),
                'workspace_id' => $this->workspaceId,
                'contact_id' => $contact->id,
                'whatsapp_account_id' => $account->id,
                'type' => 'inbound',
                'chat_type' => $data['message_type'] ?? 'text',
                'message_status' => 'received',
                'provider_type' => $data['provider_type'] ?? 'webjs',
                'last_message' => $this->formatMessageContent($data),
                'sent_at' => isset($data['timestamp']) ? \Carbon\Carbon::parse($data['timestamp']) : now(),
                'created_at' => now(),
                'updated_at' => now(),
                'metadata' => [
                    'from_nodejs' => true,
                    'message_data' => $data,
                ],
            ]);

            // Update contact activity
            $contact->update([
                'last_message_at' => $chat->created_at,
                'last_activity' => $chat->created_at,
            ]);

            $this->logger->info('Message received and stored', [
                'workspace_id' => $this->workspaceId,
                'chat_id' => $chat->id,
                'contact_id' => $contact->id,
                'message_type' => $chat->chat_type,
            ]);

            return (object) [
                'success' => true,
                'message' => 'Message received successfully',
                'data' => [
                    'chat_id' => $chat->id,
                    'contact_id' => $contact->id,
                    'message_type' => $chat->chat_type,
                ],
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to handle received message: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle message sent confirmation
     *
     * @param array $data
     * @return object
     */
    protected function handleMessageSent($data)
    {
        try {
            $chat = Chat::where('uuid', $data['message_id'] ?? null)
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if ($chat) {
                $chat->update([
                    'message_status' => 'sent',
                    'sent_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return (object) [
                'success' => true,
                'message' => 'Message sent confirmation processed',
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to handle message sent: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle message delivery confirmation
     *
     * @param array $data
     * @return object
     */
    protected function handleMessageDelivered($data)
    {
        try {
            $chat = Chat::where('uuid', $data['message_id'] ?? null)
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if ($chat) {
                $chat->update([
                    'message_status' => 'delivered',
                    'updated_at' => now(),
                ]);
            }

            return (object) [
                'success' => true,
                'message' => 'Message delivery confirmation processed',
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to handle message delivery: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle message read confirmation
     *
     * @param array $data
     * @return object
     */
    protected function handleMessageRead($data)
    {
        try {
            $chat = Chat::where('uuid', $data['message_id'] ?? null)
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if ($chat) {
                $chat->update([
                    'message_status' => 'read',
                    'is_read' => true,
                    'updated_at' => now(),
                ]);
            }

            return (object) [
                'success' => true,
                'message' => 'Message read confirmation processed',
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to handle message read: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle message failed event
     *
     * @param array $data
     * @return object
     */
    protected function handleMessageFailed($data)
    {
        try {
            $chat = Chat::where('uuid', $data['message_id'] ?? null)
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if ($chat) {
                $chat->update([
                    'message_status' => 'failed',
                    'metadata' => array_merge($chat->metadata ?? [], [
                        'error_message' => $data['error_message'] ?? 'Unknown error',
                        'error_code' => $data['error_code'] ?? null,
                        'failed_at' => now()->toISOString(),
                    ]),
                    'updated_at' => now(),
                ]);
            }

            return (object) [
                'success' => true,
                'message' => 'Message failure processed',
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to handle message failure: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle contact updated event
     *
     * @param array $data
     * @return object
     */
    protected function handleContactUpdated($data)
    {
        try {
            $contact = Contact::where('phone', $this->normalizePhoneNumber($data['phone'] ?? ''))
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if ($contact) {
                $contact->update([
                    'first_name' => $data['name'] ?? $contact->first_name,
                    'last_name' => $data['last_name'] ?? $contact->last_name,
                    'full_name' => trim(($data['name'] ?? $contact->first_name) . ' ' . ($data['last_name'] ?? $contact->last_name)),
                    'profile_picture' => $data['profile_picture_url'] ?? $contact->profile_picture,
                    'updated_at' => now(),
                ]);
            }

            return (object) [
                'success' => true,
                'message' => 'Contact update processed',
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to handle contact update: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle chats synced event
     *
     * @param array $data
     * @return object
     */
    protected function handleChatsSynced($data)
    {
        try {
            // Delegate to SyncService
            $syncService = new SyncService($this->workspaceId);
            return $syncService->processWebhookData('chats.sync', $data);

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to handle chats sync: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle contacts synced event
     *
     * @param array $data
     * @return object
     */
    protected function handleContactsSynced($data)
    {
        try {
            // Delegate to SyncService
            $syncService = new SyncService($this->workspaceId);
            return $syncService->processWebhookData('contacts.sync', $data);

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to handle contacts sync: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle session error event
     *
     * @param array $data
     * @return object
     */
    protected function handleSessionError($data)
    {
        try {
            $account = WhatsAppAccount::where('session_id', $data['session_id'] ?? null)
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if ($account) {
                $account->update([
                    'status' => 'error',
                    'last_activity_at' => now(),
                    'metadata' => array_merge($account->metadata ?? [], [
                        'last_error' => $data['error'] ?? 'Unknown error',
                        'error_code' => $data['error_code'] ?? null,
                        'error_at' => now()->toISOString(),
                    ]),
                ]);
            }

            $this->logger->error('Session error reported from Node.js', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $data['session_id'] ?? null,
                'error' => $data['error'] ?? 'Unknown error',
            ]);

            return (object) [
                'success' => true,
                'message' => 'Session error logged',
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to handle session error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle rate limit warning event
     *
     * @param array $data
     * @return object
     */
    protected function handleRateLimitWarning($data)
    {
        try {
            $account = WhatsAppAccount::where('session_id', $data['session_id'] ?? null)
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if ($account) {
                $account->update([
                    'metadata' => array_merge($account->metadata ?? [], [
                        'rate_limit_warning' => $data['current_usage'] ?? 0,
                        'rate_limit_reset' => $data['reset_time'] ?? null,
                    ]),
                ]);
            }

            $this->logger->warning('Rate limit warning from Node.js', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $data['session_id'] ?? null,
                'current_usage' => $data['current_usage'] ?? 0,
                'limit' => $data['limit'] ?? 'unknown',
            ]);

            return (object) [
                'success' => true,
                'message' => 'Rate limit warning processed',
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to handle rate limit warning: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle campaign completed event
     *
     * @param array $data
     * @return object
     */
    protected function handleCampaignCompleted($data)
    {
        try {
            $campaignLog = CampaignLog::where('uuid', $data['campaign_id'] ?? null)
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if ($campaignLog) {
                $campaignLog->update([
                    'status' => 'completed',
                    'sent_count' => $data['sent_count'] ?? 0,
                    'delivered_count' => $data['delivered_count'] ?? 0,
                    'failed_count' => $data['failed_count'] ?? 0,
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return (object) [
                'success' => true,
                'message' => 'Campaign completion processed',
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to handle campaign completion: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate webhook signature
     *
     * @param array $data
     * @param string $signature
     * @return bool
     */
    protected function validateWebhookSignature($data, $signature)
    {
        if (!$this->webhookSecret) {
            // Skip validation if no secret configured
            return true;
        }

        if (!$signature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', json_encode($data), $this->webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Check rate limiting for webhooks
     *
     * @param int $workspaceId
     * @param string $event
     * @return bool
     */
    protected function checkRateLimit($workspaceId, $event)
    {
        try {
            $key = "{$this->queuePrefix}:rate_limit:{$workspaceId}:{$event}";
            $limit = config('whatsapp.webhook_rate_limit', 1000); // per minute
            $window = 60; // seconds

            $current = Redis::incr($key);

            if ($current === 1) {
                Redis::expire($key, $window);
            }

            return $current <= $limit;

        } catch (\Exception $e) {
            $this->logger->warning('Rate limiting check failed', [
                'workspace_id' => $workspaceId,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            // Allow request if rate limiting fails
            return true;
        }
    }

    /**
     * Send acknowledgment to Node.js service
     *
     * @param string $event
     * @param object $result
     * @return void
     */
    protected function sendAcknowledgment($event, $result)
    {
        try {
            // Only send acknowledgments for critical events
            $criticalEvents = [
                'message.received',
                'message.sent',
                'message.failed',
                'session.connected',
                'session.disconnected',
            ];

            if (!in_array($event, $criticalEvents)) {
                return;
            }

            Http::timeout(5)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-API-Key' => config('whatsapp.node_api_key'),
                ])
                ->post("{$this->nodeServiceUrl}/api/webhooks/ack", [
                    'event' => $event,
                    'workspace_id' => $this->workspaceId,
                    'success' => $result->success,
                    'timestamp' => now()->toISOString(),
                ]);

        } catch (\Exception $e) {
            $this->logger->warning('Failed to send webhook acknowledgment', [
                'workspace_id' => $this->workspaceId,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Normalize phone number
     *
     * @param string $phone
     * @return string
     */
    protected function normalizePhoneNumber($phone)
    {
        if (!$phone) {
            return $phone;
        }

        // Remove all non-digit characters except +
        $normalized = preg_replace('/[^\d+]/', '', $phone);

        // Ensure proper international format
        if (strlen($normalized) >= 10 && !str_starts_with($normalized, '+')) {
            $normalized = '+' . $normalized;
        }

        return $normalized;
    }

    /**
     * Extract name from phone number
     *
     * @param string $phone
     * @return string
     */
    protected function extractNameFromPhone($phone)
    {
        $digitsOnly = preg_replace('/\D/', '', $phone);
        if (strlen($digitsOnly) >= 4) {
            return 'User ' . substr($digitsOnly, -4);
        }

        return 'Unknown';
    }

    /**
     * Format message content for storage
     *
     * @param array $data
     * @return string
     */
    protected function formatMessageContent($data)
    {
        switch ($data['message_type'] ?? 'text') {
            case 'text':
                return $data['message']['body'] ?? '';
            case 'image':
                return '📷 ' . ($data['message']['caption'] ?? 'Image');
            case 'video':
                return '🎥 ' . ($data['message']['caption'] ?? 'Video');
            case 'audio':
                return '🎵 Audio message';
            case 'document':
                return '📄 ' . ($data['message']['filename'] ?? 'Document');
            case 'location':
                return '📍 ' . ($data['message']['name'] ?? 'Location');
            case 'contact':
                return '👤 ' . ($data['message']['display_name'] ?? 'Contact');
            default:
                return $data['message']['body'] ?? 'Message';
        }
    }

    /**
     * Get webhook processing statistics
     *
     * @return array
     */
    public function getStatistics()
    {
        try {
            $keys = Redis::keys("{$this->queuePrefix}:*");
            $stats = [
                'total_queues' => 0,
                'rate_limited_events' => 0,
                'queue_depth' => 0,
            ];

            foreach ($keys as $key) {
                if (str_contains($key, 'rate_limit')) {
                    $stats['rate_limited_events']++;
                } else {
                    $stats['queue_depth'] += Redis::llen($key);
                    $stats['total_queues']++;
                }
            }

            return $stats;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get webhook statistics', [
                'workspace_id' => $this->workspaceId,
                'error' => $e->getMessage(),
            ]);

            return [
                'total_queues' => 0,
                'rate_limited_events' => 0,
                'queue_depth' => 0,
            ];
        }
    }

    /**
     * Clear webhook rate limits
     *
     * @param int $workspaceId
     * @return object
     */
    public function clearRateLimits($workspaceId = null)
    {
        try {
            $pattern = $workspaceId
                ? "{$this->queuePrefix}:rate_limit:{$workspaceId}:*"
                : "{$this->queuePrefix}:rate_limit:*";

            $keys = Redis::keys($pattern);
            $deleted = 0;

            foreach ($keys as $key) {
                Redis::del($key);
                $deleted++;
            }

            return (object) [
                'success' => true,
                'message' => "Cleared {$deleted} rate limit entries",
                'data' => ['deleted_count' => $deleted],
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to clear rate limits: ' . $e->getMessage(),
            ];
        }
    }
}