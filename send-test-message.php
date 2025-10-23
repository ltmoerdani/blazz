<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get connected session
$session = \App\Models\WhatsAppSession::where('status', 'connected')
    ->where('workspace_id', 1)
    ->first();

if (!$session) {
    echo "No connected session!\n";
    exit(1);
}

// Send test message
$adapter = new \App\Services\Adapters\WebJSAdapter(1, $session);

// Ganti dengan nomor HP Anda yang mau di-test
$testPhone = '6282146291472';  // Format: 62xxx (tanpa +)

echo "Sending test message to: +$testPhone\n";
echo "From session: {$session->phone_number}\n\n";

$result = $adapter->sendMessage(
    new \App\Models\Contact(['phone' => "+$testPhone"]),
    "Test message dari Blazz - " . date('H:i:s'),
    1
);

echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
