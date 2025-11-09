#!/bin/bash

echo "=========================================="
echo "CHECKING WEBHOOK PROCESSING"
echo "=========================================="
echo ""

echo "1. Latest WhatsApp webhook logs:"
echo "-----------------------------------"
tail -50 storage/logs/laravel.log | grep -A 5 "WhatsApp message received via WebJS" | tail -30

echo ""
echo "2. Processing logs (if any):"
echo "-----------------------------------"
tail -50 storage/logs/laravel.log | grep -E "(Processing WhatsApp|Contact provisioned|processed successfully)" | tail -10

echo ""
echo "3. Error logs (if any):"
echo "-----------------------------------"
tail -50 storage/logs/laravel.log | grep -E "(ERROR|Failed|Exception)" | tail -10

echo ""
echo "4. Database state:"
echo "-----------------------------------"
php artisan tinker --execute="
    echo 'Contacts: ' . \App\Models\Contact::count() . PHP_EOL;
    echo 'Chats: ' . \App\Models\Chat::count() . PHP_EOL;
    echo 'Contact Sessions: ' . \App\Models\ContactSession::count() . PHP_EOL;

    \$latest = \App\Models\Contact::latest()->first();
    if (\$latest) {
        echo 'Latest contact: ' . \$latest->phone . ' (' . \$latest->full_name . ')' . PHP_EOL;
    }
"

echo ""
echo "=========================================="
