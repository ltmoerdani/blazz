<?php

namespace App\Services;

use App\Models\Addon;
use App\Exceptions\SecurityDisabledException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class ModuleService
{
    /**
     * Install addon - Disabled for security
     * External addon installation has been disabled for security reasons
     */
    public function install(Request $request)
    {
        // External addon installation disabled for security
        throw new SecurityDisabledException('External addon installation has been disabled for security. Please install modules manually.');
    }

    /**
     * Update addon - Disabled for security
     * External addon updates have been disabled for security reasons
     */
    public function update(Request $request)
    {
        // External addon updates disabled for security
        throw new SecurityDisabledException('External addon updates have been disabled for security. Please update modules manually.');
    }

    /**
     * Download addon files - Disabled for security
     */
    protected function downloadAddonFiles($addonName, $zipFilePath)
    {
        // External addon downloads have been disabled for security
        throw new SecurityDisabledException('External addon downloads have been disabled for security. Please install modules manually.');
    }

    /**
     * Update addon files - Disabled for security
     */
    protected function updateAddonFiles($addonName, $zipFilePath)
    {
        // External addon updates have been disabled for security
        throw new SecurityDisabledException('External addon updates have been disabled for security. Please update modules manually.');
    }

    /**
     * Setup addon metadata - Disabled for security
     */
    protected function setupAddonMetadata($addonName)
    {
        // External metadata retrieval has been disabled for security
        throw new SecurityDisabledException('External metadata retrieval has been disabled for security. Please configure modules manually.');
    }

    /**
     * Extract addon files - Disabled for security
     */
    protected function extractAddonFiles($zipFilePath)
    {
        // File extraction disabled for security without proper validation
        throw new SecurityDisabledException('Addon file extraction has been disabled for security. Please extract modules manually.');
    }

    /**
     * Handle errors with proper logging
     */
    protected function handleError(\Exception $e, $zipFilePath = null)
    {
        // Clean up zip file if exists
        if ($zipFilePath && file_exists($zipFilePath)) {
            unlink($zipFilePath);
        }

        // Log error for debugging
        Log::error("ModuleService error: " . $e->getMessage());
        
        return Redirect::back()->with('status', [
            'type' => 'error',
            'message' => 'Module operation failed: ' . $e->getMessage()
        ])->withInput();
    }
}