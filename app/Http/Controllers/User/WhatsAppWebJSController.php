<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WhatsAppWebJSController extends Controller
{
    /**
     * Display WhatsApp Web JS setup page
     */
    public function setup(Request $request)
    {
        $workspaceId = session()->get('current_workspace');
        if (!$workspaceId) {
            return redirect('/select-workspace');
        }
        $workspace = \App\Models\Workspace::find($workspaceId);

        // Get broadcast settings for frontend Echo configuration
        $broadcastDriver = Setting::where('key', 'broadcast_driver')->value('value') ?: 'reverb';
        
        $pusherSettings = [
            'pusher_app_key' => Setting::where('key', 'pusher_app_key')->value('value') ?: '',
            'pusher_app_cluster' => Setting::where('key', 'pusher_app_cluster')->value('value') ?: 'mt1',
        ];
        
        $reverbSettings = [
            'reverb_app_key' => Setting::where('key', 'reverb_app_key')->value('value') ?: '',
            'reverb_host' => Setting::where('key', 'reverb_host')->value('value') ?: 'localhost',
            'reverb_port' => Setting::where('key', 'reverb_port')->value('value') ?: '8080',
            'reverb_scheme' => Setting::where('key', 'reverb_scheme')->value('value') ?: 'http',
        ];

        return Inertia::render('User/Settings/WhatsAppSetup', [
            'workspace' => $workspace,
            'broadcastDriver' => $broadcastDriver,
            'pusherSettings' => $pusherSettings,
            'reverbSettings' => $reverbSettings,
            'title' => 'WhatsApp Web JS Setup',
        ]);
    }
}
