<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\InstanceRouter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProxyController extends Controller
{
    protected $router;

    public function __construct(InstanceRouter $router)
    {
        $this->router = $router;
    }

    /**
     * Create a new WhatsApp session on the appropriate instance.
     */
    public function createSession(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'workspace_id' => 'required|integer',
        ]);

        $workspaceId = $request->workspace_id;
        $sessionId = $request->session_id;

        // 1. Determine target instance based on workspace sharding
        $instanceIndex = $this->router->getInstanceIndex($workspaceId);
        $targetInstanceUrl = $this->router->getInstanceUrl($instanceIndex);

        Log::info("Routing session creation for Workspace {$workspaceId} to Instance {$instanceIndex} ({$targetInstanceUrl})");

        try {
            // 2. Forward request to Node.js service
            // Node.js endpoint: POST /api/sessions (as per SessionController)
            $response = Http::timeout(60)->post("{$targetInstanceUrl}/api/sessions", [
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId,
                'account_id' => WhatsAppAccount::where('session_id', $sessionId)->first()?->id,
                'api_key' => config('whatsapp.node_api_key'),
            ]);

            // 3. Update database with instance assignment if successful
            if ($response->successful()) {
                $account = WhatsAppAccount::where('session_id', $sessionId)->first();
                
                if ($account) {
                    $account->assignToInstance($instanceIndex, $targetInstanceUrl);
                    Log::info("Assigned session {$sessionId} to Instance {$instanceIndex} in DB");
                } else {
                    Log::warning("Session {$sessionId} created on instance but account not found in DB");
                }
            }

            return response()->json($response->json(), $response->status());

        } catch (\Exception $e) {
            Log::error("Failed to proxy createSession: " . $e->getMessage(), [
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId,
                'target_instance' => $targetInstanceUrl,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to communicate with WhatsApp service'], 502);
        }
    }

    /**
     * Disconnect a session.
     */
    public function disconnect(Request $request, string $sessionId)
    {
        // 1. Find account to get assigned instance
        $account = WhatsAppAccount::where('session_id', $sessionId)->first();

        if (!$account) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        // Use assigned URL or fallback to router if not set (migration compatibility)
        $targetInstanceUrl = $account->assigned_instance_url 
            ?? $this->router->getInstanceForWorkspace($account->workspace_id);

        Log::info("Disconnecting session {$sessionId} on {$targetInstanceUrl}");

        try {
            // 2. Forward request to Node.js service
            $response = Http::timeout(30)->post("{$targetInstanceUrl}/sessions/{$sessionId}/logout");

            // 3. Update database status
            if ($response->successful() || $response->status() === 404) {
                // Even if 404 (already gone), we mark as disconnected in DB
                $account->markDisconnected(
                    $request->input('reason', 'user_initiated'),
                    $request->input('details', 'Disconnected via API')
                );
            }

            return response()->json($response->json(), $response->status());

        } catch (\Exception $e) {
            Log::error("Failed to proxy disconnect: " . $e->getMessage());
            return response()->json(['error' => 'Failed to communicate with WhatsApp service'], 502);
        }
    }

    /**
     * Get session status.
     */
    public function getStatus(string $sessionId)
    {
        $account = WhatsAppAccount::where('session_id', $sessionId)->first();

        if (!$account) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $targetInstanceUrl = $account->assigned_instance_url 
            ?? $this->router->getInstanceForWorkspace($account->workspace_id);

        try {
            $response = Http::timeout(5)->get("{$targetInstanceUrl}/sessions/{$sessionId}/status");
            return response()->json($response->json(), $response->status());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Service unreachable'], 502);
        }
    }
}
