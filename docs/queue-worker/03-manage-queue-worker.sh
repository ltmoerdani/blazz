#!/bin/bash

# Campaign Queue Worker Manager
# Manage queue worker untuk campaign processing

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

case "$1" in
    start)
        echo "Starting queue worker..."
        nohup php artisan queue:work \
            --queue=whatsapp-urgent,whatsapp-high,whatsapp-normal,whatsapp-campaign \
            --tries=3 \
            --timeout=300 \
            > storage/logs/queue-worker.log 2>&1 &
        
        PID=$!
        echo "Queue worker started with PID: $PID"
        echo $PID > storage/queue-worker.pid
        ;;
    
    stop)
        echo "Stopping queue worker..."
        if [ -f storage/queue-worker.pid ]; then
            PID=$(cat storage/queue-worker.pid)
            kill $PID 2>/dev/null && echo "Queue worker stopped (PID: $PID)" || echo "Queue worker not running"
            rm storage/queue-worker.pid
        fi
        pkill -f "queue:work" && echo "All queue workers stopped"
        ;;
    
    restart)
        echo "Restarting queue worker..."
        $0 stop
        sleep 2
        $0 start
        ;;
    
    status)
        echo "Checking queue worker status..."
        if pgrep -f "queue:work" > /dev/null; then
            echo "✅ Queue worker is running"
            ps aux | grep "queue:work" | grep -v grep
        else
            echo "❌ Queue worker is NOT running"
        fi
        
        echo ""
        echo "Jobs in queue:"
        php artisan queue:monitor
        ;;
    
    log)
        echo "Showing queue worker log (last 50 lines)..."
        tail -50 storage/logs/queue-worker.log
        ;;
    
    monitor)
        echo "Monitoring queue worker log (press Ctrl+C to stop)..."
        tail -f storage/logs/queue-worker.log
        ;;
    
    *)
        echo "Usage: $0 {start|stop|restart|status|log|monitor}"
        echo ""
        echo "Commands:"
        echo "  start    - Start queue worker"
        echo "  stop     - Stop queue worker"
        echo "  restart  - Restart queue worker"
        echo "  status   - Check if queue worker is running"
        echo "  log      - Show last 50 lines of queue worker log"
        echo "  monitor  - Monitor queue worker log in real-time"
        exit 1
        ;;
esac
