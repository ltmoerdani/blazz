<?php

namespace App\Http\Controllers;

use App\Helpers\WebhookHelper;
use App\Http\Requests\StoreContact;
use App\Http\Resources\AutoReplyResource;
use App\Http\Resources\ContactResource;
use App\Http\Resources\ContactGroupResource;
use App\Http\Resources\TemplateResource;
use App\Models\AutoReply;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\workspace;
use App\Models\Template;
use App\Rules\CannedReplyLimit;
use App\Rules\ContactLimit;
use App\Rules\UniquePhone;
use App\Services\ChatService;
use App\Services\ContactService;
use App\Services\MediaService;
use App\Services\SubscriptionService;
use App\Services\WhatsappService;
use App\Traits\TemplateTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\PhoneNumber;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiController extends Controller
{
    use TemplateTrait;

    // Constants for repeated string literals
    const VALIDATION_INTEGER_MIN_1 = 'integer|min:1';
    const VALIDATION_INTEGER_MIN_1_MAX_100 = 'integer|min:1|max:100';
    const VALIDATION_MAX_255 = 'max:255';
    const VALIDATION_PHONE_AUTO = 'phone:AUTO';
    const MSG_INVALID_DATA = 'The given data was invalid.';
    const MSG_SUBSCRIPTION_REQUIRED = 'Please renew or subscribe to a plan to continue!';
    const MSG_SUCCESS = 'Request processed successfully';
    const MSG_PROCESSING_ERROR = 'Request unable to be processed';
    const MSG_INVALID_PROVIDED_DATA = 'The provided data is invalid.';
    const MSG_WHATSAPP_SETUP_REQUIRED = 'Please setup your whatsapp account!';
    const MSG_API_KEY_INACTIVE = 'API key is inactive. Please renew or subscribe to a plan to continue!';

    private $whatsappService;

    /**
     * List all contacts.
     *
     * @return \Illuminate\Http\Response
     */
    public function listContacts(Request $request){
        $validator = Validator::make($request->all(), [
            'page' => self::VALIDATION_INTEGER_MIN_1,
            'per_page' => self::VALIDATION_INTEGER_MIN_1_MAX_100, // Adjust max per_page limit as needed
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        $contacts = Contact::where('workspace_id', $request->workspace)
            ->where('deleted_at', null)
            ->paginate($perPage, ['*'], 'page', $page);

        return ContactResource::collection($contacts);
    }

    /**
     * Create a new contact.
     *
     * @param  \App\Http\Requests\CreateContactRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function storeContact(Request $request, $uuid = null){
        $validator = Validator::make($request->all(), [
            'first_name' => $request->isMethod('post') ? 'required' : 'required|sometimes',
            //'last_name' => 'required',
            'phone' => [
                'required',
                'string',
                self::VALIDATION_MAX_255,
                self::VALIDATION_PHONE_AUTO,
                new UniquePhone($request->workspace, $uuid),
            ],
            //'email' => 'required|string|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'statusCode' => 400,
                'message' => __(self::MSG_INVALID_DATA),
                'errors' => $validator->errors()
            ], 400);
        }

        if(!SubscriptionService::isSubscriptionActive($request->workspace)){
            return response()->json([
                'statusCode' => 403,
                'message' => __(self::MSG_SUBSCRIPTION_REQUIRED),
            ], 403);
        }

        if ($request->isMethod('post')) {
            if(!SubscriptionService::isSubscriptionFeatureLimitReached($request->workspaceId, 'contacts_limit')){
                return response()->json([
                    'statusCode' => 403,
                    'message' => __('You have reached your limit of contacts. Please upgrade your account to add more!'),
                ], 403);
            }
        }

        try {
            $contactService = new ContactService($request->workspace);
            $contact = $contactService->store($request, $uuid);

            return response()->json([
                'statusCode' => 200,
                'id' => $contact->uuid,
                'message' => __(self::MSG_SUCCESS)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'statusCode' => 500,
                'message' => __(self::MSG_PROCESSING_ERROR)
            ], 500);
        }
    }

    /**
     * Delete a contact.
     *
     * @param  \App\Models\Contact  $contact
     * @return \Illuminate\Http\Response
     */
    public function destroyContact(Request $request, $uuid){
        try {
            $contactService = new ContactService($request->workspace);
            $contactService->delete([$uuid]);

            return response()->json([
                'statusCode' => 200,
                'id' => $uuid,
                'message' => __('Request processed successfully')
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'statusCode' => 500,
                'message' => __(self::MSG_PROCESSING_ERROR)
            ], 500);
        }
    }

    /**
     * List all contact groups.
     *
     * @return \Illuminate\Http\Response
     */
    public function listContactGroups(Request $request){
        $validator = Validator::make($request->all(), [
            'page' => self::VALIDATION_INTEGER_MIN_1,
            'per_page' => self::VALIDATION_INTEGER_MIN_1_MAX_100, // Adjust max per_page limit as needed
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        $contactGroups = ContactGroup::where('workspace_id', $request->workspace)
            ->where('deleted_at', null)
            ->paginate($perPage, ['*'], 'page', $page);

        return ContactGroupResource::collection($contactGroups);
    }

    /**
     * Create a new contact group.
     *
     * @param  \App\Http\Requests\CreateContactGroupRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function storeContactGroup(Request $request, $uuid = null){
        $workspaceId = $request->workspace;

        if ($request->isMethod('post')) {
            $rules = [
                'name' => [
                    'required',
                    Rule::unique('contact_groups', 'name')->where(function ($query) use ($workspaceId) {
                        return $query->where('workspace_id', $workspaceId)
                            ->where('deleted_at', null);
                    }),
                ],
            ];
        } else {
            $rules = [
                'name' => [
                    'required',
                    Rule::unique('contact_groups', 'name')->where(function ($query) use ($workspaceId, $uuid) {
                        return $query->where('workspace_id', $workspaceId)
                            ->where('deleted_at', null)
                            ->whereNotIn('uuid', [$uuid]);
                    }),
                ],
            ];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'statusCode' => 400,
                'message' => __(self::MSG_INVALID_DATA),
                'errors' => $validator->errors()
            ], 400);
        }

        if(!SubscriptionService::isSubscriptionActive($request->workspace)){
            return response()->json([
                'statusCode' => 403,
                'message' => __(self::MSG_SUBSCRIPTION_REQUIRED),
            ], 403);
        }

        try {
            $contactGroup = $request->isMethod('post') ? new ContactGroup() : ContactGroup::where('uuid', $uuid)->firstOrFail();
            $contactGroup->Workspace_id = $request->workspace;
            $contactGroup->name = $request->name;
            $contactGroup->created_by = 0;
            $contactGroup->save();

            // Prepare a clean contact object for webhook
            $cleanContactGroup = $contactGroup->makeHidden(['id', 'workspace_id', 'created_by']);

            // Trigger webhook
            WebhookHelper::triggerWebhookEvent($request->isMethod('post') ? 'group.created' : 'group.updated', $cleanContactGroup, $request->workspace);

            return response()->json([
                'statusCode' => 200,
                'id' => $contactGroup->uuid,
                'message' => __(self::MSG_SUCCESS)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'statusCode' => 500,
                'message' => __(self::MSG_PROCESSING_ERROR)
            ], 500);
        }
    }

    /**
     * Delete a contact group.
     *
     * @param  \App\Models\ContactGroup  $contactGroup
     * @return \Illuminate\Http\Response
     */
    public function destroyContactGroup(Request $request, $uuid){
        try {
            $contactGroup = ContactGroup::where('workspace_id', $request->workspace)->where('uuid', $uuid)->firstOrFail();
            $contactGroup->deleted_at = date('Y-m-d H:i:s');
            $contactGroup->save();

            //Remove contact associations
            Contact::where('contact_group_id', $contactGroup->id)->update([
                'contact_group_id' => null
            ]);

            // Trigger webhook with deleted contacts
            $deletedGroups[] = [
                'uuid' => $uuid,
                'deleted_at' => now()->toISOString(),
            ];

            WebhookHelper::triggerWebhookEvent('group.deleted', [
                'list' => $deletedGroups
            ], $request->workspace);

            return response()->json([
                'statusCode' => 200,
                'id' => $uuid,
                'message' => __(self::MSG_SUCCESS)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'statusCode' => 500,
                'message' => __(self::MSG_PROCESSING_ERROR)
            ], 500);
        }
    }

    public function listCannedReplies(Request $request){
        $validator = Validator::make($request->all(), [
            'page' => self::VALIDATION_INTEGER_MIN_1,
            'per_page' => self::VALIDATION_INTEGER_MIN_1_MAX_100, // Adjust max per_page limit as needed
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        $rows = AutoReply::where('workspace_id', $request->workspace)
            ->where('deleted_at', null)
            ->paginate($perPage, ['*'], 'page', $page);

        return AutoReplyResource::collection($rows);
    }

    /**
     * Create a new canned reply.
     *
     * @param  \App\Http\Requests\CreateCannedReplyRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function storeCannedReply(Request $request, $uuid = null){
        $rules = [
            'name' => 'required',
            'trigger' => 'required',
            'match_criteria' => 'required|in:exact match,contains',
            'response_type' => 'required|in:text,image,audio',
            'response' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'statusCode' => 400,
                'message' => __(self::MSG_INVALID_DATA),
                'errors' => $validator->errors()
            ], 400);
        }

        if(!SubscriptionService::isSubscriptionActive($request->workspace)){
            return response()->json([
                'statusCode' => 403,
                'message' => __(self::MSG_SUBSCRIPTION_REQUIRED),
            ], 403);
        }

        if ($request->isMethod('post')) {
            if(!SubscriptionService::isSubscriptionFeatureLimitReached($request->workspaceId, 'canned_replies_limit')){
                return response()->json([
                    'statusCode' => 403,
                    'message' => __('You\'ve reached your limit. Upgrade your account'),
                ], 403);
            }
        }

        try {
            $model = $uuid == null ? new AutoReply : AutoReply::where('uuid', $uuid)->first();
            $model['name'] = $request->name;
            $model['trigger'] = $request->trigger;
            $model['match_criteria'] = $request->match_criteria;

            $metadata['type'] = $request->response_type;
            if($request->response_type === 'image' || $request->response_type === 'audio'){
                if($request->hasFile('response')){
                    $uploadedMedia = MediaService::upload($request->file('response'));

                    $metadata['data']['file']['name'] = $uploadedMedia['name'];
                    $metadata['data']['file']['location'] = $uploadedMedia['path'];
                } else {
                    $media = json_decode($model->metadata)->data;
                    $metadata['data']['file']['name'] = $media->file->name;
                    $metadata['data']['file']['location'] = $media->file->location;
                }
            } elseif($request->response_type === 'text') {
                $metadata['data']['text'] = $request->response;
            } else {
                $metadata['data']['template'] = $request->response;
            }

            $model['metadata'] = json_encode($metadata);
            $model['updated_at'] = now();

            if($uuid === null){
                $model['workspace_id'] = $request->workspace;
                $model['created_by'] = 0;
                $model['created_at'] = now();
            }

            $model->save();

            // Prepare a clean contact object for webhook
            $cleanModel = $model->makeHidden(['id', 'workspace_id', 'created_by']);

            // Trigger webhook
            WebhookHelper::triggerWebhookEvent($uuid === null ? 'autoreply.created' : 'autoreply.updated', $cleanModel, $request->workspace);

            return response()->json([
                'statusCode' => 200,
                'id' => $model->uuid,
                'message' => __(self::MSG_SUCCESS)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'statusCode' => 500,
                'message' => __(self::MSG_PROCESSING_ERROR)
            ], 500);
        }
    }

    /**
     * Delete a canned reply.
     *
     * @param  \App\Models\CannedReply  $cannedReply
     * @return \Illuminate\Http\Response
     */
    public function destroyCannedReply(Request $request, $uuid){
        try {
            $autoreply = AutoReply::where('workspace_id', $request->workspace)->where('uuid', $uuid)->firstOrFail();
            $autoreply->deleted_at = now();
            $autoreply->deleted_by = 0;
            $autoreply->save();

            // Trigger webhook
            WebhookHelper::triggerWebhookEvent('autoreply.deleted', [
                'list' => [
                    'uuid' => $uuid,
                    'deleted_at' => now()->toISOString()
                ],
            ], $request->workspace);

            return response()->json([
                'statusCode' => 200,
                'id' => $uuid,
                'message' => __(self::MSG_SUCCESS)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'statusCode' => 500,
                'message' => __(self::MSG_PROCESSING_ERROR)
            ], 500);
        }
    }

    /**
     * Send a chat message.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
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
            $contact->first_name = $request->first_name;
            $contact->last_name = $request->last_name;
            $contact->email = $request->email;
            $contact->phone = $phone;
            $contact->created_by = 0;
            $contact->save();
        }

        // Extract the UUID of the contact
        $this->initializeWhatsappService($request->workspace);
        $type = !isset($request->buttons) ? 'text' : 'interactive buttons';

        $header = [];
        if($request->header){
            $header['type'] = 'text';
            $header['text'] = clean($request->header);
        }

        $message = $this->whatsappService->sendMessage($contact->uuid, $request->message, 0, $type, $request->buttons, $header, $request->footer);
        
        return response()->json([
            'statusCode' => 200,
            'data' => $message
        ], 200);
    }

    public function sendTemplateMessage(Request $request){
        $rules = [
            'phone' => ['required', 'string', self::VALIDATION_MAX_255, self::VALIDATION_PHONE_AUTO],
            'template.name' => 'required',
            'template.language' => 'required',
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

        $contact = Contact::where('phone', $phone)->where('workspace_id', $request->workspace)
            ->whereNull('deleted_at')->first();

        if(!$contact){
            $contact = new Contact();
            $contact->Workspace_id = $request->workspace;
            $contact->first_name = $request->first_name;
            $contact->last_name = $request->last_name;
            $contact->email = $request->email;
            $contact->phone = $phone;
            $contact->created_by = 0;
            $contact->save();
        }

        // Extract the UUID of the contact
        $this->initializeWhatsappService($request->workspace);
        $responseObject = $this->whatsappService->sendTemplateMessage($contact->uuid, $request->template, 0);

        return response()->json([
            'statusCode' => 200,
            'data' => $responseObject
        ], 200);
    }

    public function sendMediaMessage(Request $request){
        $rules = [
            'phone' => ['required', 'string', self::VALIDATION_MAX_255, self::VALIDATION_PHONE_AUTO],
            'media_type' => 'required',
            'media_url' => 'required',
            'caption' => 'required',
            'file_name' => 'required',
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
            $contact->first_name = $request->first_name;
            $contact->last_name = $request->last_name;
            $contact->email = $request->email;
            $contact->phone = $phone;
            $contact->created_by = 0;
            $contact->save();
        }

        // Extract the UUID of the contact
        $this->initializeWhatsappService($request->workspace);

        $message = $this->whatsappService->sendMedia($contact->uuid, $request->media_type, $request->file_name, $request->media_url, $request->media_url, 'amazon');
        
        return response()->json([
            'statusCode' => 200,
            'data' => $message
        ], 200);
    }

    /**
     * Store a campaign.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeCampaign(Request $request){
        
    }

    private function isWhatsAppConnected($workspaceId){
        $settings = workspace::where('id', $workspaceId)->first();
        $metadata = $settings->metadata ? json_decode($settings->metadata, true) : [];

        return isset($metadata['whatsapp']);
    }

    private function initializeWhatsappService($workspaceId)
    {
        $config = workspace::where('id', $workspaceId)->first()->metadata;
        $config = $config ? json_decode($config, true) : [];

        $accessToken = $config['whatsapp']['access_token'] ?? null;
        $apiVersion = config('graph.api_version');
        $appId = $config['whatsapp']['app_id'] ?? null;
        $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
        $wabaId = $config['whatsapp']['waba_id'] ?? null;

        $this->whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId);
    }

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
     * Verify if the API key is active.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
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
                    'message' => __('Invalid API key.')
                ], 401);
            }

            $workspaceId = $token->Workspace_id;

            if (!SubscriptionService::isSubscriptionActive($workspaceId)) {
                return response()->json([
                    'statusCode' => 403,
                    'message' => __(self::MSG_API_KEY_INACTIVE)
                ], 403);
            }

            return response()->json([
                'statusCode' => 200,
                'message' => __('API key is valid and active')
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'statusCode' => 500,
                'message' => __(self::MSG_PROCESSING_ERROR)
            ], 500);
        }
    }
}
