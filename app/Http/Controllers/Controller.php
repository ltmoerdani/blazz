<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Helpers\WorkspaceHelper;
use App\Models\Workspace;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Get current workspace ID from session
     * 
     * @return int
     * @throws \Exception if no workspace context available
     */
    protected function getWorkspaceId(): int
    {
        $workspaceId = session('current_workspace');
        
        if (!$workspaceId) {
            throw new \Exception('No workspace context available. User may not be authenticated.');
        }
        
        return $workspaceId;
    }
    
    /**
     * Get current workspace ID or null (safe variant)
     * 
     * Use this when workspace context is optional
     * 
     * @return int|null
     */
    protected function getWorkspaceIdOrNull(): ?int
    {
        return session('current_workspace');
    }
    
    /**
     * Get current workspace model
     * 
     * @return Workspace
     * @throws \Exception if workspace not found
     */
    protected function getCurrentWorkspace(): Workspace
    {
        return WorkspaceHelper::getCurrentWorkspace();
    }
    
    /**
     * Get current workspace model or null (safe variant)
     * 
     * Use this when workspace context is optional
     * 
     * @return Workspace|null
     */
    protected function getCurrentWorkspaceOrNull(): ?Workspace
    {
        $workspaceId = $this->getWorkspaceIdOrNull();
        
        if (!$workspaceId) {
            return null;
        }
        
        return Workspace::find($workspaceId);
    }
}
