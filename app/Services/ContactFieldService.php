<?php

namespace App\Services;

use App\Http\Resources\ContactFieldResource;
use App\Models\ContactField;
use Illuminate\Support\Facades\Auth;

class ContactFieldService
{
    private $workspaceId;
    
    public function __construct($workspaceId = null)
    {
        $this->workspaceId = $workspaceId;
    }

    /**
     * Get all contact fields based on the provided request filters.
     *
     * @param Request $request
     * @return mixed
     */
    public function get()
    {
        $rows = ContactField::where('workspace_id', $this->workspaceId)
            ->where('deleted_at', null)->latest()->paginate(5);

        return ContactFieldResource::collection($rows);
    }

    public function getByUuid($uuid = null)
    {
        return ContactField::where('workspace_id', $this->workspaceId)->where('uuid', $uuid)->first();
    }

    /**
     * Store Contact Field
     *
     * @param Request $request
     * @param string $uuid
     * @return \App\Models\ContactField
     */
    public function store(object $request, $uuid = null)
    {
        $last_position = ContactField::where('workspace_id', $this->workspaceId)->where('deleted_at', null)->count();

        $field = $uuid === null ? new ContactField() : ContactField::where('uuid', $uuid)->firstOrFail();
        $field->Workspace_id = $this->workspaceId;
        $field->name = $request->name;
        $field->type = $request->component;

        if($uuid === null){
            $field->position = $last_position + 1;
        }

        if($request->component === 'select'){
            $transformedString = collect($request->options)->pluck('value')->implode(', ');
            $field->value = $transformedString;
        } elseif($request->component === 'input'){
            $field->value = $request->type;
        } else {
            $field->value = null;
        }

        $field->required = $request->required;
        $field->save();

        return $field;
    }

    /**
     * Delete ContactField
     *
     * @param Request $request
     * @param string $uuid
     * @return \App\Models\ContactField
     */
    public function delete($uuid)
    {
        return ContactField::where('uuid', $uuid)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => Auth::id()
        ]);
    }
}
