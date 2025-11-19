#!/bin/bash

# Quick Fix Script - Restart WhatsApp Service
# Date: 2025-11-15
# Purpose: Restart stuck WhatsApp session immediately

echo "=========================================="
echo "🚨 QUICK FIX - WhatsApp Session Restart"
echo "=========================================="
echo ""

cd /Applications/MAMP/htdocs/blazz

echo "⏸️  Stopping services..."
./stop-dev.sh
echo ""

echo "⏳ Waiting 5 seconds for clean shutdown..."
sleep 5
echo ""

echo "🚀 Starting services..."
./start-dev.sh
echo ""

echo "✅ Services restarted!"
echo ""
echo "=========================================="
echo "📊 MONITORING"
echo "=========================================="
echo ""
echo "1. Watch logs:"
echo "   tail -f /Applications/MAMP/htdocs/blazz/whatsapp-service/whatsapp-service.out.log"
echo ""
echo "2. Check account status:"
echo "   cd /Applications/MAMP/htdocs/blazz"
echo "   php artisan tinker --execute=\"\\\$acc = App\\\\Models\\\\WhatsAppAccount::find(5); echo 'Status: ' . \\\$acc->status . ' | Last Activity: ' . \\\$acc->last_activity_at;\""
echo ""
echo "3. Test by sending a WhatsApp message to: 62811801641"
echo ""
echo "=========================================="
echo "Expected Timeline:"
echo "  [00:00] Services stopped"
echo "  [00:05] Services starting"
echo "  [00:20] Session reconnecting"
echo "  [00:30] Ready to receive messages"
echo "=========================================="
echo ""
