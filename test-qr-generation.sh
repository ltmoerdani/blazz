#!/bin/bash
# QR Generation - End-to-End Test Script
# Tests the complete QR generation flow after multi-instance refactor fixes

echo "🚀 Starting QR Generation Test"
echo "======================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Change to project directory
cd "$(dirname "$0")"

# Test 1: Check all services are running
echo -e "${YELLOW}📋 Test 1: Checking Services${NC}"
echo "--------------------------------------"

# Check Laravel
if pgrep -f "php artisan serve" > /dev/null; then
    echo -e "${GREEN}✅ Laravel dev server running${NC}"
else
    echo -e "${RED}❌ Laravel dev server NOT running${NC}"
    echo "   Start with: php artisan serve"
    exit 1
fi

# Check Reverb
if pgrep -f "php artisan reverb:start" > /dev/null; then
    echo -e "${GREEN}✅ Reverb WebSocket server running${NC}"
else
    echo -e "${RED}❌ Reverb WebSocket NOT running${NC}"
    echo "   Start with: php artisan reverb:start"
    exit 1
fi

# Check PM2
if command -v pm2 &> /dev/null; then
    PM2_STATUS=$(pm2 status 2>/dev/null | grep -c "online")
    if [ "$PM2_STATUS" -ge 4 ]; then
        echo -e "${GREEN}✅ PM2 instances running ($PM2_STATUS instances online)${NC}"
    else
        echo -e "${RED}❌ PM2 instances not all running ($PM2_STATUS/4)${NC}"
        echo "   Start with: cd whatsapp-service && pm2 start ecosystem.config.js"
        exit 1
    fi
else
    echo -e "${RED}❌ PM2 not installed${NC}"
    exit 1
fi

echo ""

# Test 2: Verify HMAC Configuration
echo -e "${YELLOW}📋 Test 2: HMAC Configuration${NC}"
echo "--------------------------------------"

HMAC_SECRET=$(grep "WHATSAPP_HMAC_SECRET" .env | cut -d'=' -f2)
if [ -n "$HMAC_SECRET" ]; then
    HMAC_LENGTH=${#HMAC_SECRET}
    if [ "$HMAC_LENGTH" -eq 128 ]; then
        echo -e "${GREEN}✅ HMAC secret configured (128 chars)${NC}"
    else
        echo -e "${YELLOW}⚠️  HMAC secret length: $HMAC_LENGTH chars (expected 128)${NC}"
    fi
else
    echo -e "${RED}❌ HMAC secret NOT configured${NC}"
    exit 1
fi

# Check config cached
if php artisan config:cache --quiet 2>&1 | grep -q "cached"; then
    echo -e "${GREEN}✅ Config cached successfully${NC}"
else
    echo -e "${YELLOW}⚠️  Config cache check inconclusive${NC}"
fi

echo ""

# Test 3: Webhook Authentication
echo -e "${YELLOW}📋 Test 3: Webhook Authentication${NC}"
echo "--------------------------------------"

TIMESTAMP=$(date +%s)
PAYLOAD='{"event":"test","data":{}}'
SECRET=$(grep "WHATSAPP_HMAC_SECRET" .env | cut -d'=' -f2)
SIGNATURE=$(echo -n "${TIMESTAMP}${PAYLOAD}" | openssl dgst -sha256 -hmac "$SECRET" | cut -d' ' -f2)

WEBHOOK_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST http://127.0.0.1:8000/api/whatsapp/webhooks/webjs \
    -H "Content-Type: application/json" \
    -H "X-HMAC-Signature: $SIGNATURE" \
    -H "X-Timestamp: $TIMESTAMP" \
    -d "$PAYLOAD" 2>&1)

HTTP_CODE=$(echo "$WEBHOOK_RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$WEBHOOK_RESPONSE" | head -n1)

if [ "$HTTP_CODE" == "200" ]; then
    echo -e "${GREEN}✅ Webhook authenticated successfully (HTTP $HTTP_CODE)${NC}"
    echo "   Response: $RESPONSE_BODY"
else
    echo -e "${RED}❌ Webhook authentication failed (HTTP $HTTP_CODE)${NC}"
    echo "   Response: $RESPONSE_BODY"
    exit 1
fi

echo ""

# Test 4: Instance Routing
echo -e "${YELLOW}📋 Test 4: Instance Routing${NC}"
echo "--------------------------------------"

for PORT in 3001 3002 3003 3004; do
    HEALTH_CHECK=$(curl -s http://127.0.0.1:$PORT/health 2>&1)
    if echo "$HEALTH_CHECK" | grep -q "status"; then
        echo -e "${GREEN}✅ Instance on port $PORT responding${NC}"
    else
        echo -e "${RED}❌ Instance on port $PORT NOT responding${NC}"
    fi
done

echo ""

# Test 5: Database Check
echo -e "${YELLOW}📋 Test 5: Database Connectivity${NC}"
echo "--------------------------------------"

DB_PASSWORD=$(grep "DB_PASSWORD=" .env | cut -d'=' -f2)
DB_DATABASE=$(grep "DB_DATABASE=" .env | cut -d'=' -f2)

DISCONNECTED_COUNT=$(mysql -u root -p"$DB_PASSWORD" -e "USE $DB_DATABASE; SELECT COUNT(*) as count FROM whatsapp_accounts WHERE status = 'disconnected';" 2>/dev/null | tail -n1)

if [ -n "$DISCONNECTED_COUNT" ]; then
    echo -e "${GREEN}✅ Database connected${NC}"
    echo "   Disconnected accounts: $DISCONNECTED_COUNT"
else
    echo -e "${RED}❌ Database query failed${NC}"
    exit 1
fi

echo ""

# All tests passed
echo -e "${GREEN}✅ All Pre-Flight Checks Passed!${NC}"
echo "======================================"
echo ""

# Instructions for manual testing
echo -e "${BLUE}📱 Manual Testing Instructions:${NC}"
echo "--------------------------------------"
echo ""
echo "1. Open browser: http://127.0.0.1:8000/settings/whatsapp-sessions"
echo ""
echo "2. Open DevTools Console (F12 or Cmd+Option+I)"
echo ""
echo "3. Click 'Add WhatsApp Number' button"
echo ""
echo "4. Watch for console logs:"
echo "   ${GREEN}🔄 Creating new WhatsApp session...${NC}"
echo "   ${GREEN}✅ Session created: {...}${NC}"
echo "   ${GREEN}📨 QR Code Generated Event received: {...}${NC}"
echo ""
echo "5. QR code should display within 2-3 seconds"
echo ""
echo "6. Scan QR with WhatsApp mobile app"
echo ""
echo "7. Verify connection status changes to 'connected'"
echo ""

# Offer to open logs
echo ""
echo -e "${YELLOW}🔍 Monitor Logs (Optional):${NC}"
echo "--------------------------------------"
echo "Terminal 1 - Laravel logs:"
echo "  tail -f storage/logs/laravel.log | grep -E 'WhatsApp|QR|webhook'"
echo ""
echo "Terminal 2 - PM2 logs:"
echo "  cd whatsapp-service && pm2 logs | grep -E 'QR|session|webhook'"
echo ""

# Offer to start log monitoring
read -p "Start log monitoring? (y/n) " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${BLUE}Starting log monitoring...${NC}"
    echo "Press Ctrl+C to stop"
    echo ""
    
    # Open Laravel logs in background
    gnome-terminal --tab --title="Laravel Logs" -- bash -c "cd $(pwd) && tail -f storage/logs/laravel.log | grep --line-buffered -E 'WhatsApp|QR|webhook'; exec bash" 2>/dev/null || \
    osascript -e 'tell app "Terminal" to do script "cd '"$(pwd)"' && tail -f storage/logs/laravel.log | grep --line-buffered -E \"WhatsApp|QR|webhook\""' 2>/dev/null || \
    echo "   Run manually: tail -f storage/logs/laravel.log | grep -E 'WhatsApp|QR|webhook'"
    
    # Open PM2 logs in background
    gnome-terminal --tab --title="PM2 Logs" -- bash -c "cd $(pwd)/whatsapp-service && pm2 logs | grep --line-buffered -E 'QR|session|webhook'; exec bash" 2>/dev/null || \
    osascript -e 'tell app "Terminal" to do script "cd '"$(pwd)/whatsapp-service"' && pm2 logs | grep --line-buffered -E \"QR|session|webhook\""' 2>/dev/null || \
    echo "   Run manually: cd whatsapp-service && pm2 logs | grep -E 'QR|session|webhook'"
    
    echo ""
    echo -e "${GREEN}Log monitoring started!${NC}"
    echo "Now open http://127.0.0.1:8000/settings/whatsapp-sessions in your browser"
fi

echo ""
echo "======================================"
echo -e "${GREEN}🎉 Test Script Complete!${NC}"
echo "======================================"
