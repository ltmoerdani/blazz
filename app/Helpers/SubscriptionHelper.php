<?php

namespace App\Helpers;

use App\Models\Setting;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SubscriptionHelper
{
    public static function status(string $workspaceId)
    {
        $subscription = Subscription::where('workspace_id', $workspaceId)->first();

        if($subscription){
            return $subscription->status;
        } else {
            return 'trial';
        }
    }

    public static function info(string $workspaceId)
    {
        return Subscription::where('workspace_id', $workspaceId)->first();
    }

    public static function isActive(string $workspaceId)
    {
        $subscription = Subscription::where('workspace_id', $workspaceId)->first();

        if ($subscription && $subscription->valid_until >= now()) {
            return true;
        }
    
        return false;
    }
}

