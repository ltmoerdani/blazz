<?php

namespace App\Contracts;

use App\Models\Contact;
use App\Models\WhatsAppSession;

interface WhatsAppAdapterInterface
{
    /**
     * Check if the provider is available and ready to send messages
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Send a text message to a contact
     *
     * @param Contact $contact
     * @param string $message
     * @param int|null $userId
     * @return array
     */
    public function sendMessage(Contact $contact, string $message, ?int $userId = null): array;

    /**
     * Send a media message to a contact
     *
     * @param Contact $contact
     * @param string $mediaType
     * @param string $mediaUrl
     * @param string $caption
     * @param int|null $userId
     * @return array
     */
    public function sendMedia(Contact $contact, string $mediaType, string $mediaUrl, string $caption = '', ?int $userId = null): array;

    /**
     * Send a template message to a contact
     *
     * @param Contact $contact
     * @param array $templateData
     * @param int|null $userId
     * @param int|null $campaignId
     * @return array
     */
    public function sendTemplate(Contact $contact, array $templateData, ?int $userId = null, ?int $campaignId = null): array;

    /**
     * Get the session associated with this adapter
     *
     * @return WhatsAppSession|null
     */
    public function getSession(): ?WhatsAppSession;

    /**
     * Get provider type (meta, webjs)
     *
     * @return string
     */
    public function getProviderType(): string;

    /**
     * Get provider health information
     *
     * @return array
     */
    public function getHealthInfo(): array;

    /**
     * Mark a message as read
     *
     * @param string $messageId
     * @return bool
     */
    public function markAsRead(string $messageId): bool;

    /**
     * Get message status
     *
     * @param string $messageId
     * @return string|null
     */
    public function getMessageStatus(string $messageId): ?string;
}
