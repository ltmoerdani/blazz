<?php

namespace App\Console\Commands;

use App\Models\WhatsAppAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncWhatsAppAccountPhone extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:sync-phone {--account-id=} {--all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync phone numbers from Node.js service for authenticated/connected accounts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $nodeServiceUrl = config('whatsapp.node_service_url', 'http://localhost:3000');
        $apiKey = config('whatsapp.node_api_key');

        $this->info('🔄 Starting WhatsApp phone number sync...');
        $this->newLine();

        // Get accounts to sync
        $query = WhatsAppAccount::whereIn('status', ['authenticated', 'connected'])
            ->whereNull('phone_number');

        if ($this->option('account-id')) {
            $query->where('id', $this->option('account-id'));
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->info('✅ No accounts need phone number sync');
            return 0;
        }

        $this->info("📋 Found {$accounts->count()} account(s) to sync");
        $this->newLine();

        $synced = 0;
        $failed = 0;

        foreach ($accounts as $account) {
            $this->info("🔍 Processing: {$account->session_id}");

            try {
                // Get session status from Node.js service
                $response = Http::timeout(10)->get("{$nodeServiceUrl}/api/sessions/{$account->session_id}/status", [
                    'api_key' => $apiKey,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data['phone_number']) && !empty($data['phone_number'])) {
                        $phoneNumber = $data['phone_number'];
                        
                        // Check for duplicate phone numbers (including soft-deleted) in the same workspace
                        // MySQL unique constraint checks ALL rows including soft-deleted ones
                        $duplicates = WhatsAppAccount::withTrashed()
                            ->where('phone_number', $phoneNumber)
                            ->where('workspace_id', $account->workspace_id)
                            ->where('id', '!=', $account->id)
                            ->get();

                        if ($duplicates->count() > 0) {
                            foreach ($duplicates as $duplicate) {
                                if ($duplicate->trashed()) {
                                    // Hard delete soft-deleted duplicates to clear constraint
                                    $this->warn("  ⚠️  Found soft-deleted duplicate (ID: {$duplicate->id}). Force deleting...");
                                    $duplicate->forceDelete();
                                } else {
                                    // Soft delete active duplicates
                                    $this->warn("  ⚠️  Found active duplicate (ID: {$duplicate->id}). Soft deleting...");
                                    $duplicate->delete();
                                }
                            }
                            $this->info("  ✅ Cleaned up {$duplicates->count()} duplicate(s)");
                        }
                        
                        $account->update([
                            'phone_number' => $phoneNumber,
                            'status' => $data['status'] ?? $account->status,
                        ]);
                        
                        $this->info("  ✅ Synced: {$phoneNumber}");
                        $synced++;
                    } else {
                        $this->warn("  ⚠️  No phone number available from Node.js service");
                        $failed++;
                    }
                } else {
                    $this->error("  ❌ Failed to get status from Node.js: " . $response->status());
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Error: " . $e->getMessage());
                Log::error('Failed to sync phone number', [
                    'account_id' => $account->id,
                    'session_id' => $account->session_id,
                    'error' => $e->getMessage()
                ]);
                $failed++;
            }

            $this->newLine();
        }

        $this->newLine();
        $this->info("📊 Summary:");
        $this->info("  ✅ Synced: {$synced}");
        $this->info("  ❌ Failed: {$failed}");
        $this->info("  📋 Total: {$accounts->count()}");

        return 0;
    }
}
