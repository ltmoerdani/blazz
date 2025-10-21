<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactField extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $workspaceId = session()->get('current_workspace');
        $method = $this->getMethod();

        $rules = [
            'component' => 'required', // Component is required for both POST and PUT requests
        ];

        // If the component is 'input', the type field is required
        if ($this->input('component') === 'input') {
            $rules['type'] = 'required';
        }

        if($method == 'POST'){
            $rules['name'] = [
                'required',
                Rule::unique('contact_fields', 'name')->where(function ($query) use ($workspaceId) {
                    return $query->where('workspace_id', $workspaceId)
                        ->where('deleted_at', null);
                }),
            ];
        } else {
            $rules['name'] = [
                Rule::unique('contact_fields', 'name')->where(function ($query) use ($workspaceId) {
                    return $query
                        ->where('workspace_id', $workspaceId)
                        ->where('deleted_at', null)
                        ->whereNotIn('uuid', [$this->route('contact_field')]);
                }),
            ];
        }

        return $rules;
    }
}
