#!/bin/bash

# Restart WhatsApp Services Script
# Date: 2025-10-13
# Purpose: Restart Node.js after fixes applied

echo "=========================================="
echo "Restarting WhatsApp Services"
echo "=========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

cd /Applications/MAMP/htdocs/blazz

# Step 1: Kill Node.js
echo -e "${YELLOW}[1/4] Stopping Node.js service...${NC}"
pkill -9 node
sleep 2
echo -e "${GREEN}✅ Node.js stopped${NC}"

# Step 2: Clear Laravel config
echo ""
echo -e "${YELLOW}[2/4] Clearing Laravel config cache...${NC}"
php artisan config:clear
echo -e "${GREEN}✅ Config cache cleared${NC}"

# Step 3: Start Node.js
echo ""
echo -e "${YELLOW}[3/4] Starting Node.js service...${NC}"
cd whatsapp-service
nohup node server.js > whatsapp-service.out.log 2>&1 &
NODE_PID=$!
echo -e "${GREEN}✅ Node.js started (PID: $NODE_PID)${NC}"
cd ..

# Step 4: Verify
echo ""
echo -e "${YELLOW}[4/4] Verifying services...${NC}"
sleep 2

if lsof -i :3001 > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Node.js listening on port 3001${NC}"
else
    echo -e "${RED}❌ Node.js failed to start!${NC}"
    echo "Check logs: cat whatsapp-service/whatsapp-service.out.log"
    exit 1
fi

if lsof -i :8000 > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Laravel listening on port 8000${NC}"
else
    echo -e "${RED}❌ Laravel not running!${NC}"
fi

if lsof -i :8080 > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Reverb listening on port 8080${NC}"
else
    echo -e "${YELLOW}⚠️  Reverb not running (needed for WebSocket)${NC}"
    echo "Start with: php artisan reverb:start &"
fi

echo ""
echo "=========================================="
echo "✅ Services restarted successfully!"
echo "=========================================="
echo ""
echo "Run testing script:"
echo "  ./test-whatsapp-qr-fix.sh"
echo ""
