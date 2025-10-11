<?php

namespace App\Services\WhatsApp\Adapters;

interface WhatsAppAdapterInterface
{
    /**
     * Send a message with optional rich options.
     * $options may include: type, buttons, header, footer, button_label
     */
    public function sendMessage($contactUuId, $messageContent, $userId = null, array $options = []);
    public function syncTemplates();
    public function createTemplate($request);
    public function updateTemplate($request, $uuid);
    public function deleteTemplate($uuid);
}
