<?php 

namespace App\Helpers;

use App\Helpers\CustomHelper;
use App\Models\Addon;
use App\Models\Setting;

class WebhookHelper
{
    public static function triggerWebhookEvent($event, $data, $workspaceId = NULL)
    {
        $workspaceId = $workspaceId = NULL ? session()->get('current_workspace') : $workspaceId;

        if(CustomHelper::isModuleEnabled('Webhooks', $workspaceId)){
            // Check if MainController exists
            if (class_exists(\Modules\Webhook\Controllers\MainController::class)) {
                $webhookController = new \Modules\Webhook\Controllers\MainController();
                return $webhookController->trigger($event, $workspaceId, $data);
            }

            // Handle the case where the class doesn't exist
            return response()->json(['error' => 'Webhook controller not found'], 404);
        }
    }
}
