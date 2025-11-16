<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Contact;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

/**
 * WhatsApp Service Client
 *
 * Handles HTTP communication between Laravel and Node.js WhatsApp service
 * Provides methods for session management, messaging, and status operations
 *
 * TASK-SERVICE-4: HTTP client for Node.js WhatsApp service communication
 */
class WhatsAppServiceClient
{
    protected $baseUrl;
    protected $apiKey;
    protected $hmacSecret;
    protected $client;
    protected $logger;
    protected $timeout;
    protected $retryAttempts;
    protected $retryDelay;

    public function __construct()
    {
        $this->baseUrl = config('services.whatsapp.nodejs_url', 'http://127.0.0.1:3001');
        $this->apiKey = config('services.whatsapp.api_key', env('LARAVEL_API_TOKEN'));
        $this->hmacSecret = config('services.whatsapp.hmac_secret');
        $this->timeout = config('services.whatsapp.timeout', 30);
        $this->retryAttempts = config('services.whatsapp.retry_attempts', 3);
        $this->retryDelay = config('services.whatsapp.retry_delay', 1000);

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'connect_timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'Laravel-WhatsApp-Service/1.0',
            ],
        ]);

        $this->logger = Log::channel('whatsapp');
    }

    /**
     * Send message via Node.js service
     *
     * @param int $workspaceId
     * @param string $accountUuid
     * @param string $contactUuid
     * @param string $message
     * @param string $type
     * @param array $options
     * @return array
     */
    public function sendMessage($workspaceId, $accountUuid, $contactUuid, $message, $type = 'text', $options = [])
    {
        // Use correct endpoint based on message type
        $endpoint = ($type === 'text') ? '/api/messages/send' : '/api/messages/send-media';

        // Convert contact UUID to actual phone number
        $contactPhone = $this->getContactPhone($contactUuid, $workspaceId);
        
        // CRITICAL FIX: Get actual session_id from WhatsAppAccount (not UUID)
        $account = \App\Models\WhatsAppAccount::where('uuid', $accountUuid)
            ->where('workspace_id', $workspaceId)
            ->first();
        
        if (!$account || !$account->session_id) {
            throw new \Exception('WhatsApp account not found or session_id missing');
        }
        
        $sessionId = $account->session_id;  // e.g., webjs_1_1763300356_ot6RUaMF

        // Build correct payload for Node.js service
        if ($type === 'text') {
            // Text message payload
            $payload = [
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId,  // FIXED: Use actual session_id from database
                'recipient_phone' => $contactPhone,  // Fixed: contact_uuid -> recipient_phone
                'message' => $message,
                'type' => $type,
                'api_key' => config('services.whatsapp.api_key', env('LARAVEL_API_TOKEN')),  // Added authentication
            ];
        } else {
            // Media message payload
            $payload = [
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId,  // FIXED: Use actual session_id from database
                'recipient_phone' => $contactPhone,  // Fixed: contact_uuid -> recipient_phone
                'media_url' => $options['media_url'] ?? null,
                'caption' => $message,  // For media, message is the caption
                'filename' => $options['file_name'] ?? 'media',
                'api_key' => config('services.whatsapp.api_key', env('LARAVEL_API_TOKEN')),  // Added authentication
            ];
        }

        return $this->makeRequest('POST', $endpoint, $payload);
    }

    /**
     * Send template message
     *
     * @param int $workspaceId
     * @param string $accountUuid
     * @param string $contactUuid
     * @param array $templateData
     * @param array $options
     * @return array
     */
    public function sendTemplateMessage($workspaceId, $accountUuid, $contactUuid, $templateData, $options = [])
    {
        $endpoint = '/api/messages/send-template';

        // Convert contact UUID to actual phone number
        $contactPhone = $this->getContactPhone($contactUuid, $workspaceId);

        // Build correct payload for Node.js service
        $payload = [
            'workspace_id' => $workspaceId,
            'session_id' => $accountUuid,  // Fixed: account_uuid -> session_id
            'recipient_phone' => $contactPhone,  // Fixed: contact_uuid -> recipient_phone
            'template_name' => $templateData['name'] ?? 'unknown',
            'template_data' => $templateData,
            'api_key' => config('services.whatsapp.api_key', env('LARAVEL_API_TOKEN')),  // Added authentication
        ];

        return $this->makeRequest('POST', $endpoint, $payload);
    }

    /**
     * Send bulk messages
     *
     * @param int $workspaceId
     * @param string $accountUuid
     * @param array $messages
     * @return array
     */
    public function sendBulkMessages($workspaceId, $accountUuid, $messages)
    {
        $endpoint = '/api/messages/bulk-send';

        // Convert contact UUIDs to phone numbers and prepare recipients list
        $recipients = [];
        foreach ($messages as $messageData) {
            $contactPhone = $this->getContactPhone($messageData['contact_uuid'], $workspaceId);
            if ($contactPhone) {
                $recipients[] = $contactPhone;
            }
        }

        if (empty($recipients)) {
            return [
                'success' => false,
                'error' => 'No valid recipients found'
            ];
        }

        // Get message content from first message (assuming all are the same)
        $messageText = $messages[0]['message'] ?? '';
        $messageType = $messages[0]['type'] ?? 'text';

        // Build correct payload for Node.js service
        $payload = [
            'workspace_id' => $workspaceId,
            'session_id' => $accountUuid,  // Fixed: account_uuid -> session_id
            'recipients' => $recipients,  // Fixed: contact_uuid -> recipient_phone
            'message' => $messageText,
            'type' => $messageType,
            'api_key' => config('services.whatsapp.api_key', env('LARAVEL_API_TOKEN')),  // Added authentication
        ];

        return $this->makeRequest('POST', $endpoint, $payload);
    }

    /**
     * Create new WhatsApp session
     *
     * @param int $workspaceId
     * @param string $accountUuid
     * @param array $config
     * @return array
     */
    public function createSession($workspaceId, $accountUuid, $config = [])
    {
        $endpoint = '/api/sessions';
        $payload = array_merge([
            'workspace_id' => $workspaceId,
            'session_id' => $accountUuid,  // Fixed: account_uuid -> session_id
            'api_key' => config('services.whatsapp.api_key', env('LARAVEL_API_TOKEN')),  // Added authentication
        ], $config);

        return $this->makeRequest('POST', $endpoint, $payload);
    }

    /**
     * Get session status
     *
     * @param int $workspaceId
     * @param string $accountUuid
     * @return array
     */
    public function getSessionStatus($workspaceId, $accountUuid)
    {
        $endpoint = "/api/sessions/{$accountUuid}/status";  // RESTful endpoint
        $params = [
            'workspace_id' => $workspaceId,
            'api_key' => config('services.whatsapp.api_key', env('LARAVEL_API_TOKEN')),  // Added authentication
        ];

        return $this->makeRequest('GET', $endpoint, $params);
    }

    /**
     * Disconnect WhatsApp session
     *
     * @param int $workspaceId
     * @param string $accountUuid
     * @return array
     */
    public function disconnectSession($workspaceId, $accountUuid)
    {
        $endpoint = "/api/sessions/{$accountUuid}";  // RESTful DELETE endpoint
        $payload = [
            'workspace_id' => $workspaceId,
            'api_key' => config('services.whatsapp.api_key', env('LARAVEL_API_TOKEN')),  // Added authentication
        ];

        return $this->makeRequest('DELETE', $endpoint, $payload);
    }

    /**
     * Reconnect WhatsApp session
     *
     * @param int $workspaceId
     * @param string $accountUuid
     * @return array
     */
    public function reconnectSession($workspaceId, $accountUuid)
    {
        $endpoint = "/api/sessions/{$accountUuid}/reconnect";  // RESTful endpoint
        $payload = [
            'workspace_id' => $workspaceId,
            'api_key' => config('services.whatsapp.api_key', env('LARAVEL_API_TOKEN')),  // Added authentication
        ];

        return $this->makeRequest('POST', $endpoint, $payload);
    }

    /**
     * Regenerate QR code for session
     *
     * @param int $workspaceId
     * @param string $accountUuid
     * @return array
     */
    public function regenerateQR($workspaceId, $accountUuid)
    {
        $endpoint = "/api/sessions/{$accountUuid}/regenerate-qr";  // RESTful endpoint
        $payload = [
            'workspace_id' => $workspaceId,
            'api_key' => config('services.whatsapp.api_key', env('LARAVEL_API_TOKEN')),  // Added authentication
        ];

        return $this->makeRequest('POST', $endpoint, $payload);
    }

    /**
     * Get QR code for session
     *
     * @param int $workspaceId
     * @param string $accountUuid
     * @return array
     */
    public function getQRCode($workspaceId, $accountUuid)
    {
        $endpoint = "/api/sessions/{$accountUuid}/qr";  // RESTful endpoint
        $payload = [
            'workspace_id' => $workspaceId,
            'api_key' => config('services.whatsapp.api_key', env('LARAVEL_API_TOKEN')),  // Added authentication
        ];

        return $this->makeRequest('POST', $endpoint, $payload);
    }

    /**
     * Perform health check on Node.js service
     *
     * @return array
     */
    public function healthCheck()
    {
        $endpoint = '/health';

        try {
            $response = $this->client->get($endpoint);
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'status' => $data['status'] ?? 'unknown',
                'data' => $data,
                'response_time' => $this->getResponseTime($response),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 'unhealthy',
            ];
        }
    }

    /**
     * Get detailed health information
     *
     * @return array
     */
    public function getHealthDetails()
    {
        $endpoint = '/api/health/detailed';

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Check service readiness
     *
     * @return array
     */
    public function readinessCheck()
    {
        $endpoint = '/api/health/ready';

        try {
            $response = $this->client->get($endpoint);
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => $response->getStatusCode() === 200,
                'ready' => $data['ready'] ?? false,
                'data' => $data,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'ready' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get service metrics and statistics
     *
     * @return array
     */
    public function getMetrics()
    {
        $endpoint = '/api/metrics';

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Trigger chat/contact sync for account
     *
     * @param int $workspaceId
     * @param string $accountUuid
     * @param array $options
     * @return array
     */
    public function triggerSync($workspaceId, $accountUuid, $options = [])
    {
        $endpoint = '/api/sync/trigger';
        $payload = array_merge([
            'workspace_id' => $workspaceId,
            'session_id' => $accountUuid,  // Fixed: account_uuid -> session_id
            'api_key' => config('services.whatsapp.api_key', env('LARAVEL_API_TOKEN')),  // Added authentication
        ], $options);

        return $this->makeRequest('POST', $endpoint, $payload);
    }

    /**
     * Get sync status for account
     *
     * @param int $workspaceId
     * @param string $accountUuid
     * @return array
     */
    public function getSyncStatus($workspaceId, $accountUuid)
    {
        $endpoint = '/api/sync/status';
        $payload = [
            'workspace_id' => $workspaceId,
            'session_id' => $accountUuid,  // Fixed: account_uuid -> session_id
            'api_key' => config('services.whatsapp.api_key', env('LARAVEL_API_TOKEN')),  // Added authentication
        ];

        return $this->makeRequest('POST', $endpoint, $payload);
    }

    /**
     * Make HTTP request to Node.js service
     *
     * @param string $method
     * @param string $endpoint
     * @param array $payload
     * @return array
     */
    protected function makeRequest($method, $endpoint, $payload = [])
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retryAttempts) {
            try {
                $options = [];

                // Add authentication headers
                $options['headers'] = $this->getAuthHeaders();

                // Add payload for POST/PUT requests
                if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH']) && !empty($payload)) {
                    $options['json'] = $payload;
                }

                // Add query parameters for GET requests
                if (strtoupper($method) === 'GET' && !empty($payload)) {
                    $queryString = http_build_query($payload);
                    $endpoint .= '?' . $queryString;
                }

                $this->logger->debug('WhatsApp service request', [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'payload' => $payload,
                    'attempt' => $attempt + 1,
                ]);

                $response = $this->client->request($method, $endpoint, $options);
                $data = json_decode($response->getBody()->getContents(), true);

                $this->logger->debug('WhatsApp service response', [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'status_code' => $response->getStatusCode(),
                    'success' => $data['success'] ?? false,
                ]);

                return [
                    'success' => true,
                    'data' => $data,
                    'status_code' => $response->getStatusCode(),
                    'response_time' => $this->getResponseTime($response),
                ];

            } catch (ConnectException $e) {
                $lastException = $e;
                $this->logger->warning('WhatsApp service connection failed', [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);

            } catch (RequestException $e) {
                $lastException = $e;
                $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;

                // Don't retry for client errors (4xx)
                if ($statusCode >= 400 && $statusCode < 500) {
                    $this->logger->error('WhatsApp service client error', [
                        'method' => $method,
                        'endpoint' => $endpoint,
                        'status_code' => $statusCode,
                        'error' => $e->getMessage(),
                    ]);

                    return [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'status_code' => $statusCode,
                    ];
                }

                $this->logger->warning('WhatsApp service server error', [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt + 1,
                    'status_code' => $statusCode,
                    'error' => $e->getMessage(),
                ]);

            } catch (\Exception $e) {
                $lastException = $e;
                $this->logger->error('WhatsApp service unexpected error', [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);
            }

            $attempt++;

            // Wait before retry (except on last attempt)
            if ($attempt < $this->retryAttempts) {
                usleep($this->retryDelay * 1000); // Convert to microseconds
            }
        }

        // All attempts failed
        return [
            'success' => false,
            'error' => $lastException ? $lastException->getMessage() : 'Unknown error',
            'attempts' => $attempt,
        ];
    }

    /**
     * Get authentication headers for requests
     *
     * @return array
     */
    protected function getAuthHeaders()
    {
        $headers = [];

        // Note: API key is sent in payload, not headers, based on Node.js service expectations
        // if ($this->apiKey) {
        //     $headers['X-API-Key'] = $this->apiKey;
        // }

        if ($this->hmacSecret) {
            $timestamp = time();
            $signature = hash_hmac('sha256', $timestamp, $this->hmacSecret);
            $headers['X-Timestamp'] = $timestamp;
            $headers['X-Signature'] = $signature;
        }

        return $headers;
    }

    /**
     * Get response time from response
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return float|null
     */
    protected function getResponseTime($response)
    {
        // Try to get response time from header if available
        $serverTiming = $response->getHeaderLine('Server-Timing');
        if ($serverTiming && preg_match('/total=([\d.]+)/', $serverTiming, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    /**
     * Get contact phone number by UUID
     *
     * @param string $contactUuid
     * @param int $workspaceId
     * @return string|null
     */
    protected function getContactPhone($contactUuid, $workspaceId)
    {
        try {
            $contact = Contact::where('uuid', $contactUuid)
                ->where('workspace_id', $workspaceId)
                ->first();

            if (!$contact) {
                $this->logger->error('Contact not found for phone conversion', [
                    'contact_uuid' => $contactUuid,
                    'workspace_id' => $workspaceId
                ]);
                return null;
            }

            // CRITICAL FIX: Strip '+' prefix for WhatsApp Web.js compatibility
            // WhatsApp Web.js expects: 628xxx, not +628xxx
            $phone = $contact->phone;
            $phone = ltrim($phone, '+');  // Remove leading '+' if present
            
            return $phone;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get contact phone', [
                'contact_uuid' => $contactUuid,
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Test connection to Node.js service
     *
     * @return array
     */
    public function testConnection()
    {
        try {
            $startTime = microtime(true);
            $response = $this->client->get('/health');
            $endTime = microtime(true);

            $responseTime = round(($endTime - $startTime) * 1000, 2);

            return [
                'success' => true,
                'response_time_ms' => $responseTime,
                'status_code' => $response->getStatusCode(),
                'message' => 'Connection successful',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Connection failed',
            ];
        }
    }

    /**
     * Get service version information
     *
     * @return array
     */
    public function getVersion()
    {
        $endpoint = '/api/version';

        try {
            $response = $this->client->get($endpoint);
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'data' => $data,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}