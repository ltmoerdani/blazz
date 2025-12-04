<?php

namespace App\Helpers;

use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;

class WorkspaceHelper
{
    /**
     * Get current workspace from session/auth
     *
     * @return workspace
     */
    public static function getCurrentWorkspace(): workspace
    {
        $workspaceId = session()->get('current_workspace')
            ?? Auth::user()?->teams->first()?->workspace_id
            ?? 1;

        return workspace::findOrFail($workspaceId);
    }

    /**
     * Get current workspace ID
     *
     * @return int
     */
    public static function getCurrentWorkspaceId(): int
    {
        return self::getCurrentWorkspace()->id;
    }

    /**
     * Check if workspace exists and is accessible
     *
     * @param int $workspaceId
     * @return bool
     */
    public static function isWorkspaceAccessible(int $workspaceId): bool
    {
        try {
            $workspace = workspace::findOrFail($workspaceId);

            // Check if current user has access to this workspace
            if (Auth::check()) {
                return Auth::user()->teams()->where('workspace_id', $workspaceId)->exists();
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Set current workspace in session
     *
     * @param int $workspaceId
     * @return void
     */
    public static function setCurrentWorkspace(int $workspaceId): void
    {
        if (self::isWorkspaceAccessible($workspaceId)) {
            session()->put('current_workspace', $workspaceId);
        }
    }
}