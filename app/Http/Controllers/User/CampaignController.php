<?php

namespace App\Http\Controllers\User;

use App\Exports\CampaignDetailsExport;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreCampaign;
use App\Http\Requests\HybridCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\CampaignLogResource;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\ContactGroup;
use App\Models\Workspace;
use App\Models\Template;
use App\Services\CampaignService;
use App\Models\WhatsAppAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class CampaignController extends BaseController
{
    private $campaignService;

    public function __construct(CampaignService $campaignService)
    {
        $this->campaignService = $campaignService;
    }

    public function index(Request $request, $uuid = null){
        $workspaceId = session()->get('current_workspace');
        if($uuid == null){
            $searchTerm = $request->query('search');
            $campaignType = $request->query('campaign_type');
            $status = $request->query('status');
            $provider = $request->query('provider');

            $settings = Workspace::where('id', $workspaceId)->first();

            $campaignsQuery = Campaign::with(['template', 'contactGroup', 'whatsappAccount', 'campaignLogs'])
                ->where('workspace_id', $workspaceId)
                ->whereNull('deleted_at');

            // Apply filters
            if ($searchTerm) {
                $campaignsQuery->where(function ($query) use ($searchTerm) {
                    $query->where('name', 'like', '%' . $searchTerm . '%')
                          ->orWhere('body_text', 'like', '%' . $searchTerm . '%')
                          ->orWhereHas('template', function ($templateQuery) use ($searchTerm) {
                              $templateQuery->where('name', 'like', '%' . $searchTerm . '%');
                          });
                });
            }

            if ($campaignType) {
                $campaignsQuery->where('campaign_type', $campaignType);
            }

            if ($status) {
                $campaignsQuery->where('status', $status);
            }

            if ($provider) {
                $campaignsQuery->where('preferred_provider', $provider);
            }

            $rows = CampaignResource::collection(
                $campaignsQuery->latest()->paginate(10)
            );

            return Inertia::render('User/Campaign/Index', [
                'title' => __('Campaigns'),
                'allowCreate' => true,
                'rows' => $rows,
                'filters' => request()->all(['search', 'campaign_type', 'status', 'provider']),
                'settings' => $settings,
                'campaignTypes' => [
                    ['value' => '', 'label' => __('All Types')],
                    ['value' => 'template', 'label' => __('Template-based')],
                    ['value' => 'direct', 'label' => __('Direct Message')]
                ],
                'statusOptions' => [
                    ['value' => '', 'label' => __('All Status')],
                    ['value' => 'pending', 'label' => __('Pending')],
                    ['value' => 'scheduled', 'label' => __('Scheduled')],
                    ['value' => 'ongoing', 'label' => __('Ongoing')],
                    ['value' => 'completed', 'label' => __('Completed')],
                    ['value' => 'failed', 'label' => __('Failed')]
                ],
                'providerOptions' => [
                    ['value' => '', 'label' => __('All Providers')],
                    ['value' => 'webjs', 'label' => 'WhatsApp Web JS'],
                    ['value' => 'meta_api', 'label' => 'Meta Business API']
                ]
            ]);
        } else if($uuid == 'create'){
            $data['settings'] = Workspace::where('id', $workspaceId)->first();
            $data['templates'] = Template::where('workspace_id', $workspaceId)
                ->where('deleted_at', null)
                ->where('status', 'APPROVED')
                ->get();

            $data['contactGroups'] = ContactGroup::where('workspace_id', $workspaceId)
                ->where('deleted_at', null)
                ->get();

            // Get WhatsApp sessions for provider selection
            $data['whatsappAccounts'] = WhatsAppAccount::forWorkspace($workspaceId)
                ->active()
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
                        'health_score' => $session->health_score,
                        'is_healthy' => $session->isHealthy(),
                    ];
                });

            // Campaign types for hybrid system
            $data['campaignTypes'] = [
                [
                    'value' => 'template',
                    'label' => __('Use Template'),
                    'description' => __('Select an approved template from your template library')
                ],
                [
                    'value' => 'direct',
                    'label' => __('Direct Message'),
                    'description' => __('Create a custom message without using templates')
                ]
            ];

            // Provider options
            $data['providerOptions'] = [
                [
                    'value' => 'webjs',
                    'label' => 'WhatsApp Web JS',
                    'description' => __('Recommended for better compatibility and features')
                ],
                [
                    'value' => 'meta_api',
                    'label' => 'Meta Business API',
                    'description' => __('Official WhatsApp Business API')
                ]
            ];

            $data['title'] = __('Create campaign');

            return Inertia::render('User/Campaign/Create', $data);
        } else {
            $data['campaign'] = Campaign::with(['contactGroup', 'template', 'whatsappAccount', 'campaignLogs' => function($query) {
                $query->with('contact', 'chat')->latest();
            }])->where('uuid', $uuid)->first();

            if ($data['campaign']) {
                // Get campaign statistics using optimized counters
                $statistics = $data['campaign']->getStatistics();
                $data['campaign']['statistics'] = $statistics;

                // Get message content for display
                $data['campaign']['message_content'] = $data['campaign']->getResolvedMessageContent();

                // Additional campaign metadata
                $data['campaign']['campaign_type_label'] = $data['campaign']->isTemplateBased() ? __('Template-based') : __('Direct Message');
                $data['campaign']['provider_label'] = $data['campaign']->preferred_provider === 'webjs' ? 'WhatsApp Web JS' : 'Meta Business API';

                // WhatsApp session info
                if ($data['campaign']->whatsappAccount) {
                    $data['campaign']['session_info'] = [
                        'phone_number' => $data['campaign']->whatsappAccount->formatted_phone_number,
                        'provider_type' => $data['campaign']->whatsappAccount->provider_type,
                        'health_score' => $data['campaign']->whatsappAccount->health_score,
                        'is_healthy' => $data['campaign']->whatsappAccount->isHealthy(),
                    ];
                }
            }

            $data['filters'] = request()->all(['search']);

            $searchTerm = $request->query('search');
            $data['rows'] = CampaignLogResource::collection(
                CampaignLog::with('contact', 'chat.logs')
                    ->where('campaign_id', $data['campaign']->id)
                    ->where(function ($query) use ($searchTerm) {
                        $query->whereHas('contact', function ($contactQuery) use ($searchTerm) {
                            $contactQuery->where('first_name', 'like', '%' . $searchTerm . '%')
                                         ->orWhere('last_name', 'like', '%' . $searchTerm . '%')
                                         ->orWhere('phone', 'like', '%' . $searchTerm . '%');
                        });
                    })
                    ->orderBy('id')
                    ->paginate(10)
            );
            $data['title'] = __('View campaign');

            return Inertia::render('User/Campaign/View', $data);
        }
    }

    public function store(StoreCampaign $request){
        $this->campaignService->store($request);

        return Redirect::route('campaigns')->with(
            'status', [
                'type' => 'success',
                'message' => __('Campaign created successfully!')
            ]
        );
    }

    /**
     * Store hybrid campaign (template or direct message)
     */
    /**
     * Store hybrid campaign (template or direct message)
     */
    public function storeHybrid(HybridCampaignRequest $request)
    {
        $this->campaignService->createHybridCampaign($request);

        return Redirect::route('campaigns')->with(
            'status', [
                'type' => 'success',
                'message' => __('Campaign created successfully!')
            ]
        );
    }

    /**
     * Get campaign statistics
     */
    public function statistics($uuid): JsonResponse
    {
        $workspaceId = session()->get('current_workspace');
        $campaign = Campaign::where('uuid', $uuid)
            ->where('workspace_id', $workspaceId)
            ->first();

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => __('Campaign not found')
            ], 404);
        }

        return response()->json([
            'success' => true,
            'statistics' => $campaign->getStatistics()
        ]);
    }

    /**
     * Get available WhatsApp sessions for provider selection
     */
    public function availableSessions(): JsonResponse
    {
        $workspaceId = session()->get('current_workspace');

        $sessions = WhatsAppAccount::forWorkspace($workspaceId)
            ->active()
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'uuid' => $session->uuid,
                    'phone_number' => $session->formatted_phone_number,
                    'provider_type' => $session->provider_type,
                    'status' => $session->status,
                    'is_primary' => $session->is_primary,
                    'health_score' => $session->health_score,
                    'is_healthy' => $session->isHealthy(),
                    'last_activity' => $session->last_activity_at?->diffForHumans(),
                ];
            });

        return response()->json([
            'success' => true,
            'sessions' => $sessions,
            'webjs_count' => $sessions->where('provider_type', 'webjs')->count(),
            'meta_api_count' => $sessions->where('provider_type', 'meta_api')->count(),
        ]);
    }

    /**
     * Validate template availability for selected provider
     */
    public function validateTemplateProvider(Request $request): JsonResponse
    {
        $request->validate([
            'template_uuid' => 'required|string',
            'provider' => 'required|in:webjs,meta_api'
        ]);

        $workspaceId = session()->get('current_workspace');

        $template = Template::where('uuid', $request->template_uuid)
            ->where('workspace_id', $workspaceId)
            ->first();

        if (!$template) {
            return response()->json([
                'valid' => false,
                'message' => __('Template not found')
            ], 404);
        }

        // Template compatibility logic
        $isValid = true;
        $message = __('Template is compatible with selected provider');

        // Check if template requires media (WebJS has different limitations than Meta API)
        if ($request->provider === 'webjs' && $template->header_type === 'video') {
            // WebJS might have video size limitations
            $isValid = false;
            $message = __('Video templates may have limitations with WhatsApp Web JS');
        }

        return response()->json([
            'valid' => $isValid,
            'message' => $message,
            'template' => [
                'header_type' => $template->header_type,
                'requires_media' => in_array($template->header_type, ['image', 'document', 'video']),
                'button_count' => count($template->buttons_data ?? []),
            ]
        ]);
    }

    /**
     * Preview campaign message (for both template and direct)
     */
    public function previewMessage(Request $request): JsonResponse
    {
        $request->validate([
            'campaign_type' => 'required|in:template,direct',
            'template_uuid' => 'required_if:campaign_type,template|string',
            'body_text' => 'required_if:campaign_type,direct|string',
        ]);

        try {
            $workspaceId = session()->get('current_workspace');

            if ($request->campaign_type === 'template') {
                $template = Template::where('uuid', $request->template_uuid)
                    ->where('workspace_id', $workspaceId)
                    ->first();

                if (!$template) {
                    return response()->json([
                        'success' => false,
                        'message' => __('Template not found')
                    ], 404);
                }

                $messageContent = [
                    'header_type' => $template->header_type,
                    'header_text' => $template->header_text,
                    'header_media' => $template->header_media,
                    'body_text' => $template->body_text,
                    'footer_text' => $template->footer_text,
                    'buttons_data' => $template->buttons_data
                ];
            } else {
                $messageContent = [
                    'header_type' => $request->header_type ?? 'text',
                    'header_text' => $request->header_text ?? null,
                    'header_media' => $request->header_media ?? null,
                    'body_text' => $request->body_text,
                    'footer_text' => $request->footer_text ?? null,
                    'buttons_data' => $request->buttons ?? []
                ];
            }

            return response()->json([
                'success' => true,
                'message_content' => $messageContent
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to generate preview: ') . $e->getMessage()
            ], 500);
        }
    }

    public function export($uuid = null){
        return Excel::download(new CampaignDetailsExport($uuid), 'campaign.csv');
    }

    public function delete($uuid){
        $this->campaignService->destroy($uuid);

        return Redirect::back()->with(
            'status', [
                'type' => 'success',
                'message' => __('Row deleted successfully!')
            ]
        );
    }
}