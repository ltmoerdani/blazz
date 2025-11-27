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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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

    /**
     * =========================================================================
     * DRAFT TEMPLATE METHODS (Scenario A: Local-First Approach)
     * =========================================================================
     * These methods enable template creation without WhatsApp connection.
     * Templates are saved locally first, then optionally published to Meta API.
     * =========================================================================
     */

    /**
     * Save template as draft (local only, no Meta API call)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveDraft(Request $request)
    {
        if ($response = $this->abortIfDemo('create')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:512',
            'category' => 'required|string',
            'language' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('Some required fields have not been filled'),
                'errors' => $validator->messages()->get('*')
            ], 422);
        }

        try {
            $template = DB::transaction(function () use ($request) {
                // Build components from request
                $components = $this->buildComponentsFromRequest($request);

                // Create draft template
                $template = Template::create([
                    'uuid' => Str::uuid()->toString(),
                    'workspace_id' => $this->workspaceId,
                    'meta_id' => null, // Draft - not submitted to Meta yet
                    'name' => $this->formatTemplateName($request->input('name')),
                    'category' => strtoupper($request->input('category')),
                    'language' => $request->input('language'),
                    'status' => Template::STATUS_DRAFT,
                    'metadata' => json_encode([
                        'components' => $components,
                        'allow_category_change' => $request->input('allow_category_change', false),
                    ]),
                    'created_by' => Auth::id(),
                ]);

                Log::info('Template draft saved', [
                    'template_id' => $template->id,
                    'uuid' => $template->uuid,
                    'name' => $template->name,
                    'workspace_id' => $this->workspaceId,
                    'created_by' => Auth::id(),
                ]);

                return $template;
            });

            return response()->json([
                'success' => true,
                'message' => __('Template saved as draft successfully'),
                'data' => [
                    'uuid' => $template->uuid,
                    'id' => $template->id,
                    'status' => $template->status,
                    'can_publish' => $this->isMetaApiConfigured(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save template draft', [
                'error' => $e->getMessage(),
                'workspace_id' => $this->workspaceId,
                'request_data' => $request->except(['access_token']),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to save template. Please try again.'),
            ], 500);
        }
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
        if ($response = $this->abortIfDemo('update')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:512',
            'category' => 'required|string',
            'language' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('Some required fields have not been filled'),
                'errors' => $validator->messages()->get('*')
            ], 422);
        }

        try {
            $template = Template::where('uuid', $uuid)
                ->where('workspace_id', $this->workspaceId)
                ->whereNull('deleted_at')
                ->first();

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => __('Template not found'),
                ], 404);
            }

            // Only drafts can be updated this way
            if (!$template->isDraft()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Only draft templates can be updated. For published templates, please create a new version.'),
                ], 422);
            }

            $template = DB::transaction(function () use ($request, $template) {
                $components = $this->buildComponentsFromRequest($request);

                $template->update([
                    'name' => $this->formatTemplateName($request->input('name')),
                    'category' => strtoupper($request->input('category')),
                    'language' => $request->input('language'),
                    'metadata' => json_encode([
                        'components' => $components,
                        'allow_category_change' => $request->input('allow_category_change', false),
                    ]),
                ]);

                Log::info('Template draft updated', [
                    'template_id' => $template->id,
                    'uuid' => $template->uuid,
                    'workspace_id' => $this->workspaceId,
                ]);

                return $template->fresh();
            });

            return response()->json([
                'success' => true,
                'message' => __('Template draft updated successfully'),
                'data' => [
                    'uuid' => $template->uuid,
                    'id' => $template->id,
                    'status' => $template->status,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update template draft', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
                'workspace_id' => $this->workspaceId,
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to update template. Please try again.'),
            ], 500);
        }
    }

    /**
     * Publish draft template to Meta API
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function publishToMeta(string $uuid)
    {
        if ($response = $this->abortIfDemo('create')) {
            return $response;
        }

        // Check if Meta API is configured
        if (!$this->isMetaApiConfigured()) {
            return response()->json([
                'success' => false,
                'message' => __('Meta API is not configured. Please configure your WhatsApp Business API settings first.'),
            ], 422);
        }

        try {
            $template = Template::where('uuid', $uuid)
                ->where('workspace_id', $this->workspaceId)
                ->whereNull('deleted_at')
                ->first();

            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => __('Template not found'),
                ], 404);
            }

            // Only drafts can be published
            if (!$template->isDraft()) {
                return response()->json([
                    'success' => false,
                    'message' => __('This template has already been submitted to Meta.'),
                ], 422);
            }

            // Build request from template data for TemplateManagementService
            $templateRequest = $this->buildRequestFromTemplate($template);

            // Submit to Meta API using existing service
            // Note: TemplateManagementService::createTemplate returns stdClass, not JsonResponse
            $responseObject = $this->templateService->createTemplate($templateRequest);

            if (isset($responseObject->success) && $responseObject->success === true) {
                // Update template with Meta response
                $metaId = $responseObject->data->id ?? null;
                $metaStatus = $responseObject->data->status ?? Template::STATUS_PENDING;

                $template->update([
                    'meta_id' => $metaId,
                    'status' => $metaStatus,
                ]);

                Log::info('Template published to Meta successfully', [
                    'template_id' => $template->id,
                    'uuid' => $template->uuid,
                    'meta_id' => $metaId,
                    'workspace_id' => $this->workspaceId,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => __('Template submitted to Meta successfully. Status: :status', [
                        'status' => $template->fresh()->status_label
                    ]),
                    'data' => [
                        'uuid' => $template->uuid,
                        'meta_id' => $metaId,
                        'status' => $metaStatus,
                    ],
                ]);
            } else {
                // Meta API returned error
                $errorMessage = $responseObject->data->error->message
                    ?? $responseObject->error
                    ?? 'Unknown error from Meta API';

                Log::warning('Failed to publish template to Meta', [
                    'template_id' => $template->id,
                    'uuid' => $template->uuid,
                    'error' => $errorMessage,
                    'workspace_id' => $this->workspaceId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 422);
            }

        } catch (\Exception $e) {
            Log::error('Exception while publishing template to Meta', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'uuid' => $uuid,
                'workspace_id' => $this->workspaceId,
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to publish template to Meta. Please try again.'),
            ], 500);
        }
    }

    /**
     * Build template components array from request data
     *
     * @param Request $request
     * @return array
     */
    protected function buildComponentsFromRequest(Request $request): array
    {
        $components = [];

        // Header component
        if ($request->has('header') && !empty($request->input('header.format'))) {
            $header = [
                'type' => 'HEADER',
                'format' => strtoupper($request->input('header.format')),
            ];

            if ($header['format'] === 'TEXT') {
                $header['text'] = $request->input('header.text', '');
                if ($request->has('header.example')) {
                    $header['example'] = [
                        'header_text' => (array) $request->input('header.example.header_text', []),
                    ];
                }
            } else {
                // Media header (IMAGE, VIDEO, DOCUMENT)
                if ($request->has('header.example.header_handle')) {
                    $header['example'] = [
                        'header_handle' => (array) $request->input('header.example.header_handle', []),
                    ];
                }
            }

            $components[] = $header;
        }

        // Body component (required)
        if ($request->has('body') || $request->has('body_text')) {
            $bodyText = $request->input('body.text', $request->input('body_text', ''));
            $body = [
                'type' => 'BODY',
                'text' => $bodyText,
            ];

            if ($request->has('body.example') || $request->has('body_example')) {
                $bodyExample = $request->input('body.example.body_text', $request->input('body_example', []));
                if (!empty($bodyExample)) {
                    $body['example'] = [
                        'body_text' => [(array) $bodyExample],
                    ];
                }
            }

            $components[] = $body;
        }

        // Footer component
        if ($request->has('footer') && !empty($request->input('footer.text', $request->input('footer')))) {
            $footerText = is_array($request->input('footer'))
                ? $request->input('footer.text', '')
                : $request->input('footer', '');

            if (!empty($footerText)) {
                $components[] = [
                    'type' => 'FOOTER',
                    'text' => $footerText,
                ];
            }
        }

        // Buttons component
        if ($request->has('buttons') && !empty($request->input('buttons'))) {
            $buttons = $request->input('buttons');
            if (is_array($buttons) && count($buttons) > 0) {
                $buttonItems = [];
                foreach ($buttons as $button) {
                    if (!empty($button['type'])) {
                        $buttonItem = [
                            'type' => strtoupper($button['type']),
                        ];

                        if (isset($button['text'])) {
                            $buttonItem['text'] = $button['text'];
                        }

                        // Handle different button types
                        switch (strtoupper($button['type'])) {
                            case 'URL':
                                if (isset($button['url'])) {
                                    $buttonItem['url'] = $button['url'];
                                }
                                if (isset($button['example'])) {
                                    $buttonItem['example'] = (array) $button['example'];
                                }
                                break;

                            case 'PHONE_NUMBER':
                                if (isset($button['phone_number'])) {
                                    $buttonItem['phone_number'] = $button['phone_number'];
                                }
                                break;

                            case 'QUICK_REPLY':
                                // Quick reply only needs text
                                break;

                            case 'COPY_CODE':
                                if (isset($button['example'])) {
                                    $buttonItem['example'] = $button['example'];
                                }
                                break;
                        }

                        $buttonItems[] = $buttonItem;
                    }
                }

                if (!empty($buttonItems)) {
                    $components[] = [
                        'type' => 'BUTTONS',
                        'buttons' => $buttonItems,
                    ];
                }
            }
        }

        return $components;
    }

    /**
     * Build a mock request object from template data for Meta API submission
     *
     * @param Template $template
     * @return Request
     */
    protected function buildRequestFromTemplate(Template $template): Request
    {
        $metadata = $template->metadata ?? [];
        $components = $metadata['components'] ?? [];

        $requestData = [
            'name' => $template->name,
            'category' => $template->category,
            'language' => $template->language,
            'allow_category_change' => $metadata['allow_category_change'] ?? false,
        ];

        // Parse components back to request format
        foreach ($components as $component) {
            $type = strtolower($component['type'] ?? '');

            switch ($type) {
                case 'header':
                    $requestData['header'] = [
                        'format' => $component['format'] ?? 'TEXT',
                        'text' => $component['text'] ?? '',
                    ];
                    if (isset($component['example'])) {
                        $requestData['header']['example'] = $component['example'];
                    }
                    break;

                case 'body':
                    $requestData['body'] = [
                        'text' => $component['text'] ?? '',
                    ];
                    if (isset($component['example'])) {
                        $requestData['body']['example'] = $component['example'];
                    }
                    break;

                case 'footer':
                    $requestData['footer'] = [
                        'text' => $component['text'] ?? '',
                    ];
                    break;

                case 'buttons':
                    $requestData['buttons'] = $component['buttons'] ?? [];
                    break;
            }
        }

        return new Request($requestData);
    }

    /**
     * Check if Meta API is properly configured for the workspace
     *
     * @return bool
     */
    public function isMetaApiConfigured(): bool
    {
        $workspace = workspace::find($this->workspaceId);

        if (!$workspace || !$workspace->metadata) {
            return false;
        }

        $config = is_string($workspace->metadata)
            ? json_decode($workspace->metadata, true)
            : $workspace->metadata;

        return !empty($config['whatsapp']['access_token'] ?? null)
            && !empty($config['whatsapp']['waba_id'] ?? null);
    }

    /**
     * Format template name to comply with Meta requirements
     * - lowercase only
     * - underscores instead of spaces
     * - alphanumeric and underscores only
     *
     * @param string $name
     * @return string
     */
    protected function formatTemplateName(string $name): string
    {
        // Convert to lowercase
        $name = strtolower($name);

        // Replace spaces with underscores
        $name = str_replace(' ', '_', $name);

        // Remove any characters that aren't alphanumeric or underscore
        $name = preg_replace('/[^a-z0-9_]/', '', $name);

        // Remove consecutive underscores
        $name = preg_replace('/_+/', '_', $name);

        // Trim underscores from start and end
        $name = trim($name, '_');

        return $name;
    }
}
