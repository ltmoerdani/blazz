<?php

namespace App\Http\Controllers\User;

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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class SettingController extends BaseController
{
    protected $contactFieldService;

    public function __construct(ContactFieldService $contactFieldService)
    {
        $this->contactFieldService = $contactFieldService;
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

        if($config['whatsapp']['is_embedded_signup'] && $config['whatsapp']['is_embedded_signup'] == 1){
            if (class_exists(\Modules\EmbeddedSignup\Services\MetaService::class)) {
                $embeddedSetup = new \Modules\EmbeddedSignup\Services\MetaService();
                $embeddedSetup->overrideWabaCallbackUrl($workspaceId);
            }
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
            $contactFieldService = new ContactFieldService(session()->get('current_workspace'));
            $settings = workspace::where('id', session()->get('current_workspace'))->first();

            return Inertia::render('User/Settings/Contact', [
                'title' => __('Settings'),
                'filters' => $request->all(),
                'rows' => $contactFieldService->get($request),
                'settings' => $settings,
                'modules' => Addon::get(),
            ]);
        } else if($request->isMethod('post')) {
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
            $contactFieldService = new ContactFieldService(session()->get('current_workspace'));
            $settings = workspace::where('id', session()->get('current_workspace'))->first();

            return Inertia::render('User/Settings/Ticket', [
                'title' => __('Settings'),
                'filters' => $request->all(),
                'rows' => $contactFieldService->get($request),
                'settings' => $settings,
                'modules' => Addon::get(),
            ]);
        } else if($request->isMethod('post')) {
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

            /*return back()->with(
                'status', [
                    'type' => 'success',
                    'message' => __('Settings updated successfully')
                ]
            );*/
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
        } else if($request->isMethod('post')) {
            $currentworkspaceId = session()->get('current_workspace');
            $workspaceConfig = workspace::where('id', $currentworkspaceId)->first();
    
            $metadataArray = $workspaceConfig->metadata ? json_decode($workspaceConfig->metadata, true) : [];
            $metadataArray['automation']['response_sequence'] = $request->response_sequence;

            $updatedMetadataJson = json_encode($metadataArray);
            $workspaceConfig->metadata = $updatedMetadataJson;
            $workspaceConfig->save();

            /*return back()->with(
                'status', [
                    'type' => 'success',
                    'message' => __('Settings updated successfully')
                ]
            );*/
        }
    }

    public function whatsappBusinessProfileUpdate(StoreWhatsappProfile $request){
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $workspaceId = session()->get('current_workspace');
        $config = workspace::where('id', $workspaceId)->first()->metadata;
        $config = $config ? json_decode($config, true) : [];

        if(isset($config['whatsapp'])){
            $accessToken = $config['whatsapp']['access_token'] ?? null;
            $apiVersion = config('graph.api_version');
            $appId = $config['whatsapp']['app_id'] ?? null;
            $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
            $wabaId = $config['whatsapp']['waba_id'] ?? null;

            $whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId);
            
            $response = $whatsappService->updateBusinessProfile($request);

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
            
                $whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId);
                $unsubscribe = $whatsappService->unSubscribeToWaba();
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

    private function saveWhatsappSettings($accessToken, $appId, $phoneNumberId, $wabaId, $subscribeToWebhook = false) {
        $workspaceId = session()->get('current_workspace');
        $apiVersion = config('graph.api_version');
    
        $whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId);

        $phoneNumberResponse = $whatsappService->getPhoneNumberId($accessToken, $wabaId);
        
        if(!$phoneNumberResponse->success){
            return back()->with(
                'status', [
                    'type' => 'error',
                    'message' => $phoneNumberResponse->data->error->message
                ]
            );
        }

        //Get Phone Number Status
        $phoneNumberStatusResponse = $whatsappService->getPhoneNumberStatus($accessToken, $phoneNumberResponse->data->id);
        
        if(!$phoneNumberStatusResponse->success){
            return back()->with(
                'status', [
                    'type' => 'error',
                    'message' => $phoneNumberStatusResponse->data->error->message
                ]
            );
        }

        //Get Account Review Status
        $accountReviewStatusResponse = $whatsappService->getAccountReviewStatus($accessToken, $wabaId);
        
        if(!$accountReviewStatusResponse->success){
            return back()->with(
                'status', [
                    'type' => 'error',
                    'message' => $accountReviewStatusResponse->data->error->message
                ]
            );
        }

        //Get business profile
        $businessProfileResponse = $whatsappService->getBusinessProfile($accessToken, $phoneNumberResponse->data->id);
        
        if(!$businessProfileResponse->success){
            return back()->with(
                'status', [
                    'type' => 'error',
                    'message' => $businessProfileResponse->data->error->message
                ]
            );
        }

        $workspaceConfig = workspace::where('id', $workspaceId)->first();
        
        $metadataArray = $workspaceConfig->metadata ? json_decode($workspaceConfig->metadata, true) : [];
        $metadataArray['whatsapp']['is_embedded_signup'] = $metadataArray['whatsapp']['is_embedded_signup'] ?? 0;
        $metadataArray['whatsapp']['access_token'] = $accessToken;
        $metadataArray['whatsapp']['app_id'] = $appId;
        $metadataArray['whatsapp']['waba_id'] = $wabaId;
        $metadataArray['whatsapp']['phone_number_id'] = $phoneNumberResponse->data->id;
        $metadataArray['whatsapp']['display_phone_number'] = $phoneNumberResponse->data->display_phone_number;
        $metadataArray['whatsapp']['verified_name'] = $phoneNumberResponse->data->verified_name;
        $metadataArray['whatsapp']['quality_rating'] = $phoneNumberResponse->data->quality_rating;
        $metadataArray['whatsapp']['name_status'] = $phoneNumberResponse->data->name_status;
        $metadataArray['whatsapp']['messaging_limit_tier'] = $phoneNumberResponse->data->messaging_limit_tier ?? NULL;
        $metadataArray['whatsapp']['max_daily_conversation_per_phone'] = NULL;
        $metadataArray['whatsapp']['max_phone_numbers_per_business'] = NULL;
        $metadataArray['whatsapp']['number_status'] = $phoneNumberStatusResponse->data->status;
        $metadataArray['whatsapp']['code_verification_status'] = $phoneNumberStatusResponse->data->code_verification_status;
        $metadataArray['whatsapp']['business_verification'] = '';
        $metadataArray['whatsapp']['account_review_status'] = $accountReviewStatusResponse->data->account_review_status;
        $metadataArray['whatsapp']['business_profile']['about'] = $businessProfileResponse->data->about ?? NULL;
        $metadataArray['whatsapp']['business_profile']['address'] = $businessProfileResponse->data->address ?? NULL;
        $metadataArray['whatsapp']['business_profile']['description'] = $businessProfileResponse->data->description ?? NULL;
        $metadataArray['whatsapp']['business_profile']['industry'] = $businessProfileResponse->data->vertical ?? NULL;
        $metadataArray['whatsapp']['business_profile']['email'] = $businessProfileResponse->data->email ?? NULL;

        $updatedMetadataJson = json_encode($metadataArray);
        $workspaceConfig->metadata = $updatedMetadataJson;

        if($workspaceConfig->save()){
            $whatsappService->syncTemplates($accessToken, $wabaId);

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
