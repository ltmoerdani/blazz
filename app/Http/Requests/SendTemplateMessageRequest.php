<?php

namespace App\Http\Requests;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SendTemplateMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user belongs to the workspace
        $workspaceId = $this->input('workspace_id');

        if (!$workspaceId) {
            return false;
        }

        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        return $user->teams()->where('workspace_id', $workspaceId)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'workspace_id' => 'required|integer|exists:workspaces,id',
            'contacts' => 'required|array|min:1|max:100',
            'contacts.*' => 'string|exists:contacts,uuid',
            'template_name' => 'required|string|max:255',
            'template_namespace' => 'nullable|string|max:255',
            'language' => 'required|string|size:2',
            'components' => 'required|array',
            'components.*.type' => 'required|string|in:header,body,footer,buttons',
            'components.*.text' => 'required_if:components.*.type,body|string|max:1024',
            'components.*.format' => 'nullable|string|in:PLAIN,TEXT,BOLD,ITALIC,UNDERLINE,STRIKETHROUGH,MONOSPACE',
            'components.*.buttons' => 'required_if:components.*.type,buttons|array|max:10',
            'components.*.buttons.*.type' => 'required|string|in:URL,PHONE_NUMBER,QUICK_REPLY,CATALOG',
            'components.*.buttons.*.text' => 'required|string|max:25',
            'components.*.buttons.*.url' => 'required_if:components.*.buttons.*.type,URL|url|max:2000',
            'components.*.buttons.*.phone_number' => 'required_if:components.*.buttons.*.type,PHONE_NUMBER|string|max:20',
            'variables' => 'nullable|array',
            'variables.*.name' => 'required_with:variables.*|string|max:100',
            'variables.*.value' => 'required_with:variables.*|string|max:1024',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'workspace_id.required' => 'Workspace ID is required.',
            'workspace_id.exists' => 'Workspace not found.',
            'contacts.required' => 'At least one contact is required.',
            'contacts.max' => 'Cannot send to more than 100 contacts at once.',
            'template_name.required' => 'Template name is required.',
            'language.required' => 'Language code is required.',
            'language.size' => 'Language code must be exactly 2 characters.',
            'components.required' => 'Message components are required.',
            'components.*.type.in' => 'Invalid component type.',
            'components.*.text.required_if' => 'Text is required for body components.',
            'components.*.buttons.max' => 'Cannot have more than 10 buttons per message.',
            'components.*.buttons.*.type.in' => 'Invalid button type.',
            'components.*.buttons.*.text.max' => 'Button text cannot exceed 25 characters.',
            'variables.*.name.required_with' => 'Variable name is required.',
            'variables.*.value.required_with' => 'Variable value is required.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'workspace_id' => 'workspace',
            'contacts' => 'contacts',
            'template_name' => 'template name',
            'language' => 'language code',
            'components' => 'message components',
            'variables' => 'template variables',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize all text inputs
        $components = $this->input('components', []);

        foreach ($components as $index => $component) {
            if (isset($component['text'])) {
                $components[$index]['text'] = strip_tags($component['text']);
            }

            if (isset($component['buttons'])) {
                foreach ($component['buttons'] as $btnIndex => $button) {
                    if (isset($button['text'])) {
                        $components[$index]['buttons'][$btnIndex]['text'] = strip_tags($button['text']);
                    }
                }
            }
        }

        $this->merge(['components' => $components]);

        // Sanitize variables
        $variables = $this->input('variables', []);
        foreach ($variables as $index => $variable) {
            if (isset($variable['value'])) {
                $variables[$index]['value'] = strip_tags($variable['value']);
            }
        }
        $this->merge(['variables' => $variables]);
    }

    /**
     * Get validated data with additional processing
     */
    public function getValidatedWithAdditional(): array
    {
        $data = $this->validated();

        // Add user information
        $data['user_id'] = Auth::id();
        $data['total_contacts'] = count($data['contacts']);

        // Process contact UUIDs to IDs
        $contacts = \App\Models\Contact::whereIn('uuid', $data['contacts'])->get();
        $data['contact_ids'] = $contacts->pluck('id')->toArray();

        // Validate all contacts belong to the same workspace
        $workspaceContacts = $contacts->where('workspace_id', $data['workspace_id']);
        if ($workspaceContacts->count() !== count($contacts)) {
            throw new \Illuminate\Validation\ValidationException(
                'Some contacts do not belong to the specified workspace'
            );
        }

        // Format template data for WhatsApp API
        $data['formatted_template'] = [
            'name' => $data['template_name'],
            'language' => [
                'code' => $data['language']
            ],
            'components' => $data['components']
        ];

        if (!empty($data['template_namespace'])) {
            $data['formatted_template']['namespace'] = $data['template_namespace'];
        }

        if (!empty($data['variables'])) {
            $data['formatted_template']['components']['body'] = [
                'parameters' => collect($data['variables'])->map(function ($variable) {
                    return [
                        'type' => 'text',
                        'text' => $variable['value']
                    ];
                })->toArray()
            ];
        }

        return $data;
    }
}
