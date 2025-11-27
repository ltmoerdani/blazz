#!/bin/bash

# Blazz Development Server Restart Script
# This script restarts all development services
# Combines stop-dev.sh and start-dev.sh functionality

echo "🔄 Restarting Blazz Development Environment..."
echo "=============================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Parse arguments
QUICK_MODE=false
SERVICE_ONLY=""

while [[ $# -gt 0 ]]; do
    case $1 in
        -q|--quick)
            QUICK_MODE=true
            shift
            ;;
        --laravel)
            SERVICE_ONLY="laravel"
            shift
            ;;
        --reverb)
            SERVICE_ONLY="reverb"
            shift
            ;;
        --whatsapp)
            SERVICE_ONLY="whatsapp"
            shift
            ;;
        --queue)
            SERVICE_ONLY="queue"
            shift
            ;;
        --scheduler)
            SERVICE_ONLY="scheduler"
            shift
            ;;
        -h|--help)
            echo "Blazz Development Server Restart Script"
            echo ""
            echo "Usage: ./restart-dev.sh [options]"
            echo ""
            echo "Options:"
            echo "  -q, --quick      Quick restart (skip verification)"
            echo "  --laravel        Restart only Laravel Backend"
            echo "  --reverb         Restart only Laravel Reverb"
            echo "  --whatsapp       Restart only WhatsApp Service"
            echo "  --queue          Restart only Queue Worker"
            echo "  --scheduler      Restart only Scheduler"
            echo "  -h, --help       Show this help message"
            echo ""
            echo "Examples:"
            echo "  ./restart-dev.sh              # Restart all services"
            echo "  ./restart-dev.sh -q           # Quick restart all services"
            echo "  ./restart-dev.sh --whatsapp   # Restart only WhatsApp service"
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            echo "Use -h or --help for usage information"
            exit 1
            ;;
    esac
done

# Function to restart a specific service
restart_service() {
    local service_name=$1
    local stop_pattern=$2
    local start_command=$3
    local log_file=$4
    local check_url=$5
    
    echo -e "${YELLOW}🔄 Restarting ${service_name}...${NC}"
    
    # Stop the service
    pkill -f "$stop_pattern" 2>/dev/null
    sleep 1
    
    # Start the service
    eval "nohup $start_command > $log_file 2>&1 &"
    local pid=$!
    
    # Wait and verify if URL is provided
    if [ -n "$check_url" ] && [ "$QUICK_MODE" = false ]; then
        local attempts=0
        local max_attempts=15
        
        while [ $attempts -lt $max_attempts ]; do
            if curl -s --connect-timeout 2 "$check_url" > /dev/null 2>&1; then
                echo -e "${GREEN}✅ ${service_name} restarted successfully (PID: $pid)${NC}"
                return 0
            fi
            sleep 1
            attempts=$((attempts + 1))
        done
        
        echo -e "${RED}❌ ${service_name} failed to restart${NC}"
        return 1
    else
        echo -e "${GREEN}✅ ${service_name} restart initiated (PID: $pid)${NC}"
        return 0
    fi
}

# Function to restart WhatsApp service (handles both single and multi-instance)
restart_whatsapp() {
    echo -e "${YELLOW}🔄 Restarting WhatsApp Service...${NC}"
    
    # Stop existing processes
    pkill -f "nodemon" 2>/dev/null
    pkill -f "whatsapp-service" 2>/dev/null
    
    # Clear ports
    for port in 3001 3002 3003 3004; do
        lsof -ti:$port 2>/dev/null | xargs kill -9 2>/dev/null
    done
    
    sleep 2
    
    cd whatsapp-service
    
    # Check if multi-instance mode
    if [ "$WHATSAPP_MULTI_INSTANCE" = "true" ] || [ -f "ecosystem.multi-instance.config.js" ]; then
        echo -e "${BLUE}🚀 Restarting Multi-Instance WhatsApp Service...${NC}"
        
        if command -v pm2 &> /dev/null; then
            pm2 restart ecosystem.multi-instance.config.js 2>/dev/null || pm2 start ecosystem.multi-instance.config.js
            pm2 save
            echo -e "${GREEN}✅ Multi-Instance WhatsApp Service restarted${NC}"
        else
            echo -e "${RED}❌ PM2 not found. Install with: npm install -g pm2${NC}"
            cd ..
            return 1
        fi
    else
        echo -e "${BLUE}💬 Restarting Single Instance WhatsApp Service...${NC}"
        nohup node_modules/.bin/nodemon server.js > ../logs/whatsapp-service.log 2>&1 &
        
        if [ "$QUICK_MODE" = false ]; then
            local attempts=0
            while [ $attempts -lt 20 ]; do
                if curl -s --connect-timeout 2 "http://127.0.0.1:3001/health" > /dev/null 2>&1; then
                    echo -e "${GREEN}✅ WhatsApp Service restarted successfully${NC}"
                    cd ..
                    return 0
                fi
                sleep 1
                attempts=$((attempts + 1))
            done
            echo -e "${RED}❌ WhatsApp Service failed to restart${NC}"
        else
            echo -e "${GREEN}✅ WhatsApp Service restart initiated${NC}"
        fi
    fi
    
    cd ..
    return 0
}

# Restart specific service or all services
if [ -n "$SERVICE_ONLY" ]; then
    echo -e "${CYAN}Restarting single service: ${SERVICE_ONLY}${NC}"
    echo ""
    
    case $SERVICE_ONLY in
        laravel)
            restart_service "Laravel Backend" "php artisan serve" \
                "php artisan serve --host=127.0.0.1 --port=8000" \
                "logs/laravel.log" "http://127.0.0.1:8000"
            ;;
        reverb)
            restart_service "Laravel Reverb" "php artisan reverb:start" \
                "php artisan reverb:start --host=127.0.0.1 --port=8080" \
                "logs/reverb.log" "http://127.0.0.1:8080"
            ;;
        whatsapp)
            restart_whatsapp
            ;;
        queue)
            restart_service "Queue Worker" "php artisan queue:work" \
                "php artisan queue:work --queue=messaging,campaign-stats,whatsapp-urgent,whatsapp-high,whatsapp-normal,whatsapp-campaign --tries=3 --timeout=300" \
                "logs/queue.log" ""
            ;;
        scheduler)
            restart_service "Scheduler" "php artisan schedule:work" \
                "php artisan schedule:work" \
                "logs/scheduler.log" ""
            ;;
    esac
else
    # Full restart - Stop all services first
    echo -e "${RED}🛑 Phase 1: Stopping all services...${NC}"
    echo ""
    
    # Kill all existing processes
    echo -e "${YELLOW}Stopping Laravel Backend...${NC}"
    pkill -f "php artisan serve" 2>/dev/null && echo -e "${GREEN}✅ Stopped${NC}" || echo -e "${BLUE}ℹ️  Not running${NC}"
    
    echo -e "${YELLOW}Stopping Laravel Reverb...${NC}"
    pkill -f "php artisan reverb:start" 2>/dev/null && echo -e "${GREEN}✅ Stopped${NC}" || echo -e "${BLUE}ℹ️  Not running${NC}"
    
    echo -e "${YELLOW}Stopping Queue Worker...${NC}"
    pkill -f "php artisan queue:work" 2>/dev/null && echo -e "${GREEN}✅ Stopped${NC}" || echo -e "${BLUE}ℹ️  Not running${NC}"
    
    echo -e "${YELLOW}Stopping Scheduler...${NC}"
    pkill -f "php artisan schedule:work" 2>/dev/null && echo -e "${GREEN}✅ Stopped${NC}" || echo -e "${BLUE}ℹ️  Not running${NC}"
    
    echo -e "${YELLOW}Stopping WhatsApp Service...${NC}"
    pkill -f "nodemon" 2>/dev/null
    pkill -f "whatsapp-service" 2>/dev/null
    
    # Check for PM2 multi-instance
    if command -v pm2 &> /dev/null && pm2 list 2>/dev/null | grep -q "whatsapp-instance"; then
        pm2 stop all 2>/dev/null
        pm2 delete all 2>/dev/null
    fi
    
    # Clear all ports
    for port in 3001 3002 3003 3004 8000 8080; do
        lsof -ti:$port 2>/dev/null | xargs kill -9 2>/dev/null
    done
    
    echo -e "${GREEN}✅ All services stopped${NC}"
    
    # Wait for cleanup
    sleep 3
    
    echo ""
    echo -e "${GREEN}🚀 Phase 2: Starting all services...${NC}"
    echo ""
    
    # Check Redis
    echo -e "${BLUE}Checking Redis...${NC}"
    if redis-cli ping > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Redis is running${NC}"
        REDIS_RUNNING=true
    else
        echo -e "${YELLOW}⚠️  Redis is not running (RemoteAuth will fallback to LocalAuth)${NC}"
        REDIS_RUNNING=false
    fi
    
    # Create logs directory
    mkdir -p logs
    
    # Start Laravel Backend
    echo -e "${BLUE}1. Starting Laravel Backend (Port 8000)...${NC}"
    nohup php artisan serve --host=127.0.0.1 --port=8000 > logs/laravel.log 2>&1 &
    LARAVEL_PID=$!
    
    # Start Laravel Reverb
    echo -e "${BLUE}2. Starting Laravel Reverb (Port 8080)...${NC}"
    nohup php artisan reverb:start --host=127.0.0.1 --port=8080 > logs/reverb.log 2>&1 &
    REVERB_PID=$!
    
    # Start WhatsApp Service
    echo -e "${BLUE}3. Starting WhatsApp Service...${NC}"
    cd whatsapp-service
    
    if [ "$WHATSAPP_MULTI_INSTANCE" = "true" ] || [ -f "ecosystem.multi-instance.config.js" ]; then
        echo -e "${GREEN}🚀 Starting Multi-Instance WhatsApp Service...${NC}"
        if command -v pm2 &> /dev/null; then
            pm2 start ecosystem.multi-instance.config.js
            pm2 save
            WHATSAPP_PID="pm2_multi_instance"
        else
            echo -e "${YELLOW}⚠️  PM2 not found, falling back to single instance${NC}"
            nohup node_modules/.bin/nodemon server.js > ../logs/whatsapp-service.log 2>&1 &
            WHATSAPP_PID=$!
        fi
    else
        nohup node_modules/.bin/nodemon server.js > ../logs/whatsapp-service.log 2>&1 &
        WHATSAPP_PID=$!
    fi
    cd ..
    
    # Start Queue Worker
    echo -e "${BLUE}4. Starting Queue Worker...${NC}"
    nohup php artisan queue:work --queue=messaging,campaign-stats,whatsapp-urgent,whatsapp-high,whatsapp-normal,whatsapp-campaign --tries=3 --timeout=300 > logs/queue.log 2>&1 &
    QUEUE_PID=$!
    
    # Start Scheduler
    echo -e "${BLUE}5. Starting Scheduler...${NC}"
    nohup php artisan schedule:work > logs/scheduler.log 2>&1 &
    SCHEDULER_PID=$!
    
    # Verify services (unless quick mode)
    if [ "$QUICK_MODE" = false ]; then
        echo ""
        echo -e "${YELLOW}⏳ Verifying services...${NC}"
        sleep 5
        
        SERVICES_OK=true
        
        # Check Laravel
        echo -n "Laravel Backend: "
        if curl -s --connect-timeout 5 "http://127.0.0.1:8000" > /dev/null 2>&1; then
            echo -e "${GREEN}✅ Running${NC}"
        else
            echo -e "${RED}❌ Failed${NC}"
            SERVICES_OK=false
        fi
        
        # Check Reverb
        echo -n "Laravel Reverb: "
        if curl -s --connect-timeout 5 "http://127.0.0.1:8080" > /dev/null 2>&1; then
            echo -e "${GREEN}✅ Running${NC}"
        else
            echo -e "${RED}❌ Failed${NC}"
            SERVICES_OK=false
        fi
        
        # Check WhatsApp
        echo -n "WhatsApp Service: "
        local wa_attempts=0
        while [ $wa_attempts -lt 20 ]; do
            if curl -s --connect-timeout 2 "http://127.0.0.1:3001/health" > /dev/null 2>&1; then
                echo -e "${GREEN}✅ Running${NC}"
                break
            fi
            sleep 1
            wa_attempts=$((wa_attempts + 1))
        done
        if [ $wa_attempts -eq 20 ]; then
            echo -e "${RED}❌ Failed${NC}"
            SERVICES_OK=false
        fi
        
        # Check Queue Worker
        echo -n "Queue Worker: "
        if pgrep -f "php artisan queue:work" > /dev/null 2>&1; then
            echo -e "${GREEN}✅ Running${NC}"
        else
            echo -e "${RED}❌ Failed${NC}"
            SERVICES_OK=false
        fi
        
        # Check Scheduler
        echo -n "Scheduler: "
        if pgrep -f "php artisan schedule:work" > /dev/null 2>&1; then
            echo -e "${GREEN}✅ Running${NC}"
        else
            echo -e "${RED}❌ Failed${NC}"
            SERVICES_OK=false
        fi
    else
        SERVICES_OK=true
        echo -e "${YELLOW}⚡ Quick mode: skipping verification${NC}"
    fi
    
    echo ""
    echo "=============================================="
    
    if [ "$SERVICES_OK" = true ]; then
        echo -e "${GREEN}🎉 All services restarted successfully!${NC}"
        echo ""
        echo -e "${BLUE}Service URLs:${NC}"
        echo "📱 Laravel App: http://127.0.0.1:8000"
        echo "🔄 Reverb: http://127.0.0.1:8080"
        echo "💬 WhatsApp: http://127.0.0.1:3001"
        echo ""
        echo -e "${BLUE}Process IDs:${NC}"
        echo "Laravel: $LARAVEL_PID | Reverb: $REVERB_PID | WhatsApp: $WHATSAPP_PID"
        echo "Queue: $QUEUE_PID | Scheduler: $SCHEDULER_PID"
    else
        echo -e "${RED}❌ Some services failed to restart${NC}"
        echo -e "${YELLOW}📋 Check logs: tail -f logs/*.log${NC}"
    fi
    
    echo ""
    echo -e "${YELLOW}💡 Monitor status: ./monitor-dev.sh${NC}"
    echo "=============================================="
fi
