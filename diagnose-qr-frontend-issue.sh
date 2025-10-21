#!/bin/bash

# WhatsApp QR Code Frontend Display - Diagnostic & Fix Test
# Date: 2025-10-14
# Purpose: Diagnose why QR code not displaying in frontend

echo "======================================================"
echo "🔍 WhatsApp QR Code Frontend Display - Diagnostic Test"
echo "======================================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counter
PASS=0
FAIL=0

# Test 1: Check services running
echo "📋 Test 1: Check all services running"
echo "------------------------------------------------------"

echo -n "  Laravel (port 8000): "
if lsof -i :8000 > /dev/null 2>&1; then
    echo -e "${GREEN}✅ RUNNING${NC}"
    ((PASS++))
else
    echo -e "${RED}❌ NOT RUNNING${NC}"
    ((FAIL++))
fi

echo -n "  Node.js (port 3001): "
if lsof -i :3001 > /dev/null 2>&1; then
    echo -e "${GREEN}✅ RUNNING${NC}"
    ((PASS++))
else
    echo -e "${RED}❌ NOT RUNNING${NC}"
    ((FAIL++))
fi

echo -n "  Reverb (port 8080): "
if lsof -i :8080 > /dev/null 2>&1; then
    echo -e "${GREEN}✅ RUNNING${NC}"
    ((PASS++))
else
    echo -e "${RED}❌ NOT RUNNING${NC}"
    ((FAIL++))
fi

echo ""

# Test 2: Check Event classes have broadcastAs()
echo "📋 Test 2: Check Event classes configuration"
echo "------------------------------------------------------"

echo -n "  WhatsAppQRGeneratedEvent has broadcastAs(): "
if grep -q "public function broadcastAs()" app/Events/WhatsAppQRGeneratedEvent.php; then
    BROADCAST_NAME=$(grep -A 2 "public function broadcastAs()" app/Events/WhatsAppQRGeneratedEvent.php | grep "return" | sed "s/.*return '\(.*\)'.*/\1/")
    echo -e "${GREEN}✅ YES (returns: '$BROADCAST_NAME')${NC}"
    ((PASS++))
else
    echo -e "${RED}❌ NO${NC}"
    ((FAIL++))
fi

echo -n "  WhatsAppSessionStatusChangedEvent has broadcastAs(): "
if grep -q "public function broadcastAs()" app/Events/WhatsAppSessionStatusChangedEvent.php; then
    BROADCAST_NAME=$(grep -A 2 "public function broadcastAs()" app/Events/WhatsAppSessionStatusChangedEvent.php | grep "return" | sed "s/.*return '\(.*\)'.*/\1/")
    echo -e "${GREEN}✅ YES (returns: '$BROADCAST_NAME')${NC}"
    ((PASS++))
else
    echo -e "${RED}❌ NO${NC}"
    ((FAIL++))
fi

echo ""

# Test 3: Check frontend Echo listeners
echo "📋 Test 3: Check frontend Echo event listeners"
echo "------------------------------------------------------"

echo -n "  Frontend listens to '.qr-code-generated': "
if grep -q "listen('.qr-code-generated'" resources/js/Pages/User/Settings/WhatsAppSessions.vue; then
    echo -e "${GREEN}✅ YES${NC}"
    ((PASS++))
else
    echo -e "${RED}❌ NO${NC}"
    ((FAIL++))
fi

echo -n "  Frontend listens to '.session-status-changed': "
if grep -q "listen('.session-status-changed'" resources/js/Pages/User/Settings/WhatsAppSessions.vue; then
    echo -e "${GREEN}✅ YES${NC}"
    ((PASS++))
else
    echo -e "${RED}❌ NO${NC}"
    ((FAIL++))
fi

echo ""

# Test 4: Check stuck sessions in database
echo "📋 Test 4: Check for stuck sessions in database"
echo "------------------------------------------------------"

STUCK_SESSIONS=$(php artisan tinker --execute="echo DB::table('whatsapp_sessions')->where('status', 'qr_scanning')->count();")

if [ "$STUCK_SESSIONS" -gt 0 ]; then
    echo -e "  ${YELLOW}⚠️  Found $STUCK_SESSIONS stuck sessions with status 'qr_scanning'${NC}"
    echo "  Sessions:"
    php artisan tinker --execute="
    DB::table('whatsapp_sessions')
        ->where('status', 'qr_scanning')
        ->get(['id', 'session_id', 'created_at'])
        ->each(function(\$s) {
            echo '    - ID: ' . \$s->id . ', Session: ' . \$s->session_id . ', Created: ' . \$s->created_at . PHP_EOL;
        });
    "
else
    echo -e "  ${GREEN}✅ No stuck sessions found${NC}"
    ((PASS++))
fi

echo ""

# Test 5: Check disconnect/delete logic handles qr_scanning
echo "📋 Test 5: Check Controller handles qr_scanning status"
echo "------------------------------------------------------"

echo -n "  disconnect() handles 'qr_scanning': "
if grep -A 10 "public function disconnect" app/Http/Controllers/User/WhatsAppSessionController.php | grep -q "qr_scanning"; then
    echo -e "${GREEN}✅ YES${NC}"
    ((PASS++))
else
    echo -e "${RED}❌ NO - needs fix${NC}"
    ((FAIL++))
fi

echo -n "  destroy() handles 'qr_scanning': "
if grep -A 10 "public function destroy" app/Http/Controllers/User/WhatsAppSessionController.php | grep -q "qr_scanning"; then
    echo -e "${GREEN}✅ YES${NC}"
    ((PASS++))
else
    echo -e "${RED}❌ NO - needs fix${NC}"
    ((FAIL++))
fi

echo ""

# Test 6: Test broadcast manually
echo "📋 Test 6: Manual broadcast test"
echo "------------------------------------------------------"

echo "  Broadcasting test event to workspace.1..."
php artisan tinker --execute="
use App\Events\WhatsAppQRGeneratedEvent;
\$event = new WhatsAppQRGeneratedEvent('test-qr-data', 300, 1, 'diagnostic-test');
broadcast(\$event);
echo 'Event broadcasted successfully';
" > /dev/null 2>&1

if [ $? -eq 0 ]; then
    echo -e "  ${GREEN}✅ Broadcast test successful${NC}"
    echo "  ${BLUE}ℹ️  Check browser console for: '📨 QR Code Generated Event received'${NC}"
    ((PASS++))
else
    echo -e "  ${RED}❌ Broadcast test failed${NC}"
    ((FAIL++))
fi

echo ""

# Test 7: Check recent logs
echo "📋 Test 7: Check recent logs for QR events"
echo "------------------------------------------------------"

RECENT_QR_EVENTS=$(grep -c "QR code generated" storage/logs/laravel.log 2>/dev/null || echo "0")
RECENT_BROADCASTS=$(grep -c "Broadcasting WhatsAppQRGeneratedEvent" storage/logs/laravel.log 2>/dev/null || echo "0")

echo "  Recent QR code generated events: $RECENT_QR_EVENTS"
echo "  Recent broadcast events: $RECENT_BROADCASTS"

if [ "$RECENT_BROADCASTS" -gt 0 ]; then
    echo -e "  ${GREEN}✅ Events are being broadcasted${NC}"
    echo "  ${YELLOW}⚠️  If QR still not showing, check browser console for event reception${NC}"
    ((PASS++))
else
    echo -e "  ${YELLOW}⚠️  No recent broadcast events found${NC}"
fi

echo ""

# Summary
echo "======================================================"
echo "📊 DIAGNOSTIC SUMMARY"
echo "======================================================"
echo ""
echo -e "${GREEN}PASSED: $PASS${NC}"
echo -e "${RED}FAILED: $FAIL${NC}"
echo ""

# Recommendations
echo "======================================================"
echo "💡 RECOMMENDATIONS"
echo "======================================================"
echo ""

if [ "$STUCK_SESSIONS" -gt 0 ]; then
    echo "1. ${YELLOW}Cleanup stuck sessions:${NC}"
    echo "   ./cleanup-whatsapp-sessions.sh"
    echo ""
fi

if [ $FAIL -gt 0 ]; then
    echo "2. ${RED}Fix required for disconnect/delete logic${NC}"
    echo "   See: docs/whatsapp-webjs-integration/bugs/08-QR-CODE-NOT-DISPLAYING-FRONTEND-ISSUE.md"
    echo ""
fi

echo "3. ${BLUE}To test QR code display:${NC}"
echo "   a. Open http://127.0.0.1:8000/settings/whatsapp-sessions in browser"
echo "   b. Open browser DevTools Console (F12)"
echo "   c. Click 'Add WhatsApp Number'"
echo "   d. Watch console for:"
echo "      - '📡 Subscribing to Echo channel: workspace.1'"
echo "      - '✅ Echo channel subscribed successfully'"
echo "      - '📨 QR Code Generated Event received' (should appear in ~15 seconds)"
echo ""

echo "4. ${BLUE}To monitor WebSocket traffic:${NC}"
echo "   a. DevTools → Network → WS tab"
echo "   b. Click on WebSocket connection"
echo "   c. Go to 'Messages' tab"
echo "   d. Look for 'qr-code-generated' event"
echo ""

echo "======================================================"
echo "🏁 DIAGNOSTIC COMPLETE"
echo "======================================================"
echo ""

# Exit code
if [ $FAIL -gt 0 ]; then
    exit 1
else
    exit 0
fi
