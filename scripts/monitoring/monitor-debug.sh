#!/bin/bash
echo "════════════════════════════════════════════════════════"
echo "  MONITORING DEBUG LOGS (Press Ctrl+C to stop)"
echo "════════════════════════════════════════════════════════"
echo ""
echo "Waiting for WhatsApp messages..."
echo ""

tail -f storage/logs/laravel.log | grep --line-buffered -E "(🔍|WhatsApp message received|✅|ERROR)" --color=always

