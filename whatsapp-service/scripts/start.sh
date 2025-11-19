#!/bin/bash

# WhatsApp Node.js Service Startup Script
#
# This script provides easy commands to start, stop, and manage the WhatsApp service
# using PM2 with different environments and configurations.
#
# Usage: ./scripts/start.sh [command] [environment]
#
# Commands:
#   start       Start the service
#   stop        Stop the service
#   restart     Restart the service
#   reload      Reload the service (graceful restart with zero downtime)
#   status      Show service status
#   logs        Show service logs
#   monitor     Open PM2 monitoring dashboard
#
# Environments:
#   development (default)
#   staging
#   production
#
# TASK-ARCH-4: Deployment and management scripts

set -e

# Default values
COMMAND=${1:-"start"}
ENVIRONMENT=${2:-"development"}
APP_NAME="whatsapp-service"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if PM2 is installed
check_pm2() {
    if ! command -v pm2 &> /dev/null; then
        log_error "PM2 is not installed. Please install it first:"
        echo "npm install -g pm2"
        exit 1
    fi
}

# Check if required directories exist
check_directories() {
    local dirs=("logs" "sessions")

    for dir in "${dirs[@]}"; do
        if [ ! -d "$PROJECT_DIR/$dir" ]; then
            log_info "Creating $dir directory..."
            mkdir -p "$PROJECT_DIR/$dir"
        fi
    done
}

# Check if .env file exists
check_env_file() {
    local env_file="$PROJECT_DIR/.env"

    if [ ! -f "$env_file" ]; then
        log_warning ".env file not found. Creating from template..."

        cat > "$env_file" << EOF
# WhatsApp Node.js Service Environment Configuration
# Generated on $(date)

# Application
NODE_ENV=$ENVIRONMENT
PORT=3000
LOG_LEVEL=info

# API Security
API_KEY=your-api-key-here
HMAC_SECRET=your-hmac-secret-here

# Laravel Integration
LARAVEL_URL=http://localhost:8000
LARAVEL_API_TOKEN=your-laravel-api-token-here

# WhatsApp Configuration
WHATSAPP_SYNC_BATCH_SIZE=50
WHATSAPP_SYNC_MAX_CONCURRENT=3
WHATSAPP_SYNC_WINDOW_DAYS=30
WHATSAPP_SYNC_MAX_CHATS=500
WHATSAPP_SYNC_RETRY_ATTEMPTS=3
WHATSAPP_SYNC_RETRY_DELAY_MS=1000

# Logging
LOG_FILE=./logs/whatsapp-service.log
LOG_MAX_SIZE=10485760
LOG_MAX_FILES=7
EOF

        log_warning "Please update the .env file with your actual configuration values."
        return 1
    fi

    return 0
}

# Start the service
start_service() {
    log_info "Starting $APP_NAME in $ENVIRONMENT mode..."

    cd "$PROJECT_DIR"

    # Check PM2 and prerequisites
    check_pm2
    check_directories

    # Check environment file
    if ! check_env_file; then
        log_warning "Environment file created. Please configure it and run again."
        exit 1
    fi

    # Start with PM2
    if [ "$ENVIRONMENT" = "production" ]; then
        pm2 start ecosystem.config.js --env production
    elif [ "$ENVIRONMENT" = "staging" ]; then
        pm2 start ecosystem.config.js --env staging
    else
        pm2 start ecosystem.config.js
    fi

    # Save PM2 configuration
    pm2 save

    log_success "$APP_NAME started successfully in $ENVIRONMENT mode"
    log_info "Status: $(pm2 prettylist | grep -A1 $APP_NAME | grep 'status' | cut -d':' -f2 | tr -d ' ')"
}

# Stop the service
stop_service() {
    log_info "Stopping $APP_NAME..."

    if pm2 list | grep -q "$APP_NAME"; then
        pm2 stop "$APP_NAME"
        log_success "$APP_NAME stopped successfully"
    else
        log_warning "$APP_NAME is not running"
    fi
}

# Restart the service
restart_service() {
    log_info "Restarting $APP_NAME in $ENVIRONMENT mode..."

    cd "$PROJECT_DIR"

    if [ "$ENVIRONMENT" = "production" ]; then
        pm2 restart ecosystem.config.js --env production
    elif [ "$ENVIRONMENT" = "staging" ]; then
        pm2 restart ecosystem.config.js --env staging
    else
        pm2 restart "$APP_NAME"
    fi

    pm2 save

    log_success "$APP_NAME restarted successfully"
}

# Reload the service (zero downtime)
reload_service() {
    log_info "Reloading $APP_NAME (zero downtime)..."

    cd "$PROJECT_DIR"

    if [ "$ENVIRONMENT" = "production" ]; then
        pm2 reload ecosystem.config.js --env production
    elif [ "$ENVIRONMENT" = "staging" ]; then
        pm2 reload ecosystem.config.js --env staging
    else
        pm2 reload "$APP_NAME"
    fi

    pm2 save

    log_success "$APP_NAME reloaded successfully"
}

# Show service status
show_status() {
    log_info "Service status for $APP_NAME:"

    if pm2 list | grep -q "$APP_NAME"; then
        pm2 show "$APP_NAME"
    else
        log_warning "$APP_NAME is not running or not managed by PM2"
    fi
}

# Show service logs
show_logs() {
    log_info "Showing logs for $APP_NAME..."
    pm2 logs "$APP_NAME"
}

# Open PM2 monitoring
open_monitor() {
    log_info "Opening PM2 monitoring dashboard..."
    pm2 monit
}

# Show help
show_help() {
    cat << EOF
WhatsApp Node.js Service Management Script

Usage: $0 [command] [environment]

Commands:
    start       Start the service
    stop        Stop the service
    restart     Restart the service
    reload      Reload the service (zero downtime)
    status      Show service status
    logs        Show service logs
    monitor     Open PM2 monitoring dashboard
    help        Show this help message

Environments:
    development (default)
    staging
    production

Examples:
    $0 start                    # Start in development mode
    $0 start production        # Start in production mode
    $0 reload                  # Reload in development mode
    $0 reload production      # Reload in production mode
    $0 status                  # Show service status

EOF
}

# Main execution
cd "$PROJECT_DIR"

case "$COMMAND" in
    "start")
        start_service
        ;;
    "stop")
        stop_service
        ;;
    "restart")
        restart_service
        ;;
    "reload")
        reload_service
        ;;
    "status")
        show_status
        ;;
    "logs")
        show_logs
        ;;
    "monitor")
        open_monitor
        ;;
    "help"|"--help"|"-h")
        show_help
        ;;
    *)
        log_error "Unknown command: $COMMAND"
        show_help
        exit 1
        ;;
esac