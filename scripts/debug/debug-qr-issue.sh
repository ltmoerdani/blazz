#!/bin/bash
# Quick Debug Script - Run this if QR code not showing

echo "🔍 Quick Debugging - WhatsApp QR Code Issue"
echo "=========================================="
echo ""

# Check console for errors
echo "1️⃣ Check Browser Console:"
echo "   Look for RED error messages"
echo "   Common errors:"
echo "   - 'Failed to create session'"
echo "   - 'Network error'"
echo "   - 'Echo connection failed'"
echo ""

# Check network tab
echo "2️⃣ Check Network Tab (DevTools):"
echo "   Find: POST /settings/whatsapp-accounts"
echo "   Status should be: 200 OK"
echo "   If 500/404/403: Check Laravel logs"
echo ""

# Laravel logs
echo "3️⃣ Check Laravel Logs:"
echo "   Run: tail -n 50 storage/logs/laravel.log | grep -i error"
echo ""
tail -n 50 storage/logs/laravel.log 2>/dev/null | grep -i "error\|exception\|failed" | tail -n 10

echo ""
echo "4️⃣ Check WhatsApp Service:"
echo "   Run: curl http://127.0.0.1:3001/health"
echo ""
curl -s http://127.0.0.1:3001/health | python3 -m json.tool

echo ""
echo "5️⃣ Check Reverb Connection:"
echo "   In browser console, look for:"
echo "   'Subscribing to Echo channel: workspace.X'"
echo "   If missing: Echo not initialized"
echo ""

# Check config
echo "6️⃣ Verify Configuration:"
echo ""
echo "   WHATSAPP_NODE_SERVICE_URL:"
grep "WHATSAPP_NODE_SERVICE_URL" .env

echo ""
echo "   REVERB Settings:"
grep "REVERB_" .env | grep -v "SECRET"

echo ""
echo "=========================================="
echo "🛠️  Common Fixes:"
echo ""
echo "Fix 1: Clear cache"
echo "  php artisan config:clear"
echo "  php artisan cache:clear"
echo ""
echo "Fix 2: Restart services"
echo "  pkill -f 'reverb:start'"
echo "  php artisan reverb:start --host=0.0.0.0 --port=8080 &"
echo ""
echo "Fix 3: Check file permissions"
echo "  chmod -R 775 storage"
echo "  chmod -R 775 bootstrap/cache"
echo ""
echo "Fix 4: Rebuild assets"
echo "  npm run build"
echo ""
