#!/bin/bash

# Blazz Development Server Stop Script
# This script stops all services started by start-dev.sh

echo "🛑 Stopping Blazz Development Environment..."
echo "=============================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Kill processes by name/pattern
echo -e "${BLUE}Stopping services...${NC}"

# Stop Laravel serve
echo -e "${YELLOW}Stopping Laravel Backend...${NC}"
pkill -f "php artisan serve" && echo -e "${GREEN}✅ Laravel Backend stopped${NC}" || echo -e "${RED}❌ Laravel Backend not running${NC}"

# Stop Laravel Reverb
echo -e "${YELLOW}Stopping Laravel Reverb...${NC}"
pkill -f "php artisan reverb:start" && echo -e "${GREEN}✅ Laravel Reverb stopped${NC}" || echo -e "${RED}❌ Laravel Reverb not running${NC}"

# Stop Queue Worker
echo -e "${YELLOW}Stopping Queue Worker...${NC}"
pkill -f "php artisan queue:work" && echo -e "${GREEN}✅ Queue Worker stopped${NC}" || echo -e "${RED}❌ Queue Worker not running${NC}"

# Stop Node.js WhatsApp Service
echo -e "${YELLOW}Stopping WhatsApp Service...${NC}"
pkill -f "whatsapp-service" && echo -e "${GREEN}✅ WhatsApp Service stopped${NC}" || echo -e "${RED}❌ WhatsApp Service not running${NC}"

# Stop nodemon if running
pkill -f "nodemon" && echo -e "${GREEN}✅ Nodemon stopped${NC}" || echo -e "${RED}❌ Nodemon not running${NC}"

# Stop any remaining Node processes on port 3000
echo -e "${YELLOW}Checking for remaining processes on port 3000...${NC}"
lsof -ti:3000 | xargs kill -9 2>/dev/null && echo -e "${GREEN}✅ Port 3000 cleared${NC}" || echo -e "${BLUE}ℹ️  Port 3000 already free${NC}"

# Stop any remaining processes on port 8000
echo -e "${YELLOW}Checking for remaining processes on port 8000...${NC}"
lsof -ti:8000 | xargs kill -9 2>/dev/null && echo -e "${GREEN}✅ Port 8000 cleared${NC}" || echo -e "${BLUE}ℹ️  Port 8000 already free${NC}"

# Stop any remaining processes on port 8080
echo -e "${YELLOW}Checking for remaining processes on port 8080...${NC}"
lsof -ti:8080 | xargs kill -9 2>/dev/null && echo -e "${GREEN}✅ Port 8080 cleared${NC}" || echo -e "${BLUE}ℹ️  Port 8080 already free${NC}"

sleep 2

echo "=============================================="
echo -e "${GREEN}🏁 All services stopped successfully!${NC}"
echo -e "${BLUE}💡 To start services again, run: ./start-dev.sh${NC}"
echo "=============================================="