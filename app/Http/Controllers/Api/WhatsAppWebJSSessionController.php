<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace as WorkspaceModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class WhatsAppWebJSSessionController extends Controller
{
    private Client $httpClient;
    private string $nodeServiceUrl;

    public function __construct()
    {
        $this->nodeServiceUrl = rtrim(config('services.whatsapp_node.url', 'http://127.0.0.1:3000'), '/');
        $this->httpClient = new Client([
            'base_uri' => $this->nodeServiceUrl,
            'timeout' => (int) config('services.whatsapp_node.timeout', 30),
        ]);
    }

    /**
     * Create a new WhatsApp session
     */
    public function create(Request $request): JsonResponse
    {
        $workspaceId = session('current_workspace');
        if (!$workspaceId) {
            return response()->json(['error' => 'No workspace selected'], 400);
        }

        try {
            $timestamp = time();
            $payload = ['workspace_id' => $workspaceId];
            $jsonBody = json_encode($payload);
            $signature = hash_hmac('sha256', $jsonBody . $timestamp, config('services.whatsapp_node.hmac_secret'));

            $response = $this->httpClient->post('/api/sessions/create', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Workspace-ID' => (string) $workspaceId,
                    'X-API-Token' => config('services.whatsapp_node.api_token'),
                    'X-HMAC-Signature' => $signature,
                    'X-Timestamp' => (string) $timestamp,
                ],
                'body' => $jsonBody,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('WhatsApp session creation initiated', [
                'workspace_id' => $workspaceId,
                'session_id' => $result['session_id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (RequestException $e) {
            Log::error('Failed to create WhatsApp session', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disconnect WhatsApp session
     */
    public function disconnect(Request $request): JsonResponse
    {
        $workspaceId = session('current_workspace');
        if (!$workspaceId) {
            return response()->json(['error' => 'No workspace selected'], 400);
        }

        try {
            $timestamp = time();
            $payload = ['workspace_id' => $workspaceId];
            $jsonBody = json_encode($payload);
            $signature = hash_hmac('sha256', $jsonBody . $timestamp, config('services.whatsapp_node.hmac_secret'));

            $response = $this->httpClient->post('/api/sessions/disconnect', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Workspace-ID' => (string) $workspaceId,
                    'X-API-Token' => config('services.whatsapp_node.api_token'),
                    'X-HMAC-Signature' => $signature,
                    'X-Timestamp' => (string) $timestamp,
                ],
                'body' => $jsonBody,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('WhatsApp session disconnection initiated', [
                'workspace_id' => $workspaceId,
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (RequestException $e) {
            Log::error('Failed to disconnect WhatsApp session', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to disconnect session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh QR code by recreating session
     */
    public function refreshQr(Request $request): JsonResponse
    {
        $workspaceId = session('current_workspace');
        if (!$workspaceId) {
            return response()->json(['error' => 'No workspace selected'], 400);
        }

        try {
            $timestamp = time();
            $payload = ['workspace_id' => $workspaceId];
            $jsonBody = json_encode($payload);
            $signature = hash_hmac('sha256', $jsonBody . $timestamp, config('services.whatsapp_node.hmac_secret'));

            $response = $this->httpClient->post('/api/sessions/refresh-qr', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Workspace-ID' => (string) $workspaceId,
                    'X-API-Token' => config('services.whatsapp_node.api_token'),
                    'X-HMAC-Signature' => $signature,
                    'X-Timestamp' => (string) $timestamp,
                ],
                'body' => $jsonBody,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('WhatsApp QR refresh initiated', [
                'workspace_id' => $workspaceId,
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (RequestException $e) {
            Log::error('Failed to refresh WhatsApp QR', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to refresh QR: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current session status
     */
    public function status(Request $request, int $workspaceId = null): JsonResponse
    {
        $workspaceId = $workspaceId ?: session('current_workspace');
        if (!$workspaceId) {
            return response()->json(['error' => 'No workspace selected'], 400);
        }

        // Get current status from workspace metadata
        $workspace = WorkspaceModel::find($workspaceId);
        if (!$workspace) {
            return response()->json(['error' => 'Workspace not found'], 404);
        }

        $metadata = json_decode($workspace->metadata, true) ?: [];
        $whatsappMeta = $metadata['whatsapp'] ?? [];

        $status = $whatsappMeta['webjs_status'] ?? 'disconnected';
        $phoneNumber = $whatsappMeta['webjs_phone_number'] ?? null;
        $sessionId = $whatsappMeta['webjs_session_id'] ?? null;
        $connectedAt = $whatsappMeta['webjs_connected_at'] ?? null;

        return response()->json([
            'success' => true,
            'status' => $status,
            'phone_number' => $phoneNumber,
            'session_id' => $sessionId,
            'connected_at' => $connectedAt,
        ]);
    }
}
