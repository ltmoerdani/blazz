<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller as BaseController;
use App\Models\BillingPayment;
use App\Models\BillingTransaction;
use App\Models\Subscription;
use App\Models\workspace;
use App\Models\Setting;
use App\Resolvers\PaymentPlatformResolver;
use App\Services\StripeService;
use App\Services\PayPalService;
use App\Services\FlutterwaveService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends BaseController
{
    protected $paymentPlatformResolver;

    public function __construct()
    {
        $this->paymentPlatformResolver = new PaymentPlatformResolver();
    }

    /**
     * Handle generic payment webhook
     */
    public function handle(Request $request, $platform = null, $identifier = null)
    {
        try {
            // Get workspace by identifier if provided
            $workspace = null;
            if ($identifier) {
                $workspace = workspace::where('identifier', $identifier)->first();
            }

            Log::info('Payment webhook received', [
                'platform' => $platform,
                'workspace_id' => $workspace?->id,
                'identifier' => $identifier,
                'content_length' => strlen($request->getContent()),
            ]);

            switch (strtolower($platform)) {
                case 'stripe':
                    return $this->handleStripeWebhook($request, $workspace);
                case 'paypal':
                    return $this->handlePayPalWebhook($request, $workspace);
                case 'flutterwave':
                    return $this->handleFlutterwaveWebhook($request, $workspace);
                default:
                    return $this->handleGenericWebhook($request, $platform, $workspace);
            }

        } catch (\Exception $e) {
            Log::error('Payment webhook processing failed', [
                'error' => $e->getMessage(),
                'platform' => $platform,
                'workspace_id' => $workspace?->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return Response::json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle Stripe webhooks
     */
    protected function handleStripeWebhook(Request $request, workspace $workspace = null)
    {
        try {
            $payload = $request->getContent();
            $sigHeader = $request->header('Stripe-Signature');

            $stripeService = app(StripeService::class);

            // Verify webhook signature
            $event = $stripeService->verifyWebhookSignature($payload, $sigHeader);

            if (!$event) {
                Log::warning('Invalid Stripe webhook signature');
                return Response::json(['error' => 'Invalid signature'], 400);
            }

            Log::info('Stripe webhook event', [
                'event_type' => $event->type,
                'event_id' => $event->id,
                'workspace_id' => $workspace?->id,
            ]);

            switch ($event->type) {
                case 'payment_intent.succeeded':
                    return $this->handlePaymentSuccess($event->data->object, 'stripe', $workspace);

                case 'payment_intent.payment_failed':
                    return $this->handlePaymentFailure($event->data->object, 'stripe', $workspace);

                case 'invoice.payment_succeeded':
                    return $this->handleSubscriptionPaymentSuccess($event->data->object, 'stripe', $workspace);

                case 'invoice.payment_failed':
                    return $this->handleSubscriptionPaymentFailure($event->data->object, 'stripe', $workspace);

                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                    return $this->handleSubscriptionUpdate($event->data->object, 'stripe', $workspace);

                case 'customer.subscription.deleted':
                    return $this->handleSubscriptionCancellation($event->data->object, 'stripe', $workspace);

                default:
                    Log::info('Unhandled Stripe event', [
                        'event_type' => $event->type,
                        'event_id' => $event->id,
                    ]);
                    return Response::json(['status' => 'received'], 200);
            }

        } catch (\Exception $e) {
            Log::error('Stripe webhook processing failed', [
                'error' => $e->getMessage(),
                'workspace_id' => $workspace?->id,
            ]);

            return Response::json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle PayPal webhooks
     */
    protected function handlePayPalWebhook(Request $request, workspace $workspace = null)
    {
        try {
            $payload = $request->getContent();
            $headers = $request->headers->all();

            $paypalService = app(PayPalService::class);

            // Verify webhook signature
            $verified = $paypalService->verifyWebhookSignature($payload, $headers);

            if (!$verified) {
                Log::warning('Invalid PayPal webhook signature');
                return Response::json(['error' => 'Invalid signature'], 400);
            }

            $eventType = $request->input('event_type');
            $resource = $request->input('resource');

            Log::info('PayPal webhook event', [
                'event_type' => $eventType,
                'resource_id' => $resource['id'] ?? null,
                'workspace_id' => $workspace?->id,
            ]);

            switch ($eventType) {
                case 'PAYMENT.SALE.COMPLETED':
                    return $this->handlePaymentSuccess($resource, 'paypal', $workspace);

                case 'PAYMENT.SALE.DENIED':
                case 'PAYMENT.SALE.FAILED':
                    return $this->handlePaymentFailure($resource, 'paypal', $workspace);

                case 'BILLING.SUBSCRIPTION.CREATED':
                case 'BILLING.SUBSCRIPTION.ACTIVATED':
                    return $this->handleSubscriptionUpdate($resource, 'paypal', $workspace);

                case 'BILLING.SUBSCRIPTION.CANCELLED':
                    return $this->handleSubscriptionCancellation($resource, 'paypal', $workspace);

                default:
                    Log::info('Unhandled PayPal event', [
                        'event_type' => $eventType,
                        'resource_id' => $resource['id'] ?? null,
                    ]);
                    return Response::json(['status' => 'received'], 200);
            }

        } catch (\Exception $e) {
            Log::error('PayPal webhook processing failed', [
                'error' => $e->getMessage(),
                'workspace_id' => $workspace?->id,
            ]);

            return Response::json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle Flutterwave webhooks
     */
    protected function handleFlutterwaveWebhook(Request $request, workspace $workspace = null)
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('verif-hash');

            $flutterwaveService = app(FlutterwaveService::class);

            // Verify webhook signature
            $verified = $flutterwaveService->verifyWebhookSignature($payload, $signature);

            if (!$verified) {
                Log::warning('Invalid Flutterwave webhook signature');
                return Response::json(['error' => 'Invalid signature'], 400);
            }

            $eventType = $request->input('event');
            $data = $request->input('data');

            Log::info('Flutterwave webhook event', [
                'event_type' => $eventType,
                'transaction_id' => $data['id'] ?? null,
                'workspace_id' => $workspace?->id,
            ]);

            switch ($eventType) {
                case 'charge.completed':
                    return $this->handlePaymentSuccess($data, 'flutterwave', $workspace);

                case 'charge.failed':
                    return $this->handlePaymentFailure($data, 'flutterwave', $workspace);

                default:
                    Log::info('Unhandled Flutterwave event', [
                        'event_type' => $eventType,
                        'transaction_id' => $data['id'] ?? null,
                    ]);
                    return Response::json(['status' => 'received'], 200);
            }

        } catch (\Exception $e) {
            Log::error('Flutterwave webhook processing failed', [
                'error' => $e->getMessage(),
                'workspace_id' => $workspace?->id,
            ]);

            return Response::json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle generic webhook for other payment platforms
     */
    protected function handleGenericWebhook(Request $request, $platform, workspace $workspace = null)
    {
        try {
            Log::info('Generic payment webhook', [
                'platform' => $platform,
                'workspace_id' => $workspace?->id,
                'payload' => $request->getContent(),
            ]);

            // Implement generic webhook handling logic
            // This would depend on your specific payment platform integrations

            return Response::json(['status' => 'received'], 200);

        } catch (\Exception $e) {
            Log::error('Generic webhook processing failed', [
                'error' => $e->getMessage(),
                'platform' => $platform,
                'workspace_id' => $workspace?->id,
            ]);

            return Response::json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle successful payment
     */
    protected function handlePaymentSuccess($paymentData, $platform, workspace $workspace = null)
    {
        try {
            DB::beginTransaction();

            $transactionId = $this->extractTransactionId($paymentData, $platform);
            $amount = $this->extractAmount($paymentData, $platform);
            $currency = $this->extractCurrency($paymentData, $platform);

            // Create or update payment record
            $payment = BillingPayment::updateOrCreate(
                ['transaction_id' => $transactionId, 'platform' => $platform],
                [
                    'workspace_id' => $workspace?->id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => 'completed',
                    'payment_data' => json_encode($paymentData),
                    'paid_at' => now(),
                ]
            );

            // Create transaction record
            BillingTransaction::create([
                'workspace_id' => $workspace?->id,
                'payment_id' => $payment->id,
                'type' => 'payment',
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'completed',
                'description' => "Payment via {$platform}",
                'transaction_data' => json_encode($paymentData),
            ]);

            // Update workspace balance/credits if applicable
            if ($workspace) {
                $this->updateWorkspaceBalance($workspace, $amount, $platform);
            }

            Log::info('Payment processed successfully', [
                'transaction_id' => $transactionId,
                'platform' => $platform,
                'amount' => $amount,
                'workspace_id' => $workspace?->id,
            ]);

            DB::commit();

            return Response::json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to process successful payment', [
                'error' => $e->getMessage(),
                'platform' => $platform,
                'workspace_id' => $workspace?->id,
            ]);

            return Response::json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle failed payment
     */
    protected function handlePaymentFailure($paymentData, $platform, workspace $workspace = null)
    {
        try {
            $transactionId = $this->extractTransactionId($paymentData, $platform);

            // Update payment record if it exists
            $payment = BillingPayment::where('transaction_id', $transactionId)
                ->where('platform', $platform)
                ->first();

            if ($payment) {
                $payment->update([
                    'status' => 'failed',
                    'payment_data' => json_encode($paymentData),
                    'failed_at' => now(),
                ]);

                // Create failed transaction record
                BillingTransaction::create([
                    'workspace_id' => $workspace?->id,
                    'payment_id' => $payment->id,
                    'type' => 'payment',
                    'amount' => $this->extractAmount($paymentData, $platform),
                    'currency' => $this->extractCurrency($paymentData, $platform),
                    'status' => 'failed',
                    'description' => "Failed payment via {$platform}",
                    'transaction_data' => json_encode($paymentData),
                ]);
            }

            Log::warning('Payment failed', [
                'transaction_id' => $transactionId,
                'platform' => $platform,
                'workspace_id' => $workspace?->id,
            ]);

            return Response::json(['status' => 'received'], 200);

        } catch (\Exception $e) {
            Log::error('Failed to process payment failure', [
                'error' => $e->getMessage(),
                'platform' => $platform,
                'workspace_id' => $workspace?->id,
            ]);

            return Response::json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle subscription payment success
     */
    protected function handleSubscriptionPaymentSuccess($invoiceData, $platform, workspace $workspace = null)
    {
        try {
            $subscriptionId = $this->extractSubscriptionId($invoiceData, $platform);

            if ($subscriptionId && $workspace) {
                $subscription = Subscription::where('subscription_id', $subscriptionId)
                    ->where('workspace_id', $workspace->id)
                    ->first();

                if ($subscription) {
                    $subscription->update([
                        'status' => 'active',
                        'last_payment_at' => now(),
                        'next_payment_at' => $this->calculateNextPaymentDate($invoiceData, $platform),
                    ]);

                    Log::info('Subscription payment successful', [
                        'subscription_id' => $subscriptionId,
                        'platform' => $platform,
                        'workspace_id' => $workspace->id,
                    ]);
                }
            }

            return Response::json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('Failed to process subscription payment', [
                'error' => $e->getMessage(),
                'platform' => $platform,
                'workspace_id' => $workspace?->id,
            ]);

            return Response::json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle subscription update
     */
    protected function handleSubscriptionUpdate($subscriptionData, $platform, workspace $workspace = null)
    {
        try {
            $subscriptionId = $this->extractSubscriptionId($subscriptionData, $platform);

            if ($subscriptionId && $workspace) {
                $subscription = Subscription::updateOrCreate(
                    ['subscription_id' => $subscriptionId, 'platform' => $platform],
                    [
                        'workspace_id' => $workspace->id,
                        'status' => $this->extractSubscriptionStatus($subscriptionData, $platform),
                        'plan_id' => $this->extractPlanId($subscriptionData, $platform),
                        'subscription_data' => json_encode($subscriptionData),
                    ]
                );

                Log::info('Subscription updated', [
                    'subscription_id' => $subscriptionId,
                    'platform' => $platform,
                    'status' => $subscription->status,
                    'workspace_id' => $workspace->id,
                ]);
            }

            return Response::json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('Failed to update subscription', [
                'error' => $e->getMessage(),
                'platform' => $platform,
                'workspace_id' => $workspace?->id,
            ]);

            return Response::json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle subscription cancellation
     */
    protected function handleSubscriptionCancellation($subscriptionData, $platform, workspace $workspace = null)
    {
        try {
            $subscriptionId = $this->extractSubscriptionId($subscriptionData, $platform);

            if ($subscriptionId && $workspace) {
                $subscription = Subscription::where('subscription_id', $subscriptionId)
                    ->where('platform', $platform)
                    ->where('workspace_id', $workspace->id)
                    ->first();

                if ($subscription) {
                    $subscription->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'subscription_data' => json_encode($subscriptionData),
                    ]);

                    Log::info('Subscription cancelled', [
                        'subscription_id' => $subscriptionId,
                        'platform' => $platform,
                        'workspace_id' => $workspace->id,
                    ]);
                }
            }

            return Response::json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription', [
                'error' => $e->getMessage(),
                'platform' => $platform,
                'workspace_id' => $workspace?->id,
            ]);

            return Response::json(['error' => 'Processing failed'], 500);
        }
    }

    // Helper methods for extracting data from different payment platforms
    protected function extractTransactionId($data, $platform) { /* Implementation */ }
    protected function extractAmount($data, $platform) { /* Implementation */ }
    protected function extractCurrency($data, $platform) { /* Implementation */ }
    protected function extractSubscriptionId($data, $platform) { /* Implementation */ }
    protected function extractSubscriptionStatus($data, $platform) { /* Implementation */ }
    protected function extractPlanId($data, $platform) { /* Implementation */ }
    protected function calculateNextPaymentDate($data, $platform) { /* Implementation */ }
    protected function updateWorkspaceBalance($workspace, $amount, $platform) { /* Implementation */ }
}