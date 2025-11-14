<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class HybridCampaignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $campaignType = $this->input('campaign_type', 'template');

        return array_merge([
            // Basic campaign fields
            'name' => ['required', 'string', 'max:128'],
            'campaign_type' => ['required', 'string', 'in:template,direct'],
            'contacts' => ['required', 'string'],
            'preferred_provider' => ['nullable', 'string', 'in:webjs,meta_api'],
            'whatsapp_session_id' => ['nullable', 'integer', 'exists:whatsapp_sessions,id'],
            'scheduled_at' => ['nullable', 'date'],
            'skip_schedule' => ['nullable', 'boolean'],
        ], $this->getTemplateValidationRules($campaignType), $this->getDirectMessageValidationRules($campaignType));
    }

    /**
     * Get validation rules for template-based campaigns
     */
    private function getTemplateValidationRules(string $campaignType): array
    {
        if ($campaignType !== 'template') {
            return [];
        }

        return [
            'template' => ['required', 'string', 'exists:templates,uuid'],
            'header' => ['required', 'array'],
            'header.format' => ['required', 'string', 'in:TEXT,IMAGE,DOCUMENT,VIDEO'],
            'header.text' => ['required_if:header.format,TEXT', 'nullable', 'string', 'max:60'],
            'header.parameters' => ['required_if:header.format,IMAGE,DOCUMENT,VIDEO', 'nullable', 'array'],
            'header.parameters.*.type' => ['required', 'string', 'in:text,image,document,video'],
            'header.parameters.*.selection' => ['required', 'string', 'in:upload,media_library,url'],
            'header.parameters.*.value' => ['required'],
            'body' => ['required', 'array'],
            'body.parameters' => ['nullable', 'array'],
            'body.parameters.*.type' => ['required', 'string', 'in:text'],
            'body.parameters.*.text' => ['required', 'string', 'max:1024'],
            'footer' => ['nullable', 'array'],
            'footer.text' => ['nullable', 'string', 'max:60'],
            'buttons' => ['nullable', 'array'],
            'buttons.*.type' => ['required', 'string', 'in:reply,url,phone_number'],
            'buttons.*.text' => ['required', 'string', 'max:20'],
            'buttons.*.url' => ['required_if:buttons.*.type,url', 'nullable', 'url'],
            'buttons.*.phone_number' => ['required_if:buttons.*.type,phone_number', 'nullable', 'regex:/^[0-9+\-\s()]+$/'],
        ];
    }

    /**
     * Get validation rules for direct message campaigns
     */
    private function getDirectMessageValidationRules(string $campaignType): array
    {
        if ($campaignType !== 'direct') {
            return [];
        }

        return [
            // Direct message content
            'message_content' => ['nullable', 'string', 'max:4096'],
            'header_type' => ['nullable', 'string', 'in:text,image,document,video'],
            'header_text' => ['required_if:header_type,text', 'nullable', 'string', 'max:60'],
            'header_media' => [
                'required_if:header_type,image,document,video',
                'nullable',
                'file',
                'mimes:jpeg,jpg,png,gif,pdf,doc,docx,mp4,mov,avi',
                'max:16384' // 16MB max
            ],
            'body_text' => ['required', 'string', 'min:1', 'max:1024'],
            'footer_text' => ['nullable', 'string', 'max:60'],
            'buttons' => ['nullable', 'array', 'max:10'],
            'buttons.*.type' => ['required', 'string', 'in:reply,url,phone_number'],
            'buttons.*.text' => ['required', 'string', 'max:20'],
            'buttons.*.url' => ['required_if:buttons.*.type,url', 'nullable', 'url'],
            'buttons.*.phone_number' => ['required_if:buttons.*.type,phone_number', 'nullable', 'regex:/^[0-9+\-\s()]+$/'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Basic campaign messages
            'name.required' => 'Campaign name is required',
            'name.max' => 'Campaign name cannot exceed 128 characters',
            'campaign_type.required' => 'Please select a campaign type',
            'campaign_type.in' => 'Invalid campaign type selected',
            'contacts.required' => 'Please select contacts or contact group',
            'preferred_provider.in' => 'Invalid provider selected',
            'whatsapp_session_id.exists' => 'Selected WhatsApp session not found',

            // Template validation messages
            'template.required' => 'Please select a template for template-based campaign',
            'template.exists' => 'Selected template not found',
            'header.format.required' => 'Header format is required',
            'header.format.in' => 'Invalid header format selected',
            'header.text.required_if' => 'Header text is required when format is TEXT',
            'header.text.max' => 'Header text cannot exceed 60 characters',
            'header.parameters.required_if' => 'Media parameters are required for this header format',
            'header.text.max' => 'Header text cannot exceed 60 characters',
            'body.parameters.*.text.max' => 'Body text cannot exceed 1024 characters',
            'footer.text.max' => 'Footer text cannot exceed 60 characters',
            'buttons.*.type.required' => 'Button type is required',
            'buttons.*.type.in' => 'Invalid button type selected',
            'buttons.*.text.required' => 'Button text is required',
            'buttons.*.text.max' => 'Button text cannot exceed 20 characters',
            'buttons.*.url.required_if' => 'URL is required for URL buttons',
            'buttons.*.url.url' => 'Please provide a valid URL',
            'buttons.*.phone_number.required_if' => 'Phone number is required for phone buttons',
            'buttons.*.phone_number.regex' => 'Please provide a valid phone number',

            // Direct message validation messages
            'message_content.max' => 'Message content cannot exceed 4096 characters',
            'header_type.in' => 'Invalid header type selected',
            'header_text.required_if' => 'Header text is required when type is text',
            'header_text.max' => 'Header text cannot exceed 60 characters',
            'header_media.required_if' => 'Media file is required for this header type',
            'header_media.mimes' => 'Invalid file type. Allowed: jpeg, jpg, png, gif, pdf, doc, docx, mp4, mov, avi',
            'header_media.max' => 'File size cannot exceed 16MB',
            'body_text.required' => 'Message body is required',
            'body_text.min' => 'Message body cannot be empty',
            'body_text.max' => 'Message body cannot exceed 1024 characters',
            'footer_text.max' => 'Footer text cannot exceed 60 characters',
            'buttons.max' => 'Maximum 10 buttons allowed',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'campaign name',
            'campaign_type' => 'campaign type',
            'template' => 'template',
            'contacts' => 'contacts',
            'preferred_provider' => 'preferred provider',
            'whatsapp_session_id' => 'WhatsApp session',
            'scheduled_at' => 'scheduled time',
            'header.format' => 'header format',
            'header.text' => 'header text',
            'header_media' => 'header media',
            'body_text' => 'message body',
            'footer_text' => 'footer text',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateCampaignLogic($validator);
            $this->validateTemplateCompatibility($validator);
            $this->validateDirectMessageStructure($validator);
            $this->validateScheduleLogic($validator);
        });
    }

    /**
     * Validate campaign business logic
     */
    private function validateCampaignLogic($validator): void
    {
        $campaignType = $this->input('campaign_type');

        // Ensure contact group exists (unless 'all')
        if ($this->input('contacts') !== 'all') {
            $contactGroupExists = \App\Models\ContactGroup::where('uuid', $this->input('contacts'))
                ->where('workspace_id', session()->get('current_workspace'))
                ->exists();

            if (!$contactGroupExists) {
                $validator->errors()->add('contacts', 'Selected contact group not found');
            }
        }

        // Validate provider session compatibility
        $sessionId = $this->input('whatsapp_session_id');
        $provider = $this->input('preferred_provider', 'webjs');

        if ($sessionId) {
            $session = \App\Models\WhatsAppSession::find($sessionId);
            if (!$session || $session->workspace_id !== session()->get('current_workspace')) {
                $validator->errors()->add('whatsapp_session_id', 'Invalid WhatsApp session selected');
            } elseif ($session->provider_type !== $provider) {
                $validator->errors()->add('preferred_provider', 'Selected provider does not match the WhatsApp session type');
            }
        }
    }

    /**
     * Validate template compatibility with selected provider
     */
    private function validateTemplateCompatibility($validator): void
    {
        if ($this->input('campaign_type') !== 'template') {
            return;
        }

        $templateUuid = $this->input('template');
        $provider = $this->input('preferred_provider', 'webjs');

        $template = \App\Models\Template::where('uuid', $templateUuid)
            ->where('workspace_id', session()->get('current_workspace'))
            ->first();

        if (!$template) {
            return; // Already handled by existence validation
        }

        // Check template status
        if ($template->status !== 'APPROVED' && $provider === 'meta_api') {
            $validator->errors()->add('template', 'Template must be APPROVED for Meta Business API usage');
        }

        // Check media compatibility
        if (in_array($template->header_type, ['image', 'document', 'video'])) {
            if (!$template->header_media && $provider === 'meta_api') {
                $validator->errors()->add('template', 'Template with media requires media file for Meta Business API');
            }
        }

        // Check button limitations
        $buttonCount = is_array($template->buttons_data) ? count($template->buttons_data) : 0;
        if ($buttonCount > 10) {
            $validator->errors()->add('template', 'Template has too many buttons (maximum 10 allowed)');
        }
    }

    /**
     * Validate direct message structure
     */
    private function validateDirectMessageStructure($validator): void
    {
        if ($this->input('campaign_type') !== 'direct') {
            return;
        }

        $headerType = $this->input('header_type', 'text');
        $bodyText = $this->input('body_text', '');
        $buttons = $this->input('buttons', []);

        // Validate message length
        $totalLength = strlen($bodyText) + strlen($this->input('header_text', '')) + strlen($this->input('footer_text', ''));
        if ($totalLength > 4096) {
            $validator->errors()->add('body_text', 'Total message content exceeds WhatsApp limits (4096 characters)');
        }

        // Validate button structure
        if (!empty($buttons)) {
            $replyButtons = collect($buttons)->where('type', 'reply')->count();
            $actionButtons = collect($buttons)->where('type', '!=', 'reply')->count();

            if ($replyButtons > 3) {
                $validator->errors()->add('buttons', 'Maximum 3 reply buttons allowed');
            }

            if ($actionButtons > 2) {
                $validator->errors()->add('buttons', 'Maximum 2 action buttons (URL/Phone) allowed');
            }

            if ($replyButtons > 0 && $actionButtons > 0) {
                $validator->errors()->add('buttons', 'Cannot mix reply buttons with action buttons');
            }
        }

        // Media validation for WebJS compatibility
        if ($headerType !== 'text' && $this->hasFile('header_media')) {
            $file = $this->file('header_media');

            if ($headerType === 'image' && $file->getSize() > 5 * 1024 * 1024) { // 5MB
                $validator->errors()->add('header_media', 'Image file size should not exceed 5MB for WhatsApp Web JS');
            }

            if ($headerType === 'video' && $file->getSize() > 16 * 1024 * 1024) { // 16MB
                $validator->errors()->add('header_media', 'Video file size should not exceed 16MB for WhatsApp');
            }
        }
    }

    /**
     * Validate scheduling logic
     */
    private function validateScheduleLogic($validator): void
    {
        $skipSchedule = $this->boolean('skip_schedule', false);
        $scheduledAt = $this->input('scheduled_at');

        if (!$skipSchedule && !$scheduledAt) {
            $validator->errors()->add('scheduled_at', 'Schedule time is required when not sending immediately');
        }

        if ($scheduledAt && !$skipSchedule) {
            $scheduledTime = \Carbon\Carbon::parse($scheduledAt);
            $now = \Carbon\Carbon::now();

            if ($scheduledTime->lt($now)) {
                $validator->errors()->add('scheduled_at', 'Schedule time cannot be in the past');
            }

            if ($scheduledTime->gt($now->addDays(30))) {
                $validator->errors()->add('scheduled_at', 'Schedule time cannot be more than 30 days in the future');
            }
        }
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}