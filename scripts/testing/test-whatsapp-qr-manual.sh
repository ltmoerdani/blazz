#!/bin/bash
# Quick WhatsApp QR Testing Script
# This script will guide you through manual testing steps

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

clear

echo -e "${BLUE}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                                                        ║${NC}"
echo -e "${BLUE}║     WhatsApp QR Code - Manual Testing Assistant       ║${NC}"
echo -e "${BLUE}║                                                        ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════╝${NC}"
echo ""

# Step 1: Infrastructure Check
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}STEP 1: Infrastructure Verification${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo -e "${PURPLE}Checking services...${NC}"
echo ""

# Check Node.js
NODE_CHECK=$(curl -s http://127.0.0.1:3001/health 2>/dev/null)
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Node.js Service (port 3001)${NC}"
else
    echo -e "${RED}❌ Node.js Service (port 3001)${NC}"
    echo -e "${YELLOW}   Fix: cd whatsapp-service && npm run dev${NC}"
fi

# Check Laravel
LARAVEL_CHECK=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000 2>/dev/null)
if [ "$LARAVEL_CHECK" = "200" ] || [ "$LARAVEL_CHECK" = "302" ]; then
    echo -e "${GREEN}✅ Laravel App (port 8000)${NC}"
else
    echo -e "${RED}❌ Laravel App (port 8000)${NC}"
    echo -e "${YELLOW}   Fix: php artisan serve --host=0.0.0.0 --port=8000${NC}"
fi

# Check Reverb
REVERB_PORT=$(lsof -i :8080 -sTCP:LISTEN -t 2>/dev/null)
if [ ! -z "$REVERB_PORT" ]; then
    echo -e "${GREEN}✅ Reverb WebSocket (port 8080) - PID: $REVERB_PORT${NC}"
else
    echo -e "${RED}❌ Reverb WebSocket (port 8080)${NC}"
    echo -e "${YELLOW}   Fix: php artisan reverb:start --host=0.0.0.0 --port=8080${NC}"
fi

echo ""
read -p "Press ENTER to continue to next step..."
clear

# Step 2: Monitoring Setup
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}STEP 2: Setup Monitoring${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo -e "${PURPLE}Open TWO separate terminal windows and run:${NC}"
echo ""
echo -e "${GREEN}Terminal 1 - Laravel Logs:${NC}"
echo -e "${CYAN}cd /Applications/MAMP/htdocs/blazz${NC}"
echo -e "${CYAN}tail -f storage/logs/laravel.log${NC}"
echo ""
echo -e "${GREEN}Terminal 2 - WhatsApp Service Logs:${NC}"
echo -e "${CYAN}cd /Applications/MAMP/htdocs/blazz${NC}"
echo -e "${CYAN}tail -f whatsapp-service/whatsapp-service.log${NC}"
echo ""

read -p "Have you opened both terminals? (y/n): " terminals_ready
if [ "$terminals_ready" != "y" ]; then
    echo -e "${YELLOW}Please open the terminals before continuing${NC}"
    exit 1
fi

clear

# Step 3: Browser Setup
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}STEP 3: Browser Setup${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo -e "${PURPLE}1. Open browser${NC}"
echo -e "${PURPLE}2. Press F12 or Cmd+Option+I to open DevTools${NC}"
echo -e "${PURPLE}3. Click 'Console' tab${NC}"
echo -e "${PURPLE}4. Navigate to:${NC}"
echo -e "${GREEN}   http://127.0.0.1:8000/settings/whatsapp-sessions${NC}"
echo ""

read -p "Browser ready with DevTools open? (y/n): " browser_ready
if [ "$browser_ready" != "y" ]; then
    echo -e "${YELLOW}Please setup browser before continuing${NC}"
    exit 1
fi

clear

# Step 4: Test Execution
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}STEP 4: Test Execution${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo -e "${GREEN}Now perform these actions in the browser:${NC}"
echo ""
echo -e "${PURPLE}Action 1: Clear Console${NC}"
echo -e "  - Click 'Clear console' icon (🚫) in DevTools"
echo ""
read -p "Press ENTER when done..."

echo ""
echo -e "${PURPLE}Action 2: Click 'Add WhatsApp Number' Button${NC}"
echo -e "  - Click the green button in the top right"
echo ""
read -p "Press ENTER when clicked..."

clear

# Step 5: Verification Checklist
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}STEP 5: Verification Checklist${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo -e "${GREEN}Check the following in Browser Console:${NC}"
echo ""
echo -e "1. ${PURPLE}Console logs show:${NC}"
echo -e "   ${CYAN}📡 Subscribing to Echo channel: workspace.X${NC}"
echo -e "   ${CYAN}✅ Echo channel subscribed successfully${NC}"
echo -e "   ${CYAN}🔄 Creating new WhatsApp session...${NC}"
echo -e "   ${CYAN}✅ Session created: {...}${NC}"
echo ""
read -p "Do you see these logs? (y/n): " console_logs

echo ""
echo -e "2. ${PURPLE}Network tab shows:${NC}"
echo -e "   ${CYAN}POST /settings/whatsapp-sessions → 200 OK${NC}"
echo ""
read -p "Do you see successful POST request? (y/n): " network_post

echo ""
echo -e "3. ${PURPLE}Modal displays:${NC}"
echo -e "   ${CYAN}✅ QR Code image${NC}"
echo -e "   ${CYAN}✅ Timer counting down (5:00, 4:59, ...)${NC}"
echo -e "   ${CYAN}✅ Instructions visible${NC}"
echo ""
read -p "Do you see QR code and timer? (y/n): " modal_qr

clear

# Step 6: Laravel Logs Check
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}STEP 6: Check Terminal Logs${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo -e "${GREEN}Check Terminal 1 (Laravel Logs):${NC}"
echo ""
echo -e "Expected logs:"
echo -e "${CYAN}  [timestamp] local.INFO: 📥 WhatsApp session creation request${NC}"
echo -e "${CYAN}  [timestamp] local.INFO: workspace_id: 1${NC}"
echo -e "${CYAN}  [timestamp] local.INFO: 🔄 Calling Node.js service${NC}"
echo -e "${CYAN}  [timestamp] local.INFO: ✅ Session initialized successfully${NC}"
echo -e "${CYAN}  [timestamp] local.INFO: 📨 WhatsApp webhook received${NC}"
echo -e "${CYAN}  [timestamp] local.INFO: Event: qr_code_generated${NC}"
echo ""
read -p "Do you see these Laravel logs? (y/n): " laravel_logs

echo ""
echo -e "${GREEN}Check Terminal 2 (WhatsApp Service Logs):${NC}"
echo ""
echo -e "Expected logs:"
echo -e "${CYAN}  [timestamp] [POST /api/sessions] Creating new WhatsApp session${NC}"
echo -e "${CYAN}  [timestamp] workspace_id: 1${NC}"
echo -e "${CYAN}  [timestamp] Initializing whatsapp-web.js client...${NC}"
echo -e "${CYAN}  [timestamp] ✅ QR code generated successfully${NC}"
echo -e "${CYAN}  [timestamp] Sending webhook to Laravel${NC}"
echo ""
read -p "Do you see these WhatsApp service logs? (y/n): " whatsapp_logs

clear

# Step 7: Results Summary
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}STEP 7: Test Results Summary${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Count successful checks
success_count=0
total_checks=5

[ "$console_logs" = "y" ] && ((success_count++))
[ "$network_post" = "y" ] && ((success_count++))
[ "$modal_qr" = "y" ] && ((success_count++))
[ "$laravel_logs" = "y" ] && ((success_count++))
[ "$whatsapp_logs" = "y" ] && ((success_count++))

echo -e "${PURPLE}Checklist Results:${NC}"
echo ""
[ "$console_logs" = "y" ] && echo -e "${GREEN}✅${NC} Console logs" || echo -e "${RED}❌${NC} Console logs"
[ "$network_post" = "y" ] && echo -e "${GREEN}✅${NC} Network POST request" || echo -e "${RED}❌${NC} Network POST request"
[ "$modal_qr" = "y" ] && echo -e "${GREEN}✅${NC} QR code displayed" || echo -e "${RED}❌${NC} QR code displayed"
[ "$laravel_logs" = "y" ] && echo -e "${GREEN}✅${NC} Laravel logs correct" || echo -e "${RED}❌${NC} Laravel logs correct"
[ "$whatsapp_logs" = "y" ] && echo -e "${GREEN}✅${NC} WhatsApp service logs correct" || echo -e "${RED}❌${NC} WhatsApp service logs correct"

echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

if [ $success_count -eq $total_checks ]; then
    echo -e "${GREEN}╔════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                                                        ║${NC}"
    echo -e "${GREEN}║        🎉 ALL TESTS PASSED! (5/5) 🎉                  ║${NC}"
    echo -e "${GREEN}║                                                        ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${PURPLE}✅ QR code generation is working correctly!${NC}"
    echo -e "${PURPLE}✅ Ready for production deployment${NC}"
    echo ""
    echo -e "${YELLOW}Next steps:${NC}"
    echo -e "  1. Test scanning QR with WhatsApp mobile app"
    echo -e "  2. Verify session status changes to 'authenticated'"
    echo -e "  3. Test sending messages"
    echo -e "  4. Test disconnect/reconnect flow"
elif [ $success_count -ge 3 ]; then
    echo -e "${YELLOW}╔════════════════════════════════════════════════════════╗${NC}"
    echo -e "${YELLOW}║                                                        ║${NC}"
    echo -e "${YELLOW}║      ⚠️  PARTIAL PASS ($success_count/$total_checks) - Needs Investigation     ║${NC}"
    echo -e "${YELLOW}║                                                        ║${NC}"
    echo -e "${YELLOW}╚════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${YELLOW}Some checks failed. Review the failed items above.${NC}"
    echo -e "${YELLOW}Check MANUAL-TESTING-GUIDE.md for troubleshooting.${NC}"
else
    echo -e "${RED}╔════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║                                                        ║${NC}"
    echo -e "${RED}║          ❌ TEST FAILED ($success_count/$total_checks) ❌                       ║${NC}"
    echo -e "${RED}║                                                        ║${NC}"
    echo -e "${RED}╚════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${RED}Multiple checks failed. Debug required.${NC}"
    echo ""
    echo -e "${YELLOW}Debugging steps:${NC}"
    echo -e "  1. Review Laravel logs: tail -f storage/logs/laravel.log"
    echo -e "  2. Review WhatsApp logs: tail -f whatsapp-service/whatsapp-service.log"
    echo -e "  3. Check browser console for JavaScript errors"
    echo -e "  4. Verify .env configuration"
    echo -e "  5. Run: php artisan config:clear && php artisan cache:clear"
    echo ""
    echo -e "${YELLOW}See MANUAL-TESTING-GUIDE.md for detailed troubleshooting.${NC}"
fi

echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Optional: Scan QR Code Test
if [ $success_count -eq $total_checks ]; then
    echo ""
    read -p "Do you want to test scanning QR code with WhatsApp mobile? (y/n): " scan_test
    
    if [ "$scan_test" = "y" ]; then
        echo ""
        echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
        echo -e "${YELLOW}BONUS: WhatsApp Mobile Scan Test${NC}"
        echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
        echo ""
        echo -e "${PURPLE}1. Open WhatsApp on your phone${NC}"
        echo -e "${PURPLE}2. Tap Menu (3 dots) → Linked Devices${NC}"
        echo -e "${PURPLE}3. Tap 'Link a Device'${NC}"
        echo -e "${PURPLE}4. Scan the QR code on your browser${NC}"
        echo ""
        read -p "Press ENTER after scanning..."
        echo ""
        echo -e "${GREEN}Check browser console for:${NC}"
        echo -e "${CYAN}📨 Session Status Changed Event received: {status: 'authenticated'}${NC}"
        echo ""
        read -p "Did you see 'authenticated' status? (y/n): " authenticated
        
        if [ "$authenticated" = "y" ]; then
            echo ""
            echo -e "${GREEN}🎉 Perfect! WhatsApp session fully functional!${NC}"
        else
            echo ""
            echo -e "${YELLOW}⚠️  Authentication event not received${NC}"
            echo -e "${YELLOW}Check WhatsApp service logs for authentication flow${NC}"
        fi
    fi
fi

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}Testing session completed!${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
