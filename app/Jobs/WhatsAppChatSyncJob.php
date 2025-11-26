<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\WhatsAppGroup;
use App\Models\WhatsAppAccount;
use App\Events\NewChatEvent;
use App\Services\ContactProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * WhatsApp Chat Sync Job
 *
 * Queue-based processing for batch chat sync from Node.js service.
 *
 * Strategy:
 * - Process in small transaction chunks (10 chats per transaction)
 * - Avoid long table locks
 * - Retry failed batches up to 3 times with exponential backoff
 * - Handle both private and group chats
 * - Broadcast events for real-time UI updates
 *
 * Performance targets:
 * - Process 50 chats in < 10 seconds
 * - Support concurrent job processing
 *
 * @package App\Jobs
 */
class WhatsAppChatSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Job timeout (5 minutes)
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Number of retry attempts
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Progressive backoff intervals
     *
     * @var array
     */
    public $backoff = [20, 60, 180]; // Progressive backoff: 20s, 1m, 3m

    /**
     * Rate limiting
     *
     * @var int
     */
    public $retryAfter = 30;

    /**
     * Calculate retry backoff in seconds (exponential)
     *
     * @return array
     */
    public function backoff()
    {
        return [10, 30, 60]; // 10s, 30s, 60s between retries
    }

    /**
     * @var int WhatsApp account ID
     */
    protected $accountId;

    /**
     * @var int Workspace ID
     */
    protected $workspaceId;

    /**
     * @var array Batch of chats to sync
     */
    protected $chats;

    /**
     * Create a new job instance.
     *
     * @param int $accountId WhatsApp account ID
     * @param int $workspaceId Workspace ID
     * @param array $chats Array of chat data from Node.js
     * @return void
     */
    public function __construct(int $accountId, int $workspaceId, array $chats)
    {
        $this->accountId = $accountId;
        $this->workspaceId = $workspaceId;
        $this->chats = $chats;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $startTime = microtime(true);

        Log::channel('whatsapp')->info('Chat sync job started', [
            'account_id' => $this->accountId,
            'workspace_id' => $this->workspaceId,
            'batch_size' => count($this->chats),
            'attempt' => $this->attempts(),
        ]);

        $contactService = new ContactProvisioningService();
        $processedCount = 0;
        $errorCount = 0;

        // Process in smaller transaction chunks (10 chats per transaction)
        // This prevents long table locks and allows partial rollback
        $chunks = array_chunk($this->chats, 10);

        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                DB::transaction(function () use ($chunk, $contactService, &$processedCount) {
                    foreach ($chunk as $chatData) {
                        if ($chatData['chat_type'] === 'private') {
                            $this->syncPrivateChat($chatData, $contactService);
                        } else {
                            $this->syncGroupChat($chatData, $contactService);
                        }

                        $processedCount++;
                    }
                });

                // Log chunk success
                Log::channel('whatsapp')->debug('Sync chunk completed', [
                    'chunk_index' => $chunkIndex,
                    'chunk_size' => count($chunk),
                    'total_processed' => $processedCount,
                ]);

            } catch (\Exception $e) {
                $errorCount++;

                Log::channel('whatsapp')->error('Sync chunk failed', [
                    'chunk_index' => $chunkIndex,
                    'chunk_size' => count($chunk),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Continue processing other chunks (isolated rollback)
                continue;
            }
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2); // milliseconds

        Log::channel('whatsapp')->info('Chat sync job completed', [
            'account_id' => $this->accountId,
            'total_chats' => count($this->chats),
            'processed' => $processedCount,
            'errors' => $errorCount,
            'duration_ms' => $duration,
            'attempt' => $this->attempts(),
        ]);

        // Update account metadata with sync status
        $this->updateAccountMetadata($processedCount, $errorCount);
    }

    /**
     * Sync private chat
     *
     * @param array $chatData Chat data from Node.js
     * @param ContactProvisioningService $contactService
     * @return void
     */
    protected function syncPrivateChat(array $chatData, ContactProvisioningService $contactService)
    {
        // Get or create contact
        $contact = $contactService->getOrCreateContact(
            $chatData['contact_phone'],
            $chatData['contact_name'] ?? null,
            $this->workspaceId,
            'webjs',
            $this->accountId
        );

        // Create chat record
        $chat = Chat::updateOrCreate(
            [
                'workspace_id' => $this->workspaceId,
                'wam_id' => $chatData['chat_id'] ?? null,
            ],
            [
                'whatsapp_account_id' => $this->accountId,
                'contact_id' => $contact->id,
                'group_id' => null,
                'provider_type' => 'webjs',
                'chat_type' => 'private',
                'type' => $chatData['from_me'] ?? false ? 'outbound' : 'inbound',
                'metadata' => [
                    'body' => $chatData['message_body'] ?? '',
                    'timestamp' => $chatData['timestamp'] ?? null,
                    'has_media' => $chatData['has_media'] ?? false,
                ],
                'status' => 'delivered',
                'is_read' => $chatData['is_read'] ?? false,
                'created_at' => isset($chatData['timestamp'])
                    ? Carbon::createFromTimestamp($chatData['timestamp'])
                    : now(),
            ]
        );

        // Update contact latest activity
        $contactService->updateLatestActivity($contact, $chat->created_at);

        // Broadcast event for real-time UI update
        broadcast(new NewChatEvent($chat, $this->workspaceId));
    }

    /**
     * Sync group chat
     *
     * @param array $chatData Chat data from Node.js
     * @param ContactProvisioningService $contactService
     * @return void
     */
    protected function syncGroupChat(array $chatData, ContactProvisioningService $contactService)
    {
        // Get or create group
        $group = WhatsAppGroup::updateOrCreate(
            [
                'group_jid' => $chatData['group_jid'],
            ],
            [
                'workspace_id' => $this->workspaceId,
                'whatsapp_account_id' => $this->accountId,
                'name' => $chatData['group_name'] ?? 'Unknown Group',
                'description' => $chatData['group_description'] ?? null,
                'owner_phone' => $chatData['owner_phone'] ?? null,
                'participants' => $chatData['participants'] ?? [],
                'group_created_at' => isset($chatData['group_created_at'])
                    ? Carbon::parse($chatData['group_created_at'])
                    : null,
            ]
        );

        // Create chat record
        $chat = Chat::updateOrCreate(
            [
                'workspace_id' => $this->workspaceId,
                'wam_id' => $chatData['chat_id'] ?? null,
            ],
            [
                'whatsapp_account_id' => $this->accountId,
                'contact_id' => null, // Group chats don't have contact_id
                'group_id' => $group->id,
                'provider_type' => 'webjs',
                'chat_type' => 'group',
                'type' => $chatData['from_me'] ?? false ? 'outbound' : 'inbound',
                'metadata' => [
                    'body' => $chatData['message_body'] ?? '',
                    'timestamp' => $chatData['timestamp'] ?? null,
                    'has_media' => $chatData['has_media'] ?? false,
                    'sender_phone' => $chatData['sender_phone'] ?? null,
                    'sender_name' => $chatData['sender_name'] ?? null,
                ],
                'status' => 'delivered',
                'is_read' => $chatData['is_read'] ?? false,
                'created_at' => isset($chatData['timestamp'])
                    ? Carbon::createFromTimestamp($chatData['timestamp'])
                    : now(),
            ]
        );

        // Broadcast event for real-time UI update
        broadcast(new NewChatEvent($chat, $this->workspaceId));
    }

    /**
     * Update account metadata with sync status
     *
     * @param int $processedCount
     * @param int $errorCount
     * @return void
     */
    protected function updateAccountMetadata(int $processedCount, int $errorCount)
    {
        try {
            $session = WhatsAppAccount::find($this->accountId);

            if ($session) {
                $metadata = $session->metadata ?? [];

                $metadata['sync_status'] = $errorCount > 0 ? 'partial' : 'completed';
                $metadata['last_sync_at'] = now()->toISOString();
                $metadata['total_synced'] = ($metadata['total_synced'] ?? 0) + $processedCount;
                $metadata['sync_errors'] = ($metadata['sync_errors'] ?? 0) + $errorCount;

                $session->update(['metadata' => $metadata]);
            }
        } catch (\Exception $e) {
            Log::channel('whatsapp')->warning('Failed to update account metadata', [
                'account_id' => $this->accountId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle job failure
     *
     * Called when all retry attempts are exhausted.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::channel('whatsapp')->error('Chat sync job failed permanently', [
            'account_id' => $this->accountId,
            'workspace_id' => $this->workspaceId,
            'total_chats' => count($this->chats),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'attempts' => $this->attempts(),
        ]);

        // Update account metadata with failure status
        try {
            $session = WhatsAppAccount::find($this->accountId);

            if ($session) {
                $metadata = $session->metadata ?? [];
                $metadata['sync_status'] = 'failed';
                $metadata['last_error'] = $exception->getMessage();
                $metadata['last_failed_at'] = now()->toISOString();

                $session->update(['metadata' => $metadata]);
            }
        } catch (\Exception $e) {
            // Silent fail - already in error handler
        }
    }
}
