<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyWhatsAppHmacSignature
{
    /**
     * Validate HMAC signature from Node.js WhatsApp service
     *
     * Security Features:
     * 1. HMAC-SHA256 signature validation
     * 2. Timestamp validation (±300 seconds)
     * 3. Replay attack prevention
     * 4. Request body integrity check
     */
    public function handle(Request $request, Closure $next)
    {
        $validationResult = $this->validateHeaders($request);
        if ($validationResult) {
            return $validationResult;
        }

        $timestampValidation = $this->validateTimestamp($request);
        if ($timestampValidation) {
            return $timestampValidation;
        }

        $signatureValidation = $this->validateSignature($request);
        if ($signatureValidation) {
            return $signatureValidation;
        }

        // Signature valid - attach workspace_id to request
        $workspaceId = $request->header('X-Workspace-ID');
        $request->attributes->add(['workspace_id' => (int) $workspaceId]);

        Log::info("Webhook authenticated successfully", [
            'workspace_id' => $workspaceId,
            'event' => $request->input('event'),
        ]);

        return $next($request);
    }

    private function validateHeaders(Request $request)
    {
        $signature = $request->header('X-HMAC-Signature');
        $timestamp = $request->header('X-Timestamp');
        $workspaceId = $request->header('X-Workspace-ID');

        if (!$signature || !$timestamp || !$workspaceId) {
            Log::warning("Webhook rejected: Missing required headers", [
                'ip' => $request->ip(),
                'headers' => $request->headers->all(),
            ]);
            return response()->json(['error' => 'Missing authentication headers'], 401);
        }

        return null;
    }

    private function validateTimestamp(Request $request)
    {
        $timestamp = $request->header('X-Timestamp');
        $currentTime = time();
        $timestampInt = (int) $timestamp;

        if (abs($currentTime - $timestampInt) > 300) { // 5 minutes tolerance
            Log::warning("Webhook rejected: Timestamp expired", [
                'timestamp' => $timestamp,
                'current_time' => $currentTime,
                'diff' => abs($currentTime - $timestampInt),
            ]);
            return response()->json(['error' => 'Request timestamp expired'], 401);
        }

        return null;
    }

    private function validateSignature(Request $request)
    {
        $signature = $request->header('X-HMAC-Signature');
        $timestamp = $request->header('X-Timestamp');
        $workspaceId = $request->header('X-Workspace-ID');

        $secret = config('services.whatsapp_node.hmac_secret');
        if (!$secret) {
            Log::error("HMAC secret not configured");
            return response()->json(['error' => 'Server misconfiguration'], 500);
        }

        $payload = $request->getContent() . $timestamp;
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        // Constant-time comparison to prevent timing attacks
        if (!hash_equals($expectedSignature, $signature)) {
            Log::error("Webhook rejected: Invalid HMAC signature", [
                'workspace_id' => $workspaceId,
                'ip' => $request->ip(),
                'expected_length' => strlen($expectedSignature),
                'received_length' => strlen($signature),
            ]);
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        return null;
    }
}
