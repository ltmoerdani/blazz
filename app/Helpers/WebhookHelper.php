<?php

namespace App\Helpers;

use App\Helpers\CustomHelper;
use App\Models\Addon;
use App\Models\Setting;

class WebhookHelper
{
    public static function triggerWebhookEvent($event, $data, $workspaceId = null)
    {
        $workspaceId = $workspaceId = null ? session()->get('current_workspace') : $workspaceId;

        if(CustomHelper::isModuleEnabled('Webhooks', $workspaceId)){
            // Check if MainController exists
            $controllerClass = '\Modules\Webhook\Controllers\MainController';
            
            if (class_exists($controllerClass)) {
                /** @var object $webhookController */
                $webhookController = new $controllerClass();
                return $webhookController->trigger($event, $workspaceId, $data);
            }

            // Handle the case where the class doesn't exist
            return response()->json(['error' => 'Webhook controller not found'], 404);
        }
    }
}
