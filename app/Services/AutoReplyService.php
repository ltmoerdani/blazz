<?php

namespace App\Services;

use App\Helpers\WebhookHelper;
use App\Http\Resources\AutoReplyResource;
use App\Models\AutoReply;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\workspace;
use App\Models\Setting;
use App\Services\MediaService;
use App\Services\WhatsappService;
use App\Services\WhatsApp\MessageSendingService;
use App\Services\WhatsApp\MediaProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AutoReplyService
{
    // Constants for repeated string literals
    const AUTOMATED_FLOWS = 'Automated Flows';

    private MessageSendingService $messageService;
    private MediaProcessingService $mediaService;
    private $workspaceId;

    public function __construct(
        $workspaceId,
        MessageSendingService $messageService,
        MediaProcessingService $mediaService
    ) {
        $this->workspaceId = $workspaceId;
        $this->messageService = $messageService;
        $this->mediaService = $mediaService;
    }
    
    public function getRows(object $request)
    {
        $workspaceId = session()->get('current_workspace');
        $model = new AutoReply;
        $searchTerm = $request->query('search');

        return AutoReplyResource::collection($model->listAll($workspaceId, $searchTerm));
    }

    public function store(object $request, $uuid = null)
    {
        $model = $uuid == null ? new AutoReply : AutoReply::where('uuid', $uuid)->first();
        $model['name'] = $request->name;
        $model['trigger'] = $request->trigger;
        $model['match_criteria'] = $request->match_criteria;

        $metadata['type'] = $request->response_type;
        if($request->response_type === 'image' || $request->response_type === 'audio'){
            if($request->hasFile('response')){
                $storage = Setting::where('key', 'storage_system')->first()->value;
                $fileName = $request->file('response')->getClientOriginalName();
                $fileContent = $request->file('response');

                if($storage === 'local'){
                    $file = Storage::disk('local')->put('public', $fileContent);
                    $mediaFilePath = $file;
                    $mediaUrl = rtrim(config('app.url'), '/') . '/media/' . ltrim($mediaFilePath, '/');
                } elseif($storage === 'aws') {
                    $filePath = 'uploads/media/received'  . session()->get('current_workspace') . '/' . $fileName;
                    Storage::disk('s3')->put($filePath, $fileContent, 'public');
                    /** @var \Illuminate\Filesystem\FilesystemAdapter $s3Disk */
                    $s3Disk = Storage::disk('s3');
                    $mediaFilePath = $s3Disk->url($filePath);
                    $mediaUrl = $mediaFilePath;
                }

                MediaService::upload($request->file('response'));

                $metadata['data']['file']['name'] = $fileName;
                $metadata['data']['file']['location'] = $mediaFilePath;
                $metadata['data']['file']['url'] = $mediaUrl;
            } else {
                $media = json_decode($model->metadata)->data;
                $metadata['data']['file']['name'] = $media->file->name;
                $metadata['data']['file']['location'] = $media->file->location;
                $metadata['data']['file']['url'] = $media->file->url;
            }
        } elseif($request->response_type === 'text') {
            $metadata['data']['text'] = $request->response;
        } else {
            $metadata['data']['template'] = $request->response;
        }

        $model['metadata'] = json_encode($metadata);
        $model['updated_at'] = now();

        if($uuid === null){
            $model['workspace_id'] = session()->get('current_workspace');
            $model['created_by'] = Auth::id();
            $model['created_at'] = now();
        }

        $model->save();

        // Prepare a clean contact object for webhook
        $cleanModel = $model->makeHidden(['id', 'workspace_id', 'created_by']);

        // Trigger webhook
        WebhookHelper::triggerWebhookEvent($uuid === null ? 'autoreply.created' : 'autoreply.updated', $cleanModel);
    }

    public function destroy($uuid)
    {
        AutoReply::where('uuid', $uuid)->update([
            'deleted_by' => Auth::id(),
            'deleted_at' => now()
        ]);

        // Trigger webhook
        WebhookHelper::triggerWebhookEvent('autoreply.deleted', [
            'list' => [
                'uuid' => $uuid,
                'deleted_at' => now()->toISOString()
            ],
        ]);
    }

    public function checkAutoReply(Chat $chat, $isNewContact)
    {
        $workspaceId = $chat->Workspace_id;

        $this->replySequence($workspaceId, $chat, $isNewContact);
    }

    private function replySequence($workspaceId, $chat, $isNewContact)
    {
        $workspaceConfig = workspace::where('id', $workspaceId)->first();
        $metadataArray = $workspaceConfig->metadata ? json_decode($workspaceConfig->metadata, true) : [];
        $activeFlow = false;
        $modulePath = base_path('modules/FlowBuilder');
        
        if (File::exists($modulePath)) {
            $flowServiceClass = '\Modules\FlowBuilder\Services\FlowExecutionService';
            if (class_exists($flowServiceClass)) {
                /** @var object $query */
                $query = new $flowServiceClass($workspaceId);
                $activeFlow = $query->hasActiveFlow($chat);
            }
        }

        // Override response sequence if there is an active flow
        if ($activeFlow) {
            $response_sequence = [self::AUTOMATED_FLOWS];
        } else {
            // Use the response sequence from metadata or fallback to default
            $response_sequence = $metadataArray['automation']['response_sequence'] ?? ['Basic Replies', self::AUTOMATED_FLOWS, 'AI Reply Assistant'];
        }

        // Define mapping of sequence items to functions
        $sequenceFunctions = [
            'Basic Replies' => function() use ($chat) {
                return $this->handleBasicReplies($chat);
            },
            self::AUTOMATED_FLOWS => function() use ($workspaceId, $chat, $isNewContact) {
                return $this->handleAutomatedFlows($workspaceId, $chat, $isNewContact);
            },
            'AI Reply Assistant' => function() use ($chat) {
                return $this->handleAIReplyAssistant($chat);
            },
        ];

        // Initialize a variable to hold the response (or handle chaining, etc.)
        $response = null;

        // Iterate through the sequence, applying each function in order
        foreach ($response_sequence as $sequenceItem) {
            if (isset($sequenceFunctions[$sequenceItem])) {
                $response = $sequenceFunctions[$sequenceItem]();

                Log::info($sequenceItem);
                Log::info($response);

                if ($response) {
                    // If a response is found, exit the loop
                    break;
                }
            }
        }

        return $response;
    }

    private function handleBasicReplies($chat)
    {
        $workspaceId = $chat->Workspace_id;
        $text = '';
        $metadata = json_decode($chat->metadata, true);

        if($metadata['type'] == 'text'){
            $text = $metadata['text']['body'];
        } elseif(json_decode($chat->metadata)->type == 'button'){
            $text = $metadata['button']['payload'];
        } elseif(json_decode($chat->metadata)->type == 'interactive'){
            if($metadata['interactive']['type'] == 'button_reply'){
                $text = $metadata['interactive']['button_reply']['title'];
            } elseif($metadata['interactive']['type'] == 'list_reply'){
                $text = $metadata['interactive']['list_reply']['title'];
            }
        }
        
        $receivedMessage = " " . strtolower($text);

        //Check basic reply flow
        $autoReplies = AutoReply::where('workspace_id', $workspaceId)
            ->where('deleted_at', null)
            ->get();

        foreach ($autoReplies as $autoReply) {
            $triggerValues = $this->getTriggerValues($autoReply->trigger);

            foreach ($triggerValues as $trigger) {
                if ($this->checkMatch($receivedMessage, $trigger, $autoReply->match_criteria)) {
                    $this->sendReply($chat, $autoReply);
                    return true;
                }
            }
        }

        return false; // No reply was sent
    }

    private function handleAIReplyAssistant($chat)
    {
        $text = '';
        $metadata = json_decode($chat->metadata, true);

        if($metadata['type'] == 'text'){
            $text = $metadata['text']['body'];
        } elseif(json_decode($chat->metadata)->type == 'button'){
            $text = $metadata['button']['payload'];
        } elseif(json_decode($chat->metadata)->type == 'interactive'){
            if($metadata['interactive']['type'] == 'button_reply'){
                $text = $metadata['interactive']['button_reply']['title'];
            } elseif($metadata['interactive']['type'] == 'list_reply'){
                $text = $metadata['interactive']['list_reply']['title'];
            }
        }
        
        $receivedMessage = " " . strtolower($text);

        if (file_exists(base_path('modules/IntelliReply/Services/AIResponseService.php'))) {
            $aiServiceClass = '\Modules\IntelliReply\Services\AIResponseService';
            /** @var object $query */
            $query = new $aiServiceClass();
            if ($query->handleAIResponse($chat, $receivedMessage)) {
                return true;
            }
        }

        return false; // No reply was sent
    }

    private function handleAutomatedFlows($workspaceId, $chat, $isNewContact)
    {
        $text = '';
        $metadata = json_decode($chat->metadata, true);

        if($metadata['type'] == 'text'){
            $text = $metadata['text']['body'];
        } elseif(json_decode($chat->metadata)->type == 'button'){
            $text = $metadata['button']['payload'];
        } elseif(json_decode($chat->metadata)->type == 'interactive'){
            if($metadata['interactive']['type'] == 'button_reply'){
                $text = $metadata['interactive']['button_reply']['title'];
            } elseif($metadata['interactive']['type'] == 'list_reply'){
                $text = $metadata['interactive']['list_reply']['title'];
            }
        }

        $receivedMessage = " " . strtolower($text);

        if (file_exists(base_path('modules/FlowBuilder/Services/FlowExecutionService.php'))) {
            $flowServiceClass = '\Modules\FlowBuilder\Services\FlowExecutionService';
            /** @var object $query */
            $query = new $flowServiceClass($workspaceId);
            return $query->executeFlow($chat, $isNewContact, $receivedMessage);
        }
    }

    private function getTriggerValues($trigger)
    {
        return is_string($trigger) && strpos($trigger, ',') !== false
            ? explode(',', $trigger)
            : (array) $trigger;
    }

    private function checkMatch($receivedMessage, $trigger, $criteria)
    {
        $normalizedTrigger = strtolower(trim($trigger));

        if ($criteria === 'exact match') {
            return $receivedMessage === " " . $normalizedTrigger;
        } elseif ($criteria === 'contains') {
            $triggerWords = explode(' ', $normalizedTrigger);
            $pattern = '/\b(' . implode('|', array_map('preg_quote', $triggerWords)) . ')\b/i';

            return preg_match($pattern, $receivedMessage) === 1;
        }
    
        return false;
    }

    protected function sendReply(Chat $chat, AutoReply $autoreply)
    {
        $contact = Contact::where('id', $chat->contact_id)->first();
        $workspace_id = $chat->Workspace_id;
        $metadata = json_decode($autoreply->metadata);
        $replyType = $metadata->type;

        // OLD: Keep for reference during transition
        /*
        if($replyType === 'text'){
            $message = $this->replacePlaceholders($workspace_id, $contact->uuid, $metadata->data->text);
            $this->initializeWhatsappService($workspace_id)->sendMessage($contact->uuid, $message);
        } elseif($replyType === 'audio' || $replyType === 'image'){
            $location = strpos($metadata->data->file->location, 'public\\') === 0 ? 'local' : 'amazon';
            $this->initializeWhatsappService($workspace_id)->sendMedia($contact->uuid, $replyType, $metadata->data->file->name, $metadata->data->file->location, $metadata->data->file->url, $location);
        }
        */

        // NEW: Use injected services
        if($replyType === 'text'){
            $message = $this->replacePlaceholders($workspace_id, $contact->uuid, $metadata->data->text);
            $this->messageService->sendMessage($contact->uuid, $message);
        } elseif($replyType === 'audio' || $replyType === 'image'){
            $location = strpos($metadata->data->file->location, 'public\\') === 0 ? 'local' : 'amazon';
            $this->messageService->sendMedia($contact->uuid, $replyType, $metadata->data->file->name, $metadata->data->file->location, $metadata->data->file->url, $location);
        }
    }

    /**
     * @deprecated Use constructor injection instead
     * OLD CODE - Commented out
     */
    /*
    private function initializeWhatsappService($workspaceId)
    {
        $config = workspace::where('id', $workspaceId)->first()->metadata;
        $config = $config ? json_decode($config, true) : [];

        $accessToken = $config['whatsapp']['access_token'] ?? null;
        $apiVersion = config('graph.api_version');
        $appId = $config['whatsapp']['app_id'] ?? null;
        $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
        $wabaId = $config['whatsapp']['waba_id'] ?? null;

        return new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId);
    }
    */

    /**
     * Replace placeholders in message with contact data
     */
    private function replacePlaceholders($workspaceId, $contactUuid, $message){
        $workspace = workspace::where('id', $workspaceId)->first();
        $contact = Contact::with('contactGroups')->where('uuid', $contactUuid)->first();
        $address = $contact->address ? json_decode($contact->address, true) : [];
        $metadata = $contact->metadata ? json_decode($contact->metadata, true) : [];
        $full_address = ($address['street'] ?? null) . ', ' .
                        ($address['city'] ?? null) . ', ' .
                        ($address['state'] ?? null) . ', ' .
                        ($address['zip'] ?? null) . ', ' .
                        ($address['country'] ?? null);

        $data = [
            'first_name' => $contact->first_name ?? null,
            'last_name' => $contact->last_name ?? null,
            'full_name' => $contact->full_name ?? null,
            'email' => $contact->email ?? null,
            'phone' => $contact->phone ?? null,
            'Workspace_name' => $workspace->name,
            'full_address' => $full_address,
            'street' => $address['street'] ?? null,
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? null,
            'zip_code' => $address['zip'] ?? null,
            'country' => $address['country'] ?? null,
        ];

        $transformedMetadata = [];
        if($metadata){
            foreach ($metadata as $key => $value) {
                $transformedKey = strtolower(str_replace(' ', '_', $key));
                $transformedMetadata[$transformedKey] = $value;
            }
        }

        $mergedData = array_merge($data, $transformedMetadata);

        return preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($mergedData) {
            $key = $matches[1];
            return isset($mergedData[$key]) ? $mergedData[$key] : $matches[0];
        }, $message);
    }
}
