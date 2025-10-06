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
    protected $subscriptionService;

    public function __construct()
    {
        $this->subscriptionService = new SubscriptionService();
    }

    public function index(Request $request){
        $workspaceId = session()->get('current_workspace');
        $data['subscription'] = Subscription::with('plan')->where('workspace_id', $workspaceId)->first();
        $data['subscriptionDetails'] = SubscriptionService::calculateSubscriptionBillingDetails($workspaceId, $data['subscription']->plan_id);
        $data['subscriptionIsActive'] = SubscriptionService::isSubscriptionActive($workspaceId);
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

        $workspaceId = session()->get('current_workspace');
        $workspace = workspace::where('id', $workspaceId)->first();
        $config = $workspace->metadata ? json_decode($workspace->metadata, true) : [];
        $settings = Setting::whereIn('key', ['is_embedded_signup_active', 'whatsapp_client_id', 'whatsapp_config_id'])
            ->pluck('value', 'key');

        $data['workspace'] = $workspace;
        $data['campaigns'] = Campaign::where('workspace_id', $workspaceId)
            ->whereIn('status', ['pending', 'scheduled'])
            ->limit(5)
            ->get();
        $data['setupWhatsapp'] = isset($config['whatsapp']) ? false : true;;
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
        $currentworkspaceId = session()->get('current_workspace');
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
        $workspaceId = session()->get('current_workspace');
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
