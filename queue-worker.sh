#!/bin/bash

# Queue Worker Watchdog Script
# Ensures queue worker stays running even after crashes or restarts

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
LOG_FILE="$SCRIPT_DIR/logs/queue-watchdog.log"
QUEUE_LOG="$SCRIPT_DIR/logs/queue.log"
PID_FILE="$SCRIPT_DIR/logs/queue.pid"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
    echo -e "$1"
}

check_queue_worker() {
    pgrep -f "php artisan queue:work" > /dev/null 2>&1
    return $?
}

start_queue_worker() {
    log "${GREEN}Starting queue worker...${NC}"
    cd "$SCRIPT_DIR"
    
    nohup php artisan queue:work \
        --queue=default,messaging,campaign-stats,whatsapp-urgent,whatsapp-high,whatsapp-normal,whatsapp-campaign \
        --tries=3 \
        --timeout=300 \
        --memory=512 \
        --sleep=3 \
        >> "$QUEUE_LOG" 2>&1 &
    
    echo $! > "$PID_FILE"
    log "${GREEN}Queue worker started with PID: $!${NC}"
}

stop_queue_worker() {
    log "${YELLOW}Stopping queue worker...${NC}"
    pkill -f "php artisan queue:work" 2>/dev/null
    rm -f "$PID_FILE"
    sleep 2
}

restart_queue_worker() {
    stop_queue_worker
    start_queue_worker
}

# Main logic
case "$1" in
    start)
        if check_queue_worker; then
            log "${YELLOW}Queue worker is already running${NC}"
        else
            start_queue_worker
        fi
        ;;
    stop)
        stop_queue_worker
        ;;
    restart)
        restart_queue_worker
        ;;
    status)
        if check_queue_worker; then
            PID=$(pgrep -f "php artisan queue:work" | head -1)
            log "${GREEN}Queue worker is running (PID: $PID)${NC}"
            
            # Show queue stats
            php artisan monitor:queue-size 2>/dev/null || echo "Queue monitor command not available"
        else
            log "${RED}Queue worker is NOT running${NC}"
        fi
        ;;
    watch)
        # Watchdog mode - runs continuously and restarts worker if it dies
        log "${YELLOW}Starting queue worker watchdog...${NC}"
        
        # Initial start if not running
        if ! check_queue_worker; then
            start_queue_worker
        fi
        
        # Monitor loop
        while true; do
            sleep 30
            
            if ! check_queue_worker; then
                log "${RED}Queue worker died! Restarting...${NC}"
                start_queue_worker
            fi
        done
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status|watch}"
        echo ""
        echo "Commands:"
        echo "  start   - Start queue worker if not running"
        echo "  stop    - Stop queue worker"
        echo "  restart - Restart queue worker"
        echo "  status  - Check queue worker status"
        echo "  watch   - Start watchdog mode (auto-restart on crash)"
        exit 1
        ;;
esac
