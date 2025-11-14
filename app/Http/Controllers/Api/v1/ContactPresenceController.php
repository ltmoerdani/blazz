<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\ContactPresenceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ContactPresenceController extends Controller
{
    public function __construct(
        private ContactPresenceService $presenceService
    ) {}

    /**
     * Update contact typing status
     */
    public function updateTypingStatus(Request $request, int $contactId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'typing_status' => 'required|in:idle,typing,recording',
            ]);

            $this->authorizeWorkspaceAccess($contactId);

            $this->presenceService->updateTypingStatus(
                contactId: $contactId,
                typingStatus: $validated['typing_status'],
                userId: Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Typing status updated',
                'contact_id' => $contactId,
                'typing_status' => $validated['typing_status']
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid typing status',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error updating typing status', [
                'contact_id' => $contactId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update typing status'
            ], 500);
        }
    }

    /**
     * Update contact online status
     */
    public function updateOnlineStatus(Request $request, int $contactId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'is_online' => 'required|boolean',
            ]);

            $this->authorizeWorkspaceAccess($contactId);

            $this->presenceService->updateOnlineStatus(
                contactId: $contactId,
                isOnline: $validated['is_online'],
                userId: Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Online status updated',
                'contact_id' => $contactId,
                'is_online' => $validated['is_online']
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid online status',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error updating online status', [
                'contact_id' => $contactId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update online status'
            ], 500);
        }
    }

    /**
     * Get contact presence status
     */
    public function getPresence(int $contactId): JsonResponse
    {
        try {
            $this->authorizeWorkspaceAccess($contactId);

            $presence = $this->presenceService->getContactPresence($contactId);

            return response()->json([
                'success' => true,
                'contact_id' => $contactId,
                'presence' => $presence
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting contact presence', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get contact presence'
            ], 500);
        }
    }

    /**
     * Get workspace contacts with presence info
     */
    public function getWorkspaceContactsPresence(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'workspace_id' => 'required|integer',
                'contact_ids' => 'array',
                'contact_ids.*' => 'integer',
            ]);

            $this->authorizeWorkspace($validated['workspace_id']);

            $contacts = $this->presenceService->getWorkspaceContactsWithPresence(
                workspaceId: $validated['workspace_id'],
                contactIds: $validated['contact_ids'] ?? null
            );

            // Add presence info to each contact
            $contactsWithPresence = $contacts->map(function ($contact) {
                $presence = $this->presenceService->getContactPresence($contact->id);
                return array_merge($contact->toArray(), $presence);
            });

            return response()->json([
                'success' => true,
                'workspace_id' => $validated['workspace_id'],
                'contacts' => $contactsWithPresence,
                'online_count' => $this->presenceService->getOnlineContactsCount($validated['workspace_id']),
                'typing_contacts' => $this->presenceService->getTypingContacts($validated['workspace_id'])
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request parameters',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error getting workspace contacts presence', [
                'workspace_id' => $request->input('workspace_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get workspace contacts presence'
            ], 500);
        }
    }

    /**
     * Bulk update presence for multiple contacts
     */
    public function bulkUpdatePresence(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'contact_ids' => 'required|array',
                'contact_ids.*' => 'integer',
                'presence_data' => 'required|array',
                'presence_data.is_online' => 'boolean',
                'presence_data.typing_status' => 'in:idle,typing,recording',
            ]);

            // Verify all contacts belong to user's workspace
            foreach ($validated['contact_ids'] as $contactId) {
                $this->authorizeWorkspaceAccess($contactId);
            }

            $this->presenceService->bulkUpdatePresence(
                contactIds: $validated['contact_ids'],
                presenceData: $validated['presence_data']
            );

            return response()->json([
                'success' => true,
                'message' => 'Bulk presence updated',
                'updated_count' => count($validated['contact_ids'])
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request parameters',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error in bulk presence update', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update bulk presence'
            ], 500);
        }
    }

    /**
     * Cleanup offline contacts (admin endpoint)
     */
    public function cleanupOfflineContacts(): JsonResponse
    {
        try {
            $updated = $this->presenceService->cleanupOfflineContacts();

            return response()->json([
                'success' => true,
                'message' => 'Offline contacts cleaned up',
                'updated_count' => $updated
            ]);

        } catch (\Exception $e) {
            Log::error('Error cleaning up offline contacts', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup offline contacts'
            ], 500);
        }
    }

    /**
     * Get typing contacts for workspace
     */
    public function getTypingContacts(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'workspace_id' => 'required|integer',
            ]);

            $this->authorizeWorkspace($validated['workspace_id']);

            $typingContacts = $this->presenceService->getTypingContacts($validated['workspace_id']);

            return response()->json([
                'success' => true,
                'workspace_id' => $validated['workspace_id'],
                'typing_contacts' => $typingContacts,
                'count' => $typingContacts->count()
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid workspace ID',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error getting typing contacts', [
                'workspace_id' => $request->input('workspace_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get typing contacts'
            ], 500);
        }
    }

    /**
     * Update last message time for contact
     */
    public function updateLastMessageTime(Request $request, int $contactId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'message_id' => 'string',
            ]);

            $this->authorizeWorkspaceAccess($contactId);

            $this->presenceService->updateLastMessageTime($contactId, $validated['message_id']);

            return response()->json([
                'success' => true,
                'message' => 'Last message time updated',
                'contact_id' => $contactId
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid message ID',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error updating last message time', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update last message time'
            ], 500);
        }
    }

    /**
     * Authorize workspace access for contact
     */
    private function authorizeWorkspaceAccess(int $contactId): void
    {
        $contact = \App\Models\Contact::findOrFail($contactId);

        if (!\App\Models\Team::where('user_id', Auth::id())->where('workspace_id', $contact->workspace_id)->exists()) {
            abort(403, 'Access denied to this contact');
        }
    }

    /**
     * Authorize workspace access
     */
    private function authorizeWorkspace(int $workspaceId): void
    {
        if (!\App\Models\Team::where('user_id', Auth::id())->where('workspace_id', $workspaceId)->exists()) {
            abort(403, 'Access denied to this workspace');
        }
    }
}