<?php

namespace App\Http\Requests;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user belongs to the workspace of the contact
        $contactId = $this->input('uuid');
        $contact = \App\Models\Contact::where('uuid', $contactId)->first();

        if (!$contact) {
            return false;
        }

        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        return $user->teams()->where('workspace_id', $contact->workspace_id)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'uuid' => 'required|string|exists:contacts,uuid',
            'message' => 'required_without:file|string|max:4096',
            'type' => 'required|string|in:text,image,video,audio,document',
            'file' => [
                'nullable',
                'file',
                'max:10240', // 10MB max
                'mimes:jpg,jpeg,png,gif,mp4,mpeg,mp3,wav,pdf,doc,docx,xls,xlsx,ppt,pptx'
            ],
            'optimistic_id' => 'nullable|string|max:255', // For optimistic UI tracking
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'uuid.required' => 'Contact ID is required.',
            'uuid.exists' => 'Contact not found.',
            'message.required_without' => 'Message text is required when no file is provided.',
            'message.max' => 'Message cannot exceed 4096 characters.',
            'type.required' => 'Message type is required.',
            'type.in' => 'Invalid message type.',
            'file.max' => 'File cannot be larger than 10MB.',
            'file.mimes' => 'File type is not supported.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'uuid' => 'contact',
            'message' => 'message text',
            'type' => 'message type',
            'file' => 'file attachment',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->input('type', 'text'), // Default to text type
        ]);

        // Remove HTML tags from message for security
        if ($this->has('message')) {
            $this->merge([
                'message' => strip_tags($this->input('message'))
            ]);
        }
    }

    /**
     * Get validated data with additional processing
     */
    public function getValidatedWithAdditional(): array
    {
        $data = $this->validated();

        // Add workspace information
        $contact = \App\Models\Contact::where('uuid', $data['uuid'])->first();
        $data['workspace_id'] = $contact->workspace_id;
        $data['contact_id'] = $contact->id;
        $data['user_id'] = Auth::id();

        // Process file if present
        if ($this->hasFile('file')) {
            $file = $this->file('file');
            $data['file_info'] = [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'extension' => $file->getClientOriginalExtension(),
            ];
        }

        return $data;
    }
}
