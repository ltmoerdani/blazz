<?php

namespace App\Services;

use App\Models\Contact;
use App\Events\ContactPresenceUpdated;
use App\Events\TypingIndicator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ContactPresenceService
{
    private $workspaceId;

    public function __construct($workspaceId = null)
    {
        // Backward compatible: fallback to session if not provided
        $this->workspaceId = $workspaceId ?? session('current_workspace');
    }

    /**
     * Update contact online status
     */
    public function updateOnlineStatus(int $contactId, bool $isOnline, ?int $userId = null): void
    {
        try {
            DB::transaction(function () use ($contactId, $isOnline, $userId) {
                // ✅ NOW: Workspace-scoped query
                $contact = Contact::where('workspace_id', $this->workspaceId)
                    ->where('id', $contactId)
                    ->first();
                if (!$contact) {
                    Log::warning('Contact not found for presence update', ['contact_id' => $contactId]);
                    return;
                }

                $contact->update([
                    'is_online' => $isOnline,
                    'last_activity' => now(),
                ]);

                // Update cache for quick presence checks
                $this->updatePresenceCache($contactId, $isOnline);

                // Broadcast presence update to all users in workspace
                broadcast(new ContactPresenceUpdated($contact, $isOnline, $userId));

                Log::debug('Contact presence updated', [
                    'contact_id' => $contactId,
                    'is_online' => $isOnline,
                    'workspace_id' => $contact->workspace_id
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error updating contact presence', [
                'contact_id' => $contactId,
                'is_online' => $isOnline,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update contact typing status
     */
    public function updateTypingStatus(int $contactId, string $typingStatus, ?int $userId = null): void
    {
        try {
            DB::transaction(function () use ($contactId, $typingStatus, $userId) {
                // ✅ NOW: Workspace-scoped query
                $contact = Contact::where('workspace_id', $this->workspaceId)
                    ->where('id', $contactId)
                    ->first();
                if (!$contact) {
                    Log::warning('Contact not found for typing update', ['contact_id' => $contactId]);
                    return;
                }

                $contact->update([
                    'typing_status' => $typingStatus,
                    'last_activity' => now(),
                ]);

                // Broadcast typing indicator
                broadcast(new TypingIndicator($contact, $userId ?? \Illuminate\Support\Facades\Auth::id(), $typingStatus === 'typing'));

                Log::debug('Contact typing status updated', [
                    'contact_id' => $contactId,
                    'typing_status' => $typingStatus,
                    'workspace_id' => $contact->workspace_id
                ]);

                // Auto-set to idle after 5 seconds of no typing activity
                $this->scheduleTypingReset($contactId);
            });

        } catch (\Exception $e) {
            Log::error('Error updating contact typing status', [
                'contact_id' => $contactId,
                'typing_status' => $typingStatus,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update last message timestamp for sorting
     */
    public function updateLastMessageTime(int $contactId, ?string $messageId = null): void
    {
        try {
            DB::transaction(function () use ($contactId, $messageId) {
                // ✅ NOW: Workspace-scoped query
                $contact = Contact::where('workspace_id', $this->workspaceId)
                    ->where('id', $contactId)
                    ->first();
                if (!$contact) {
                    return;
                }

                $contact->update([
                    'last_message_at' => now(),
                    'last_activity' => now(),
                ]);

                // Update cache for sorting
                Cache::put("contact.last_message.{$contactId}", now(), now()->addMinutes(30));

                Log::debug('Contact last message time updated', [
                    'contact_id' => $contactId,
                    'message_id' => $messageId,
                    'workspace_id' => $contact->workspace_id
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error updating last message time', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get contact presence status with cache
     */
    public function getContactPresence(int $contactId): array
    {
        try {
            // Try cache first for performance
            $cached = Cache::get("contact.presence.{$contactId}");
            if ($cached) {
                return $cached;
            }

            // ✅ NOW: Workspace-scoped query
            $contact = Contact::where('workspace_id', $this->workspaceId)
                ->where('id', $contactId)
                ->first();
            if (!$contact) {
                return ['is_online' => false, 'typing_status' => 'idle', 'last_activity' => null];
            }

            $presence = [
                'is_online' => $contact->is_online,
                'typing_status' => $contact->typing_status,
                'last_activity' => $contact->last_activity,
                'last_message_at' => $contact->last_message_at,
            ];

            // Cache for 2 minutes
            Cache::put("contact.presence.{$contactId}", $presence, now()->addMinutes(2));

            return $presence;

        } catch (\Exception $e) {
            Log::error('Error getting contact presence', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
            return ['is_online' => false, 'typing_status' => 'idle', 'last_activity' => null];
        }
    }

    /**
     * Get workspace contacts with presence info for chat list
     */
    public function getWorkspaceContactsWithPresence(int $workspaceId, ?array $contactIds = null): \Illuminate\Database\Eloquent\Collection
    {
        try {
            $query = Contact::where('workspace_id', $workspaceId)
                ->whereNull('deleted_at')
                ->select([
                    'id',
                    'first_name',
                    'last_name',
                    'phone',
                    'is_online',
                    'typing_status',
                    'last_activity',
                    'last_message_at',
                    'latest_chat_created_at',
                    'is_favorite'
                ]);

            if ($contactIds) {
                $query->whereIn('id', $contactIds);
            }

            // Order by last_message_at (most recent first), then by latest_chat_created_at
            return $query->orderByDesc('last_message_at')
                ->orderByDesc('latest_chat_created_at')
                ->orderBy('is_favorite', 'desc')
                ->get();

        } catch (\Exception $e) {
            Log::error('Error getting workspace contacts with presence', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage()
            ]);
            return new \Illuminate\Database\Eloquent\Collection();
        }
    }

    /**
     * Clean up offline contacts (mark as offline after inactivity)
     */
    public function cleanupOfflineContacts(): int
    {
        try {
            return DB::transaction(function () {
                $cutoffTime = now()->subMinutes(5); // Mark as offline after 5 minutes

                $updated = Contact::where('is_online', true)
                    ->where('last_activity', '<', $cutoffTime)
                    ->update([
                        'is_online' => false,
                        'typing_status' => 'idle'
                    ]);

                if ($updated > 0) {
                    Log::info('Cleaned up offline contacts', ['count' => $updated]);
                }

                return $updated;
            });

        } catch (\Exception $e) {
            Log::error('Error cleaning up offline contacts', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Bulk update presence for multiple contacts
     */
    public function bulkUpdatePresence(array $contactIds, array $presenceData): void
    {
        try {
            DB::transaction(function () use ($contactIds, $presenceData) {
                foreach ($contactIds as $contactId) {
                    $data = array_intersect_key($presenceData, [
                        'is_online' => true,
                        'typing_status' => true,
                        'last_activity' => true,
                        'last_message_at' => true
                    ]);

                    if (!empty($data)) {
                        Contact::where('id', $contactId)->update($data);
                    }
                }
            });

            Log::debug('Bulk presence updated', [
                'contact_count' => count($contactIds),
                'data_keys' => array_keys($presenceData)
            ]);

        } catch (\Exception $e) {
            Log::error('Error in bulk presence update', [
                'contact_count' => count($contactIds),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update presence cache
     */
    private function updatePresenceCache(int $contactId, bool $isOnline): void
    {
        Cache::put("contact.presence.{$contactId}", [
            'is_online' => $isOnline,
            'last_activity' => now()
        ], now()->addMinutes(2));
    }

    /**
     * Schedule typing status reset to idle
     */
    private function scheduleTypingReset(int $contactId): void
    {
        // Use Laravel's cache expiration to trigger reset
        Cache::put("contact.typing_reset.{$contactId}", true, now()->addSeconds(5));
    }

    /**
     * Check if contact should be marked as offline
     */
    public function shouldMarkOffline(int $contactId): bool
    {
        // ✅ NOW: Workspace-scoped query
        $contact = Contact::where('workspace_id', $this->workspaceId)
            ->where('id', $contactId)
            ->first();
        if (!$contact || !$contact->is_online) {
            return false;
        }

        // Mark as offline if no activity for 5 minutes
        return $contact->last_activity && $contact->last_activity->lt(now()->subMinutes(5));
    }

    /**
     * Get online contacts count for workspace
     */
    public function getOnlineContactsCount(int $workspaceId): int
    {
        try {
            return Contact::where('workspace_id', $workspaceId)
                ->where('is_online', true)
                ->whereNull('deleted_at')
                ->count();
        } catch (\Exception $e) {
            Log::error('Error getting online contacts count', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get typing contacts for workspace
     */
    public function getTypingContacts(int $workspaceId): \Illuminate\Database\Eloquent\Collection
    {
        try {
            return Contact::where('workspace_id', $workspaceId)
                ->where('typing_status', 'typing')
                ->whereNull('deleted_at')
                ->select(['id', 'first_name', 'last_name', 'phone', 'last_activity'])
                ->get();
        } catch (\Exception $e) {
            Log::error('Error getting typing contacts', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage()
            ]);
            return new \Illuminate\Database\Eloquent\Collection();
        }
    }
}