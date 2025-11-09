<?php

namespace App\Http\Controllers\Admin;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\PaymentRequest;
use App\Models\Addon;
use App\Models\BillingPayment;
use App\Models\workspace;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Models\Subscription;
use App\Resolvers\PaymentPlatformResolver;
use App\Services\BillingService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Redirect;

class BillingController extends BaseController
{
    // Constants for repeated string literals
    const BILLING_ROUTE = '/billing';
    
    protected $billingService;
    protected $subscriptionService;
    protected $paymentPlatformResolver;

    public function __construct()
    {
        $this->billingService = new BillingService();
        $this->subscriptionService = new SubscriptionService();
        $this->paymentPlatformResolver = new PaymentPlatformResolver();
    }
    
    public function index(Request $request){
        $workspaceId = session()->get('current_workspace');
        $workspace = workspace::where('id', $workspaceId)->first();
        $data['subscription'] = Subscription::with('plan')->where('workspace_id', $workspaceId)->first();
        $data['subscriptionIsActive'] = SubscriptionService::isSubscriptionActive($workspaceId);
        $data['rows'] = $this->billingService->get($request, $workspace->uuid);
        $data['filters'] = $request->all();
        $data['methods'] = $this->paymentMethods();
        $data['subscriptionDetails'] = SubscriptionService::calculateSubscriptionBillingDetails($workspaceId, $data['subscription']->plan_id);
        $data['title'] = __('Billing');
        $data['isPaymentLoading'] = false;
        $data['pusherSettings'] = Setting::whereIn('key', [
            'pusher_app_id',
            'pusher_app_key',
            'pusher_app_secret',
            'pusher_app_cluster',
        ])->pluck('value', 'key')->toArray();
        $data['setting'] = Setting::whereIn('key', ['enable_custom_payment'])->pluck('value', 'key')->toArray();
        $data['workspaceId'] = $workspaceId;

        if($request->has('paymentId') && $request->has('token')){
            //Check if payment id exists in DB
            $payment = BillingPayment::where('details', $request->paymentId)->first();
            if(!$payment){
                $data['isPaymentLoading'] = true;
            } else {
                return redirect(self::BILLING_ROUTE)->with(
                    'status', [
                        'type' => 'success',
                        'message' => __('Payment processed successfully!')
                    ]
                );
            }
        } elseif($request->has('hostedpage')){
            if (file_exists(base_path('modules/Pabbly/Services/PabblyService.php'))) {
                $data['isPaymentLoading'] = true;

                $pabblyServiceClass = '\Modules\Pabbly\Services\PabblyService';
                /** @var object $pabblyService */
                $pabblyService = new $pabblyServiceClass();
                $response = $pabblyService->subscribeToPlan($request->hostedpage);
                $data = $response->getData();
                
                return redirect(self::BILLING_ROUTE)->with(
                    'status', [
                        'type' => $response->status() === '200' ? 'success' : 'error',
                        'message' => $data->message
                    ]
                );
            }
        }

        return Inertia::render('User/Billing/Index', $data);
    }

    public function pay(PaymentRequest $request){
        $paymentPlatform = $this->paymentPlatformResolver->resolveService($request->method);
        session()->put('paymentPlatform', $request->method);

        $response = $paymentPlatform->handlePayment($request->amount);

        if ($response->success === true) {
            return inertia::location($response->data);
        } else {
            return redirect(self::BILLING_ROUTE)->with(
                'status', [
                    'type' => 'error',
                    'message' => __('Could not process your payment successfully!')
                ]
            );
        }
    }

    private function paymentMethods(){
        $mergedData = [];

        // Retrieve active payment methods and add to mergedData
        $paymentMethods = PaymentGateway::where('is_active', 1)->get();
        $mergedData = $paymentMethods->map(function ($method) {
            return ['name' => $method->name];
        })->toArray();

        // Retrieve active addons and check settings
        $activeAddons = Addon::where('category', 'payments')
            ->where('status', 1)
            ->where('is_active', 1)
            ->get()
            ->pluck('name')
            ->toArray();

        // Add active addons to mergedData
        foreach ($activeAddons as $addonName) {
            $mergedData[] = ['name' => $addonName];
        }

        return $mergedData;
    }
}
