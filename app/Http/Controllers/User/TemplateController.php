<?php

namespace App\Http\Controllers\User;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use App\Services\TemplateService;
use App\Services\WhatsApp\TemplateManagementService;
use App\Services\WhatsApp\MessageSendingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Validator;

class TemplateController extends BaseController
{
    private TemplateService $templateService;
    private TemplateManagementService $templateManagementService;
    private MessageSendingService $messageSendingService;

    public function __construct(
        TemplateService $templateService,
        TemplateManagementService $templateManagementService,
        MessageSendingService $messageSendingService
    ) {
        $this->templateService = $templateService;
        $this->templateManagementService = $templateManagementService;
        $this->messageSendingService = $messageSendingService;
    }

    public function index(Request $request, $uuid = null)
    {
        if ($uuid === null) {
            return $this->templateService->listTemplates($request, $request->query('search'));
        } elseif ($uuid === 'sync') {
            return $this->templateService->syncTemplatesFromWhatsApp();
        } else {
            return $this->templateService->getTemplateDetail($request, $uuid);
        }
    }

    public function create(Request $request)
    {
        return $this->templateService->createTemplate($request);
    }

    public function update(Request $request, $uuid)
    {
        return $this->templateService->updateTemplate($request, $uuid);
    }

    public function delete($uuid)
    {
        return $this->templateService->deleteTemplate($uuid);
    }

    /**
     * =========================================================================
     * DRAFT TEMPLATE ENDPOINTS (Scenario A: Local-First Approach)
     * =========================================================================
     */

    /**
     * Save template as draft (no Meta API submission)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveDraft(Request $request)
    {
        return $this->templateService->saveDraft($request);
    }

    /**
     * Update an existing draft template
     *
     * @param Request $request
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateDraft(Request $request, string $uuid)
    {
        return $this->templateService->updateDraft($request, $uuid);
    }

    /**
     * Publish a draft template to Meta API
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function publishToMeta(string $uuid)
    {
        return $this->templateService->publishToMeta($uuid);
    }

    /**
     * Check if Meta API is configured for the current workspace
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkMetaConfig()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'is_configured' => $this->templateService->isMetaApiConfigured(),
            ],
        ]);
    }
}
