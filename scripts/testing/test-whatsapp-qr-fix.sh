#!/bin/bash

# WhatsApp QR Code Testing Script
# Date: 2025-10-13
# Purpose: Test webhook QR code delivery after HMAC & timestamp fixes

echo "=========================================="
echo "WhatsApp QR Code Testing Script"
echo "=========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Step 1: Check Node.js service
echo -e "${YELLOW}[1/6] Checking Node.js service...${NC}"
if lsof -i :3001 > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Node.js running on port 3001${NC}"
    NODE_PID=$(lsof -t -i :3001)
    echo "   PID: $NODE_PID"
else
    echo -e "${RED}❌ Node.js NOT running!${NC}"
    echo "   Please start: cd whatsapp-service && node server.js &"
    exit 1
fi

# Step 2: Verify HMAC secrets match
echo ""
echo -e "${YELLOW}[2/6] Verifying HMAC secrets...${NC}"
NODE_SECRET=$(grep "^HMAC_SECRET=" whatsapp-service/.env | cut -d'=' -f2)
LARAVEL_SECRET=$(php artisan tinker --execute="echo config('whatsapp.node_api_secret');" 2>/dev/null)

if [ "$NODE_SECRET" = "$LARAVEL_SECRET" ]; then
    echo -e "${GREEN}✅ HMAC secrets match${NC}"
    echo "   Secret: ${NODE_SECRET:0:20}..."
else
    echo -e "${RED}❌ HMAC secrets DON'T match!${NC}"
    echo "   Node.js: ${NODE_SECRET:0:30}..."
    echo "   Laravel: ${LARAVEL_SECRET:0:30}..."
    exit 1
fi

# Step 3: Check server.js timestamp fix
echo ""
echo -e "${YELLOW}[3/6] Checking timestamp fix in server.js...${NC}"
if grep -q "Math.floor(Date.now() / 1000)" whatsapp-service/server.js; then
    echo -e "${GREEN}✅ Timestamp fix present (Math.floor)${NC}"
else
    echo -e "${RED}❌ Timestamp fix NOT found!${NC}"
    echo "   Expected: Math.floor(Date.now() / 1000)"
    exit 1
fi

# Step 4: Check Laravel services
echo ""
echo -e "${YELLOW}[4/6] Checking Laravel services...${NC}"

# Check Laravel
if lsof -i :8000 > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Laravel running on port 8000${NC}"
else
    echo -e "${RED}❌ Laravel NOT running!${NC}"
    exit 1
fi

# Check Reverb
if lsof -i :8080 > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Reverb running on port 8080${NC}"
else
    echo -e "${YELLOW}⚠️  Reverb NOT running (WebSocket needed for QR)${NC}"
    echo "   Please start: php artisan reverb:start &"
fi

# Step 5: Test webhook manually
echo ""
echo -e "${YELLOW}[5/6] Testing webhook HMAC validation...${NC}"

# Generate test signature
TEST_RESULT=$(php artisan tinker --execute="
\$timestamp = time();
\$payload = json_encode(['event' => 'test', 'data' => ['test' => true]]);
\$secret = config('whatsapp.node_api_secret');
\$signature = hash_hmac('sha256', \$timestamp . \$payload, \$secret);
echo \$timestamp . '|' . \$signature;
" 2>/dev/null)

TIMESTAMP=$(echo $TEST_RESULT | cut -d'|' -f1)
SIGNATURE=$(echo $TEST_RESULT | cut -d'|' -f2)

# Test webhook
WEBHOOK_RESPONSE=$(curl -s -X POST http://127.0.0.1:8000/api/whatsapp/webhooks/webjs \
  -H "Content-Type: application/json" \
  -H "X-Timestamp: $TIMESTAMP" \
  -H "X-HMAC-Signature: $SIGNATURE" \
  -d '{"event":"test","data":{"test":true}}' \
  2>&1)

if echo "$WEBHOOK_RESPONSE" | grep -q "401\|Unauthorized\|expired"; then
    echo -e "${RED}❌ Webhook test FAILED (401 Unauthorized)${NC}"
    echo "   Response: $WEBHOOK_RESPONSE"
    exit 1
else
    echo -e "${GREEN}✅ Webhook HMAC validation passed${NC}"
    echo "   Timestamp: $TIMESTAMP ($(date -r $TIMESTAMP '+%Y-%m-%d %H:%M:%S'))"
fi

# Step 6: Monitor logs
echo ""
echo -e "${YELLOW}[6/6] Log monitoring setup...${NC}"
echo ""
echo "=========================================="
echo "✅ All checks passed!"
echo "=========================================="
echo ""
echo "Now test via browser:"
echo "1. Open: http://127.0.0.1:8000/settings/whatsapp-sessions"
echo "2. Click 'Add WhatsApp Number'"
echo "3. Wait ~15 seconds for QR code"
echo ""
echo "Monitor logs with:"
echo ""
echo "  # Laravel logs:"
echo "  tail -f storage/logs/laravel.log | grep -i 'whatsapp\|hmac\|qr'"
echo ""
echo "  # Node.js logs:"
echo "  tail -f whatsapp-service/logs/whatsapp-service.log | grep -i 'qr\|webhook\|success'"
echo ""
echo "Expected success indicators:"
echo "  ✅ Laravel: 'WhatsApp WebJS webhook received'"
echo "  ✅ Laravel: 'Broadcasting WhatsAppQRGeneratedEvent'"
echo "  ✅ Node.js: 'Data sent to Laravel successfully'"
echo "  ✅ Browser: QR code appears in modal"
echo ""
