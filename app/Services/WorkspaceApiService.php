<?php

namespace App\Services;

use App\Http\Resources\NotificationsResource;
use App\Models\WorkspaceApiKey;
use Str;

class WorkspaceApiService
{
    public function generate(object $request)
    {
        WorkspaceApiKey::create([
            'workspace_id' => session()->get('current_workspace'),
            'token' => Str::random(40)
        ]);
    }

    public function destroy($uuid)
    {
        WorkspaceApiKey::where('uuid', $uuid)->update([
            'deleted_at' => now()
        ]);
    }
}