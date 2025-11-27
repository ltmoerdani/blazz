<?php

namespace App\Http\Controllers\User;

use Carbon\Carbon;
use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Helpers\CustomHelper;
use App\Helpers\SubscriptionHelper;
use App\Models\Addon;
use App\Models\Chat;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\workspace;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\Template;
use App\Services\SubscriptionService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends BaseController
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {
        // Constructor injection - no manual instantiation
    }

    public function index(Request $request){
        $workspaceId = $this->getWorkspaceId();
        $data['subscription'] = Subscription::with('plan')->where('workspace_id', $workspaceId)->first();
        $data['subscriptionDetails'] = $this->subscriptionService->calculateSubscriptionBillingDetails($workspaceId, $data['subscription']->plan_id);
        $data['subscriptionIsActive'] = $this->subscriptionService->isSubscriptionActive($workspaceId);
        $data['chatCount'] = Chat::where('workspace_id', $workspaceId)
            ->whereNull('deleted_at')
            ->whereHas('contact', function ($query) {
                $query->whereNull('deleted_at');
            })
            ->count();
        $data['campaignCount'] = Campaign::where('workspace_id', $workspaceId)->whereNull('deleted_at')->count();
        $data['contactCount'] = Contact::where('workspace_id', $workspaceId)->whereNull('deleted_at')->count();
        $data['templateCount'] = Template::where('workspace_id', $workspaceId)->whereNull('deleted_at')->count();
        $data['graphAPIVersion'] = config('graph.api_version');

        $workspaceId = $this->getWorkspaceId();
        $workspace = workspace::where('id', $workspaceId)->first();
        $config = $workspace->metadata ? json_decode($workspace->metadata, true) : [];
        $settings = Setting::whereIn('key', ['is_embedded_signup_active', 'whatsapp_client_id', 'whatsapp_config_id'])
            ->pluck('value', 'key');

        $data['workspace'] = $workspace;
        $data['campaigns'] = Campaign::where('workspace_id', $workspaceId)
            ->whereIn('status', ['pending', 'scheduled'])
            ->limit(5)
            ->get();
        
        // Check if workspace needs WhatsApp setup
        // This checks both Meta API and WhatsApp Web.js connections
        $data['setupWhatsapp'] = CustomHelper::needsWhatsAppSetup($workspaceId);
        
        $data['period'] = $this->period();
        $data['inbound'] = $this->getChatCounts('inbound');
        $data['outbound'] = $this->getChatCounts('outbound');
        $data['embeddedSignupActive'] = CustomHelper::isModuleEnabled('Embedded Signup');
        $data['appId'] = $settings->get('whatsapp_client_id', null);
        $data['configId'] = $settings->get('whatsapp_config_id', null);
        $data['title'] = __('Dashboard');

        return Inertia::render('User/Dashboard', $data);
    }

    public function dismissNotification(Request $request, $type){
        $currentworkspaceId = $this->getWorkspaceId();
        $workspaceConfig = workspace::where('id', $currentworkspaceId)->first();

        $metadataArray = $workspaceConfig->metadata ? json_decode($workspaceConfig->metadata, true) : [];

        if($type === 'team'){
            $metadataArray['notification']['team'] = false;
        }

        $updatedMetadataJson = json_encode($metadataArray);

        $workspaceConfig->metadata = $updatedMetadataJson;
        $workspaceConfig->save();

        return redirect()->route('dashboard')->with(
            'status', [
                'type' => 'success',
                'message' => __('Notification dismissed successfully!')
            ]
        );
    }

    private function period(){
        $currentDate = Carbon::now();
        $dateArray = [];

        for ($i = 0; $i < 7; $i++) {
            $currentDate->startOfDay();
            $dateArray[] = $currentDate->format('Y-m-d\TH:i:s.000\Z');
            $currentDate->subDay();
        }

        $dateArray = array_reverse($dateArray);

        return $dateArray;
    }

    private function getChatCounts($type){
        $workspaceId = $this->getWorkspaceId();
        $chatCounts = [];

        foreach ($this->period() as $dateString) {
            $date = Carbon::parse($dateString);
            $chatCount = Chat::where('workspace_id', $workspaceId)
                ->where('type', $type)
                ->whereNull('deleted_at')
                ->whereDate('created_at', $date->toDateString())
                ->count();
            $chatCounts[] = $chatCount;
        }

        return $chatCounts;
    }
}
