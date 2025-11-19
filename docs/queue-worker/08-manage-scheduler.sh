#!/bin/bash

# Laravel Scheduler Management Script
# Manages Laravel Task Scheduler for campaign scheduling

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Project root
PROJECT_ROOT="/Applications/MAMP/htdocs/blazz"
LOG_FILE="$PROJECT_ROOT/logs/scheduler.log"

case "$1" in
    start)
        echo -e "${BLUE}Starting Laravel Scheduler...${NC}"
        cd "$PROJECT_ROOT"
        
        # Check if already running
        if pgrep -f "php artisan schedule:work" > /dev/null; then
            echo -e "${YELLOW}⚠️  Scheduler is already running${NC}"
            exit 0
        fi
        
        # Start scheduler
        nohup php artisan schedule:work > "$LOG_FILE" 2>&1 &
        SCHEDULER_PID=$!
        sleep 2
        
        # Verify started
        if pgrep -f "php artisan schedule:work" > /dev/null; then
            echo -e "${GREEN}✅ Scheduler started (PID: $SCHEDULER_PID)${NC}"
            echo -e "${BLUE}📋 Logs: tail -f $LOG_FILE${NC}"
        else
            echo -e "${RED}❌ Failed to start scheduler${NC}"
            exit 1
        fi
        ;;
        
    stop)
        echo -e "${BLUE}Stopping Laravel Scheduler...${NC}"
        
        if ! pgrep -f "php artisan schedule:work" > /dev/null; then
            echo -e "${YELLOW}⚠️  Scheduler is not running${NC}"
            exit 0
        fi
        
        pkill -f "php artisan schedule:work"
        sleep 2
        
        if ! pgrep -f "php artisan schedule:work" > /dev/null; then
            echo -e "${GREEN}✅ Scheduler stopped${NC}"
        else
            echo -e "${RED}❌ Failed to stop scheduler${NC}"
            exit 1
        fi
        ;;
        
    restart)
        echo -e "${BLUE}Restarting Laravel Scheduler...${NC}"
        
        # Stop
        if pgrep -f "php artisan schedule:work" > /dev/null; then
            pkill -f "php artisan schedule:work"
            echo -e "${GREEN}✅ Scheduler stopped${NC}"
        fi
        
        sleep 2
        
        # Start
        cd "$PROJECT_ROOT"
        nohup php artisan schedule:work > "$LOG_FILE" 2>&1 &
        SCHEDULER_PID=$!
        sleep 2
        
        if pgrep -f "php artisan schedule:work" > /dev/null; then
            echo -e "${GREEN}✅ Scheduler restarted (PID: $SCHEDULER_PID)${NC}"
        else
            echo -e "${RED}❌ Failed to restart scheduler${NC}"
            exit 1
        fi
        ;;
        
    status)
        echo -e "${BLUE}Checking Laravel Scheduler status...${NC}"
        echo ""
        
        if pgrep -f "php artisan schedule:work" > /dev/null; then
            echo -e "${GREEN}✅ Scheduler is running${NC}"
            echo ""
            echo -e "${BLUE}Process details:${NC}"
            ps aux | grep "php artisan schedule:work" | grep -v grep | awk '{print "PID: " $2 ", CPU: " $3 "%, MEM: " $4 "%, Started: " $9}'
            echo ""
            echo -e "${BLUE}Log file:${NC} $LOG_FILE"
            
            # Show last few log lines
            if [ -f "$LOG_FILE" ]; then
                echo ""
                echo -e "${BLUE}Last 5 log entries:${NC}"
                tail -5 "$LOG_FILE"
            fi
        else
            echo -e "${RED}❌ Scheduler is not running${NC}"
            echo ""
            echo -e "${YELLOW}💡 Start scheduler with: $0 start${NC}"
            exit 1
        fi
        ;;
        
    log)
        echo -e "${BLUE}Scheduler logs (last 50 lines):${NC}"
        echo "================================================"
        
        if [ -f "$LOG_FILE" ]; then
            tail -50 "$LOG_FILE"
        else
            echo -e "${RED}❌ Log file not found: $LOG_FILE${NC}"
        fi
        ;;
        
    monitor)
        echo -e "${BLUE}Monitoring scheduler logs (Ctrl+C to stop)...${NC}"
        echo "================================================"
        
        if [ ! -f "$LOG_FILE" ]; then
            echo -e "${YELLOW}⚠️  Log file doesn't exist yet. Creating...${NC}"
            touch "$LOG_FILE"
        fi
        
        tail -f "$LOG_FILE"
        ;;
        
    list)
        echo -e "${BLUE}Scheduled tasks:${NC}"
        echo "================================================"
        cd "$PROJECT_ROOT"
        php artisan schedule:list
        ;;
        
    test)
        echo -e "${BLUE}Running scheduler manually (dry run)...${NC}"
        echo "================================================"
        cd "$PROJECT_ROOT"
        php artisan schedule:run --verbose
        ;;
        
    info)
        echo -e "${BLUE}Laravel Scheduler Information${NC}"
        echo "================================================"
        echo ""
        echo -e "${YELLOW}Scheduled Tasks:${NC}"
        cd "$PROJECT_ROOT"
        php artisan schedule:list
        echo ""
        echo -e "${YELLOW}Configuration:${NC}"
        echo "  - File: app/Console/Kernel.php"
        echo "  - Frequency: Every minute"
        echo "  - Log file: $LOG_FILE"
        echo ""
        echo -e "${YELLOW}Status:${NC}"
        
        if pgrep -f "php artisan schedule:work" > /dev/null; then
            echo -e "  ${GREEN}✅ Running${NC}"
        else
            echo -e "  ${RED}❌ Not running${NC}"
        fi
        ;;
        
    *)
        echo -e "${BLUE}Laravel Scheduler Management${NC}"
        echo "================================================"
        echo ""
        echo "Usage: $0 {start|stop|restart|status|log|monitor|list|test|info}"
        echo ""
        echo -e "${YELLOW}Commands:${NC}"
        echo "  start     - Start the scheduler"
        echo "  stop      - Stop the scheduler"
        echo "  restart   - Restart the scheduler"
        echo "  status    - Check scheduler status"
        echo "  log       - View last 50 log lines"
        echo "  monitor   - Monitor logs in real-time"
        echo "  list      - List all scheduled tasks"
        echo "  test      - Run scheduler manually (dry run)"
        echo "  info      - Show scheduler information"
        echo ""
        echo -e "${YELLOW}Examples:${NC}"
        echo "  $0 start              # Start scheduler"
        echo "  $0 status             # Check if running"
        echo "  $0 monitor            # Watch logs live"
        echo "  $0 list               # Show scheduled tasks"
        echo ""
        exit 1
        ;;
esac
