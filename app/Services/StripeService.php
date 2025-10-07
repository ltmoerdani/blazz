<?php

namespace App\Services;

use Carbon\Carbon;
use Helper;
use App\Models\BillingPayment;
use App\Models\BillingTransaction;
use App\Models\workspace;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Traits\ConsumesExternalServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripeService
{
    protected $config;
    protected $subscriptionService;
    protected $stripe;

    public function __construct()
    {
        $this->subscriptionService = new SubscriptionService();

        $stripeInfo = PaymentGateway::where('name', 'Stripe')->first();
        $this->config = json_decode($stripeInfo->metadata);
        $this->stripe = new \Stripe\StripeClient($this->config->secret_key);
    }

    public function handlePayment($amount, $planId = null)
    {
        try {
            if($planId == null){
                $stripeSession = $this->stripe->checkout->sessions->create([
                    'line_items' => [[
                        'price_data' => [
                            'currency' => strtolower(Setting::where('key', 'currency')->first()->value),
                            'product_data' => [
                                'name' => 'Account Credits'
                            ],
                            'unit_amount' => $amount * 100
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'payment',
                    
                    'customer_email' => Auth::user()->email,
                    'metadata' => [
                        'workspace_id' => session()->get('current_workspace'),
                        'user_id' => Auth::id(),
                        'amount' => $amount,
                        'plan_id' => $planId
                    ],
                    'success_url' => url('billing'),
                    'cancel_url' => url('billing'),
                ]);
            } else {
                $plan = SubscriptionPlan::where('id', $planId)->first();
                $metadata = json_decode($plan->metadata, true);
                $priceId = $metadata['stripe']['price']['id'] ?? null;

                $stripeSession = $this->stripe->checkout->sessions->create([
                    'line_items' => [[
                        'price' => $priceId,
                        'quantity' => 1,
                    ]],
                    'mode' => 'subscription',
                    
                    'customer_email' => Auth::user()->email,
                    'metadata' => [
                        'workspace_id' => session()->get('current_workspace'),
                        'user_id' => Auth::id(),
                        'amount' => $amount,
                        'plan_id' => $planId
                    ],
                    'success_url' => url('billing'),
                    'cancel_url' => url('billing'),
                ]);
            }

            return (object) array('success' => true, 'data' => $stripeSession->url);
        } catch (\Exception $e) {
            return (object) array('success' => false, 'error' => $e->getMessage());
        }
    }

    public function createProduct($productData){
        try {
            $plan = SubscriptionPlan::where('id', $productData->id)->first();
            $metadata = json_decode($plan->metadata, true);

            $product = $this->stripe->products->create([
                'name' => $plan->name,
            ]);

            $currency = Setting::where('key', 'currency')->value('value') ?? 'usd';
            $interval = $plan->period == 'monthly' ? 'month' : 'year';

            $price = $this->stripe->prices->create([
                'currency' => $currency,
                'unit_amount' => $plan->price * 100,
                'recurring' => ['interval' => $interval],
                'product' => $product->id,
            ]);
            
            $metadata['stripe'] = [
                'product' => $product,
                'price' => $price,
            ];
            $plan->metadata = json_encode($metadata);
            $plan->save();

            return (object) array('success' => true);
        } catch (\Exception $e) {
            return (object) array('success' => false, 'error' => $e->getMessage());
        }
    }

    public function updateProduct($productData){
        try {
            // Retrieve the subscription plan and its metadata
            $plan = SubscriptionPlan::where('id', $productData->id)->first();
            $metadata = json_decode($plan->metadata, true);

            // Get currency and interval settings
            $currency = Setting::where('key', 'currency')->value('value') ?? 'usd';
            $interval = $plan->period == 'monthly' ? 'month' : 'year';

            // Retrieve product and price IDs from metadata
            $productId = $metadata['stripe']['product']['id'] ?? null;
            $priceId = $metadata['stripe']['price']['id'] ?? null;

            if($productId){
                // Update the product in Stripe
                $product = $this->stripe->products->update(
                    $productId,
                    [
                        'name' => $plan->name ?? null,
                    ]
                );
            } else {
                // Create a new product if it does not exist
                $product = $this->stripe->products->create([
                    'name' => $plan->name,
                ]);

                $productId = $product->id;
            }

            // Check if we need to update the existing price or create a new one
            if ($priceId) {
                // Update the existing price for the product
                $price = $this->stripe->prices->update(
                    $priceId,
                    [
                        'active' => false, //Deactivate price
                    ]
                );
            }

            // Create a new price if the price ID does not exist
            $price = $this->stripe->prices->create([
                'currency' => $currency,
                'unit_amount' => $plan->price * 100,  // Stripe expects amount in the smallest currency unit
                'recurring' => isset($interval) ? ['interval' => $interval] : null,
                'product' => $productId,
            ]);
            
            // Update the metadata with the new product and price information
            $metadata['stripe'] = [
                'product' => $product,
                'price' => $price ?? null, // Use null if price is not created or updated
            ];
            $plan->metadata = json_encode($metadata);
            $plan->save();

            return (object) array('success' => true);
        } catch (\Exception $e) {
            return (object) array('success' => false, 'error' => $e->getMessage());
        }
    }

    public function updateProductPrices(){
        $plans = SubscriptionPlan::whereNull('deleted_at')->get();

        foreach($plans as $plan){
            $metadata = json_decode($plan->metadata, true);

            // Get currency and interval settings
            $currency = Setting::where('key', 'currency')->value('value') ?? 'usd';
            $interval = $plan->period == 'monthly' ? 'month' : 'year';

            // Retrieve product and price IDs from metadata
            $productId = $metadata['stripe']['product']['id'] ?? null;
            $priceId = $metadata['stripe']['price']['id'] ?? null;

            try {
                if($productId){
                    // Update the product in Stripe
                    $product = $this->stripe->products->update(
                        $productId,
                        [
                            'name' => $plan->name ?? null,
                        ]
                    );
                } else {
                    // Create a new product if it does not exist
                    $product = $this->stripe->products->create([
                        'name' => $plan->name,
                    ]);

                    $productId = $product->id;
                }

                // Check if we need to update the existing price or create a new one
                if ($priceId) {
                    // Update the existing price for the product
                    $price = $this->stripe->prices->update(
                        $priceId,
                        [
                            'active' => false, //Deactivate price
                        ]
                    );
                }

                $isTaxInclusive = Setting::where('key', 'is_tax_inclusive')->first()->value === '1';

                if($isTaxInclusive){
                    $totalAmount = $plan->price * 100;  // Stripe expects amount in the smallest currency unit
                } else {
                    $totalTax = $this->calculateTaxRates($plan->price);
                    $totalAmount = round(($plan->price + $totalTax), 2) * 100;  // Stripe expects amount in the smallest currency unit
                }

                // Create a new price if the price ID does not exist
                $price = $this->stripe->prices->create([
                    'currency' => $currency,
                    'unit_amount' => $totalAmount,
                    'recurring' => isset($interval) ? ['interval' => $interval] : null,
                    'product' => $productId,
                ]);
                
                // Update the metadata with the new product and price information
                $metadata['stripe'] = [
                    'product' => $product,
                    'price' => $price ?? null, // Use null if price is not created or updated
                ];

                $plan->metadata = json_encode($metadata);
                $plan->save();
            } catch (\Exception $e) {
                Log::error('Error updating product prices: ' . $e->getMessage(), [
                    'plan_id' => $plan->id,
                ]);
            }
        }

        return (object) array('success' => true);
    }

    public function deleteProduct($productData){
        try {
            $plan = SubscriptionPlan::where('id', $productData->id)->first();
            $metadata = json_decode($plan->metadata, true);

            //Retrieve the product id
            $productId = $metadata['stripe']['product']['id'] ?? null;

            //Archive the product
            $this->stripe->products->update(
                $productId,
                [
                    'active' => false,
                ]
            );

            return (object) array('success' => true);
        } catch (\Exception $e) {
            return (object) array('success' => false, 'error' => $e->getMessage());
        }
    }

    public function handleSubscription($amount, $planId = null)
    {
        try{

            // Create the subscription.
            $stripeSession = $this->stripe->subscriptions->create([
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower(Setting::where('key', 'currency')->first()->value),
                        'product_data' => [
                            'name' => 'Subscription Payment'
                        ],
                        'unit_amount' => $amount * 100
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'customer_email' => Auth::user()->email,
                'metadata' => [
                    'workspace_id' => session()->get('current_workspace'),
                    'user_id' => Auth::id(),
                    'amount' => $amount,
                    'plan_id' => $planId
                ],
                'success_url' => url('billing'),
                'cancel_url' => url('billing'),
            ]);

            return (object) array('success' => true, 'data' => $stripeSession->url);
        } catch (\Exception $e) {
            return (object) array('success' => false, 'error' => $e->getMessage());
        }
    }

    public function handleWebhook(Request $request)
    {
        $stripeEvent = $this->validateWebhookEvent($request);
        
        if (!$stripeEvent) {
            return response()->json(['status' => 400, 'message' => 'Invalid webhook'], 400);
        }

        $response = null;

        if ($stripeEvent->type == 'checkout.session.completed') {
            $response = $this->handleCheckoutSessionCompleted($stripeEvent);
        } elseif ($stripeEvent->type == 'invoice.paid') {
            $response = $this->handleInvoicePaid($stripeEvent);
        }

        return $response ?? response()->json(['status' => 200], 200);
    }

    private function validateWebhookEvent(Request $request)
    {
        try {
            return \Stripe\Webhook::constructEvent(
                $request->getContent(),
                $request->server('HTTP_STRIPE_SIGNATURE'),
                $this->config->webhook_secret
            );
        } catch (\UnexpectedValueException | \Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Webhook validation failed: ' . $e->getMessage());
            return null;
        }
    }

    private function handleCheckoutSessionCompleted($stripeEvent)
    {
        $metadata = $stripeEvent->data->object->lines->data[0]->metadata
            ?? ($stripeEvent->data->object->metadata ?? null);

        if (!isset($metadata->Workspace_id)) {
            return response()->json(['status' => 200], 200);
        }

        DB::transaction(function () use ($stripeEvent, $metadata) {
            $this->updateWorkspaceStripeId($metadata->Workspace_id, $stripeEvent->data->object->customer);

            if ($stripeEvent->data->object->mode == 'subscription') {
                $this->handleSubscriptionMode($metadata);
            } else {
                $this->handlePaymentMode($stripeEvent, $metadata);
            }
        });

        return response()->json(['status' => 200], 200);
    }

    private function handleInvoicePaid($stripeEvent)
    {
        $customerId = $stripeEvent->data->object->customer;
        $workspace = workspace::whereJsonContains('metadata->stripe_id', $customerId)->first();

        if (!$workspace) {
            return response()->json(['status' => 404], 404);
        }

        DB::transaction(function () use ($stripeEvent, $workspace) {
            $subscription = Subscription::where('workspace_id', $workspace->id)->first();
            $amount = $stripeEvent->data->object->total / 100;

            $payment = $this->createPayment($workspace->id, $stripeEvent->data->object->id, $amount);
            $this->createTransaction($workspace->id, $payment->id, $amount, 0);
            $this->updateSubscriptionPlanIfNeeded($workspace->id, $subscription->plan_id, 0);
        });

        return response()->json(['status' => 200], 200);
    }

    private function updateWorkspaceStripeId($workspaceId, $stripeCustomerId)
    {
        $workspace = workspace::where('id', $workspaceId)->first();
        $orgMetadata = json_decode($workspace->metadata, true);
        $orgMetadata['stripe_id'] = $stripeCustomerId;
        $workspace->metadata = json_encode($orgMetadata);
        $workspace->save();
    }

    private function handleSubscriptionMode($metadata)
    {
        if ($metadata->plan_id != null) {
            Subscription::where('workspace_id', $metadata->Workspace_id)->update([
                'plan_id' => $metadata->plan_id
            ]);
        }
    }

    private function handlePaymentMode($stripeEvent, $metadata)
    {
        $payment = $this->createPayment(
            $metadata->Workspace_id,
            $stripeEvent->data->object->payment_intent,
            $metadata->amount
        );

        $this->createTransaction(
            $metadata->Workspace_id,
            $payment->id,
            $metadata->amount,
            $metadata->user_id
        );

        $this->updateSubscriptionPlanIfNeeded(
            $metadata->Workspace_id,
            $metadata->plan_id,
            $metadata->user_id
        );
    }

    private function createPayment($workspaceId, $details, $amount)
    {
        return BillingPayment::create([
            'workspace_id' => $workspaceId,
            'processor' => 'stripe',
            'details' => $details,
            'amount' => $amount
        ]);
    }

    private function createTransaction($workspaceId, $paymentId, $amount, $userId)
    {
        return BillingTransaction::create([
            'workspace_id' => $workspaceId,
            'entity_type' => 'payment',
            'entity_id' => $paymentId,
            'description' => 'Stripe Payment',
            'amount' => $amount,
            'created_by' => $userId,
        ]);
    }

    private function updateSubscriptionPlanIfNeeded($workspaceId, $planId, $userId)
    {
        if ($planId == null) {
            $this->subscriptionService->activateSubscriptionIfInactiveAndExpiredWithCredits($workspaceId, $userId);
        } else {
            $this->subscriptionService->updateSubscriptionPlan($workspaceId, $planId, $userId);
        }
    }

    private static function calculateTaxRates($grossAmount)
    {
        $activeTaxRates = TaxRate::where('status', 'active')->whereNull('deleted_at')->get();
        $taxRatesDetails = [];
        $totalTaxAmount = 0;

        foreach($activeTaxRates as $taxRate){
            // Safe calculation: dividing by 100 (constant) is always safe
            $taxAmount = $taxRate->percentage * $grossAmount / 100;
            $taxRatesDetails[] = array(
                'name' => $taxRate->name,
                'percentage' => $taxRate->percentage,
                'amount' => number_format($taxAmount, 2),
            );
            $totalTaxAmount += $taxAmount;
        }

        return $totalTaxAmount;
    }
}
