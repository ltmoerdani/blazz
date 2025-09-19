# 🔧 SwiftChats Troubleshooting Guide

## 📋 Overview

This guide covers common issues encountered during SwiftChats development after the Laravel 12 upgrade, with proven solutions based on real troubleshooting experience.

## 🎯 Quick Diagnosis

### Diagnostic Checklist
```bash
# Quick health check
php artisan --version          # Should show Laravel 12.29.0
npm --version                  # Should show 9+
php artisan about             # System information
php artisan config:show app   # App configuration
```

### Common Symptoms & Quick Fixes

| Symptom | Quick Fix | Details |
|---------|-----------|---------|
| 🖥️ Black & white application | `npm run dev` + `php artisan serve` | [See Issue #1](#issue-1-black--white-application) |
| ❌ ERR_CONNECTION_CLOSED | Check `.env` APP_ENV=local | [See Issue #2](#issue-2-err_connection_closed-errors) |
| 🐌 Slow loading/timeouts | Clear caches + restart servers | [See Issue #3](#issue-3-slow-performance) |
| 📦 Composer errors | `composer clear-cache` | [See Issue #4](#issue-4-composer-dependency-issues) |
| 🗄️ Database connection failed | Check MySQL service + credentials | [See Issue #5](#issue-5-database-connection-issues) |

## 🚨 Critical Issues

### Issue #1: Black & White Application

**Description**: Application loads but appears completely unstyled, all colors missing.

**Symptoms**:
- ✅ Laravel server starts successfully
- ✅ Application loads without errors
- ❌ No styling applied (black text on white background)
- ❌ Browser console shows asset loading errors
- ❌ CSS/JS files return 404 or connection errors

**Root Cause Analysis**:
```bash
# Check asset references in browser network tab
# Expected: Assets loading from localhost:5173
# Actual: Assets failing to load or returning 404

# Check Vite server status
curl http://localhost:5173  # Should return 200
# If fails: Vite server not running
```

**Solution Steps**:
```bash
# Step 1: Verify both servers are running
# Terminal 1: Start Vite server
npm run dev
# Output should show: "VITE v4.5.14 ready in 324 ms"

# Terminal 2: Start Laravel server  
php artisan serve
# Output should show: "Starting Laravel development server"

# Step 2: Verify .env configuration
grep -E "APP_ENV|APP_DEBUG|APP_URL" .env
# Should show:
# APP_ENV=local
# APP_DEBUG=true  
# APP_URL=http://127.0.0.1:8000

# Step 3: Clear all caches
php artisan optimize:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Step 4: Test access
curl -I http://127.0.0.1:8000      # Laravel server
curl -I http://localhost:5173       # Vite server
```

**Verification**:
- Browser network tab shows assets loading from `localhost:5173`
- Application displays with full styling and colors
- No console errors related to asset loading

### Issue #2: ERR_CONNECTION_CLOSED Errors

**Description**: Browser console shows connection closed errors for CSS/JS assets.

**Symptoms**:
- ❌ `GET http://localhost:5173/resources/css/app.css net::ERR_CONNECTION_CLOSED`
- ❌ `GET http://localhost:5173/resources/js/app.js net::ERR_CONNECTION_CLOSED`
- ❌ Assets fail to load despite Laravel server running

**Root Cause Analysis**:
```bash
# Check environment configuration
cat .env | grep APP_ENV
# If shows APP_ENV=production: This is the problem

# Check server status
lsof -i :5173  # Should show npm/node process
# If empty: Vite server not running
```

**Solution Steps**:
```bash
# Step 1: Fix environment configuration
# Edit .env file
APP_ENV=local           # Change from 'production' to 'local'
APP_DEBUG=true          # Change from 'false' to 'true'
APP_URL=http://127.0.0.1:8000  # Ensure matches server URL

# Step 2: Restart both servers after .env changes
# Kill existing processes
pkill -f "npm run dev"
pkill -f "php artisan serve"

# Restart servers
npm run dev &           # Background process
php artisan serve      # Foreground process

# Step 3: Verify network connectivity
netstat -an | grep 5173  # Vite port should be listening
netstat -an | grep 8000  # Laravel port should be listening
```

**Prevention**:
- Always use `APP_ENV=local` for development
- Never set `APP_ENV=production` in local development
- Commit `.env.example` with correct development settings

### Issue #3: Slow Performance

**Description**: Application loads slowly, high response times, timeouts.

**Symptoms**:
- 🐌 Page load times > 5 seconds
- ⏰ Frequent timeout errors
- 🔄 Long asset compilation times
- 💾 High memory usage

**Root Cause Analysis**:
```bash
# Check system resources
top | grep -E "php|node|npm"
# Look for high CPU/memory usage

# Check Laravel performance
php artisan about
# Look for cache status, debug mode, environment

# Check database performance
php artisan tinker
>>> DB::enableQueryLog();
>>> App\Models\User::first();
>>> DB::getQueryLog();
# Look for slow queries
```

**Solution Steps**:
```bash
# Step 1: Clear all caches
php artisan optimize:clear
php artisan config:clear
php artisan cache:clear  
php artisan view:clear
php artisan route:clear

# Step 2: Restart servers
pkill -f "npm"
pkill -f "php artisan serve"
npm run dev &
php artisan serve

# Step 3: Check for memory leaks
# Monitor memory usage during development
ps aux | grep -E "php|node" | awk '{print $2, $4, $11}'

# Step 4: Database optimization
php artisan migrate:status
php artisan optimize
```

**Performance Monitoring**:
```bash
# Monitor real-time performance
# Terminal 1: Server logs
tail -f storage/logs/laravel.log

# Terminal 2: System resources
watch "ps aux | grep -E 'php|node' | head -10"

# Terminal 3: Network activity
watch "netstat -an | grep -E '5173|8000'"
```

## 🛠️ Installation Issues

### Issue #4: Composer Dependency Issues

**Description**: Composer install fails with dependency conflicts or platform requirements.

**Common Error Messages**:
```
Your requirements could not be resolved to an installable set of packages.
Problem 1
- laravel/framework[v12.29.0] requires php ^8.2
```

**Solution Steps**:
```bash
# Step 1: Check PHP version
php --version
# Must be 8.2 or higher for Laravel 12

# Step 2: Clear composer cache
composer clear-cache
rm -rf vendor/
rm composer.lock

# Step 3: Update dependencies
composer install --no-cache
composer update

# Step 4: If platform requirements fail
composer install --ignore-platform-reqs
# Warning: Only use temporarily, fix underlying issue
```

### Issue #5: Database Connection Issues

**Description**: Application cannot connect to MySQL database.

**Common Error Messages**:
```
SQLSTATE[HY000] [2002] Connection refused
SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost'
```

**Solution Steps**:
```bash
# Step 1: Verify MySQL service (macOS with MAMP)
# Open MAMP application
# Ensure MySQL server is running (green light)
# Note the port (usually 3306 or 8889)

# Step 2: Test database connection
mysql -h 127.0.0.1 -P 3306 -u root -p
# Should prompt for password and connect

# Step 3: Verify database exists
mysql -u root -p
SHOW DATABASES;
# Should list 'swiftchats' database

# Step 4: Create database if missing
CREATE DATABASE swiftchats CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Step 5: Update .env with correct credentials
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306              # Or 8889 for MAMP
DB_DATABASE=swiftchats
DB_USERNAME=root
DB_PASSWORD=your_password  # MAMP default is often 'root'

# Step 6: Test Laravel database connection
php artisan tinker
>>> DB::select('SELECT 1 as test');
# Should return [0 => {#...}]
```

## 🔄 Development Workflow Issues

### Issue #6: Hot Module Replacement Not Working

**Description**: Changes in Vue components don't reflect immediately.

**Solution**:
```bash
# Ensure Vite server is in development mode
npm run dev  # NOT npm run build

# Clear browser cache completely
# Chrome: Ctrl+Shift+R (hard refresh)
# Safari: Cmd+Option+R

# Restart Vite with specific configuration
npm run dev -- --host 127.0.0.1 --port 5173
```

### Issue #7: Permission Errors (macOS/Linux)

**Description**: Permission denied errors during development.

**Solution**:
```bash
# Fix storage permissions
chmod -R 775 storage bootstrap/cache

# Fix ownership (if needed)
sudo chown -R $USER:_www storage bootstrap/cache

# For MAMP users
sudo chown -R $USER:staff storage bootstrap/cache
```

## 🔍 Advanced Debugging

### Debug Mode Configuration

```bash
# Enable comprehensive debugging
APP_DEBUG=true
LOG_LEVEL=debug
DB_LOG_QUERIES=true

# View detailed error information
tail -f storage/logs/laravel.log

# Enable query logging
php artisan tinker
>>> DB::enableQueryLog();
>>> // Run your application
>>> DB::getQueryLog();
```

### Network Debugging

```bash
# Check port availability
lsof -i :5173  # Vite server
lsof -i :8000  # Laravel server

# Test connectivity
curl -v http://localhost:5173
curl -v http://127.0.0.1:8000

# Monitor network traffic
# Use browser developer tools Network tab
# Monitor for failed requests
```

### Asset Compilation Debugging

```bash
# Build assets with verbose output
npm run build -- --debug

# Check Vite configuration
cat vite.config.js

# Verify asset references
grep -r "@vite" resources/views/
```

## 📊 Performance Diagnostics

### Laravel Performance

```bash
# Check application status
php artisan about

# Monitor route performance
php artisan route:list --compact

# Database query analysis
php artisan tinker
>>> DB::enableQueryLog();
>>> // Perform application actions
>>> collect(DB::getQueryLog())->pluck('time')->sum();
```

### Frontend Performance

```bash
# Analyze bundle size
npm run build
ls -la public/build/

# Monitor Vite performance
npm run dev -- --debug

# Check for memory leaks
ps aux | grep node
```

## 🚀 Environment Specific Issues

### macOS Specific

```bash
# Fix permission issues with Homebrew
brew doctor

# MAMP configuration
# Default MAMP MySQL port: 8889
# Default MAMP PHP: /Applications/MAMP/bin/php/php8.2.0/bin/php

# Use MAMP PHP for Artisan commands
/Applications/MAMP/bin/php/php8.2.0/bin/php artisan --version
```

### Windows Specific

```bash
# Use Git Bash or PowerShell
# Check for Windows line endings
git config --global core.autocrlf true

# Permission issues
# Run CMD/PowerShell as Administrator for initial setup
```

### Linux Specific

```bash
# Install required packages
sudo apt-get update
sudo apt-get install php8.2 php8.2-mysql php8.2-mbstring

# Fix permission issues
sudo chown -R www-data:www-data storage bootstrap/cache
```

## 📚 Prevention Strategies

### Development Best Practices

1. **Always run both servers** during development
2. **Keep .env configured for local development**
3. **Monitor terminal outputs** for errors
4. **Clear caches regularly** during development
5. **Use version control** for configuration files

### Monitoring Setup

```bash
# Create monitoring script
cat > scripts/monitor.sh << 'EOF'
#!/bin/bash
echo "SwiftChats Development Monitor"
echo "=============================="

# Check servers
echo "🔍 Checking servers..."
curl -s http://localhost:5173 > /dev/null && echo "✅ Vite server: OK" || echo "❌ Vite server: DOWN"
curl -s http://127.0.0.1:8000 > /dev/null && echo "✅ Laravel server: OK" || echo "❌ Laravel server: DOWN"

# Check database
echo "🗄️ Checking database..."
php artisan tinker --execute="DB::select('SELECT 1'); echo 'Database: OK';" 2>/dev/null || echo "❌ Database: ERROR"

# Check disk space
echo "💾 Disk usage:"
df -h . | tail -1

echo "✨ Monitor complete"
EOF

chmod +x scripts/monitor.sh
./scripts/monitor.sh
```

---

**🎯 Remember**: Most issues are resolved by ensuring both Vite and Laravel servers are running with correct `.env` configuration!