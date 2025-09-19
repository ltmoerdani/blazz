# 🔄 Blazz Development Workflow Guide

## 📋 Overview

This guide outlines best practices for developing Blazz with Laravel 12, covering daily workflows, debugging techniques, performance monitoring, and team collaboration patterns.

## 🚀 Daily Development Workflow

### Morning Startup Routine

```bash
# 1. Navigate to project
cd /Applications/MAMP/htdocs/Blazz

# 2. Check system status
php artisan about
git status

# 3. Update dependencies (if needed)
git pull origin main
composer install --no-dev
npm install

# 4. Start development servers
npm run dev &          # Terminal 1: Vite server (background)
php artisan serve      # Terminal 2: Laravel server (foreground)

# 5. Verify setup
curl -s http://127.0.0.1:8000 > /dev/null && echo "✅ Laravel OK"
curl -s http://localhost:5173 > /dev/null && echo "✅ Vite OK"
```

### Active Development Session

```bash
# Monitor logs in separate terminal
tail -f storage/logs/laravel.log

# Clear caches when switching features
php artisan optimize:clear

# Run tests before committing
php artisan test --parallel

# Database operations
php artisan migrate          # Apply new migrations
php artisan migrate:rollback # Rollback last migration
php artisan db:seed         # Seed test data
```

### End of Day Routine

```bash
# 1. Run full test suite
php artisan test --coverage

# 2. Check for uncommitted changes
git status
git diff

# 3. Commit work
git add .
git commit -m "feat: implement user authentication flow"

# 4. Push to remote
git push origin feature/user-auth

# 5. Stop servers
pkill -f "npm run dev"
pkill -f "php artisan serve"
```

## 🔧 Feature Development Workflow

### Starting New Feature

```bash
# 1. Create feature branch
git checkout -b feature/chat-rooms
git push -u origin feature/chat-rooms

# 2. Plan implementation
# Create TODO list in docs/features/chat-rooms.md

# 3. Database changes first
php artisan make:migration create_chat_rooms_table
php artisan make:model ChatRoom -mfs  # Model, Migration, Factory, Seeder

# 4. Backend implementation
php artisan make:controller ChatRoomController --resource
php artisan make:request StoreChatRoomRequest
php artisan make:request UpdateChatRoomRequest

# 5. Frontend implementation
# Create Vue components in resources/js/Components/
# Update Inertia pages in resources/js/Pages/

# 6. Testing
php artisan make:test ChatRoomControllerTest
php artisan make:test ChatRoomTest --unit
```

### Testing Workflow

```bash
# Run specific test
php artisan test --filter ChatRoomTest

# Run tests with coverage
php artisan test --coverage --min=80

# Run parallel tests (faster)
php artisan test --parallel

# Watch tests during development
php artisan test --watch

# Database testing
php artisan test --env=testing
```

### Code Quality Workflow

```bash
# PHP Code Standards
composer require --dev squizlabs/php_codesniffer
./vendor/bin/phpcs app/ --standard=PSR12

# Laravel Pint (code formatting)
composer require --dev laravel/pint
./vendor/bin/pint

# Static Analysis
composer require --dev larastan/larastan
./vendor/bin/phpstan analyse

# Frontend linting
npm run lint
npm run lint:fix
```

## 🐛 Debugging Workflow

### Laravel Debugging

```bash
# Enable query logging
php artisan tinker
>>> DB::enableQueryLog();
>>> // Perform actions
>>> DB::getQueryLog();

# Debug specific routes
php artisan route:list --path=api/chat
php artisan route:cache  # Cache routes for performance

# Monitor real-time logs
tail -f storage/logs/laravel.log | grep ERROR

# Tinker debugging
php artisan tinker
>>> $user = App\Models\User::find(1);
>>> $user->chatRooms()->count();
>>> $user->tokens()->where('name', 'auth')->first();
```

### Frontend Debugging

```bash
# Vite debugging
npm run dev -- --debug

# Build analysis
npm run build -- --debug
ls -la public/build/assets/

# Vue DevTools (browser extension required)
# Components tab: Inspect Vue component state
# Performance tab: Component render times
# Network tab: Asset loading times
```

### Performance Debugging

```bash
# Laravel performance profiling
composer require --dev barryvdh/laravel-debugbar
php artisan vendor:publish --provider="Barryvdh\Debugbar\ServiceProvider"

# Database query optimization
php artisan tinker
>>> DB::enableQueryLog();
>>> // Run application action
>>> collect(DB::getQueryLog())->where('time', '>', 100); // Slow queries

# Memory usage monitoring
php artisan tinker
>>> echo memory_get_usage(true) / 1024 / 1024 . ' MB';
```

## 📊 Performance Monitoring

### Application Performance Metrics

```bash
# Response time monitoring
time curl -s http://127.0.0.1:8000/api/user

# Database performance
php artisan tinker
>>> $start = microtime(true);
>>> App\Models\User::with('chatRooms')->get();
>>> echo (microtime(true) - $start) * 1000 . 'ms';

# Memory usage tracking
php artisan about --only=environment
```

### Frontend Performance Monitoring

```bash
# Bundle size analysis
npm run build
ls -lah public/build/assets/*.js  # Check JS bundle sizes
ls -lah public/build/assets/*.css # Check CSS bundle sizes

# Lighthouse performance testing (in browser)
# Open DevTools > Lighthouse > Performance audit

# Vite build analysis
npm run build -- --report
```

### Performance Benchmarks

**Target Performance Goals**:
- **Page Load Time**: < 500ms (local), < 2s (production)
- **Database Queries**: < 50ms average
- **API Response**: < 200ms
- **Bundle Size**: JS < 1MB, CSS < 200KB
- **Memory Usage**: < 128MB per request

## 🔄 Git Workflow

### Branch Strategy

```bash
# Main branches
main           # Production-ready code
develop        # Integration branch
feature/*      # Feature development
hotfix/*       # Production fixes
release/*      # Release preparation

# Feature workflow
git checkout develop
git pull origin develop
git checkout -b feature/user-notifications
# ... develop feature ...
git push origin feature/user-notifications
# Create pull request to develop
```

### Commit Conventions

```bash
# Conventional Commits format
feat: add user notification system
fix: resolve chat message ordering issue
docs: update API documentation
style: format code with Laravel Pint
refactor: extract chat service class
test: add user authentication tests
chore: update dependencies

# Examples
git commit -m "feat(auth): implement JWT token refresh"
git commit -m "fix(chat): resolve message duplication bug"
git commit -m "perf(db): optimize user queries with eager loading"
```

### Code Review Workflow

```bash
# Before creating PR
php artisan test                    # All tests pass
php artisan pint                   # Code formatting
php artisan optimize:clear         # Clear caches
npm run build                      # Assets build successfully
git push origin feature/branch-name

# PR checklist
# □ Tests added/updated
# □ Documentation updated
# □ No merge conflicts
# □ CI/CD passes
# □ Performance impact assessed
```

## 🛠️ Environment Management

### Development Environment

```bash
# .env.local (example)
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=blazz_dev

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

# Enable query logging
DB_LOG_QUERIES=true
LOG_LEVEL=debug
```

### Testing Environment

```bash
# .env.testing (example)
APP_ENV=testing
APP_DEBUG=true
DB_CONNECTION=sqlite
DB_DATABASE=:memory:

CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
```

### Environment Switching

```bash
# Switch to testing
cp .env.testing .env
php artisan config:clear
php artisan test

# Switch back to development
cp .env.local .env
php artisan config:clear
php artisan serve
```

## 🚀 Deployment Workflow

### Pre-deployment Checklist

```bash
# 1. Code quality
php artisan test --env=testing
./vendor/bin/pint --test
./vendor/bin/phpstan analyse

# 2. Build optimization
npm run build
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 3. Security check
php artisan config:show app.debug  # Should be false
grep "APP_ENV=production" .env      # Should exist

# 4. Database migration test
php artisan migrate:status
php artisan migrate --dry-run
```

### Production Environment Setup

```bash
# Production .env settings
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Cache drivers
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Database optimization
DB_SLOW_QUERY_LOG=true
DB_QUERY_CACHE=true
```

## 📚 Team Collaboration

### Code Standards

```php
// PHP Standards (PSR-12)
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatRoomRequest;
use App\Models\ChatRoom;
use Illuminate\Http\JsonResponse;

class ChatRoomController extends Controller
{
    public function store(StoreChatRoomRequest $request): JsonResponse
    {
        $chatRoom = ChatRoom::create($request->validated());
        
        return response()->json([
            'data' => $chatRoom,
            'message' => 'Chat room created successfully',
        ], 201);
    }
}
```

### Documentation Standards

```bash
# API Documentation
php artisan make:controller Api/ChatRoomController --api
# Add OpenAPI annotations

/**
 * @OA\Post(
 *     path="/api/chat-rooms",
 *     summary="Create a new chat room",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/StoreChatRoomRequest")
 *     ),
 *     @OA\Response(response=201, description="Chat room created")
 * )
 */
```

### Communication Workflow

```bash
# Daily standup preparation
git log --oneline --since="1 day ago" --author="$(git config user.email)"

# Weekly progress report
git log --oneline --since="1 week ago" --pretty=format:"%h %s" | grep -E "feat|fix"

# Release notes generation
git log --oneline v1.0.0..HEAD --pretty=format:"- %s (%h)" | grep -E "feat|fix"
```

## 🔧 IDE Configuration

### VS Code Setup

```json
// .vscode/settings.json
{
    "php.validate.executablePath": "/usr/local/bin/php",
    "php.suggest.basic": false,
    "intelephense.files.maxSize": 5000000,
    "editor.formatOnSave": true,
    "editor.codeActionsOnSave": {
        "source.fixAll.eslint": true
    }
}
```

### PhpStorm Setup

```bash
# Laravel plugin installation
# Preferences > Plugins > Browse Repositories > Laravel

# Code style configuration
# Preferences > Editor > Code Style > PHP > Set from PSR-12
```

## 📈 Monitoring & Analytics

### Application Monitoring

```bash
# Error tracking
composer require bugsnag/bugsnag-laravel
php artisan vendor:publish --provider="Bugsnag\BugsnagLaravel\BugsnagServiceProvider"

# Performance monitoring
composer require barryvdh/laravel-debugbar  # Development only
```

### Business Metrics

```bash
# User analytics
php artisan make:observer UserObserver --model=User
# Track user registration, login, activity

# Feature usage tracking
php artisan make:event FeatureUsed
php artisan make:listener TrackFeatureUsage
```

---

**🎯 Best Practices Summary**:
1. **Always run both servers** during development
2. **Test early and often** with comprehensive test suite
3. **Monitor performance** continuously during development
4. **Follow git conventions** for clean commit history
5. **Document as you code** for team collaboration
6. **Profile before optimizing** to identify real bottlenecks

**🚀 Happy Coding!** With Laravel 12 and modern tooling, Blazz development is faster and more reliable than ever!