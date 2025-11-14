<?php
require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$session = \App\Models\WhatsAppSession::where('workspace_id', 1)
    ->where('is_active', true)
    ->latest()
    ->first();

if (!$session) {
    echo "No active session found!\n";
    exit(1);
}

echo "Initializing session via Laravel...\n";
echo "Session ID: {$session->session_id}\n";
echo "Calling Node.js API...\n";

$adapter = new \App\Services\Adapters\WebJSAdapter(1, $session);
$result = $adapter->initializeSession();

echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
