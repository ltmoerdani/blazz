<?php

namespace App\Services;

use App\Services\WhatsApp\MessageSendingService;
use App\Services\WhatsApp\TemplateManagementService;
use App\Services\WhatsApp\MediaProcessingService;
use App\Services\WhatsApp\BusinessProfileService;
use App\Services\WhatsApp\WhatsAppHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Models\Setting;
use App\Models\workspace;

class WhatsappService
{
    private $messageSendingService;
    private $templateManagementService;
    private $mediaProcessingService;
    private $businessProfileService;
    private $whatsAppHealthService;

    // Legacy properties for backward compatibility
    private $accessToken;
    private $apiVersion;
    private $appId;
    private $phoneNumberId;
    private $workspaceId;
    private $wabaId;

    public function __construct($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId)
    {
        $this->accessToken = $accessToken;
        $this->apiVersion = $apiVersion;
        $this->appId = $appId;
        $this->phoneNumberId = $phoneNumberId;
        $this->wabaId = $wabaId;
        $this->workspaceId = $workspaceId;

        // Initialize new services
        $this->messageSendingService = new MessageSendingService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId);
        $this->templateManagementService = new TemplateManagementService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId);
        $this->mediaProcessingService = new MediaProcessingService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId);
        $this->businessProfileService = new BusinessProfileService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId);
        $this->whatsAppHealthService = new WhatsAppHealthService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId);

        // Configure Pusher (maintain existing behavior)
        Config::set('broadcasting.connections.pusher', [
            'driver' => 'pusher',
            'key' => Setting::where('key', 'pusher_app_key')->first()->value,
            'secret' => Setting::where('key', 'pusher_app_secret')->first()->value,
            'app_id' => Setting::where('key', 'pusher_app_id')->first()->value,
            'options' => [
                'cluster' => Setting::where('key', 'pusher_app_cluster')->first()->value,
            ],
        ]);
    }

    // ===== MESSAGE SENDING METHODS (DELEGATED TO MessageSendingService) =====

    /**
     * @deprecated Use MessageSendingService::sendMessage() instead
     */
    public function sendMessage($contactUuId, $messageContent, $userId = null, $type="text", $buttons = [], $header = [], $footer = null, $buttonLabel = null)
    {
        return $this->messageSendingService->sendMessage($contactUuId, $messageContent, $userId, $type, $buttons, $header, $footer, $buttonLabel);
    }

    /**
     * @deprecated Use MessageSendingService::sendTemplateMessage() instead
     */
    public function sendTemplateMessage($contactUuId, $templateContent, $userId = null, $campaignId = null, $mediaId = null)
    {
        return $this->messageSendingService->sendTemplateMessage($contactUuId, $templateContent, $userId, $campaignId, $mediaId);
    }

    /**
     * @deprecated Use MessageSendingService::sendMedia() instead
     */
    public function sendMedia($contactUuId, $mediaType, $mediaFileName, $mediaUrl, $location, $caption = null, $transcription = null)
    {
        return $this->messageSendingService->sendMedia($contactUuId, $mediaType, $mediaFileName, $mediaUrl, $location, $caption, $transcription);
    }

    /**
     * @deprecated Use MessageSendingService::reactToMessage() instead
     */
    public function reactToMessage($phoneNumber, $wamId, $emoji)
    {
        return $this->messageSendingService->reactToMessage($phoneNumber, $wamId, $emoji);
    }

    /**
     * @deprecated Use MessageSendingService::sendLocation() instead
     */
    public function sendLocation($phoneNumber, $location)
    {
        return $this->messageSendingService->sendLocation($phoneNumber, $location);
    }

    // ===== TEMPLATE MANAGEMENT METHODS (DELEGATED TO TemplateManagementService) =====

    /**
     * @deprecated Use TemplateManagementService::createTemplate() instead
     */
    public function createTemplate(Request $request)
    {
        return $this->templateManagementService->createTemplate($request);
    }

    /**
     * @deprecated Use TemplateManagementService::updateTemplate() instead
     */
    public function updateTemplate(Request $request, $uuid)
    {
        return $this->templateManagementService->updateTemplate($request, $uuid);
    }

    /**
     * @deprecated Use TemplateManagementService::syncTemplates() instead
     */
    public function syncTemplates()
    {
        return $this->templateManagementService->syncTemplates();
    }

    /**
     * @deprecated Use TemplateManagementService::deleteTemplate() instead
     */
    public function deleteTemplate($uuid)
    {
        return $this->templateManagementService->deleteTemplate($uuid);
    }

    // ===== MEDIA PROCESSING METHODS (DELEGATED TO MediaProcessingService) =====

    /**
     * @deprecated Use MediaProcessingService::getMedia() instead
     */
    public function getMedia($mediaId)
    {
        return $this->mediaProcessingService->getMedia($mediaId);
    }

    /**
     * @deprecated Use MediaProcessingService::viewMedia() instead
     */
    public function viewMedia($mediaId)
    {
        return $this->mediaProcessingService->viewMedia($mediaId);
    }

    /**
     * @deprecated Use MediaProcessingService::initiateResumableUploadSession() instead
     */
    public function initiateResumableUploadSession($file)
    {
        return $this->mediaProcessingService->initiateResumableUploadSession($file);
    }

    /**
     * @deprecated Use MediaProcessingService::createResumableUploadSession() instead
     */
    public function createResumableUploadSession($file)
    {
        return $this->mediaProcessingService->createResumableUploadSession($file);
    }

    /**
     * @deprecated Use MediaProcessingService::getContentTypeFromUrl() instead
     */
    public function getContentTypeFromUrl($url)
    {
        return $this->mediaProcessingService->getContentTypeFromUrl($url);
    }

    /**
     * @deprecated Use MediaProcessingService::formatMediaResponse() instead
     */
    public function formatMediaResponse($wamId, $mediaType, $contentType, $transcription = null)
    {
        return $this->mediaProcessingService->formatMediaResponse($wamId, $mediaType, $contentType, $transcription);
    }

    /**
     * @deprecated Use MediaProcessingService::getMediaSizeInBytesFromUrl() instead
     */
    public function getMediaSizeInBytesFromUrl($url)
    {
        return $this->mediaProcessingService->getMediaSizeInBytesFromUrl($url);
    }

    // ===== BUSINESS PROFILE METHODS (DELEGATED TO BusinessProfileService) =====

    /**
     * @deprecated Use BusinessProfileService::getBusinessProfile() instead
     */
    public function getBusinessProfile()
    {
        return $this->businessProfileService->getBusinessProfile();
    }

    /**
     * @deprecated Use BusinessProfileService::updateBusinessProfile() instead
     */
    public function updateBusinessProfile(Request $request)
    {
        return $this->businessProfileService->updateBusinessProfile($request);
    }

    /**
     * @deprecated Use BusinessProfileService::deRegisterPhone() instead
     */
    public function deRegisterPhone()
    {
        return $this->businessProfileService->deRegisterPhone();
    }

  
    /**
     * @deprecated Use BusinessProfileService::getPhoneNumberStatus() instead
     */
    public function getPhoneNumberStatus()
    {
        return $this->businessProfileService->getPhoneNumberStatus();
    }

    /**
     * @deprecated Use BusinessProfileService::getAccountReviewStatus() instead
     */
    public function getAccountReviewStatus()
    {
        return $this->businessProfileService->getAccountReviewStatus();
    }

    // ===== HEALTH MONITORING METHODS (DELEGATED TO WhatsAppHealthService) =====

    /**
     * @deprecated Use WhatsAppHealthService::checkHealth() instead
     */
    public function checkHealth()
    {
        return $this->whatsAppHealthService->checkHealth();
    }

    /**
     * @deprecated Use WhatsAppHealthService::subscribeToWaba() instead
     */
    public function subscribeToWaba()
    {
        return $this->whatsAppHealthService->subscribeToWaba();
    }

    /**
     * @deprecated Use WhatsAppHealthService::getWabaSubscriptions() instead
     */
    public function getWabaSubscriptions()
    {
        return $this->whatsAppHealthService->getWabaSubscriptions();
    }

    /**
     * @deprecated Use WhatsAppHealthService::overrideCallbackUrl() instead
     */
    public function overrideCallbackUrl($callbackUrl, $verifyToken)
    {
        return $this->whatsAppHealthService->overrideCallbackUrl($callbackUrl, $verifyToken);
    }

    /**
     * @deprecated Use WhatsAppHealthService::unSubscribeToWaba() instead
     */
    public function unSubscribeToWaba()
    {
        return $this->whatsAppHealthService->unSubscribeToWaba();
    }

    // ===== NEW ENHANCED METHODS (Available on new services) =====

    /**
     * Get access to the new MessageSendingService
     */
    public function messageSending(): MessageSendingService
    {
        return $this->messageSendingService;
    }

    /**
     * Get access to the new TemplateManagementService
     */
    public function templateManagement(): TemplateManagementService
    {
        return $this->templateManagementService;
    }

    /**
     * Get access to the new MediaProcessingService
     */
    public function mediaProcessing(): MediaProcessingService
    {
        return $this->mediaProcessingService;
    }

    /**
     * Get access to the new BusinessProfileService
     */
    public function businessProfile(): BusinessProfileService
    {
        return $this->businessProfileService;
    }

    /**
     * Get access to the new WhatsAppHealthService
     */
    public function healthMonitoring(): WhatsAppHealthService
    {
        return $this->whatsAppHealthService;
    }

    // ===== BACKWARD COMPATIBILITY PROPERTIES =====

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function getPhoneNumberId(): string
    {
        return $this->phoneNumberId;
    }

    public function getWorkspaceId(): int
    {
        return $this->workspaceId;
    }

    public function getWabaId(): string
    {
        return $this->wabaId;
    }
}