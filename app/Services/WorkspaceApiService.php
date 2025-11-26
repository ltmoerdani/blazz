<?php

namespace App\Services;

use App\Http\Resources\NotificationsResource;
use App\Models\WorkspaceApiKey;
use Illuminate\Support\Str;

class WorkspaceApiService
{
    private $workspaceId;

    public function __construct($workspaceId = null)
    {
        // Backward compatible: fallback to session if not provided
        $this->workspaceId = $workspaceId ?? session('current_workspace');
    }

    public function generate(object $request)
    {
        WorkspaceApiKey::create([
            'workspace_id' => $this->workspaceId,
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