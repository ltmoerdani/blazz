#!/bin/bash

# ============================================================================
# Docker Helper Script for Blazz
# ============================================================================
# Quick commands for Docker operations
# Usage: ./docker.sh [command]
# ============================================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Functions
print_header() {
    echo -e "${BLUE}============================================${NC}"
    echo -e "${BLUE}   $1${NC}"
    echo -e "${BLUE}============================================${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Commands
case "$1" in
    build)
        print_header "Building Docker Images"
        docker compose build --no-cache
        print_success "Build complete!"
        ;;

    up|start)
        print_header "Starting Containers"
        docker compose up -d
        print_success "Containers started!"
        echo ""
        echo "Services:"
        echo "  - App:      http://localhost"
        echo "  - WhatsApp: http://localhost:3001"
        ;;

    up-dev|dev)
        print_header "Starting Development Environment"
        docker compose -f compose.yaml -f compose.dev.yaml up -d
        print_success "Development environment started!"
        echo ""
        echo "Services:"
        echo "  - App:           http://localhost"
        echo "  - Vite:          http://localhost:5173"
        echo "  - phpMyAdmin:    http://localhost:8080"
        echo "  - Redis Commander: http://localhost:8081"
        echo "  - Mailpit:       http://localhost:8025"
        echo "  - WhatsApp:      http://localhost:3001"
        ;;

    up-prod|prod)
        print_header "Starting Production Environment"
        docker compose -f compose.yaml -f compose.prod.yaml up -d
        print_success "Production environment started!"
        ;;

    down|stop)
        print_header "Stopping Containers"
        docker compose down
        print_success "Containers stopped!"
        ;;

    restart)
        print_header "Restarting Containers"
        docker compose restart
        print_success "Containers restarted!"
        ;;

    logs)
        docker compose logs -f ${2:-}
        ;;

    shell)
        print_header "Opening App Shell"
        docker compose exec app bash
        ;;

    shell-wa)
        print_header "Opening WhatsApp Shell"
        docker compose exec whatsapp sh
        ;;

    mysql)
        print_header "Opening MySQL CLI"
        docker compose exec mysql mysql -u blazz -psecret blazz
        ;;

    redis)
        print_header "Opening Redis CLI"
        docker compose exec redis redis-cli
        ;;

    migrate)
        print_header "Running Migrations"
        docker compose exec app php artisan migrate
        print_success "Migrations complete!"
        ;;

    fresh)
        print_header "Fresh Installation"
        echo "Building containers..."
        docker compose build

        echo "Starting containers..."
        docker compose up -d

        echo "Waiting for MySQL (30s)..."
        sleep 30

        echo "Running migrations..."
        docker compose exec app php artisan migrate --force

        echo "Running seeders..."
        docker compose exec app php artisan db:seed --force

        echo "Generating app key..."
        docker compose exec app php artisan key:generate --force

        echo "Clearing caches..."
        docker compose exec app php artisan optimize:clear

        print_success "Fresh install complete!"
        echo ""
        echo "Access: http://localhost"
        ;;

    test)
        print_header "Running Tests"
        docker compose exec app php artisan test
        ;;

    status)
        print_header "Container Status"
        docker compose ps
        ;;

    health)
        print_header "Health Check"
        echo ""
        echo -n "App: "
        curl -sf http://localhost/health && echo "OK" || echo "FAILED"
        echo -n "WhatsApp: "
        curl -sf http://localhost:3001/health && echo "OK" || echo "FAILED"
        echo ""
        docker compose ps
        ;;

    clean)
        print_header "Cleaning Up"
        docker compose down -v --remove-orphans
        docker system prune -f
        print_success "Cleanup complete!"
        ;;

    *)
        echo ""
        echo "Blazz Docker Helper"
        echo "==================="
        echo ""
        echo "Usage: ./docker.sh [command]"
        echo ""
        echo "Commands:"
        echo "  build       Build Docker images"
        echo "  up/start    Start containers"
        echo "  up-dev/dev  Start with dev services (phpMyAdmin, Mailpit, etc.)"
        echo "  up-prod     Start production environment"
        echo "  down/stop   Stop containers"
        echo "  restart     Restart containers"
        echo "  logs [svc]  View logs (optional: service name)"
        echo "  shell       Open app shell"
        echo "  shell-wa    Open WhatsApp shell"
        echo "  mysql       Open MySQL CLI"
        echo "  redis       Open Redis CLI"
        echo "  migrate     Run migrations"
        echo "  fresh       Fresh install (build + migrate + seed)"
        echo "  test        Run tests"
        echo "  status      Show container status"
        echo "  health      Check service health"
        echo "  clean       Remove containers and volumes"
        echo ""
        ;;
esac
