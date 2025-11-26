<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Config;

class InstanceRouter
{
    /**
     * Get the full URL of the WhatsApp instance assigned to a workspace.
     * 
     * @param int $workspaceId
     * @return string URL like 'http://whatsapp-instance-1:3001'
     */
    public function getInstanceForWorkspace(int $workspaceId): string
    {
        $instanceIndex = $this->getInstanceIndex($workspaceId);
        return $this->getInstanceUrl($instanceIndex);
    }

    /**
     * Get the index of the WhatsApp instance assigned to a workspace.
     * Uses consistent hashing (modulo) strategy.
     * 
     * @param int $workspaceId
     * @return int Instance index (0-based)
     */
    public function getInstanceIndex(int $workspaceId): int
    {
        $instanceCount = Config::get('whatsapp.instance_count', 1);
        
        // Safety check to avoid division by zero
        if ($instanceCount < 1) {
            $instanceCount = 1;
        }

        return $workspaceId % $instanceCount;
    }

    /**
     * Get the URL for a specific instance index.
     * 
     * @param int $index
     * @return string
     */
    public function getInstanceUrl(int $index): string
    {
        // Get from config or default to localhost
        return Config::get("whatsapp.instances.{$index}", 'http://localhost:3001');
    }

    /**
     * Get all configured instances.
     * 
     * @return array [index => url]
     */
    public function getAllInstances(): array
    {
        return Config::get('whatsapp.instances', [
            0 => 'http://localhost:3001'
        ]);
    }
}
