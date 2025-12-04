# ============================================================================
# Blazz Docker Makefile
# ============================================================================
# Quick commands for Docker operations
# Usage: make [command]
# ============================================================================

.PHONY: help build up down restart logs shell mysql redis fresh migrate seed test

# Default target
help:
	@echo ""
	@echo "Blazz Docker Commands"
	@echo "====================="
	@echo ""
	@echo "  make build       - Build all Docker images"
	@echo "  make up          - Start all containers"
	@echo "  make up-dev      - Start with development services"
	@echo "  make down        - Stop all containers"
	@echo "  make restart     - Restart all containers"
	@echo "  make logs        - View all container logs"
	@echo "  make logs-app    - View app container logs"
	@echo "  make logs-wa     - View WhatsApp container logs"
	@echo ""
	@echo "  make shell       - Open bash shell in app container"
	@echo "  make shell-wa    - Open shell in WhatsApp container"
	@echo "  make mysql       - Open MySQL CLI"
	@echo "  make redis       - Open Redis CLI"
	@echo ""
	@echo "  make fresh       - Fresh install (build + migrate + seed)"
	@echo "  make migrate     - Run database migrations"
	@echo "  make seed        - Run database seeders"
	@echo "  make test        - Run PHPUnit tests"
	@echo ""
	@echo "  make clean       - Remove all containers and volumes"
	@echo "  make prune       - Clean Docker system (careful!)"
	@echo ""

# ============================================================================
# Container Management
# ============================================================================

build:
	docker compose build --no-cache

up:
	docker compose up -d

up-dev:
	docker compose -f compose.yaml -f compose.dev.yaml up -d

up-prod:
	docker compose -f compose.yaml -f compose.prod.yaml up -d

down:
	docker compose down

stop:
	docker compose stop

restart:
	docker compose restart

status:
	docker compose ps

# ============================================================================
# Logs
# ============================================================================

logs:
	docker compose logs -f

logs-app:
	docker compose logs -f app

logs-queue:
	docker compose logs -f queue

logs-wa:
	docker compose logs -f whatsapp

logs-nginx:
	docker compose logs -f nginx

logs-mysql:
	docker compose logs -f mysql

# ============================================================================
# Shell Access
# ============================================================================

shell:
	docker compose exec app bash

shell-wa:
	docker compose exec whatsapp sh

mysql:
	docker compose exec mysql mysql -u blazz -psecret blazz

redis:
	docker compose exec redis redis-cli

# ============================================================================
# Laravel Commands
# ============================================================================

artisan:
	docker compose exec app php artisan $(cmd)

migrate:
	docker compose exec app php artisan migrate

migrate-fresh:
	docker compose exec app php artisan migrate:fresh

seed:
	docker compose exec app php artisan db:seed

fresh:
	@echo "Building containers..."
	docker compose build
	@echo "Starting containers..."
	docker compose up -d
	@echo "Waiting for MySQL to be ready..."
	sleep 30
	@echo "Running migrations..."
	docker compose exec app php artisan migrate --force
	@echo "Running seeders..."
	docker compose exec app php artisan db:seed --force
	@echo "Generating app key..."
	docker compose exec app php artisan key:generate --force
	@echo "Clearing caches..."
	docker compose exec app php artisan optimize:clear
	@echo ""
	@echo "✅ Fresh install complete!"
	@echo "Access the app at: http://localhost"

test:
	docker compose exec app php artisan test

tinker:
	docker compose exec app php artisan tinker

cache-clear:
	docker compose exec app php artisan optimize:clear

# ============================================================================
# Node/NPM Commands
# ============================================================================

npm-install:
	docker compose exec app npm install

npm-build:
	docker compose exec app npm run build

npm-dev:
	docker compose exec app npm run dev

# ============================================================================
# Cleanup
# ============================================================================

clean:
	docker compose down -v --remove-orphans
	docker system prune -f

prune:
	@echo "WARNING: This will remove all unused Docker data!"
	@read -p "Are you sure? [y/N] " confirm && [ "$$confirm" = "y" ] && docker system prune -a -f --volumes

# ============================================================================
# Health Checks
# ============================================================================

health:
	@echo "Checking service health..."
	@echo ""
	@echo "App:"
	@curl -s http://localhost/health || echo "❌ App not responding"
	@echo ""
	@echo "WhatsApp Service:"
	@curl -s http://localhost:3001/health || echo "❌ WhatsApp not responding"
	@echo ""
	@echo "Container Status:"
	@docker compose ps
