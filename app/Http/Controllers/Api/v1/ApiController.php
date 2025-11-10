<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\v1\ContactApiController;
use App\Http\Controllers\Api\v1\WhatsAppApiController;
use App\Http\Controllers\Api\v1\TemplateApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @deprecated Use ContactApiController for contact operations
 * @deprecated Use WhatsAppApiController for WhatsApp operations
 * @deprecated Use TemplateApiController for template operations
 */
class ApiController extends Controller
{
    private ContactApiController $contactController;
    private WhatsAppApiController $whatsappController;
    private TemplateApiController $templateController;

    public function __construct()
    {
        $this->contactController = new ContactApiController();
        $this->whatsappController = new WhatsAppApiController();
        $this->templateController = new TemplateApiController();
    }

    /**
     * Display a listing of contacts.
     * @deprecated Use ContactApiController::listContacts() instead
     */
    public function listContacts(Request $request){
        return $this->contactController->listContacts($request);
    }

    /**
     * Store a newly created contact in storage.
     * @deprecated Use ContactApiController::storeContact() instead
     */
    public function storeContact(Request $request, $uuid = null){
        return $this->contactController->storeContact($request, $uuid);
    }

    /**
     * Remove the specified contact from storage.
     * @deprecated Use ContactApiController::destroyContact() instead
     */
    public function destroyContact(Request $request, $uuid){
        return $this->contactController->destroyContact($request, $uuid);
    }

    /**
     * Send message via WhatsApp
     * @deprecated Use WhatsAppApiController::sendMessage() instead
     */
    public function sendMessage(Request $request){
        return $this->whatsappController->sendMessage($request);
    }

    /**
     * Send template message via WhatsApp
     * @deprecated Use TemplateApiController::sendTemplateMessage() instead
     */
    public function sendTemplateMessage(Request $request){
        return $this->templateController->sendTemplateMessage($request);
    }

    /**
     * Send media message via WhatsApp
     * @deprecated Use WhatsAppApiController::sendMediaMessage() instead
     */
    public function sendMediaMessage(Request $request){
        return $this->whatsappController->sendMediaMessage($request);
    }

    /**
     * List all templates.
     * @deprecated Use TemplateApiController::listTemplates() instead
     */
    public function listTemplates(Request $request){
        return $this->templateController->listTemplates($request);
    }

    /**
     * Verify if the API key is active.
     */
    public function verifyApiKey(Request $request)
    {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return response()->json([
                'statusCode' => 401,
                'message' => __('No API key provided. Please include it in the Authorization header as a Bearer token.')
            ], 401);
        }

        try {
            $token = DB::table('workspace_api_keys')
                ->where('token', $bearerToken)
                ->whereNull('deleted_at')
                ->first();

            if (!$token) {
                return response()->json([
                    'statusCode' => 401,
                    'message' => __('Invalid API key provided.')
                ], 401);
            }

            $workspace = \App\Models\Workspace::find($token->workspace_id);

            if (!$workspace) {
                return response()->json([
                    'statusCode' => 401,
                    'message' => __('Associated workspace not found.')
                ], 401);
            }

            if (!\App\Services\SubscriptionService::isSubscriptionActive($workspace->id)) {
                return response()->json([
                    'statusCode' => 403,
                    'message' => __('API key is inactive. Please renew or subscribe to a plan to continue!')
                ], 403);
            }

            return response()->json([
                'statusCode' => 200,
                'message' => __('API key is valid and active.'),
                'workspace' => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'statusCode' => 500,
                'message' => __('An error occurred while verifying the API key.')
            ], 500);
        }
    }
}