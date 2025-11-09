<?php

namespace App\Http\Controllers\Admin;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreWhatsappSettings;
use App\Helpers\CustomHelper;
use App\Http\Requests\StoreWhatsappProfile;
use App\Models\Addon;
use App\Models\Contact;
use App\Models\workspace;
use App\Models\Setting;
use App\Models\Template;
use App\Services\ContactFieldService;
use App\Services\WhatsappService;
use App\Services\WhatsApp\MessageSendingService;
use App\Services\WhatsApp\TemplateManagementService;
use App\Services\WhatsApp\BusinessProfileService;
use App\Services\WhatsApp\WhatsAppHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class SettingController extends BaseController
{
    public function __construct(
        private ContactFieldService $contactFieldService,
        private MessageSendingService $messageService,
        private TemplateManagementService $templateService,
        private BusinessProfileService $businessService,
        private WhatsAppHealthService $healthService
    ) {
        // Constructor injection - WhatsApp services now injected
    }

    public function index(Request $request, $display = null){
        if ($request->isMethod('get')) {
            $workspaceId = session()->get('current_workspace');
            $data['title'] = __('Settings');
            $data['settings'] = workspace::where('id', $workspaceId)->first();
            $data['timezones'] = config('formats.timezones');
            $data['countries'] = config('formats.countries');
            $data['sounds'] = config('sounds');
            $data['modules'] = Addon::get();
            $contactModel = new Contact;
            $data['contactGroups'] = $contactModel->getAllContactGroups($workspaceId);

            return Inertia::render('User/Settings/General', $data);
        }
    }

    public function mobileView(Request $request){
        $data['title'] = __('Settings');
        $data['settings'] = workspace::where('id', session()->get('current_workspace'))->first();
        return Inertia::render('User/Settings/Main', $data);
    }

    public function viewGeneralSettings(Request $request){
        $contactModel = new Contact;
        $workspaceId = session()->get('current_workspace');
        $data['title'] = __('Settings');
        $data['settings'] = workspace::where('id', session()->get('current_workspace'))->first();
        $data['modules'] = Addon::get();
        $data['contactGroups'] = $contactModel->getAllContactGroups($workspaceId);
        
        return Inertia::render('User/Settings/General', $data);
    }

    public function viewWhatsappSettings(Request $request){
        $settings = Setting::whereIn('key', ['is_embedded_signup_active', 'whatsapp_client_id', 'whatsapp_config_id'])
            ->pluck('value', 'key');

        $data = [
            'embeddedSignupActive' => CustomHelper::isModuleEnabled('Embedded Signup'),
            'graphAPIVersion' => config('graph.api_version'),
            'appId' => $settings->get('whatsapp_client_id', null),
            'configId' => $settings->get('whatsapp_config_id', null),
            'settings' => workspace::where('id', session()->get('current_workspace'))->first(),
            'modules' => Addon::get(),
            'title' => __('Settings'),
        ];

        return Inertia::render('User/Settings/Whatsapp', $data);
    }

    public function storeWhatsappSettings(StoreWhatsappSettings $request) {
        $embeddedSignupActive = Setting::where('key', 'is_embedded_signup_active')->value('value');
        $setWebhookUrl = $embeddedSignupActive == 1 ? true : false;

        return $this->saveWhatsappSettings(
            $request->access_token,
            $request->app_id,
            $request->phone_number_id,
            $request->waba_id,
            $setWebhookUrl
        );
    }

    public function updateToken(Request $request) {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }
        
        $workspaceId = session()->get('current_workspace');
        $config = workspace::findOrFail($workspaceId)->metadata;
        $config = $config ? json_decode($config, true) : [];

        return $this->saveWhatsappSettings(
            $request->access_token,
            $config['whatsapp']['app_id'] ?? null,
            $config['whatsapp']['phone_number_id'] ?? null,
            $config['whatsapp']['waba_id'] ?? null
        );
    }
    
    public function refreshWhatsappData() {
        $workspaceId = session()->get('current_workspace');
        $config = workspace::findOrFail($workspaceId)->metadata;
        $config = $config ? json_decode($config, true) : [];

        if($config['whatsapp']['is_embedded_signup'] && $config['whatsapp']['is_embedded_signup'] == 1 && class_exists(\Modules\EmbeddedSignup\Services\MetaService::class)) {
            $embeddedSetup = new \Modules\EmbeddedSignup\Services\MetaService();
            $embeddedSetup->overrideWabaCallbackUrl($workspaceId);
        }
    
        return $this->saveWhatsappSettings(
            $config['whatsapp']['access_token'] ?? null,
            $config['whatsapp']['app_id'] ?? null,
            $config['whatsapp']['phone_number_id'] ?? null,
            $config['whatsapp']['waba_id'] ?? null
        );
    }

    public function contacts(Request $request){
        if ($request->isMethod('get')) {
            $settings = workspace::where('id', session()->get('current_workspace'))->first();

            return Inertia::render('User/Settings/Contact', [
                'title' => __('Settings'),
                'filters' => $request->all(),
                'rows' => $this->contactFieldService->get($request),
                'settings' => $settings,
                'modules' => Addon::get(),
            ]);
        } elseif($request->isMethod('post')) {
            $currentworkspaceId = session()->get('current_workspace');
            $workspaceConfig = workspace::where('id', $currentworkspaceId)->first();
    
            $metadataArray = $workspaceConfig->metadata ? json_decode($workspaceConfig->metadata, true) : [];

            $metadataArray['contacts']['location'] = $request->location;

            $updatedMetadataJson = json_encode($metadataArray);

            $workspaceConfig->metadata = $updatedMetadataJson;
            $workspaceConfig->save();

            return back()->with(
                'status', [
                    'type' => 'success',
                    'message' => __('Settings updated successfully')
                ]
            );
        }
    }

    public function tickets(Request $request){
        if ($request->isMethod('get')) {
            $settings = workspace::where('id', session()->get('current_workspace'))->first();

            return Inertia::render('User/Settings/Ticket', [
                'title' => __('Settings'),
                'filters' => $request->all(),
                'rows' => $this->contactFieldService->get($request),
                'settings' => $settings,
                'modules' => Addon::get(),
            ]);
        } elseif($request->isMethod('post')) {
            $currentworkspaceId = session()->get('current_workspace');
            $workspaceConfig = workspace::where('id', $currentworkspaceId)->first();
    
            $metadataArray = $workspaceConfig->metadata ? json_decode($workspaceConfig->metadata, true) : [];

            $metadataArray['tickets']['active'] = $request->active;
            $metadataArray['tickets']['auto_assignment'] = $request->auto_assignment;
            $metadataArray['tickets']['reassign_reopened_chats'] = $request->reassign_reopened_chats;
            $metadataArray['tickets']['allow_agents_to_view_all_chats'] = $request->allow_agents_to_view_all_chats;

            $updatedMetadataJson = json_encode($metadataArray);

            $workspaceConfig->metadata = $updatedMetadataJson;
            $workspaceConfig->save();
        }
    }

    public function automation(Request $request){
        if ($request->isMethod('get')) {
            $settings = workspace::where('id', session()->get('current_workspace'))->first();

            return Inertia::render('User/Settings/Automation', [
                'title' => __('Settings'),
                'settings' => $settings,
                'modules' => Addon::get(),
            ]);
        } elseif($request->isMethod('post')) {
            $currentworkspaceId = session()->get('current_workspace');
            $workspaceConfig = workspace::where('id', $currentworkspaceId)->first();
    
            $metadataArray = $workspaceConfig->metadata ? json_decode($workspaceConfig->metadata, true) : [];
            $metadataArray['automation']['response_sequence'] = $request->response_sequence;

            $updatedMetadataJson = json_encode($metadataArray);
            $workspaceConfig->metadata = $updatedMetadataJson;
            $workspaceConfig->save();
        }
    }

    public function whatsappBusinessProfileUpdate(StoreWhatsappProfile $request){
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $workspaceId = session()->get('current_workspace');
        $workspace = workspace::where('id', $workspaceId)->first();
        $config = $workspace && $workspace->metadata ? json_decode($workspace->metadata, true) : [];

        if(isset($config['whatsapp'])){
            $accessToken = $config['whatsapp']['access_token'] ?? null;
            $apiVersion = config('graph.api_version');
            $appId = $config['whatsapp']['app_id'] ?? null;
            $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
            $wabaId = $config['whatsapp']['waba_id'] ?? null;

            // OLD: Keep for reference during transition
            /*
            $whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId);
            $response = $whatsappService->businessProfile()->updateBusinessProfile($request);
            */

            // NEW: Use injected service
            $response = $this->businessService->updateBusinessProfile($request->validated());

            if($response->success === true){
                return back()->with(
                    'status', [
                        'type' => 'success',
                        'message' => __('Your whatsapp business profile has been changed successfully!')
                    ]
                );
            } else {
                return back()->with(
                    'status', [
                        'type' => 'error',
                        'message' => __('Something went wrong! Your business profile could not be updated!')
                    ]
                );
            }
        }

        return back()->with(
            'status', [
                'type' => 'error',
                'message' => __('Setup your whatsapp integration first!')
            ]
        );
    }

    public function deleteWhatsappIntegration(Request $request){
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $embeddedSignupActive = Setting::where('key', 'is_embedded_signup_active')->value('value');
        $workspaceId = session()->get('current_workspace');
        $workspaceConfig = workspace::where('id', $workspaceId)->first();
        $config = $workspaceConfig->metadata ? json_decode($workspaceConfig->metadata, true) : [];

        if(isset($config['whatsapp'])){
            if($embeddedSignupActive == 1){
                //Unsubscribe webhook
                $workspaceId = session()->get('current_workspace');
                $apiVersion = config('graph.api_version');

                $accessToken = $config['whatsapp']['access_token'] ?? null;
                $appId = $config['whatsapp']['app_id'] ?? null;
                $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
                $wabaId = $config['whatsapp']['waba_id'] ?? null;
            
                // OLD: Keep for reference during transition
                /*
                $whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId);
                $whatsappService->healthMonitoring()->unSubscribeToWaba();
                */

                // NEW: Use injected service
                $this->healthService->unSubscribeToWaba();
            }
            
            //Delete whatsapp settings
            if (isset($config['whatsapp'])) {
                unset($config['whatsapp']);
            }

            $updatedMetadataJson = json_encode($config);
            $workspaceConfig->metadata = $updatedMetadataJson;
            $workspaceConfig->save();

            //Delete templates
            $templates = Template::where('workspace_id', $workspaceId)->get();
            foreach ($templates as $template) {
                $template->deleted_at = now();
                $template->save();
            }

            return back()->with(
                'status', [
                    'type' => 'success',
                    'message' => __('Your integration has been removed successfully!')
                ]
            );
        }

        return back()->with(
            'status', [
                'type' => 'error',
                'message' => __('Setup your whatsapp integration first!')
            ]
        );
    }

    /**
     * Save WhatsApp settings with proper validation
     *
     * @param string $accessToken
     * @param string $appId
     * @param string $phoneNumberId
     * @param string $wabaId
     * @return \Illuminate\Http\RedirectResponse
     */
    private function saveWhatsappSettings($accessToken, $appId, $phoneNumberId, $wabaId) {
        $workspaceId = session()->get('current_workspace');
        $apiVersion = config('graph.api_version');
    
        // OLD: Keep for reference during transition
        /*
        $whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId);
        $phoneNumberResponse = $whatsappService->businessProfile()->getPhoneNumberId($accessToken, $wabaId);
        */

        // NEW: Use injected service
        $phoneNumberResponse = $this->businessService->getPhoneNumberId($accessToken, $wabaId);

        if(!$phoneNumberResponse->success){
            $errorMessage = 'Unknown error occurred';
            if (isset($phoneNumberResponse->error)) {
                $errorMessage = $phoneNumberResponse->error;
            }
            return back()->with(
                'status', [
                    'type' => 'error',
                    'message' => $errorMessage
                ]
            );
        }

        //Get Phone Number Status
        $phoneNumberId = $phoneNumberResponse->data->phone_number_id ?? null;
        $phoneNumberStatusResponse = $this->businessService->getPhoneNumberStatus();

        if(!$phoneNumberStatusResponse->success){
            $errorMessage = 'Unknown error occurred';
            if (isset($phoneNumberStatusResponse->error)) {
                $errorMessage = $phoneNumberStatusResponse->error;
            }
            return back()->with(
                'status', [
                    'type' => 'error',
                    'message' => $errorMessage
                ]
            );
        }

        //Get Account Review Status
        $accountReviewStatusResponse = $this->businessService->getAccountReviewStatus();

        if(!$accountReviewStatusResponse->success){
            $errorMessage = 'Unknown error occurred';
            if (isset($accountReviewStatusResponse->error)) {
                $errorMessage = $accountReviewStatusResponse->error;
            }
            return back()->with(
                'status', [
                    'type' => 'error',
                    'message' => $errorMessage
                ]
            );
        }

        //Get business profile
        $businessProfileResponse = $this->businessService->getBusinessProfile();

        if(!$businessProfileResponse->success){
            $errorMessage = 'Unknown error occurred';
            if (isset($businessProfileResponse->error)) {
                $errorMessage = $businessProfileResponse->error;
            }
            return back()->with(
                'status', [
                    'type' => 'error',
                    'message' => $errorMessage
                ]
            );
        }

        $workspaceConfig = workspace::where('id', $workspaceId)->first();

        $metadataArray = $workspaceConfig->metadata ? json_decode($workspaceConfig->metadata, true) : [];
        $metadataArray['whatsapp']['is_embedded_signup'] = $metadataArray['whatsapp']['is_embedded_signup'] ?? 0;
        $metadataArray['whatsapp']['access_token'] = $accessToken;
        $metadataArray['whatsapp']['app_id'] = $appId;
        $metadataArray['whatsapp']['waba_id'] = $wabaId;

        // Extract phone number data safely
        $phoneData = $phoneNumberResponse->data;
        $metadataArray['whatsapp']['phone_number_id'] = isset($phoneData->phone_number_id) ? (string) $phoneData->phone_number_id : null;
        $metadataArray['whatsapp']['display_phone_number'] = (string) ($phoneData->display_phone_number ?? '');
        $metadataArray['whatsapp']['verified_name'] = (string) ($phoneData->name_display ?? '');
        $metadataArray['whatsapp']['quality_rating'] = (string) ($phoneData->quality_rating ?? '');
        $metadataArray['whatsapp']['name_status'] = (string) ($phoneData->name_status ?? '');
        $metadataArray['whatsapp']['messaging_limit_tier'] = $phoneData->messaging_limit_tier ?? null;
        $metadataArray['whatsapp']['max_daily_conversation_per_phone'] = null;
        $metadataArray['whatsapp']['max_phone_numbers_per_business'] = null;
        // Extract phone status data safely
        $phoneStatusData = (object) $phoneNumberStatusResponse->data;
        $metadataArray['whatsapp']['number_status'] = (string) ($phoneStatusData->status ?? '');
        $metadataArray['whatsapp']['code_verification_status'] = (string) ($phoneStatusData->code_verification_status ?? '');
        $metadataArray['whatsapp']['business_verification'] = '';

        // Extract account review data safely
        $accountReviewData = (object) $accountReviewStatusResponse->data;
        $metadataArray['whatsapp']['account_review_status'] = (string) ($accountReviewData->account_review_status ?? '');

        // Extract business profile data safely
        $businessProfileData = (object) $businessProfileResponse->data;
        $metadataArray['whatsapp']['business_profile']['about'] = $businessProfileData->about ?? null;
        $metadataArray['whatsapp']['business_profile']['address'] = $businessProfileData->address ?? null;
        $metadataArray['whatsapp']['business_profile']['description'] = $businessProfileData->description ?? null;
        $metadataArray['whatsapp']['business_profile']['industry'] = $businessProfileData->vertical ?? null;
        $metadataArray['whatsapp']['business_profile']['email'] = $businessProfileData->email ?? null;

        $updatedMetadataJson = json_encode($metadataArray);
        $workspaceConfig->metadata = $updatedMetadataJson;

        if($workspaceConfig->save()){
            $this->templateService->syncTemplates();

            return back()->with(
                'status', [
                    'type' => 'success',
                    'message' => __('Whatsapp settings updated successfully')
                ]
            );
        } else {
            return back()->with(
                'status', [
                    'type' => 'error',
                    'message' => __('Something went wrong. Refresh the page and try again')
                ]
            );
        }
    }

    protected function abortIfDemo(){
        $workspaceId = session()->get('current_workspace');

        if (app()->environment('demo') && $workspaceId == 1) {
            return back()->with(
                'status', [
                    'type' => 'error',
                    'message' => __('You cannot perform this action using the demo account. To test this feature, please create your own account.')
                ]
            );
        }

        return null;
    }
}
