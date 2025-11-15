#!/bin/bash
# WhatsApp QR Code - Integration Test Script
# Run this script to test the complete flow

echo "🚀 Starting WhatsApp QR Code Integration Test"
echo "=============================================="

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test 1: Check Node.js Service Health
echo -e "\n${YELLOW}Test 1:${NC} Checking Node.js service health..."
NODE_HEALTH=$(curl -s http://127.0.0.1:3001/health)
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Node.js service is healthy${NC}"
    echo "$NODE_HEALTH" | python3 -m json.tool
else
    echo -e "${RED}❌ Node.js service is not responding${NC}"
    exit 1
fi

# Test 2: Check Laravel App
echo -e "\n${YELLOW}Test 2:${NC} Checking Laravel application..."
LARAVEL_CHECK=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000)
if [ "$LARAVEL_CHECK" = "200" ] || [ "$LARAVEL_CHECK" = "302" ]; then
    echo -e "${GREEN}✅ Laravel app is running (HTTP $LARAVEL_CHECK)${NC}"
else
    echo -e "${RED}❌ Laravel app is not responding (HTTP $LARAVEL_CHECK)${NC}"
    exit 1
fi

# Test 3: Check Reverb WebSocket Server
echo -e "\n${YELLOW}Test 3:${NC} Checking Reverb WebSocket server..."
# Reverb is a WebSocket server, check if port is listening
REVERB_PORT_CHECK=$(lsof -i :8080 -sTCP:LISTEN -t 2>/dev/null)
if [ ! -z "$REVERB_PORT_CHECK" ]; then
    echo -e "${GREEN}✅ Reverb is listening on port 8080 (PID: $REVERB_PORT_CHECK)${NC}"
    # Try to get app info endpoint if available
    REVERB_INFO=$(curl -s http://127.0.0.1:8080/app/1 2>/dev/null || echo "")
    if [ ! -z "$REVERB_INFO" ]; then
        echo "   App info: $REVERB_INFO"
    fi
else
    echo -e "${RED}❌ Reverb is not listening on port 8080${NC}"
    echo "   Run: php artisan reverb:start --host=0.0.0.0 --port=8080"
    exit 1
fi

# Test 4: Check Vite Dev Server
echo -e "\n${YELLOW}Test 4:${NC} Checking Vite dev server..."
VITE_CHECK=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:5173)
if [ "$VITE_CHECK" = "200" ]; then
    echo -e "${GREEN}✅ Vite dev server is running (HTTP $VITE_CHECK)${NC}"
else
    echo -e "${YELLOW}⚠️  Vite dev server not responding (HTTP $VITE_CHECK) - may need 'npm run dev'${NC}"
fi

# Test 5: Check Configuration
echo -e "\n${YELLOW}Test 5:${NC} Verifying .env configuration..."
if grep -q "WHATSAPP_NODE_SERVICE_URL=http://127.0.0.1:3001" .env; then
    echo -e "${GREEN}✅ WHATSAPP_NODE_SERVICE_URL is correctly set${NC}"
else
    echo -e "${RED}❌ WHATSAPP_NODE_SERVICE_URL is missing or incorrect${NC}"
    exit 1
fi

if grep -q "REVERB_PORT=8080" .env; then
    echo -e "${GREEN}✅ REVERB_PORT is correctly set${NC}"
else
    echo -e "${RED}❌ REVERB_PORT is missing or incorrect${NC}"
    exit 1
fi

# Test 6: Test Session Creation Endpoint (Mock)
echo -e "\n${YELLOW}Test 6:${NC} Testing Node.js session endpoint (dry run)..."
echo "Simulating: POST /api/sessions with workspace_id=1, session_id=test_123"
echo "(Skipping actual creation to avoid database pollution)"

# Test 7: Check Recent Laravel Logs
echo -e "\n${YELLOW}Test 7:${NC} Checking Laravel logs for errors..."
RECENT_ERRORS=$(tail -n 50 storage/logs/laravel.log 2>/dev/null | grep -i "error\|exception\|failed" | tail -n 3)
if [ -z "$RECENT_ERRORS" ]; then
    echo -e "${GREEN}✅ No recent errors in Laravel logs${NC}"
else
    echo -e "${YELLOW}⚠️  Recent errors found:${NC}"
    echo "$RECENT_ERRORS"
fi

# Test 8: Check Recent WhatsApp Service Logs
echo -e "\n${YELLOW}Test 8:${NC} Checking WhatsApp service logs..."
if [ -f "whatsapp-service/whatsapp-service.log" ]; then
    RECENT_WA_ERRORS=$(tail -n 50 whatsapp-service/whatsapp-service.log | grep -i "error\|exception\|failed" | tail -n 3)
    if [ -z "$RECENT_WA_ERRORS" ]; then
        echo -e "${GREEN}✅ No recent errors in WhatsApp service logs${NC}"
    else
        echo -e "${YELLOW}⚠️  Recent errors found:${NC}"
        echo "$RECENT_WA_ERRORS"
    fi
else
    echo -e "${YELLOW}⚠️  WhatsApp service log not found${NC}"
fi

# Summary
echo -e "\n=============================================="
echo -e "${GREEN}🎉 All infrastructure checks passed!${NC}"
echo ""
echo "Next steps:"
echo "1. Open browser: http://127.0.0.1:8000/settings/whatsapp-accounts"
echo "2. Open DevTools Console (F12)"
echo "3. Click 'Add WhatsApp Number' button"
echo "4. Watch console logs for:"
echo "   - 🔄 Creating new WhatsApp account..."
echo "   - ✅ Session created: {...}"
echo "   - 📨 QR Code Generated Event received: {...}"
echo "5. QR code should appear within 1-2 seconds"
echo ""
echo "Monitor logs in separate terminals:"
echo "  Terminal 1: tail -f storage/logs/laravel.log"
echo "  Terminal 2: tail -f whatsapp-service/whatsapp-service.log"
echo ""
echo "If issues occur, check WHATSAPP-QR-FIX-REPORT.md for troubleshooting"
echo "=============================================="
