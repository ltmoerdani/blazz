<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use App\Traits\TemplateTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class TemplateApiController extends Controller
{
    use TemplateTrait;

    // Constants for repeated string literals
    const VALIDATION_INTEGER_MIN_1 = 'integer|min:1';
    const VALIDATION_INTEGER_MIN_1_MAX_100 = 'integer|min:1|max:100';
    const VALIDATION_MAX_255 = 'max:255';
    const MSG_SUCCESS = 'Request processed successfully';
    const MSG_PROCESSING_ERROR = 'Request unable to be processed';
    const MSG_WHATSAPP_SETUP_REQUIRED = 'Please setup your whatsapp account!';

    /**
     * List all templates.
     *
     * @return \Illuminate\Http\Response
     */
    public function listTemplates(Request $request){
        $validator = Validator::make($request->all(), [
            'page' => self::VALIDATION_INTEGER_MIN_1,
            'per_page' => self::VALIDATION_INTEGER_MIN_1_MAX_100, // Adjust max per_page limit as needed
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        $templates = Template::where('workspace_id', $request->workspace)
            ->where('deleted_at', null)
            ->paginate($perPage, ['uuid', 'name', 'metadata', 'updated_at'], 'page', $page);

        return TemplateResource::collection($templates);
    }

    /**
     * Send template message
     */
    public function sendTemplateMessage(Request $request)
    {
        $workspace = $request->workspace;

        // Check if WhatsApp is connected
        if (!$this->isWhatsAppConnected($workspace->id)) {
            return response()->json([
                'message' => self::MSG_WHATSAPP_SETUP_REQUIRED
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'contact_uuid' => ['required', 'string'],
            'template_name' => ['required', 'string', 'max:255'],
            'language' => ['required', 'string', 'max:10'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $messageSendingService = $this->initializeMessageSendingService($workspace->id);

            $templateData = [
                'name' => $request->template_name,
                'language' => [
                    'code' => $request->language
                ],
                'components' => []
            ];

            // Add header component if variables provided
            if ($request->has('variables') && !empty($request->variables)) {
                $templateData['components'][] = [
                    'type' => 'header',
                    'parameters' => array_map(function($variable) {
                        return [
                            'type' => 'text',
                            'text' => $variable
                        ];
                    }, $request->variables)
                ];
            }

            $result = $messageSendingService->sendTemplateMessage(
                $request->contact_uuid,
                $templateData
            );

            if ($result && isset($result->success) && $result->success) {
                return response()->json([
                    'message' => self::MSG_SUCCESS,
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'message' => self::MSG_PROCESSING_ERROR,
                    'error' => $result->error ?? 'Unknown error'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => self::MSG_PROCESSING_ERROR,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if WhatsApp is connected
     */
    private function isWhatsAppConnected($workspaceId)
    {
        $workspace = \App\Models\Workspace::find($workspaceId);

        return $workspace &&
               $workspace->meta_phone_number_id &&
               $workspace->meta_token &&
               $workspace->meta_waba_id;
    }

    /**
     * Initialize Message Sending service
     */
    private function initializeMessageSendingService($workspaceId)
    {
        $workspace = \App\Models\Workspace::find($workspaceId);

        if (!$workspace) {
            throw new \Exception('Workspace not found');
        }

        return new \App\Services\WhatsApp\MessageSendingService(
            $workspace->meta_token,
            $workspace->meta_version,
            $workspace->meta_app_id,
            $workspace->meta_phone_number_id,
            $workspace->meta_waba_id,
            $workspace->id
        );
    }
}