<?php

namespace App\Http\Controllers\Api\v1;

use App\Events\NewChatEvent;
use App\Helpers\WebhookHelper;
use App\Http\Controllers\Controller as BaseController;
use App\Models\AutoReply;
use App\Models\Contact;
use App\Models\Chat;
use App\Models\ChatLog;
use App\Models\ChatStatusLog;
use App\Models\ChatMedia;
use App\Models\workspace;
use App\Models\Setting;
use App\Models\Template;
use App\Resolvers\PaymentPlatformResolver;
use App\Services\AutoReplyService;
use App\Services\ChatService;
use App\Services\StripeService;
use App\Services\SubscriptionService;
use App\Services\WhatsApp\MessageSendingService;
use App\Services\WhatsApp\MediaProcessingService;
use App\Services\WhatsApp\TemplateManagementService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Propaganistas\LaravelPhone\PhoneNumber;
use GuzzleHttp\Exception\RequestException;

class WebhookController extends BaseController
{
    protected $paymentPlatformResolver;
    private ?ChatService $chatService;
    private ?AutoReplyService $autoReplyService;

    public function __construct(
        MessageSendingService $messageService,
        MediaProcessingService $mediaService,
        TemplateManagementService $templateService
    ) {
        $this->paymentPlatformResolver = new PaymentPlatformResolver();

        // Initialize services with workspace ID when needed
        $this->chatService = null;
        $this->autoReplyService = null;

        Config::set('broadcasting.connections.pusher', [
            'driver' => 'pusher',
            'key' => Setting::where('key', 'pusher_app_key')->value('value'),
            'secret' => Setting::where('key', 'pusher_app_secret')->value('value'),
            'app_id' => Setting::where('key', 'pusher_app_id')->value('value'),
            'options' => [
                'cluster' => Setting::where('key', 'pusher_app_cluster')->value('value'),
            ],
        ]);
    }

    private function getChatService($workspaceId)
    {
        if (!$this->chatService) {
            $this->chatService = new ChatService(
                $workspaceId,
                app('App\Services\WhatsApp\MessageSendingService'),
                app('App\Services\WhatsApp\MediaProcessingService'),
                app('App\Services\WhatsApp\TemplateManagementService')
            );
        }
        return $this->chatService;
    }

    private function getAutoReplyService($workspaceId)
    {
        if (!$this->autoReplyService) {
            $this->autoReplyService = new AutoReplyService(
                $workspaceId,
                app('App\Services\WhatsApp\MessageSendingService'),
                app('App\Services\WhatsApp\MediaProcessingService')
            );
        }
        return $this->autoReplyService;
    }

    public function whatsappWebhook(Request $request){
        $verifyToken = Setting::where('key', 'whatsapp_callback_token')->first()->value;

        $mode = $request->input('hub_mode');
        $token = $request->input('hub_verify_token');
        $challenge = $request->input('hub_challenge');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return Response::make($challenge, 200);
        } else {
            return Response::json(['error' => 'Forbidden'], 200);
        }
    }

    public function handle(Request $request, $identifier = null)
    {
        $workspace = $this->getWorkspaceByIdentifier($identifier);

        if (!$workspace) {
            return $this->forbiddenResponse();
        }

        return $this->handleMethod($request, $workspace);
    }

    protected function getWorkspaceByIdentifier($identifier)
    {
        return workspace::where('identifier', $identifier)->first();
    }

    protected function handleMethod(Request $request, workspace $workspace)
    {
        if ($request->isMethod('get')) {
            return $this->handleGetRequest($request, $workspace);
        } elseif ($request->isMethod('post')) {
            $metadata = json_decode($workspace->metadata);

            if (empty($metadata)) {
                return $this->forbiddenResponse();
            }



            return $this->handlePostRequest($request, $workspace);
        }

        return Response::json(['error' => 'Method Not Allowed'], 405);
    }

    protected function forbiddenResponse()
    {
        return Response::json(['error' => 'Forbidden'], 403);
    }

    protected function isValidSignature($calculatedSignature, $headerSignature)
    {
        return hash_equals($calculatedSignature, $headerSignature);
    }

    protected function invalidSignatureResponse()
    {
        return Response::json(['status' => 'error', 'message' => __('Invalid payload signature')], 400);
    }

    protected function handleGetRequest(Request $request, workspace $workspace)
    {
        try {
            $verifyToken = $workspace->identifier;

            $mode = $request->input('hub_mode');
            $token = $request->input('hub_verify_token');
            $challenge = $request->input('hub_challenge');

            if ($mode === 'subscribe' && $token === $verifyToken) {
                return Response::make($challenge, 200);
            } else {
                return Response::json(['error' => 'Forbidden'], 404);
            }
        } catch (\Exception $e) {
            Log::error("Error processing webhook: " . $e->getMessage());
            return Response::json(['error' => $e->getMessage()], 403);
        }
    }

    protected function handlePostRequest(Request $request, workspace $workspace)
    {
        $res = $request->entry[0]['changes'][0];

        

        if($res['field'] === 'messages'){
            $messages = $res['value']['messages'] ?? null;
            $statuses = $res['value']['statuses'] ?? null;

            if($statuses) {
                
                foreach($statuses as $response){
                    $chatWamId = $response['id'];
                    $status = $response['status'];

                    $chat = Chat::where('wam_id', $chatWamId)->first();

                    if($chat){
                        $chat->status = $status;
                        $chat->save();

                        $chatStatusLog = new ChatStatusLog;
                        $chatStatusLog->chat_id = $chat->id;
                        $chatStatusLog->metadata = json_encode($response);
                        $chatStatusLog->save();
                    }
                }

                // Trigger webhook
                WebhookHelper::triggerWebhookEvent('message.status.update', [
                    'data' => $res,
                ], $workspace->id);
            } elseif($messages) {
                $isLimitReached = SubscriptionService::isSubscriptionLimitReachedForInboundMessages($workspace->id);
                

                if(!$isLimitReached){
                    foreach($messages as $response){
                        $phone = $response['from'];

                        if (substr($phone, 0, 1) !== '+') {
                            $phone = '+' . $phone;
                        }

                        $phone = new PhoneNumber($phone);
                        $phone = $phone->formatE164();

                        //Check if contact exists in workspace
                        $contact = Contact::where('workspace_id', $workspace->id)->where('phone', $phone)->whereNull('deleted_at')->first();
                        $isNewContact = false;

                        if(!$contact){
                            //Create a contact
                            $contactData = $res['value']['contacts'][0]['profile'] ?? null;

                            $contact = Contact::create([
                                'first_name' => $contactData['name'] ?? null,
                                'last_name' => null,
                                'email' => null,
                                'phone' => $phone,
                                'workspace_id' => $workspace->id,
                                'created_by' => 0,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $isNewContact = true;
                        }

                        if($contact){
                            if($contact->first_name == null){
                                $contactData = $res['value']['contacts'][0]['profile'];
                                $contact->update([
                                    'first_name' => $contactData['name'],
                                ]);
                            }

                            $chat = Chat::where('wam_id', $response['id'])->where('workspace_id', $workspace->id)->first();

                            if(!$chat){
                                //First open the chat
                                $this->getChatService($workspace->id)->handleTicketAssignment($contact->id);

                                $chat = new Chat;
                                $chat->Workspace_id = $workspace->id;
                                $chat->wam_id = $response['id'];
                                $chat->contact_id = $contact->id;
                                $chat->type = 'inbound';
                                $chat->metadata = json_encode($response);
                                $chat->status = 'delivered';
                                $chat->save();
                            
                                if($chat){
                                    if($response['type'] === 'image' || $response['type'] === 'video' || $response['type'] === 'audio' || $response['type'] === 'document' || $response['type'] === 'sticker'){
                                        $type = $response['type'];
                                        $mediaId = $response[$type]['id'];

                                        //Get & Download media file
                                        $media = $this->getMedia($mediaId, $workspace);
                                        $downloadedFile = $this->downloadMedia($media, $workspace);

                                        //Upload media
                                        $chatMedia = new ChatMedia;
                                        $chatMedia->name = $type === 'document' ? $response[$type]['filename'] : 'N/A';
                                        $chatMedia->path = $downloadedFile['media_url'];
                                        $chatMedia->type = $media['mime_type'];
                                        $chatMedia->size = $media['file_size'];
                                        $chatMedia->location = $downloadedFile['location'];
                                        $chatMedia->created_at = now();
                                        $chatMedia->save();

                                        //Update chat
                                        Chat::where('id', $chat->id)->update([
                                            'media_id' => $chatMedia->id
                                        ]);
                                    }
                                }

                                $chat = Chat::with('contact','media')->where('id', $chat->id)->first();

                                $chatlogId = ChatLog::insertGetId([
                                    'contact_id' => $contact->id,
                                    'entity_type' => 'chat',
                                    'entity_id' => $chat->id,
                                    'created_at' => now()
                                ]);
                                
                                $chatLogArray = ChatLog::where('id', $chatlogId)->where('deleted_at', null)->first();
                                $chatArray = array([
                                    'type' => 'chat',
                                    'value' => $chatLogArray->relatedEntities
                                ]);

                                event(new NewChatEvent($chatArray, $workspace->id));

                                $isMessageLimitReached = SubscriptionService::isSubscriptionFeatureLimitReached($workspace->id, 'message_limit');

                                if(!$isMessageLimitReached){
                                    if($response['type'] === 'text' || $response['type'] === 'button'|| $response['type'] === 'audio'|| $response['type'] === 'interactive'){
                                        $this->getAutoReplyService($chat->Workspace_id)->checkAutoReply($chat, $isNewContact);
                                    }
                                }
                            }
                        }
                    }

                    // Trigger webhook
                    WebhookHelper::triggerWebhookEvent('message.received', [
                        'data' => $res,
                    ], $workspace->id);
                }
            }
        } elseif($res['field'] === 'message_template_status_update'){
            $response = $res['value'] ?? null;
            $template = Template::where('meta_id', $response['message_template_id'])->first();

            if($template){
                $template->status = $response['event'];
                $template->save();
            }
        } elseif($res['field'] === 'account_review_update'){
            //Account Status
            $response = $res['value'] ?? null;
            $workspaceConfig = workspace::where('id', $workspace->id)->first();
            $metadataArray = $workspaceConfig->metadata ? json_decode($workspaceConfig->metadata, true) : [];

            $metadataArray['whatsapp']['account_review_status'] = $response['decision'] ?? null;

            $updatedMetadataJson = json_encode($metadataArray);
            $workspaceConfig->metadata = $updatedMetadataJson;
            $workspaceConfig->save();
        } elseif($res['field'] === 'phone_number_name_update'){
            //Display Name
            $response = $res['value'] ?? null;

            if($response['decision'] == 'APPROVED'){
                $workspaceConfig = workspace::where('id', $workspace->id)->first();
                $metadataArray = $workspaceConfig->metadata ? json_decode($workspaceConfig->metadata, true) : [];

                $metadataArray['whatsapp']['verified_name'] = $response['requested_verified_name'] ?? null;

                $updatedMetadataJson = json_encode($metadataArray);
                $workspaceConfig->metadata = $updatedMetadataJson;
                $workspaceConfig->save();
            }
        } elseif($res['field'] === 'phone_number_quality_update'){
            //messaging_tier_limit
            $response = $res['value'] ?? null;
            $workspaceConfig = workspace::where('id', $workspace->id)->first();
            $metadataArray = $workspaceConfig->metadata ? json_decode($workspaceConfig->metadata, true) : [];

            $metadataArray['whatsapp']['messaging_limit_tier'] = $response['current_limit'] ?? null;

            $updatedMetadataJson = json_encode($metadataArray);
            $workspaceConfig->metadata = $updatedMetadataJson;
            $workspaceConfig->save();
        } elseif($res['field'] === 'business_capability_update'){
            //messaging_tier_limit
            $response = $res['value'] ?? null;
            $workspaceConfig = workspace::where('id', $workspace->id)->first();
            $metadataArray = $workspaceConfig->metadata ? json_decode($workspaceConfig->metadata, true) : [];

            $metadataArray['whatsapp']['max_daily_conversation_per_phone'] = $response['max_daily_conversation_per_phone'] ?? null;
            $metadataArray['whatsapp']['max_phone_numbers_per_business'] = $response['max_phone_numbers_per_business'] ?? null;

            $updatedMetadataJson = json_encode($metadataArray);
            $workspaceConfig->metadata = $updatedMetadataJson;
            $workspaceConfig->save();
        }

        return Response::json(['status' => 'success'], 200);
    }

    private function downloadMedia($mediaInfo, workspace $workspace)
    {
        $metadata = json_decode($workspace->metadata);

        if (empty($metadata) || empty($metadata->whatsapp->access_token)) {
            return $this->forbiddenResponse();
        }

        try {
            $client = new Client();

            $requestOptions = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $metadata->whatsapp->access_token,
                    'Content-Type' => 'application/json',
                ],
            ];

            $response = $client->request('GET', $mediaInfo['url'], $requestOptions);

            $fileContent = $response->getBody();
            $mimeType = $mediaInfo['mime_type'] ?? 'application/octet-stream'; // Default fallback
            $fileName = $this->generateFilename($fileContent, $mediaInfo['mime_type']);

            $storage = Setting::where('key', 'storage_system')->first()->value;

            if($storage === 'local'){
                $location = 'local';
                Storage::disk('local')->put('public/' . $fileName, $fileContent);
                $mediaUrl = rtrim(config('app.url'), '/') . '/media/' . 'public/' . $fileName;
            } elseif($storage === 'aws') {
                $location = 'amazon';
                $filePath = 'uploads/media/received/'  . $workspace->id . '/' . Str::random(40) . time();
                Storage::disk('s3')->put($filePath, $fileContent, [
                    'ContentType' => $mimeType
                ]);
                /** @var \Illuminate\Filesystem\FilesystemAdapter $s3Disk */
                $s3Disk = Storage::disk('s3');
                $mediaUrl = $s3Disk->url($filePath);
            }

            return [
                'media_url' => $mediaUrl,
                'location' => $location,
            ];
        } catch (RequestException $e) {
            Log::error("Error processing webhook: " . $e->getMessage());
            return Response::json(['error' => 'Failed to download file'], 403);
        }
    }

    private function generateFilename($fileContent, $mimeType)
    {
        // Generate a unique filename based on the file content
        $hash = sha1($fileContent);

        // Get the file extension from the media type
        $extension = explode('/', $mimeType)[1];

        // Combine the hash, timestamp, and extension to create a unique filename
        return "{$hash}_" . time() . ".{$extension}";
    }

    private function getMedia($mediaId, workspace $workspace)
    {
        $metadata = json_decode($workspace->metadata);

        if (empty($metadata) || empty($metadata->whatsapp->access_token)) {
            return $this->forbiddenResponse();
        }

        $client = new Client();

        try {
            $requestOptions = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $metadata->whatsapp->access_token,
                    'Content-Type' => 'application/json',
                ],
            ];

            $response = $client->request('GET', "https://graph.facebook.com/v18.0/{$mediaId}", $requestOptions);

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            return Response::json(['error' => 'Method Invalid'], 400);
        }
    }

    public function processWebhook(Request $request, $processor)
    {
        $paymentPlatform = $this->paymentPlatformResolver->resolveService($processor);
        session()->put('paymentPlatform', $processor);
        
        return $paymentPlatform->handleWebhook($request);
    }
}
