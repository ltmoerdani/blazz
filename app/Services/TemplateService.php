<?php

namespace App\Services;

use App\Events\NewChatEvent;
use App\Http\Resources\TemplateResource;
use App\Models\workspace;
use App\Models\Template;
use App\Models\WhatsAppAccount;
use App\Services\WhatsappService;
use App\Services\WhatsApp\TemplateManagementService;
use App\Services\WhatsApp\MessageSendingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TemplateService
{
    private TemplateManagementService $templateService;
    private MessageSendingService $messageService;
    private $workspaceId;

    public function __construct(
        $workspaceId,
        TemplateManagementService $templateService,
        MessageSendingService $messageService
    ) {
        $this->workspaceId = $workspaceId;
        $this->templateService = $templateService;
        $this->messageService = $messageService;
    }

    /**
     * @deprecated Use constructor injection instead
     * OLD CODE - Commented out
     */
    /*
    private function initializeWhatsappService()
    {
        $config = workspace::where('id', $this->workspaceId)->first()->metadata;
        $config = $config ? json_decode($config, true) : [];

        $accessToken = $config['whatsapp']['access_token'] ?? null;
        $apiVersion = config('graph.api_version');
        $appId = $config['whatsapp']['app_id'] ?? null;
        $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
        $wabaId = $config['whatsapp']['waba_id'] ?? null;

        $this->whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $this->workspaceId);
    }
    */

    /**
     * Get templates list or sync templates from WhatsApp
     */
    public function getTemplates(Request $request, $uuid = null)
    {
        $response = [];

        if ($uuid === null) {
            $response = $this->getTemplatesListResponse($request);
        } elseif ($uuid === 'sync') {
            // NEW: Use injected service
            $response = $this->templateService->syncTemplates();
        } else {
            $response = $this->getTemplateDetailResponse($request, $uuid);
        }

        return $response;
    }

    private function getTemplatesListResponse(Request $request)
    {
        if ($request->expectsJson()) {
            $rows = Template::where('workspace_id', $this->workspaceId)->where('deleted_at', null)
                ->get()
                ->map(function ($row) {
                    return [
                        'value' => $row->id,
                        'label' => $row->name,
                    ];
                });

            return response()->json([$rows]);
        }

        return Inertia::render('User/Templates/Index', [
            'title' => __('templates'),
            'allowCreate' => true,
            'rows' => TemplateResource::collection(
                Template::where('workspace_id', $this->workspaceId)->where('deleted_at', null)->latest()->paginate(10)
            ),
        ]);
    }

    private function getTemplateDetailResponse(Request $request, $uuid)
    {
        if ($request->expectsJson()) {
            $row = Template::where('uuid', $uuid)->where('deleted_at', null)->first();
            return response()->json($row);
        }

        $data['languages'] = config('languages');
        $data['template'] = Template::where('uuid', $uuid)->first();
        $data['title'] = 'Edit Template';
        return Inertia::render('User/Templates/Edit', $data);
    }

    public function createTemplate(Request $request)
    {
        if ($request->isMethod('get')){
            $data['languages'] = config('languages');
            $data['settings'] = workspace::where('id', $this->workspaceId)->first();

            // Get WhatsApp sessions for WebJS compatibility check
            $data['whatsappAccounts'] = WhatsAppAccount::forWorkspace($this->workspaceId)
                ->where('status', 'connected')
                ->get()
                ->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'uuid' => $session->uuid,
                        'session_id' => $session->session_id,
                        'phone_number' => $session->phone_number,
                        'provider_type' => $session->provider_type,
                        'status' => $session->status,
                        'is_primary' => $session->is_primary,
                        'is_active' => $session->is_active,
                        'formatted_phone_number' => $session->formatted_phone_number,
                    ];
                });

            return Inertia::render('User/Templates/Add', $data);
        } elseif ($request->isMethod('post')){
            if ($response = $this->abortIfDemo('create')) {
                return $response;
            }

            $validator = Validator::make($request->all(),[
                'name' => 'required',
                'category' => 'required',
                'language' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false,'message'=>'Some required fields have not been filled','errors'=>$validator->messages()->get('*')]);
            }

            // NEW: Use injected service
            return $this->templateService->createTemplate($request);
        }
    }

    public function updateTemplate(Request $request, $uuid)
    {
        if ($response = $this->abortIfDemo('update')) {
            return $response;
        }

        $validator = Validator::make($request->all(),[
            'name' => 'required',
            'category' => 'required',
            'language' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false,'message'=>'Some required fields have not been filled','errors'=>$validator->messages()->get('*')]);
        }

        // NEW: Use injected service
        return $this->templateService->updateTemplate($request, $uuid);
    }

    public function deleteTemplate($uuid)
    {
        if ($response = $this->abortIfDemo('delete')) {
            return $response;
        }

        // NEW: Use injected service
        $query = $this->templateService->deleteTemplate($uuid);

        if($query->success === true){
            return response()->json([
                'success' => true,
                'message'=> __('Template deleted successfully')
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message'=> __('something went wrong. Refresh the page and try again')
            ]);
        }
    }

    protected function abortIfDemo($type)
    {
        if (app()->environment('demo') && $this->workspaceId == 1) {
            if($type == 'delete'){
                return response()->json([
                    'success' => false,
                    'message' => 'You are not allowed to do this in this demo account. To test template features please create your own account.'
                ]);
            } else {
                $responseObject = new \stdClass();
                $responseObject->success = false;
                $responseObject->data = new \stdClass();
                $responseObject->data->error = new \stdClass();
                $responseObject->data->error->message = 'You are not allowed to do this in this demo account. To test template features please create your own account.';

                return response()->json($responseObject);
            }
        }

        return null;
    }
}
