#!/bin/bash

# Test script untuk mensimulasikan pesan masuk dari berbagai skenario
# Usage: ./test-realtime-scenarios.sh

echo "========================================="
echo "🧪 Testing Real-time WebSocket Scenarios"
echo "========================================="
echo ""
echo "Prerequisites:"
echo "1. Browser is open on chats page"
echo "2. Browser console is open (F12)"
echo "3. Reverb server is running"
echo ""

WORKSPACE_ID=1
CONTACT_ID_1=1
CONTACT_ID_2=2

echo "📋 Test Scenarios:"
echo "  1. No chat active → badge update"
echo "  2. Viewing Chat 1 → thread update"
echo "  3. Viewing Chat 1, message to Chat 2 → badge update"
echo ""

read -p "Press Enter to start testing..."

# Scenario 1: No chat active
echo ""
echo "========================================="
echo "📍 Scenario 1: No chat active"
echo "========================================="
echo "Instructions: Close any active chat (click away from contacts)"
echo ""
read -p "Ready? Press Enter to send message to Contact 1..."

php artisan tinker --execute="
echo '🚀 Sending message to Contact $CONTACT_ID_1...' . PHP_EOL;
\$chatData = [[
    'type' => 'chat',
    'value' => [
        'id' => 1000 + time(),
        'wam_id' => 'test-scenario1-' . time(),
        'message' => '📍 Scenario 1: Message when no chat active',
        'type' => 'inbound',
        'message_status' => 'delivered',
        'created_at' => now()->toISOString(),
        'from_me' => false,
        'contact_id' => $CONTACT_ID_1,
    ]
]];
event(new \App\Events\NewChatEvent(\$chatData, $WORKSPACE_ID));
echo '✅ Message sent!' . PHP_EOL;
echo '👀 Check browser: Badge should update WITHOUT opening chat' . PHP_EOL;
"

echo ""
echo "Expected Result:"
echo "✅ Badge shows +1 on Contact 1"
echo "✅ Contact 1 moves to top of list"
echo "✅ Last message preview updated"
echo ""
read -p "Did it work? (y/n): " response1

# Scenario 2: Viewing the chat
echo ""
echo "========================================="
echo "📍 Scenario 2: Viewing the chat"
echo "========================================="
echo "Instructions: Click on Contact 1 to open chat"
echo ""
read -p "Ready? Press Enter to send message to Contact 1..."

php artisan tinker --execute="
echo '🚀 Sending message to Contact $CONTACT_ID_1...' . PHP_EOL;
\$chatData = [[
    'type' => 'chat',
    'value' => [
        'id' => 2000 + time(),
        'wam_id' => 'test-scenario2-' . time(),
        'message' => '📍 Scenario 2: Message while viewing this chat',
        'type' => 'inbound',
        'message_status' => 'delivered',
        'created_at' => now()->toISOString(),
        'from_me' => false,
        'contact_id' => $CONTACT_ID_1,
    ]
]];
event(new \App\Events\NewChatEvent(\$chatData, $WORKSPACE_ID));
echo '✅ Message sent!' . PHP_EOL;
echo '👀 Check browser: Message should appear INSTANTLY in chat thread' . PHP_EOL;
"

echo ""
echo "Expected Result:"
echo "✅ Message appears immediately in chat thread"
echo "✅ Auto-scroll to bottom"
echo "✅ No badge increment (already viewing)"
echo ""
read -p "Did it work? (y/n): " response2

# Scenario 3: Viewing different chat
echo ""
echo "========================================="
echo "📍 Scenario 3: Viewing different chat"
echo "========================================="
echo "Instructions: Stay in Contact 1 chat"
echo ""
read -p "Ready? Press Enter to send message to Contact 2..."

php artisan tinker --execute="
echo '🚀 Sending message to Contact $CONTACT_ID_2...' . PHP_EOL;
\$chatData = [[
    'type' => 'chat',
    'value' => [
        'id' => 3000 + time(),
        'wam_id' => 'test-scenario3-' . time(),
        'message' => '📍 Scenario 3: Message to different contact',
        'type' => 'inbound',
        'message_status' => 'delivered',
        'created_at' => now()->toISOString(),
        'from_me' => false,
        'contact_id' => $CONTACT_ID_2,
    ]
]];
event(new \App\Events\NewChatEvent(\$chatData, $WORKSPACE_ID));
echo '✅ Message sent!' . PHP_EOL;
echo '👀 Check browser: Badge on Contact 2 should update' . PHP_EOL;
"

echo ""
echo "Expected Result:"
echo "✅ Badge shows +1 on Contact 2"
echo "✅ Contact 2 moves to top of list"
echo "✅ Last message preview updated"
echo "✅ Current chat (Contact 1) stays open"
echo ""
read -p "Did it work? (y/n): " response3

# Summary
echo ""
echo "========================================="
echo "📊 Test Results Summary"
echo "========================================="
echo "Scenario 1 (No chat active):        $response1"
echo "Scenario 2 (Viewing same chat):     $response2"
echo "Scenario 3 (Viewing different chat): $response3"
echo ""

if [[ "$response1" == "y" && "$response2" == "y" && "$response3" == "y" ]]; then
    echo "✅ ALL TESTS PASSED! Real-time messaging working perfectly!"
else
    echo "❌ Some tests failed. Check browser console for errors."
    echo ""
    echo "Debugging tips:"
    echo "1. Check if WebSocket is connected (DevTools → Network → WS)"
    echo "2. Look for 🔔 logs in browser console"
    echo "3. Check Laravel logs: tail -f storage/logs/laravel.log"
    echo "4. Verify Reverb is running: ps aux | grep reverb"
fi

echo ""
