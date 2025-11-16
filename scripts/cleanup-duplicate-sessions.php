<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🧹 Cleaning up duplicate WhatsApp sessions...\n\n";

// Find duplicates
$duplicates = DB::table('whatsapp_accounts')
    ->select(
        'phone_number', 
        'workspace_id', 
        'status', 
        DB::raw('COUNT(*) as count'), 
        DB::raw('GROUP_CONCAT(id ORDER BY id) as ids')
    )
    ->whereNotNull('phone_number')
    ->where('phone_number', '!=', '')
    ->groupBy('phone_number', 'workspace_id', 'status')
    ->having('count', '>', 1)
    ->get();

if ($duplicates->isEmpty()) {
    echo "✅ No duplicates found!\n";
    exit(0);
}

echo "Found {$duplicates->count()} duplicate group(s):\n\n";

foreach($duplicates as $dup) {
    echo "Phone: {$dup->phone_number}, Workspace: {$dup->workspace_id}, Status: {$dup->status}\n";
    echo "  IDs: {$dup->ids} (count: {$dup->count})\n";
    
    $ids = explode(',', $dup->ids);
    $keepId = (int)$ids[0]; // Keep first/oldest
    $deleteIds = array_slice($ids, 1);
    
    if (!empty($deleteIds)) {
        $deleteIds = array_map('intval', $deleteIds);
        
        echo "  Keeping ID: {$keepId}\n";
        echo "  Deleting IDs: " . implode(', ', $deleteIds) . "\n";
        
        $deleted = DB::table('whatsapp_accounts')
            ->whereIn('id', $deleteIds)
            ->delete();
        
        echo "  ✓ Deleted {$deleted} record(s)\n\n";
    }
}

echo "✅ Cleanup completed!\n";
echo "\nNow run: php artisan migrate --path=database/migrations/2025_11_16_050000_cleanup_orphaned_sessions_and_add_constraints.php\n";
