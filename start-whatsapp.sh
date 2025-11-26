#!/bin/bash

# WhatsApp Service Mode Selector
# Use this to choose between single and multi-instance deployment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo -e "${CYAN}🚀 WhatsApp Service Deployment Selector${NC}"
echo "======================================"
echo ""
echo "Choose deployment mode:"
echo ""
echo -e "${BLUE}1)${NC} Single Instance Development"
echo "   • 1 WhatsApp instance (port 3001)"
echo "   • Auto-reload with nodemon"
echo "   • 500 concurrent sessions capacity"
echo "   • Development environment"
echo ""
echo -e "${GREEN}2)${NC} Multi-Instance Production"
echo "   • 4 WhatsApp instances (ports 3001-3004)"
echo "   • Production-ready with PM2"
echo "   • 2,000 concurrent sessions capacity"
echo "   • Workspace sharding active"
echo ""
echo -e "${YELLOW}3)${NC} Stop All WhatsApp Services"
echo "   • Graceful shutdown of all instances"
echo "   • Clean up ports and processes"
echo ""

read -p "Select mode (1/2/3): " CHOICE

case $CHOICE in
    1)
        echo -e "${BLUE}🔧 Starting Single Instance Development Mode...${NC}"
        export WHATSAPP_MULTI_INSTANCE=false

        # Stop any existing multi-instance first
        if command -v pm2 &> /dev/null; then
            pm2 stop ecosystem.multi-instance.config.js 2>/dev/null || true
            pm2 delete ecosystem.multi-instance.config.js 2>/dev/null || true
        fi

        # Remove ecosystem config temporarily to force single instance
        if [ -f "whatsapp-service/ecosystem.multi-instance.config.js" ]; then
            mv whatsapp-service/ecosystem.multi-instance.config.js whatsapp-service/ecosystem.multi-instance.config.js.disabled
            DISABLED_ECOSYSTEM=true
            echo -e "${YELLOW}Temporarily disabled multi-instance config${NC}"
        fi

        # Start development environment
        ./start-dev.sh

        # Re-enable ecosystem config if it was disabled
        if [ "$DISABLED_ECOSYSTEM" = true ]; then
            mv whatsapp-service/ecosystem.multi-instance.config.js.disabled whatsapp-service/ecosystem.multi-instance.config.js
            echo -e "${GREEN}Re-enabled multi-instance config for future use${NC}"
        fi

        echo ""
        echo -e "${GREEN}✅ Single Instance Development Started${NC}"
        echo -e "${BLUE}WhatsApp Service: http://localhost:3001${NC}"
        ;;

    2)
        echo -e "${GREEN}🚀 Starting Multi-Instance Production Mode...${NC}"
        export WHATSAPP_MULTI_INSTANCE=true

        # Stop any existing single instance
        pkill -f "nodemon" 2>/dev/null || true
        pkill -f "whatsapp-service" 2>/dev/null || true

        # Ensure ecosystem config exists
        if [ ! -f "whatsapp-service/ecosystem.multi-instance.config.js" ]; then
            echo -e "${RED}❌ Multi-instance config not found${NC}"
            echo -e "${YELLOW}Ensure ecosystem.multi-instance.config.js exists in whatsapp-service/${NC}"
            exit 1
        fi

        # Start development environment with multi-instance
        ./start-dev.sh

        echo ""
        echo -e "${GREEN}✅ Multi-Instance Production Started${NC}"
        echo -e "${BLUE}Instances:${NC}"
        echo -e "   • Instance 1: http://localhost:3001"
        echo -e "   • Instance 2: http://localhost:3002"
        echo -e "   • Instance 3: http://localhost:3003"
        echo -e "   • Instance 4: http://localhost:3004"
        echo ""
        echo -e "${BLUE}Management Commands:${NC}"
        echo -e "   • Health Check: ${YELLOW}php artisan whatsapp:health-check${NC}"
        echo -e "   • PM2 Status: ${YELLOW}pm2 status${NC}"
        echo -e "   • PM2 Logs: ${YELLOW}pm2 logs${NC}"
        ;;

    3)
        echo -e "${YELLOW}🛑 Stopping All WhatsApp Services...${NC}"
        ./stop-dev.sh

        # Also stop PM2 instances if they exist
        if command -v pm2 &> /dev/null; then
            if pm2 list | grep -q "whatsapp-instance"; then
                echo -e "${YELLOW}Stopping PM2 WhatsApp instances...${NC}"
                pm2 stop ecosystem.multi-instance.config.js 2>/dev/null || pm2 stop all
                pm2 delete ecosystem.multi-instance.config.js 2>/dev/null || pm2 delete all
                pm2 save
                echo -e "${GREEN}✅ PM2 instances stopped${NC}"
            fi
        fi

        echo ""
        echo -e "${GREEN}✅ All WhatsApp Services Stopped${NC}"
        ;;

    *)
        echo -e "${RED}❌ Invalid choice. Please select 1, 2, or 3.${NC}"
        exit 1
        ;;
esac

echo ""
echo "======================================"