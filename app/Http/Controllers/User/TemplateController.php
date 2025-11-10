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

    private function templateService()
    {
        return $this->templateService;
    }

    public function index(Request $request, $uuid = null)
    {
        return $this->templateService()->getTemplates($request, $uuid, $request->query('search'));
    }

    public function create(Request $request)
    {
        return $this->templateService()->createTemplate($request);
    }

    public function update(Request $request, $uuid)
    {
        return $this->templateService()->updateTemplate($request, $uuid);
    }

    public function delete($uuid)
    {
        return $this->templateService()->deleteTemplate($uuid);
    }
}
