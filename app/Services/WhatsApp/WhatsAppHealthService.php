<?php

namespace App\Services\WhatsApp;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppHealthService
{
    const BEARER_PREFIX = 'Bearer ';

    private $accessToken;
    private $apiVersion;
    private $phoneNumberId;
    private $workspaceId;
    private $wabaId;
    private $appId;

    public function __construct($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId)
    {
        $this->accessToken = $accessToken;
        $this->apiVersion = $apiVersion;
        $this->appId = $appId;
        $this->phoneNumberId = $phoneNumberId;
        $this->wabaId = $wabaId;
        $this->workspaceId = $workspaceId;
    }

    public function checkHealth()
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}?fields=health_status";
        $headers = $this->setHeaders();

        return $this->sendHttpRequest('GET', $url, null, $headers);
    }

    public function subscribeToWaba()
    {
        $responseObject = new \stdClass();

        try {
            $response = Http::withHeaders([
                'Authorization' => self::BEARER_PREFIX . $this->accessToken
            ])->post("https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/subscribed_apps")->throw()->json();

            $responseObject->success = true;
            $responseObject->data = new \stdClass();
            $responseObject->data = (object) $response;
        } catch (\Exception $e) {
            $responseObject->success = false;
            $responseObject->data = new \stdClass();
            $responseObject->data->error = new \stdClass();
            $responseObject->data->error->message = $e->getMessage();
        }

        return $responseObject;
    }

    public function getWabaSubscriptions()
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/subscribed_apps";
        $headers = $this->setHeaders();

        return $this->sendHttpRequest('GET', $url, null, $headers);
    }

    public function overrideCallbackUrl($callbackUrl, $verifyToken)
    {
        $responseObject = new \stdClass();

        try {
            $response = Http::withHeaders([
                'Authorization' => self::BEARER_PREFIX . $this->accessToken
            ])->post("https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/subscribed_apps", [
                'override_callback_uri' => $callbackUrl,
                'verify_token' => $verifyToken
            ])->throw()->json();

            $responseObject->success = true;
            $responseObject->data = new \stdClass();
            $responseObject->data = (object) $response;
        } catch (\Exception $e) {
            $responseObject->success = false;
            $responseObject->data = new \stdClass();
            $responseObject->data->error = new \stdClass();
            $responseObject->data->error->message = $e->getMessage();
        }

        return $responseObject;
    }

    public function unSubscribeToWaba()
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/subscribed_apps";
        $headers = $this->setHeaders();

        return $this->sendHttpRequest('DELETE', $url, null, $headers);
    }

    public function verifyWebhookSetup($webhookUrl, $verifyToken)
    {
        $responseObject = new \stdClass();

        try {
            // First, set the webhook
            $setWebhookResponse = $this->overrideCallbackUrl($webhookUrl, $verifyToken);

            if (!$setWebhookResponse->success) {
                $responseObject->success = false;
                $responseObject->error = 'Failed to set webhook URL';
                $responseObject->details = $setWebhookResponse;
                return $responseObject;
            }

            // Test webhook by making a test request
            $testPayload = [
                'object' => 'whatsapp_business_account',
                'entry' => [
                    [
                        'id' => $this->wabaId,
                        'changes' => [
                            [
                                'field' => 'messages',
                                'value' => [
                                    'messaging_product' => 'whatsapp',
                                    'metadata' => [
                                        'phone_number_id' => $this->phoneNumberId,
                                        'display_phone_number' => '+1234567890'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $testResponse = Http::asJson()->post($webhookUrl, $testPayload);

            if ($testResponse->successful()) {
                $responseObject->success = true;
                $responseObject->data = new \stdClass();
                $responseObject->data->message = 'Webhook verified successfully';
                $responseObject->data->webhook_url = $webhookUrl;
            } else {
                $responseObject->success = false;
                $responseObject->error = 'Webhook test failed';
                $responseObject->details = $testResponse->body();
            }

        } catch (\Exception $e) {
            $responseObject->success = false;
            $responseObject->error = 'Webhook verification failed';
            $responseObject->details = $e->getMessage();
        }

        return $responseObject;
    }

    public function getConnectionStatus()
    {
        $healthCheck = $this->checkHealth();
        $subscriptions = $this->getWabaSubscriptions();

        $responseObject = new \stdClass();
        $responseObject->success = true;
        $responseObject->data = new \stdClass();

        // Health status
        if ($healthCheck->success) {
            $responseObject->data->health_status = $healthCheck->data->health_status ?? 'unknown';
        } else {
            $responseObject->data->health_status = 'error';
            $responseObject->data->health_error = $healthCheck->error ?? 'Unknown health check error';
        }

        // Subscription status
        if ($subscriptions->success) {
            $responseObject->data->subscription_active = true;
            $responseObject->data->subscription_data = $subscriptions->data;
        } else {
            $responseObject->data->subscription_active = false;
            $responseObject->data->subscription_error = $subscriptions->error ?? 'Unknown subscription error';
        }

        // Overall status
        $responseObject->data->overall_status = (
            ($responseObject->data->health_status === 'connected' || $responseObject->data->health_status === 'healthy') &&
            $responseObject->data->subscription_active
        ) ? 'healthy' : 'unhealthy';

        return $responseObject;
    }

    public function getSystemMetrics()
    {
        $connectionStatus = $this->getConnectionStatus();
        $phoneStatus = $this->getPhoneNumberStatus();

        $responseObject = new \stdClass();
        $responseObject->success = true;
        $responseObject->data = new \stdClass();

        $responseObject->data->timestamp = now()->toISOString();
        $responseObject->data->workspace_id = $this->workspaceId;
        $responseObject->data->phone_number_id = $this->phoneNumberId;
        $responseObject->data->waba_id = $this->wabaId;
        $responseObject->data->connection_status = $connectionStatus->data->overall_status ?? 'unknown';
        $responseObject->data->health_status = $connectionStatus->data->health_status ?? 'unknown';
        $responseObject->data->subscription_active = $connectionStatus->data->subscription_active ?? false;

        if ($phoneStatus->success) {
            $responseObject->data->phone_quality_rating = $phoneStatus->data->quality_rating ?? 'unknown';
            $responseObject->data->phone_status = $phoneStatus->data->status ?? 'unknown';
            $responseObject->data->name_status = $phoneStatus->data->name_status ?? 'unknown';
        }

        return $responseObject;
    }

    public function generateHealthReport()
    {
        $metrics = $this->getSystemMetrics();
        $healthCheck = $this->checkHealth();
        $subscriptions = $this->getWabaSubscriptions();

        $report = [
            'generated_at' => now()->toISOString(),
            'workspace_id' => $this->workspaceId,
            'summary' => [
                'overall_status' => $metrics->data->overall_status ?? 'unknown',
                'health_status' => $metrics->data->health_status ?? 'unknown',
                'subscription_active' => $metrics->data->subscription_active ?? false,
            ],
            'details' => [
                'connection' => $metrics->data,
                'health_check' => $healthCheck->success ? $healthCheck->data : ['error' => $healthCheck->error],
                'subscriptions' => $subscriptions->success ? $subscriptions->data : ['error' => $subscriptions->error],
            ],
            'recommendations' => $this->generateRecommendations($metrics, $healthCheck, $subscriptions)
        ];

        $responseObject = new \stdClass();
        $responseObject->success = true;
        $responseObject->data = $report;

        return $responseObject;
    }

    private function generateRecommendations($metrics, $healthCheck, $subscriptions)
    {
        $recommendations = [];

        // Health recommendations
        if (!$healthCheck->success || ($healthCheck->data->health_status ?? 'unknown') !== 'connected') {
            $recommendations[] = [
                'type' => 'health',
                'priority' => 'high',
                'message' => 'WhatsApp API health check failed. Check your API credentials and connection.',
                'action' => 'Verify access token and phone number ID configuration.'
            ];
        }

        // Subscription recommendations
        if (!$subscriptions->success) {
            $recommendations[] = [
                'type' => 'subscription',
                'priority' => 'high',
                'message' => 'WABA subscription not active. Subscribe to receive webhook events.',
                'action' => 'Call subscribeToWaba() to activate webhook subscriptions.'
            ];
        }

        // Quality recommendations
        if (isset($metrics->data->phone_quality_rating) && $metrics->data->phone_quality_rating === 'LOW') {
            $recommendations[] = [
                'type' => 'quality',
                'priority' => 'medium',
                'message' => 'Phone number quality rating is LOW. This may affect message delivery.',
                'action' => 'Improve message quality, reduce spam complaints, and maintain good response rates.'
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'general',
                'priority' => 'info',
                'message' => 'All systems operational. No issues detected.',
                'action' => 'Continue monitoring regularly.'
            ];
        }

        return $recommendations;
    }

    private function getPhoneNumberStatus()
    {
        $responseObject = new \stdClass();

        try {
            $response = Http::withHeaders([
                'Authorization' => self::BEARER_PREFIX . $this->accessToken
            ])->get("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}")->throw()->json();

            if (isset($response['error'])) {
                $responseObject->success = false;
                $responseObject->error = $response['error']['message'];
            } else {
                $responseObject->success = true;
                $responseObject->data = new \stdClass();
                $responseObject->data->quality_rating = $response['quality_rating'] ?? null;
                $responseObject->data->status = $response['status'] ?? null;
                $responseObject->data->name_status = $response['name_status'] ?? null;
            }
        } catch (\Exception $e) {
            $responseObject->success = false;
            $responseObject->error = $e->getMessage();
        }

        return $responseObject;
    }

    private function setHeaders()
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ];
    }

    private function sendHttpRequest($method, $url, $data = [], $headers = [])
    {
        try {
            $defaultHeaders = $this->setHeaders();
            $finalHeaders = array_merge($defaultHeaders, $headers);

            $response = Http::withHeaders($finalHeaders)->asJson()->send($method, $url, $data);

            $responseObject = new \stdClass();
            $responseObject->success = $response->successful();
            $responseObject->status = $response->status();
            $responseObject->data = $response->json();

            if ($response->failed()) {
                $responseObject->error = $response->body();
                Log::error('Health Service API Error: ' . $response->body());
            }

            return $responseObject;
        } catch (ConnectException $e) {
            Log::error('Health Service API Connection Error: ' . $e->getMessage());
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Connection failed';
            return $responseObject;
        } catch (GuzzleException $e) {
            Log::error('Health Service API Error: ' . $e->getMessage());
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Request failed';
            return $responseObject;
        }
    }
}