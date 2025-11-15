<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $this->baseUrl = config('services.whatsapp.nodejs_url', 'http://localhost:3000');
        $this->apiKey = config('services.whatsapp.api_key');
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
        $endpoint = '/api/messages/send';
        $payload = [
            'workspace_id' => $workspaceId,
            'account_uuid' => $accountUuid,
            'contact_uuid' => $contactUuid,
            'message' => $message,
            'type' => $type,
            'options' => $options,
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
        $endpoint = '/api/messages/bulk';
        $payload = [
            'workspace_id' => $workspaceId,
            'account_uuid' => $accountUuid,
            'messages' => $messages,
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
        $endpoint = '/api/sessions/create';
        $payload = array_merge([
            'workspace_id' => $workspaceId,
            'account_uuid' => $accountUuid,
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
        $endpoint = '/api/sessions/status';
        $payload = [
            'workspace_id' => $workspaceId,
            'account_uuid' => $accountUuid,
        ];

        return $this->makeRequest('POST', $endpoint, $payload);
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
        $endpoint = '/api/sessions/disconnect';
        $payload = [
            'workspace_id' => $workspaceId,
            'account_uuid' => $accountUuid,
        ];

        return $this->makeRequest('POST', $endpoint, $payload);
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
        $endpoint = '/api/sessions/reconnect';
        $payload = [
            'workspace_id' => $workspaceId,
            'account_uuid' => $accountUuid,
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
        $endpoint = '/api/sessions/regenerate-qr';
        $payload = [
            'workspace_id' => $workspaceId,
            'account_uuid' => $accountUuid,
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
        $endpoint = '/api/sessions/qr';
        $payload = [
            'workspace_id' => $workspaceId,
            'account_uuid' => $accountUuid,
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
        $endpoint = '/api/health';

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
            'account_uuid' => $accountUuid,
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
            'account_uuid' => $accountUuid,
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

        if ($this->apiKey) {
            $headers['X-API-Key'] = $this->apiKey;
        }

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
     * Test connection to Node.js service
     *
     * @return array
     */
    public function testConnection()
    {
        try {
            $startTime = microtime(true);
            $response = $this->client->get('/api/ping');
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