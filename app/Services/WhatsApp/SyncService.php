<?php

namespace App\Services\WhatsApp;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WhatsApp Sync Service
 *
 * Handles chat/contact synchronization from Node.js service
 * Processes data synchronization between Node.js and Laravel
 *
 * TASK-SERVICE-5: Chat/contact sync operations
 */
class SyncService
{
    protected $workspaceId;
    protected $logger;

    public function __construct($workspaceId)
    {
        $this->workspaceId = $workspaceId;
        $this->logger = Log::channel('whatsapp');
    }

    /**
     * Sync chats from Node.js service
     *
     * @param int $accountId
     * @param array $chatsData
     * @return object
     */
    public function syncChats($accountId, $chatsData)
    {
        try {
            DB::beginTransaction();

            $syncedCount = 0;
            $updatedCount = 0;
            $errors = [];

            foreach ($chatsData as $chatData) {
                // Validate required fields
                if (!isset($chatData['contact_phone']) || !isset($chatData['last_message'])) {
                    $errors[] = "Missing required fields for chat sync";
                    continue;
                }

                // Find or create contact
                $contact = $this->findOrCreateContact($chatData['contact_phone'], $chatData);
                if (!$contact) {
                    $errors[] = "Failed to create/contact for phone: {$chatData['contact_phone']}";
                    continue;
                }

                // Create or update chat record
                $chat = Chat::updateOrCreate(
                    [
                        'workspace_id' => $this->workspaceId,
                        'whatsapp_account_id' => $accountId,
                        'contact_id' => $contact->id,
                    ],
                    [
                        'uuid' => $chatData['uuid'] ?? Str::uuid(),
                        'chat_type' => $chatData['chat_type'] ?? 'private',
                        'provider_type' => $chatData['provider_type'] ?? 'webjs',
                        'type' => $chatData['type'] ?? 'inbound',
                        'message_status' => $chatData['message_status'] ?? 'delivered',
                        'last_message' => $chatData['last_message'],
                        'metadata' => array_merge($chatData['metadata'] ?? [], [
                            'sync_timestamp' => now()->timestamp,
                            'sync_source' => 'nodejs',
                        ]),
                        'last_message_at' => isset($chatData['last_message_at'])
                            ? \Carbon\Carbon::parse($chatData['last_message_at'])
                            : now(),
                        'created_at' => isset($chatData['created_at'])
                            ? \Carbon\Carbon::parse($chatData['created_at'])
                            : now(),
                        'updated_at' => now(),
                    ]
                );

                if ($chat->wasRecentlyCreated) {
                    $syncedCount++;
                } else {
                    $updatedCount++;
                }
            }

            DB::commit();

            $this->logger->info('Chats synced successfully', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $accountId,
                'synced_count' => $syncedCount,
                'updated_count' => $updatedCount,
                'errors_count' => count($errors),
                'total_processed' => count($chatsData),
            ]);

            return (object) [
                'success' => true,
                'message' => 'Chats synced successfully',
                'data' => [
                    'synced_count' => $syncedCount,
                    'updated_count' => $updatedCount,
                    'errors_count' => count($errors),
                    'errors' => $errors,
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            $this->logger->error('Failed to sync chats', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $accountId,
                'chats_count' => count($chatsData),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to sync chats: ' . $e->getMessage(),
                'data' => [
                    'errors' => [$e->getMessage()],
                ],
            ];
        }
    }

    /**
     * Sync contacts from Node.js service
     *
     * @param int $accountId
     * @param array $contactsData
     * @return object
     */
    public function syncContacts($accountId, $contactsData)
    {
        try {
            DB::beginTransaction();

            $syncedCount = 0;
            $updatedCount = 0;
            $errors = [];

            foreach ($contactsData as $contactData) {
                // Validate required fields
                if (!isset($contactData['phone']) || !isset($contactData['name'])) {
                    $errors[] = "Missing required fields for contact sync";
                    continue;
                }

                $contact = Contact::updateOrCreate(
                    [
                        'workspace_id' => $this->workspaceId,
                        'phone' => $this->normalizePhoneNumber($contactData['phone']),
                    ],
                    [
                        'uuid' => $contactData['uuid'] ?? Str::uuid(),
                        'first_name' => $contactData['name'],
                        'last_name' => $contactData['last_name'] ?? '',
                        'full_name' => trim(($contactData['name'] ?? '') . ' ' . ($contactData['last_name'] ?? '')),
                        'is_active' => true,
                        'metadata' => array_merge($contactData['metadata'] ?? [], [
                            'sync_timestamp' => now()->timestamp,
                            'sync_source' => 'nodejs',
                        ]),
                        'updated_at' => now(),
                    ]
                );

                if ($contact->wasRecentlyCreated) {
                    $syncedCount++;
                } else {
                    $updatedCount++;
                }

                // Associate contact with WhatsApp account
                if (!DB::table('contact_accounts')
                    ->where('contact_id', $contact->id)
                    ->where('whatsapp_account_id', $accountId)
                    ->where('workspace_id', $this->workspaceId)
                    ->exists()) {

                    DB::table('contact_accounts')->insert([
                        'uuid' => Str::uuid(),
                        'workspace_id' => $this->workspaceId,
                        'contact_id' => $contact->id,
                        'whatsapp_account_id' => $accountId,
                        'first_interaction_at' => now(),
                        'last_interaction_at' => now(),
                        'total_messages' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            $this->logger->info('Contacts synced successfully', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $accountId,
                'synced_count' => $syncedCount,
                'updated_count' => $updatedCount,
                'errors_count' => count($errors),
                'total_processed' => count($contactsData),
            ]);

            return (object) [
                'success' => true,
                'message' => 'Contacts synced successfully',
                'data' => [
                    'synced_count' => $syncedCount,
                    'updated_count' => $updatedCount,
                    'errors_count' => count($errors),
                    'errors' => $errors,
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            $this->logger->error('Failed to sync contacts', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $accountId,
                'contacts_count' => count($contactsData),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to sync contacts: ' . $e->getMessage(),
                'data' => [
                    'errors' => [$e->getMessage()],
                ],
            ];
        }
    }

    /**
     * Sync account statistics from Node.js service
     *
     * @param int $accountId
     * @param array $statsData
     * @return object
     */
    public function syncAccountStats($accountId, $statsData)
    {
        try {
            $account = WhatsAppAccount::where('id', $accountId)
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if (!$account) {
                return (object) [
                    'success' => false,
                    'message' => 'Account not found',
                ];
            }

            // Update account with statistics
            $updateData = [
                'last_activity_at' => now(),
                'metadata' => array_merge($account->metadata ?? [], $statsData, [
                    'stats_sync_timestamp' => now()->timestamp,
                    'stats_sync_source' => 'nodejs',
                ]),
            ];

            // Update connection status if provided
            if (isset($statsData['connection_status'])) {
                $updateData['status'] = $statsData['connection_status'];

                if ($statsData['connection_status'] === 'connected') {
                    $updateData['last_connected_at'] = now();
                } elseif ($statsData['connection_status'] === 'disconnected') {
                    $updateData['disconnected_at'] = now();
                }
            }

            $account->update($updateData);

            $this->logger->info('Account statistics synced', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $accountId,
                'phone_number' => $account->phone_number,
                'stats_updated' => array_keys($statsData),
            ]);

            return (object) [
                'success' => true,
                'message' => 'Account statistics synced successfully',
                'data' => $account,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to sync account statistics', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to sync account statistics: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process webhook data from Node.js service
     *
     * @param string $event
     * @param array $data
     * @return object
     */
    public function processWebhookData($event, $data)
    {
        try {
            switch ($event) {
                case 'chats.sync':
                    return $this->handleChatSyncWebhook($data);

                case 'contacts.sync':
                    return $this->handleContactSyncWebhook($data);

                case 'account.stats':
                    return $this->handleAccountStatsWebhook($data);

                case 'message.received':
                    return $this->handleMessageReceivedWebhook($data);

                default:
                    return (object) [
                        'success' => false,
                        'message' => "Unknown webhook event: {$event}",
                    ];
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to process webhook data', [
                'workspace_id' => $this->workspaceId,
                'event' => $event,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to process webhook data: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle chat sync webhook
     *
     * @param array $data
     * @return object
     */
    protected function handleChatSyncWebhook($data)
    {
        if (!isset($data['account_id']) || !isset($data['chats'])) {
            return (object) [
                'success' => false,
                'message' => 'Invalid chat sync webhook data',
            ];
        }

        return $this->syncChats($data['account_id'], $data['chats']);
    }

    /**
     * Handle contact sync webhook
     *
     * @param array $data
     * @return object
     */
    protected function handleContactSyncWebhook($data)
    {
        if (!isset($data['account_id']) || !isset($data['contacts'])) {
            return (object) [
                'success' => false,
                'message' => 'Invalid contact sync webhook data',
            ];
        }

        return $this->syncContacts($data['account_id'], $data['contacts']);
    }

    /**
     * Handle account stats webhook
     *
     * @param array $data
     * @return object
     */
    protected function handleAccountStatsWebhook($data)
    {
        if (!isset($data['account_id']) || !isset($data['stats'])) {
            return (object) [
                'success' => false,
                'message' => 'Invalid account stats webhook data',
            ];
        }

        return $this->syncAccountStats($data['account_id'], $data['stats']);
    }

    /**
     * Handle message received webhook
     *
     * @param array $data
     * @return object
     */
    protected function handleMessageReceivedWebhook($data)
    {
        if (!isset($data['account_id']) || !isset($data['message'])) {
            return (object) [
                'success' => false,
                'message' => 'Invalid message received webhook data',
            ];
        }

        // This would integrate with MessageService
        // For now, just log and return success
        $this->logger->info('Message received webhook processed', [
            'workspace_id' => $this->workspaceId,
            'account_id' => $data['account_id'],
            'message_type' => $data['message']['type'] ?? 'unknown',
        ]);

        return (object) [
            'success' => true,
            'message' => 'Message received webhook processed',
        ]);
    }

    /**
     * Find or create contact from chat data
     *
     * @param string $phone
     * @param array $chatData
     * @return Contact|null
     */
    protected function findOrCreateContact($phone, $chatData)
    {
        $normalizedPhone = $this->normalizePhoneNumber($phone);

        $contact = Contact::where('workspace_id', $this->workspaceId)
            ->where('phone', $normalizedPhone)
            ->first();

        if (!$contact) {
            $contact = Contact::create([
                'uuid' => $chatData['contact_uuid'] ?? Str::uuid(),
                'workspace_id' => $this->workspaceId,
                'phone' => $normalizedPhone,
                'first_name' => $chatData['contact_name'] ?? $this->extractFirstNameFromPhone($phone),
                'last_name' => '',
                'full_name' => $chatData['contact_name'] ?? $this->extractFirstNameFromPhone($phone),
                'is_active' => true,
                'metadata' => [
                    'source' => 'whatsapp_sync',
                    'created_from_chat' => true,
                    'original_chat_data' => $chatData,
                ],
            ]);
        }

        return $contact;
    }

    /**
     * Normalize phone number to standard format
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
        $normalizedPhone = preg_replace('/[^\d+]/', '', $phone);

        // Ensure proper international format
        if (strlen($normalizedPhone) >= 10 && !str_starts_with($normalizedPhone, '+')) {
            $normalizedPhone = '+' . $normalizedPhone;
        }

        return $normalizedPhone;
    }

    /**
     * Extract first name from phone number (fallback)
     *
     * @param string $phone
     * @return string
     */
    protected function extractFirstNameFromPhone($phone)
    {
        // For fallback, use last 4 digits as "name"
        $digitsOnly = preg_replace('/\D/', '', $phone);
        if (strlen($digitsOnly) >= 4) {
            return 'User ' . substr($digitsOnly, -4);
        }

        return 'Unknown';
    }

    /**
     * Get sync statistics for workspace
     *
     * @return array
     */
    public function getSyncStatistics()
    {
        try {
            $accountIds = WhatsAppAccount::where('workspace_id', $this->workspaceId)
                ->pluck('id');

            return [
                'total_accounts' => $accountIds->count(),
                'total_chats' => Chat::where('workspace_id', $this->workspaceId)
                    ->whereIn('whatsapp_account_id', $accountIds)
                    ->count(),
                'total_contacts' => Contact::where('workspace_id', $this->workspaceId)
                    ->count(),
                'last_sync_at' => Chat::where('workspace_id', $this->workspaceId)
                    ->whereIn('whatsapp_account_id', $accountIds)
                    ->max('updated_at'),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get sync statistics', [
                'workspace_id' => $this->workspaceId,
                'error' => $e->getMessage(),
            ]);

            return [
                'total_accounts' => 0,
                'total_chats' => 0,
                'total_contacts' => 0,
                'last_sync_at' => null,
            ];
        }
    }
}