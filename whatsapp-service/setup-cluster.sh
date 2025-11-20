#!/bin/bash

# PM2 Cluster Setup Script for WhatsApp Service
# This script sets up PM2 cluster mode for horizontal scaling

echo "🚀 Setting up PM2 Cluster for WhatsApp Service..."
echo "=============================================="

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if PM2 is installed
if ! command -v pm2 &> /dev/null; then
    echo -e "${YELLOW}PM2 not found. Installing...${NC}"
    npm install -g pm2
    
    if [ $? -ne 0 ]; then
        echo -e "${RED}❌ Failed to install PM2${NC}"
        exit 1
    fi
    echo -e "${GREEN}✅ PM2 installed successfully${NC}"
else
    echo -e "${GREEN}✅ PM2 is already installed${NC}"
fi

# Check if Redis is running
echo -e "${BLUE}Checking Redis...${NC}"
if ! redis-cli ping &> /dev/null; then
    echo -e "${YELLOW}⚠️  Redis is not running${NC}"
    echo -e "${YELLOW}Please start Redis before proceeding:${NC}"
    echo -e "${YELLOW}  brew services start redis  (macOS)${NC}"
    echo -e "${YELLOW}  sudo systemctl start redis (Linux)${NC}"
    exit 1
else
    echo -e "${GREEN}✅ Redis is running${NC}"
fi

# Stop existing WhatsApp service
echo -e "${BLUE}Stopping existing WhatsApp service...${NC}"
pm2 delete whatsapp-cluster 2>/dev/null || true
pkill -f "node.*server.js.*whatsapp-service" 2>/dev/null || true

# Start PM2 cluster
echo -e "${BLUE}Starting PM2 cluster (5 workers)...${NC}"
cd "$(dirname "$0")"
pm2 start ecosystem.config.js

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Failed to start PM2 cluster${NC}"
    exit 1
fi

# Wait for workers to start
echo -e "${BLUE}Waiting for workers to initialize...${NC}"
sleep 5

# Verify workers are running
RUNNING_WORKERS=$(pm2 list | grep -c "whatsapp-cluster" || echo "0")

if [ "$RUNNING_WORKERS" -lt 5 ]; then
    echo -e "${RED}❌ Not all workers started successfully${NC}"
    pm2 logs whatsapp-cluster --lines 20
    exit 1
fi

echo -e "${GREEN}✅ All workers started successfully${NC}"

# Save PM2 process list
echo -e "${BLUE}Saving PM2 configuration...${NC}"
pm2 save

# Setup PM2 startup script
echo -e "${BLUE}Setting up PM2 auto-startup...${NC}"
pm2 startup

echo ""
echo -e "${GREEN}=============================================="
echo -e "🎉 PM2 Cluster setup completed!"
echo -e "=============================================="
echo ""
echo -e "${BLUE}Cluster Status:${NC}"
pm2 status

echo ""
echo -e "${BLUE}Useful Commands:${NC}"
echo -e "  ${YELLOW}pm2 status${NC}          - View cluster status"
echo -e "  ${YELLOW}pm2 monit${NC}           - Real-time monitoring"
echo -e "  ${YELLOW}pm2 logs${NC}            - View logs"
echo -e "  ${YELLOW}pm2 restart all${NC}     - Restart all workers"
echo -e "  ${YELLOW}pm2 stop all${NC}        - Stop all workers"
echo -e "  ${YELLOW}pm2 delete all${NC}      - Remove all workers"
echo ""
echo -e "${GREEN}Your WhatsApp service is now running in cluster mode!${NC}"
echo -e "${GREEN}Max capacity: ~250 concurrent sessions (5 workers x 50 sessions)${NC}"
