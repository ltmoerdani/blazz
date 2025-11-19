#!/usr/bin/env php
<?php

/**
 * Test script untuk campaign dengan skip_schedule = true
 * 
 * Usage: php test-campaign-immediate.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Campaign;
use App\Models\ContactGroup;
use App\Models\WhatsAppAccount;
use App\Jobs\SendCampaignJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== TEST CAMPAIGN IMMEDIATE DISPATCH ===\n\n";

// Get workspace
$workspaceId = 1; // Adjust this
echo "Using Workspace ID: {$workspaceId}\n";

// Check WhatsApp accounts
$whatsappAccounts = WhatsAppAccount::where('workspace_id', $workspaceId)
    ->where('is_active', true)
    ->get();

echo "Found " . $whatsappAccounts->count() . " active WhatsApp account(s)\n";

foreach ($whatsappAccounts as $account) {
    echo "  - ID: {$account->id}, Phone: {$account->phone_number}, Provider: {$account->provider_type}, Status: {$account->status}\n";
}

if ($whatsappAccounts->isEmpty()) {
    echo "\n❌ ERROR: No active WhatsApp accounts found!\n";
    echo "Please make sure you have at least one active WhatsApp account.\n";
    exit(1);
}

// Get contact groups
$contactGroups = ContactGroup::where('workspace_id', $workspaceId)->get();
echo "\nFound " . $contactGroups->count() . " contact group(s)\n";

if ($contactGroups->isEmpty()) {
    echo "❌ ERROR: No contact groups found!\n";
    exit(1);
}

$firstGroup = $contactGroups->first();
echo "Using Contact Group: {$firstGroup->name} (ID: {$firstGroup->id})\n";

// Create test campaign
echo "\n--- Creating Test Campaign ---\n";

$campaignData = [
    'workspace_id' => $workspaceId,
    'name' => 'Test Campaign - ' . now()->format('Y-m-d H:i:s'),
    'campaign_type' => 'direct',
    'preferred_provider' => 'webjs',
    'contact_group_id' => $firstGroup->id,
    'whatsapp_account_id' => $whatsappAccounts->first()->id,
    'status' => 'scheduled',
    'scheduled_at' => now(),
    'header_type' => 'text',
    'header_text' => 'Test Header',
    'body_text' => 'This is a test message from campaign testing script. Timestamp: ' . now()->toDateTimeString(),
    'footer_text' => 'Test Footer',
    'buttons_data' => json_encode([]),
    'metadata' => json_encode([
        'campaign_type' => 'direct',
        'test_mode' => true
    ]),
    'created_by' => 2, // Adjust user ID
];

try {
    $campaign = Campaign::create($campaignData);
    
    echo "✅ Campaign created successfully!\n";
    echo "   Campaign ID: {$campaign->id}\n";
    echo "   Campaign UUID: {$campaign->uuid}\n";
    echo "   Status: {$campaign->status}\n";
    echo "   Scheduled At: {$campaign->scheduled_at}\n";
    
    // Dispatch job immediately
    echo "\n--- Dispatching SendCampaignJob ---\n";
    
    SendCampaignJob::dispatch($campaign->id)
        ->onQueue('whatsapp-campaign');
    
    echo "✅ Job dispatched to queue: whatsapp-campaign\n";
    
    // Check if job is in queue
    sleep(1);
    
    $jobCount = DB::table('jobs')
        ->where('queue', 'whatsapp-campaign')
        ->where('payload', 'like', '%SendCampaignJob%')
        ->count();
    
    echo "\n--- Queue Status ---\n";
    echo "Jobs in whatsapp-campaign queue: {$jobCount}\n";
    
    if ($jobCount > 0) {
        echo "✅ Job is in queue and ready to be processed!\n";
    } else {
        echo "⚠️  Job might have been processed already or there's an issue.\n";
    }
    
    echo "\n--- What's Next? ---\n";
    echo "1. Make sure queue worker is running:\n";
    echo "   php artisan queue:work --queue=whatsapp-urgent,whatsapp-high,whatsapp-normal,whatsapp-campaign\n\n";
    echo "2. Monitor the campaign:\n";
    echo "   - Campaign ID: {$campaign->id}\n";
    echo "   - Check logs: tail -f storage/logs/laravel.log\n";
    echo "   - Check database: SELECT * FROM campaigns WHERE id = {$campaign->id};\n\n";
    echo "3. Monitor campaign logs:\n";
    echo "   SELECT * FROM campaign_logs WHERE campaign_id = {$campaign->id};\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== TEST COMPLETED ===\n";
