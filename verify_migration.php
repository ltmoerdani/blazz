<?php
try {
    echo "--- Starting Verification ---\n";
    
    // 1. Check Backfill
    $nullCount = App\Models\WhatsAppAccount::whereNull('assigned_instance_index')->count();
    echo "Records with NULL assigned_instance_index: " . $nullCount . " (Should be 0)\n";

    // 2. Check Instance 0 (Default)
    $instance0Count = App\Models\WhatsAppAccount::onInstance(0)->count();
    echo "Records on Instance 0: " . $instance0Count . "\n";

    // 3. Test Helper Methods
    $account = App\Models\WhatsAppAccount::first();
    if ($account) {
        echo "Found account ID: " . $account->id . "\n";
        
        // Test assignToInstance
        echo "Testing assignToInstance(1)...\n";
        $account->assignToInstance(1, 'http://test-url:3002');
        $account->refresh();
        if ($account->assigned_instance_index === 1 && $account->assigned_instance_url === 'http://test-url:3002') {
            echo "✅ assignToInstance PASSED\n";
        } else {
            echo "❌ assignToInstance FAILED\n";
        }

        // Test markDisconnected
        echo "Testing markDisconnected...\n";
        $account->markDisconnected('testing', 'Verification script');
        $account->refresh();
        if ($account->status === 'disconnected' && $account->disconnect_reason === 'testing') {
            echo "✅ markDisconnected PASSED\n";
        } else {
            echo "❌ markDisconnected FAILED\n";
        }

        // Revert changes
        echo "Reverting changes...\n";
        $account->assignToInstance(0, 'http://localhost:3001');
        $account->update([
            'status' => 'connected', 
            'disconnect_reason' => null,
            'disconnected_at' => null
        ]);
        echo "✅ Changes reverted.\n";
    } else {
        echo "⚠️ No accounts found to test methods. (This is expected if DB is empty)\n";
    }
    
    echo "--- Verification Complete ---\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
