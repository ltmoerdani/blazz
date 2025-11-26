<?php

namespace App\Helpers;
use Cache;

use DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Models\Addon;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\WhatsAppAccount;
use App\Models\Workspace;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use NumberFormatter;
use Symfony\Component\Mime\Part\HtmlPart;

class CustomHelper {
    public static function config($key){
        $config = Setting::where('key', $key)->first();

        if($config){
            return $config->value;
        } else {
            return null;
        }
    }

    public static function isModuleEnabled($module, $workspaceId = null){
        $addon = Addon::where('name', $module)
            ->where('status', 1)
            ->where('is_active', 1)
            ->exists();

        $orgId = $workspaceId != null ? $workspaceId : session()->get('current_workspace');

        $subscription = Subscription::with('plan')
            ->where('workspace_id', $orgId)
            ->first();

        if($addon){
            if($subscription->status === 'trial' && $subscription->valid_until > now()){
                return true; //Allow user to test features during trial period
            }

            // Ensure a subscription and its plan exist
            if (!$subscription || !$subscription->plan) {
                return false; // Subscription or plan not found
            }

            $plan = SubscriptionPlan::where('id', $subscription->plan->id)->first();

            if (!$plan) {
                return false; // Plan not found
            }

            $metadata = json_decode($plan->metadata, true);

            if (isset($metadata['addons']) && is_array($metadata['addons'])) {
                // Return true if the module exists and is set to true, otherwise false
                return isset($metadata['addons'][$module]) && $metadata['addons'][$module] === true;
            }
        }

        return false; // Default to false if addons key or module is not found
    }

    /**
     * Check if workspace has any active WhatsApp connection
     * 
     * This method checks for both:
     * 1. Meta Business API connection (from workspace.metadata['whatsapp'])
     * 2. WhatsApp Web.js connection (from whatsapp_accounts table with status 'connected')
     * 
     * Note: Accounts with status 'qr_scanning', 'initializing', 'failed', 'disconnected' 
     * are NOT considered as "connected" - they need setup completion.
     * 
     * @param int|null $workspaceId The workspace ID to check. If null, uses current session workspace
     * @return bool True if any active WhatsApp connection exists, false otherwise
     */
    public static function hasAnyWhatsAppConnection($workspaceId = null): bool
    {
        $workspaceId = $workspaceId ?? session()->get('current_workspace');

        if (!$workspaceId) {
            return false;
        }

        // Check 1: Meta Business API connection from workspace metadata
        $workspace = Workspace::find($workspaceId);
        if ($workspace) {
            $metadata = $workspace->metadata ? json_decode($workspace->metadata, true) : [];
            
            // Check if Meta API WhatsApp credentials exist and have access token
            if (isset($metadata['whatsapp']) && !empty($metadata['whatsapp']['access_token'])) {
                return true;
            }
        }

        // Check 2: WhatsApp Web.js connection from whatsapp_accounts table
        // Only 'connected' status means the WhatsApp is fully functional
        $hasConnectedWebJS = WhatsAppAccount::where('workspace_id', $workspaceId)
            ->where('status', 'connected')
            ->where('is_active', true)
            ->exists();

        return $hasConnectedWebJS;
    }

    /**
     * Check if workspace needs WhatsApp setup
     * 
     * Returns true if workspace has NO active WhatsApp connections
     * This is the inverse of hasAnyWhatsAppConnection() for clearer semantics
     * 
     * @param int|null $workspaceId The workspace ID to check
     * @return bool True if setup is needed, false if already connected
     */
    public static function needsWhatsAppSetup($workspaceId = null): bool
    {
        return !self::hasAnyWhatsAppConnection($workspaceId);
    }
}
