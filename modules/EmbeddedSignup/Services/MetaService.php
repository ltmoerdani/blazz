<?php

namespace Modules\EmbeddedSignup\Services;

use App\Models\workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

class MetaService
{
    public function overrideWabaCallbackUrl($workspaceId)
    {
        $config = workspace::findOrFail($workspaceId)->metadata;
        $config = $config ? json_decode($config, true) : [];
        $accessToken = $config['whatsapp']['access_token'] ?? null;
        $wabaId = $config['whatsapp']['waba_id'] ?? null;

        $workspaceConfig = workspace::where('id', $workspaceId)->first();
        $callbackUrl = URL::to('/') . '/webhook/whatsapp/' . $workspaceConfig->identifier;
        $verifyToken = $workspaceConfig->identifier;

        $responseObject = new \stdClass();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken
            ])->post("https://graph.facebook.com/v20.0/{$wabaId}/subscribed_apps", [
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
}
