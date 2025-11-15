<?php

namespace App\Services\WhatsApp;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WhatsApp Message Service
 *
 * Handles message operations including sending, receiving, and storing messages
 * Integrates with Node.js service via WhatsAppServiceClient
 *
 * TASK-SERVICE-3: Extract message operations from controllers
 */
class MessageService
{
    protected $workspaceId;
    protected $whatsappClient;
    protected $logger;

    public function __construct($workspaceId)
    {
        $this->workspaceId = $workspaceId;
        $this->whatsappClient = new WhatsAppServiceClient();
        $this->logger = Log::channel('whatsapp');
    }

    /**
     * Send message to contact
     *
     * @param string $contactUuid
     * @param string $message
     * @param string $type
     * @param array $options
     * @return object
     */
    public function sendMessage($contactUuid, $message, $type = 'text', $options = [])
    {
        try {
            DB::beginTransaction();

            // Find contact in workspace
            $contact = Contact::where('uuid', $contactUuid)
                ->where('workspace_id', $this->workspaceId)
                ->firstOrFail();

            // Get primary WhatsApp account for workspace
            $whatsappAccount = $this->getPrimaryAccount();
            if (!$whatsappAccount) {
                throw new \Exception('No active WhatsApp account found for this workspace');
            }

            // Send via Node.js service
            $result = $this->whatsappClient->sendMessage(
                $this->workspaceId,
                $whatsappAccount->uuid,
                $contactUuid,
                $message,
                $type,
                $options
            );

            if ($result['success']) {
                // Save to database
                $chat = $this->saveChatMessage($contact, $message, $type, $result, $options);

                // Update contact activity
                $this->updateContactActivity($contact, $chat);

                DB::commit();

                $this->logger->info('WhatsApp message sent successfully', [
                    'workspace_id' => $this->workspaceId,
                    'contact_uuid' => $contactUuid,
                    'chat_id' => $chat->id,
                    'message_type' => $type,
                    'whatsapp_account_id' => $whatsappAccount->id,
                ]);

                return (object) [
                    'success' => true,
                    'data' => $chat,
                    'message' => 'Message sent successfully',
                    'nodejs_result' => $result,
                ];
            }

            DB::rollBack();

            return (object) [
                'success' => false,
                'message' => 'Failed to send message: ' . ($result['error'] ?? 'Unknown error'),
                'nodejs_result' => $result,
            ];

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            return (object) [
                'success' => false,
                'message' => 'Contact not found',
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            $this->logger->error('Failed to send WhatsApp message', [
                'workspace_id' => $this->workspaceId,
                'contact_uuid' => $contactUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to send message: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send bulk messages to multiple contacts
     *
     * @param array $contactUuids
     * @param string $message
     * @param string $type
     * @param array $options
     * @return object
     */
    public function sendBulkMessages($contactUuids, $message, $type = 'text', $options = [])
    {
        try {
            $results = [];
            $successCount = 0;
            $failureCount = 0;

            foreach ($contactUuids as $contactUuid) {
                $result = $this->sendMessage($contactUuid, $message, $type, $options);

                $results[] = [
                    'contact_uuid' => $contactUuid,
                    'success' => $result->success,
                    'message' => $result->message,
                    'chat_id' => $result->success ? $result->data->id : null,
                ];

                if ($result->success) {
                    $successCount++;
                } else {
                    $failureCount++;
                }

                // Add small delay to prevent rate limiting
                usleep(100000); // 0.1 second
            }

            return (object) [
                'success' => $failureCount === 0,
                'data' => $results,
                'summary' => [
                    'total' => count($contactUuids),
                    'success' => $successCount,
                    'failed' => $failureCount,
                    'success_rate' => count($contactUuids) > 0 ? round(($successCount / count($contactUuids)) * 100, 2) : 0,
                ],
                'message' => "Bulk messaging completed. Success: {$successCount}, Failed: {$failureCount}",
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to send bulk WhatsApp messages', [
                'workspace_id' => $this->workspaceId,
                'contact_count' => count($contactUuids),
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to send bulk messages: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Save chat message to database
     *
     * @param Contact $contact
     * @param string $message
     * @param string $type
     * @param array $nodejsResult
     * @param array $options
     * @return Chat
     */
    protected function saveChatMessage($contact, $message, $type, $nodejsResult, $options = [])
    {
        // Prepare metadata based on message type
        $metadata = $this->prepareMessageMetadata($message, $type, $options);

        // Add Node.js service response to metadata
        $metadata['nodejs_response'] = $nodejsResult;

        // Create chat record
        $chat = Chat::create([
            'uuid' => Str::uuid(),
            'workspace_id' => $this->workspaceId,
            'contact_id' => $contact->id,
            'whatsapp_account_id' => $this->getPrimaryAccount()->id,
            'type' => 'outbound',
            'chat_type' => $type,
            'message_status' => $nodejsResult['success'] ? 'sent' : 'failed',
            'provider_type' => 'webjs',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'metadata' => $metadata,
            'user_id' => Auth::id(),
        ]);

        return $chat;
    }

    /**
     * Update contact activity after sending message
     *
     * @param Contact $contact
     * @param Chat $chat
     * @return void
     */
    protected function updateContactActivity($contact, $chat)
    {
        $contact->update([
            'last_message_at' => $chat->created_at,
            'last_activity' => $chat->created_at,
        ]);

        // Update WhatsApp account statistics
        $whatsappAccount = $this->getPrimaryAccount();
        if ($whatsappAccount) {
            $whatsappAccount->updateStatistics();
        }
    }

    /**
     * Get primary WhatsApp account for workspace
     *
     * @return WhatsAppAccount|null
     */
    protected function getPrimaryAccount()
    {
        return WhatsAppAccount::where('workspace_id', $this->workspaceId)
            ->where('status', 'connected')
            ->where('is_primary', true)
            ->first();
    }

    /**
     * Prepare message metadata based on type
     *
     * @param string $message
     * @param string $type
     * @param array $options
     * @return array
     */
    protected function prepareMessageMetadata($message, $type, $options = [])
    {
        $metadata = [];

        switch ($type) {
            case 'text':
                $metadata['text'] = [
                    'body' => $message,
                    'preview_url' => $options['preview_url'] ?? false,
                ];
                break;

            case 'image':
                $metadata['image'] = [
                    'caption' => $message,
                    'url' => $options['image_url'] ?? null,
                    'media_id' => $options['media_id'] ?? null,
                ];
                break;

            case 'video':
                $metadata['video'] = [
                    'caption' => $message,
                    'url' => $options['video_url'] ?? null,
                    'media_id' => $options['media_id'] ?? null,
                ];
                break;

            case 'document':
                $metadata['document'] = [
                    'caption' => $message,
                    'url' => $options['document_url'] ?? null,
                    'media_id' => $options['media_id'] ?? null,
                    'filename' => $options['filename'] ?? null,
                ];
                break;

            case 'audio':
                $metadata['audio'] = [
                    'url' => $options['audio_url'] ?? null,
                    'media_id' => $options['media_id'] ?? null,
                ];
                break;

            case 'interactive':
                $metadata['interactive'] = $options['interactive'] ?? [];
                $metadata['text'] = [
                    'body' => $message,
                ];
                break;

            case 'location':
                $metadata['location'] = $options['location'] ?? [];
                break;

            default:
                $metadata['text'] = [
                    'body' => $message,
                ];
                break;
        }

        return $metadata;
    }

    /**
     * Get message history for contact
     *
     * @param string $contactUuid
     * @param int $limit
     * @param int $offset
     * @return object
     */
    public function getMessageHistory($contactUuid, $limit = 50, $offset = 0)
    {
        try {
            $contact = Contact::where('uuid', $contactUuid)
                ->where('workspace_id', $this->workspaceId)
                ->firstOrFail();

            $messages = Chat::where('workspace_id', $this->workspaceId)
                ->where('contact_id', $contact->id)
                ->with(['whatsappAccount:id,phone_number,provider_type', 'media'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            return (object) [
                'success' => true,
                'data' => $messages,
                'contact' => $contact,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => Chat::where('workspace_id', $this->workspaceId)
                        ->where('contact_id', $contact->id)
                        ->count(),
                ],
            ];

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return (object) [
                'success' => false,
                'message' => 'Contact not found',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get message history', [
                'workspace_id' => $this->workspaceId,
                'contact_uuid' => $contactUuid,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to get message history: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Mark message as read
     *
     * @param string $chatUuid
     * @return object
     */
    public function markAsRead($chatUuid)
    {
        try {
            $chat = Chat::where('uuid', $chatUuid)
                ->where('workspace_id', $this->workspaceId)
                ->firstOrFail();

            if ($chat->type === 'inbound' && !$chat->is_read) {
                $chat->updateStatus('read');
            }

            return (object) [
                'success' => true,
                'data' => $chat,
                'message' => 'Message marked as read',
            ];

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return (object) [
                'success' => false,
                'message' => 'Message not found',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to mark message as read', [
                'workspace_id' => $this->workspaceId,
                'chat_uuid' => $chatUuid,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to mark message as read: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get message statistics for workspace
     *
     * @param array $filters
     * @return array
     */
    public function getStatistics($filters = [])
    {
        $query = Chat::where('workspace_id', $this->workspaceId);

        // Apply date filters
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Apply message type filter
        if (!empty($filters['chat_type'])) {
            $query->where('chat_type', $filters['chat_type']);
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_messages,
            SUM(CASE WHEN type = "outbound" THEN 1 ELSE 0 END) as sent_messages,
            SUM(CASE WHEN type = "inbound" THEN 1 ELSE 0 END) as received_messages,
            SUM(CASE WHEN message_status = "sent" THEN 1 ELSE 0 END) as sent_count,
            SUM(CASE WHEN message_status = "delivered" THEN 1 ELSE 0 END) as delivered_count,
            SUM(CASE WHEN message_status = "read" THEN 1 ELSE 0 END) as read_count,
            SUM(CASE WHEN message_status = "failed" THEN 1 ELSE 0 END) as failed_count,
            SUM(CASE WHEN chat_type = "text" THEN 1 ELSE 0 END) as text_messages,
            SUM(CASE WHEN chat_type = "image" THEN 1 ELSE 0 END) as image_messages,
            SUM(CASE WHEN chat_type = "video" THEN 1 ELSE 0 END) as video_messages,
            SUM(CASE WHEN chat_type = "document" THEN 1 ELSE 0 END) as document_messages,
            SUM(CASE WHEN chat_type = "audio" THEN 1 ELSE 0 END) as audio_messages
        ')->first();

        return [
            'total_messages' => (int) $stats->total_messages,
            'sent_messages' => (int) $stats->sent_messages,
            'received_messages' => (int) $stats->received_messages,
            'delivery_rates' => [
                'sent' => (int) $stats->sent_count,
                'delivered' => (int) $stats->delivered_count,
                'read' => (int) $stats->read_count,
                'failed' => (int) $stats->failed_count,
                'delivery_rate' => $stats->sent_count > 0 ? round(($stats->delivered_count / $stats->sent_count) * 100, 2) : 0,
                'read_rate' => $stats->delivered_count > 0 ? round(($stats->read_count / $stats->delivered_count) * 100, 2) : 0,
            ],
            'by_type' => [
                'text' => (int) $stats->text_messages,
                'image' => (int) $stats->image_messages,
                'video' => (int) $stats->video_messages,
                'document' => (int) $stats->document_messages,
                'audio' => (int) $stats->audio_messages,
            ],
        ];
    }
}