<?php

namespace App\Services;

use App\Models\Contact;
use Propaganistas\LaravelPhone\PhoneNumber;
use Illuminate\Support\Facades\Log;

/**
 * Contact Provisioning Service
 *
 * Handles automatic contact creation and updates for WhatsApp integrations.
 * Extracted pattern from WebhookController.php for reusability.
 *
 * Features:
 * - E164 phone normalization
 * - Workspace isolation
 * - Soft delete awareness
 * - Name update on subsequent interactions
 *
 * @package App\Services
 */
class ContactProvisioningService
{
    /**
     * Get or create contact with phone normalization
     *
     * Strategy:
     * 1. Normalize phone to E164 format
     * 2. Find existing contact by workspace + phone
     * 3. Create if not exists
     * 4. Update name if currently null
     *
     * @param string $phone Raw phone number (with or without +)
     * @param string|null $name Contact name from WhatsApp
     * @param int $workspaceId Workspace ID for isolation
     * @param string $sourceType Source: 'meta' | 'webjs' | 'manual'
     * @param int|null $sessionId WhatsApp session ID (optional tracking)
     * @return Contact
     * @throws \Exception If phone formatting fails
     */
    public function getOrCreateContact(
        string $phone,
        ?string $name,
        int $workspaceId,
        string $sourceType = 'webjs',
        ?int $sessionId = null
    ): Contact {
        // Step 1: Format phone to E164
        $formattedPhone = $this->formatPhone($phone);

        if (!$formattedPhone) {
            Log::channel('whatsapp')->error('Phone formatting failed', [
                'phone' => $phone,
                'workspace_id' => $workspaceId,
            ]);

            throw new \Exception("Invalid phone number format: {$phone}");
        }

        // Step 2: Find existing contact (with soft delete awareness)
        $contact = Contact::where('workspace_id', $workspaceId)
            ->where('phone', $formattedPhone)
            ->whereNull('deleted_at')
            ->first();

        $isNewContact = false;

        // Step 3: Create contact if not exists
        if (!$contact) {
            $contact = Contact::create([
                'first_name' => $name,
                'last_name' => null,
                'email' => null,
                'phone' => $formattedPhone,
                'workspace_id' => $workspaceId,
                'created_by' => 0, // System-created
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $isNewContact = true;

            Log::channel('whatsapp')->info('Contact created', [
                'contact_id' => $contact->id,
                'phone' => $formattedPhone,
                'name' => $name,
                'workspace_id' => $workspaceId,
            ]);
        }

        // Step 4: Update name if currently null (enrich from incoming data)
        if ($contact->first_name === null && $name) {
            $contact->update([
                'first_name' => $name,
                'updated_at' => now(),
            ]);

            Log::channel('whatsapp')->debug('Contact name updated', [
                'contact_id' => $contact->id,
                'new_name' => $name,
            ]);
        }

        return $contact;
    }

    /**
     * Format phone number to E164 standard
     *
     * Handles:
     * - Numbers with + prefix: +6281234567890
     * - Numbers without + prefix: 6281234567890
     * - Normalizes to E164: +6281234567890
     *
     * @param string $phone Raw phone number
     * @return string|null Formatted E164 phone or null if invalid
     */
    public function formatPhone(string $phone): ?string
    {
        try {
            // Add + prefix if not present
            if (substr($phone, 0, 1) !== '+') {
                $phone = '+' . $phone;
            }

            // Use Laravel Phone package for E164 formatting
            $phoneObject = new PhoneNumber($phone);
            $formatted = $phoneObject->formatE164();

            return $formatted;

        } catch (\Exception $e) {
            Log::channel('whatsapp')->warning('Phone formatting error', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Bulk create or update contacts
     *
     * Optimized for initial sync when processing many contacts at once.
     * Uses firstOrCreate pattern with chunking for memory efficiency.
     *
     * @param array $contactsData Array of [phone, name, sessionId]
     * @param int $workspaceId Workspace ID
     * @param string $sourceType Source type
     * @return array Created/updated contact IDs
     */
    public function bulkProvision(array $contactsData, int $workspaceId, string $sourceType = 'webjs'): array
    {
        $createdIds = [];
        $chunkSize = 50;

        foreach (array_chunk($contactsData, $chunkSize) as $chunk) {
            foreach ($chunk as $data) {
                try {
                    $contact = $this->getOrCreateContact(
                        $data['phone'],
                        $data['name'] ?? null,
                        $workspaceId,
                        $sourceType,
                        $data['session_id'] ?? null
                    );

                    $createdIds[] = $contact->id;

                } catch (\Exception $e) {
                    Log::channel('whatsapp')->error('Bulk provision failed for contact', [
                        'phone' => $data['phone'],
                        'error' => $e->getMessage(),
                    ]);

                    // Continue processing other contacts
                    continue;
                }
            }
        }

        Log::channel('whatsapp')->info('Bulk contact provision completed', [
            'workspace_id' => $workspaceId,
            'total_processed' => count($contactsData),
            'total_created' => count($createdIds),
        ]);

        return $createdIds;
    }

    /**
     * Update contact's latest activity timestamp
     *
     * Called when new chat is created for contact.
     *
     * @param Contact $contact
     * @param \Carbon\Carbon $timestamp
     * @return bool
     */
    public function updateLatestActivity(Contact $contact, $timestamp): bool
    {
        return $contact->update([
            'latest_chat_created_at' => $timestamp,
            'updated_at' => now(),
        ]);
    }
}
