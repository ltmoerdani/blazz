<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ValidateWhatsAppWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $hmacSecret = config('whatsapp.security.hmac_secret');

        if (empty($hmacSecret)) {
            Log::channel('whatsapp')->error('HMAC secret not configured', [
                'config_path' => 'whatsapp.security.hmac_secret'
            ]);

            return response()->json([
                'message' => 'Webhook security not configured'
            ], 500);
        }

        // Get signature from header
        $signature = $request->header('X-Webhook-Signature');

        if (empty($signature)) {
            Log::channel('whatsapp')->warning('Webhook signature missing', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'message' => 'Webhook signature required'
            ], 401);
        }

        // Get raw payload
        $payload = (string) $request->getContent();

        // Calculate expected signature
        $expectedSignature = hash_hmac('sha256', $payload, $hmacSecret);

        // Compare signatures (timing-safe)
        if (!hash_equals($expectedSignature, $signature)) {
            Log::channel('whatsapp')->warning('Invalid webhook signature', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'expected_signature' => substr($expectedSignature, 0, 10) . '...',
                'received_signature' => substr($signature, 0, 10) . '...',
            ]);

            return response()->json([
                'message' => 'Invalid webhook signature'
            ], 401);
        }

        // Signature valid, proceed
        Log::channel('whatsapp')->debug('Webhook signature validated', [
            'path' => $request->path(),
        ]);

        return $next($request);
    }
}
