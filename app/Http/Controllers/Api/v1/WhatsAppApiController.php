<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\SubscriptionService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Propaganistas\LaravelPhone\PhoneNumber;

class WhatsAppApiController extends Controller
{
    // Constants for repeated string literals
    const VALIDATION_MAX_255 = 'max:255';
    const VALIDATION_PHONE_AUTO = 'phone:AUTO';
    const MSG_INVALID_PROVIDED_DATA = 'The provided data is invalid.';
    const MSG_SUBSCRIPTION_REQUIRED = 'Please renew or subscribe to a plan to continue!';
    const MSG_SUCCESS = 'Request processed successfully';
    const MSG_PROCESSING_ERROR = 'Request unable to be processed';
    const MSG_WHATSAPP_SETUP_REQUIRED = 'Please setup your whatsapp account!';

    /**
     * Send message via WhatsApp
     */
    public function sendMessage(Request $request){
        $rules = [
            'phone' => ['required', 'string', self::VALIDATION_MAX_255, self::VALIDATION_PHONE_AUTO],
            'message' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'statusCode' => 400,
                'message' => __(self::MSG_INVALID_PROVIDED_DATA),
                'errors' => $validator->errors()
            ], 400);
        }

        if(!SubscriptionService::isSubscriptionActive($request->workspace)){
            return response()->json([
                'statusCode' => 403,
                'message' => __(self::MSG_SUBSCRIPTION_REQUIRED),
            ], 403);
        }

        //Check if the whatsapp connection exists
        if(!$this->isWhatsAppConnected($request->workspace)){
            return response()->json([
                'statusCode' => 403,
                'message' => __(self::MSG_WHATSAPP_SETUP_REQUIRED),
            ], 403);
        }

        // Check if the contact exists, if not, create a new one
        $phone = $request->phone;

        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }

        $phone = new PhoneNumber($phone);
        $phone = $phone->formatE164();

        $contact = Contact::where('workspace_id', $request->workspace)->where('phone', $phone)->first();

        if(!$contact){
            $contact = new Contact();
            $contact->Workspace_id = $request->workspace;
            $contact->first_name = $request->first_name ?? $phone;
            $contact->phone = $phone;
            $contact->save();
        }

        try {
            $whatsappService = $this->initializeWhatsappService($request->workspace);

            $result = $whatsappService->messageSending()->sendMessage($contact->uuid, $request->message);

            if ($result && isset($result->success) && $result->success) {
                return response()->json([
                    'statusCode' => 200,
                    'message' => self::MSG_SUCCESS,
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'statusCode' => 500,
                    'message' => self::MSG_PROCESSING_ERROR,
                    'error' => $result->error ?? 'Unknown error'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'statusCode' => 500,
                'message' => self::MSG_PROCESSING_ERROR,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send media message via WhatsApp
     */
    public function sendMediaMessage(Request $request){
        $rules = [
            'phone' => ['required', 'string', self::VALIDATION_MAX_255, self::VALIDATION_PHONE_AUTO],
            'media' => 'required|file|mimes:jpg,jpeg,png,gif,mp4,mp3,pdf,doc,docx,xls,xlsx,ppt,pptx|max:10240', // 10MB max
            'caption' => 'nullable|string|max:1024',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'statusCode' => 400,
                'message' => __(self::MSG_INVALID_PROVIDED_DATA),
                'errors' => $validator->errors()
            ], 400);
        }

        if(!SubscriptionService::isSubscriptionActive($request->workspace)){
            return response()->json([
                'statusCode' => 403,
                'message' => __(self::MSG_SUBSCRIPTION_REQUIRED),
            ], 403);
        }

        //Check if the whatsapp connection exists
        if(!$this->isWhatsAppConnected($request->workspace)){
            return response()->json([
                'statusCode' => 403,
                'message' => __(self::MSG_WHATSAPP_SETUP_REQUIRED),
            ], 403);
        }

        // Check if the contact exists, if not, create a new one
        $phone = $request->phone;

        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }

        $phone = new PhoneNumber($phone);
        $phone = $phone->formatE164();

        $contact = Contact::where('workspace_id', $request->workspace)->where('phone', $phone)->first();

        if(!$contact){
            $contact = new Contact();
            $contact->Workspace_id = $request->workspace;
            $contact->first_name = $request->first_name ?? $phone;
            $contact->phone = $phone;
            $contact->save();
        }

        try {
            $whatsappService = $this->initializeWhatsappService($request->workspace);
            $mediaService = new \App\Services\MediaService();

            // Upload media
            $mediaFile = $request->file('media');
            $mediaUpload = \App\Services\MediaService::upload($mediaFile);

            if (!$mediaUpload) {
                return response()->json([
                    'statusCode' => 500,
                    'message' => self::MSG_PROCESSING_ERROR,
                    'error' => 'Failed to upload media'
                ], 500);
            }

            // Send media message
            $result = $whatsappService->messageSending()->sendMedia(
                $contact->uuid,
                $mediaFile->getMimeType(),
                $mediaUpload['name'],
                $mediaUpload['path'],
                null,
                $request->caption
            );

            if ($result && isset($result->success) && $result->success) {
                return response()->json([
                    'statusCode' => 200,
                    'message' => self::MSG_SUCCESS,
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'statusCode' => 500,
                    'message' => self::MSG_PROCESSING_ERROR,
                    'error' => $result->error ?? 'Unknown error'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'statusCode' => 500,
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
     * Initialize WhatsApp service
     */
    private function initializeWhatsappService($workspaceId)
    {
        $workspace = \App\Models\Workspace::find($workspaceId);

        if (!$workspace) {
            throw new \Exception('Workspace not found');
        }

        return new WhatsappService(
            $workspace->meta_token,
            $workspace->meta_version,
            $workspace->meta_app_id,
            $workspace->meta_phone_number_id,
            $workspace->meta_waba_id,
            $workspace->id
        );
    }
}