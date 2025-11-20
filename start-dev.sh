#!/bin/bash

# Blazz Development Server Startup Script
# This script starts all necessary services for WhatsApp Web.js integration
# Updated for RemoteAuth architecture with Redis support

echo "🚀 Starting Blazz Development Environment..."
echo "=============================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to check if service is running
check_service() {
    local service_name=$1
    local port=$2
    local url=$3
    
    echo -e "${BLUE}Checking ${service_name}...${NC}"
    if curl -s --connect-timeout 5 "$url" > /dev/null 2>&1; then
        echo -e "${GREEN}✅ ${service_name} is running on port ${port}${NC}"
        return 0
    else
        echo -e "${RED}❌ ${service_name} is not running on port ${port}${NC}"
        return 1
    fi
}

# Function to wait for service
wait_for_service() {
    local service_name=$1
    local url=$2
    local max_attempts=30
    local attempt=1
    
    echo -e "${YELLOW}⏳ Waiting for ${service_name} to start...${NC}"
    
    while [ $attempt -le $max_attempts ]; do
        if curl -s --connect-timeout 2 "$url" > /dev/null 2>&1; then
            echo -e "${GREEN}✅ ${service_name} is ready!${NC}"
            return 0
        fi
        
        echo -n "."
        sleep 2
        attempt=$((attempt + 1))
    done
    
    echo -e "${RED}❌ ${service_name} failed to start after $((max_attempts * 2)) seconds${NC}"
    return 1
}

# Check if already running
echo -e "${BLUE}Checking existing services...${NC}"

# Kill existing processes if they exist
pkill -f "php artisan serve"
pkill -f "php artisan reverb:start"
pkill -f "php artisan queue:work"
pkill -f "php artisan schedule:work"
pkill -f "whatsapp-service"
pkill -f "nodemon"
sleep 2

# Check Redis (required for RemoteAuth)
echo -e "${BLUE}Checking Redis server...${NC}"
if redis-cli ping > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Redis is running${NC}"
    REDIS_RUNNING=true
else
    echo -e "${YELLOW}⚠️  Redis is not running${NC}"
    echo -e "${YELLOW}   RemoteAuth will fallback to LocalAuth${NC}"
    echo -e "${YELLOW}   To enable RemoteAuth: brew services start redis${NC}"
    REDIS_RUNNING=false
fi

echo -e "${YELLOW}Starting services in background...${NC}"

# Start Laravel Backend
echo -e "${BLUE}1. Starting Laravel Backend (Port 8000)...${NC}"
cd /Applications/MAMP/htdocs/blazz
nohup php artisan serve --host=127.0.0.1 --port=8000 > logs/laravel.log 2>&1 &
LARAVEL_PID=$!

# Start Laravel Reverb (Broadcasting)
echo -e "${BLUE}2. Starting Laravel Reverb (Port 8080)...${NC}"
nohup php artisan reverb:start --host=127.0.0.1 --port=8080 > logs/reverb.log 2>&1 &
REVERB_PID=$!

# Start Node.js WhatsApp Service
echo -e "${BLUE}3. Starting WhatsApp Node.js Service (Port 3001)...${NC}"
cd whatsapp-service
nohup node_modules/.bin/nodemon server.js > ../logs/whatsapp-service.log 2>&1 &
WHATSAPP_PID=$!
cd ..

# Start Queue Worker
echo -e "${BLUE}4. Starting Queue Worker...${NC}"
nohup php artisan queue:work --queue=messaging,campaign-stats,whatsapp-urgent,whatsapp-high,whatsapp-normal,whatsapp-campaign --tries=3 --timeout=300 > logs/queue.log 2>&1 &
QUEUE_PID=$!

# Start Laravel Scheduler
echo -e "${BLUE}5. Starting Laravel Scheduler...${NC}"
nohup php artisan schedule:work > logs/scheduler.log 2>&1 &
SCHEDULER_PID=$!

# Create logs directory if it doesn't exist
mkdir -p logs

# Wait for services to start
sleep 5

echo -e "${YELLOW}Verifying services...${NC}"

# Check services
SERVICES_OK=true

if ! wait_for_service "Laravel Backend" "http://127.0.0.1:8000"; then
    SERVICES_OK=false
fi

if ! wait_for_service "Laravel Reverb" "http://127.0.0.1:8080"; then
    SERVICES_OK=false
fi

if ! wait_for_service "WhatsApp Service" "http://127.0.0.1:3001/health"; then
    SERVICES_OK=false
fi

# Check WhatsApp Service health details
if [ "$SERVICES_OK" = true ]; then
    echo -e "${BLUE}Checking WhatsApp Service health...${NC}"
    HEALTH_RESPONSE=$(curl -s http://127.0.0.1:3001/health)
    AUTH_STRATEGY=$(echo $HEALTH_RESPONSE | grep -o '"authStrategy":"[^"]*"' | cut -d'"' -f4)
    
    if [ "$AUTH_STRATEGY" = "remoteauth" ]; then
        echo -e "${GREEN}✅ RemoteAuth enabled (Redis-backed)${NC}"
    else
        echo -e "${YELLOW}⚠️  LocalAuth active (file-based)${NC}"
    fi
fi

# Final status
echo "=============================================="
if [ "$SERVICES_OK" = true ]; then
    echo -e "${GREEN}🎉 All services are running successfully!${NC}"
    echo ""
    echo -e "${BLUE}Service URLs:${NC}"
    echo "📱 Laravel App: http://127.0.0.1:8000"
    echo "🔄 Reverb Broadcasting: http://127.0.0.1:8080"
    echo "💬 WhatsApp Service: http://127.0.0.1:3001"
    echo ""
    echo -e "${BLUE}Health Check Endpoints:${NC}"
    echo "🏥 Overall Health: http://127.0.0.1:3001/health"
    echo "📊 Redis Health: http://127.0.0.1:3001/health/redis"
    echo "💾 Sessions: http://127.0.0.1:3001/health/sessions"
    echo "🔄 Migration: http://127.0.0.1:3001/health/migration"
    echo ""
    echo -e "${BLUE}Process IDs:${NC}"
    echo "Laravel: $LARAVEL_PID"
    echo "Reverb: $REVERB_PID" 
    echo "WhatsApp: $WHATSAPP_PID"
    echo "Queue: $QUEUE_PID"
    echo "Scheduler: $SCHEDULER_PID"
    echo ""
    if [ "$REDIS_RUNNING" = true ]; then
        echo -e "${GREEN}🔴 Redis: Running${NC}"
        echo -e "${BLUE}   Auth Strategy: ${AUTH_STRATEGY:-localauth}${NC}"
    else
        echo -e "${YELLOW}🔴 Redis: Not Running (LocalAuth fallback active)${NC}"
    fi
    echo ""
    echo -e "${YELLOW}💡 To stop all services, run: ./stop-dev.sh${NC}"
    echo -e "${YELLOW}📋 To view logs: tail -f logs/*.log${NC}"
    echo -e "${YELLOW}🔧 To migrate sessions: cd whatsapp-service && node src/utils/SessionMigration.js${NC}"
else
    echo -e "${RED}❌ Some services failed to start. Check logs for details.${NC}"
    echo -e "${YELLOW}📋 Check logs: ls -la logs/${NC}"
fi

echo "=============================================="