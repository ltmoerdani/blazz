# ⚡ Dual Server Configuration Guide

## 📋 Overview

Blazz with Laravel 12 requires **two servers running simultaneously** for optimal development experience. This guide explains the dual server setup that successfully resolved styling and asset loading issues.

## 🎯 Why Dual Servers?

### Traditional Laravel Development
- Single server: `php artisan serve` 
- Assets served from `public/` directory
- Limited hot module replacement

### Modern Laravel 12 + Vite Development
- **Laravel Server**: Backend processing, routing, API endpoints
- **Vite Server**: Asset compilation, hot module replacement, CSS/JS serving
- **Enhanced Performance**: Faster asset loading and development workflow

## 🚀 Dual Server Setup

### Server Configuration

#### Server 1: Vite Development Server

**Purpose**: Frontend asset serving with hot module replacement

```bash
npm run dev
```

**Configuration Details**:
- **Port**: 5173 (default Vite port)
- **URL**: http://localhost:5173 (internal use only)
- **Process**: Compiles and serves CSS, JS, Vue components
- **Features**: Hot reload, instant updates, source maps

**Terminal Output**:
```
  VITE v4.5.14  ready in 324 ms

  ➜  Local:   http://localhost:5173/
  ➜  Network: use --host to expose
  ➜  press h to show help

  LARAVEL v12.29.0  plugin v0.8.1

  ➜  APP_URL: http://127.0.0.1:8000
```

#### Server 2: Laravel Development Server

**Purpose**: Backend processing and main application serving

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

**Configuration Details**:
- **Port**: 8000 (Laravel default)
- **URL**: http://127.0.0.1:8000 (main application access)
- **Process**: Routes, controllers, API endpoints, database operations
- **Features**: Laravel debugging, error reporting, middleware processing

**Terminal Output**:
```
Starting Laravel development server: http://127.0.0.1:8000
[Thu Jan  2 12:00:00 2024] PHP 8.2.12 Development Server (http://127.0.0.1:8000) started
```

## 🔄 Workflow Integration

### Development Process

```bash
# Terminal 1: Start Vite server
cd /path/to/blazz
npm run dev
# Keep this running throughout development

# Terminal 2: Start Laravel server  
cd /path/to/blazz
php artisan serve
# Keep this running throughout development

# Access application
# Open browser: http://127.0.0.1:8000
```

### Asset Loading Flow

1. **Browser Request**: User visits `http://127.0.0.1:8000`
2. **Laravel Processing**: Laravel server handles route and renders Blade template
3. **Asset References**: Blade template includes `@vite` directives
4. **Vite Integration**: Laravel communicates with Vite server for assets
5. **Asset Serving**: Vite server provides compiled CSS/JS to browser
6. **Complete Page**: Browser displays fully styled application

## 🛠️ Configuration Files

### vite.config.js

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import i18n from 'laravel-vue-i18n/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        i18n(),
    ],
    server: {
        host: '127.0.0.1',
        port: 5173,
        hmr: {
            host: '127.0.0.1',
        },
    },
});
```

### app.blade.php (Layout)

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Laravel') }}</title>
    
    <!-- Vite Assets Integration -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
```

## 🐛 Common Issues & Solutions

### Issue 1: Assets Not Loading (Black & White Application)

**Symptoms**:
- Application loads but appears unstyled
- Console errors: `ERR_CONNECTION_CLOSED` for assets
- CSS and JS files return 404 errors

**Root Cause**: Only Laravel server running, Vite server not started

**Solution**:
```bash
# Ensure BOTH servers are running
npm run dev        # Terminal 1: Must be running
php artisan serve  # Terminal 2: Must be running

# Verify both are active:
curl http://localhost:5173      # Vite server
curl http://127.0.0.1:8000      # Laravel server
```

### Issue 2: Environment Configuration Conflicts

**Symptoms**:
- Assets load in development but fail in production-like settings
- Inconsistent styling between environments

**Root Cause**: Incorrect `.env` configuration

**Solution**:
```bash
# Correct .env settings for development
APP_ENV=local           # NOT 'production'
APP_DEBUG=true          # NOT 'false'
APP_URL=http://127.0.0.1:8000  # Match Laravel server

# Restart servers after changes
```

### Issue 3: Port Conflicts

**Symptoms**:
- Server fails to start with "Address already in use" error
- One of the servers doesn't start properly

**Solution**:
```bash
# Check port usage
lsof -i :5173    # Vite port
lsof -i :8000    # Laravel port

# Kill conflicting processes
kill -9 <PID>

# Use alternative ports if needed
npm run dev -- --port 5174
php artisan serve --port 8001
```

### Issue 4: Hot Module Replacement Not Working

**Symptoms**:
- Changes in Vue components don't reflect immediately
- Need to manually refresh browser for changes

**Solution**:
```bash
# Verify Vite server is in development mode
npm run dev  # NOT npm run build

# Check vite.config.js has refresh: true
# Clear browser cache and restart servers
```

## 📊 Performance Optimization

### Development Performance

With dual server setup, you should observe:
- **Asset Loading**: < 100ms per asset
- **Hot Reload**: < 50ms for style changes
- **Component Updates**: Instant Vue component refresh
- **Overall Page Load**: < 500ms in development

### Production Considerations

For production deployment:
```bash
# Build assets for production
npm run build

# Single server deployment
# Assets are compiled to public/build/
# No Vite server needed in production
```

## 🔧 Monitoring & Debugging

### Server Status Verification

```bash
# Check both servers are running
ps aux | grep "npm\|php"

# Monitor server logs
# Terminal 1: Vite logs (auto-displayed)
# Terminal 2: Laravel logs
tail -f storage/logs/laravel.log

# Network verification
curl -I http://localhost:5173    # Should return 200
curl -I http://127.0.0.1:8000    # Should return 200
```

### Browser Developer Tools

**Expected Network Activity**:
- **HTML Request**: `127.0.0.1:8000` (Laravel server)
- **CSS Request**: `localhost:5173` (Vite server)
- **JS Request**: `localhost:5173` (Vite server)
- **API Requests**: `127.0.0.1:8000` (Laravel server)

### Development Commands

```bash
# Start both servers (recommended approach)
# Option 1: Sequential start
npm run dev &
php artisan serve

# Option 2: Use screen/tmux for session management
screen -S vite npm run dev
screen -S laravel php artisan serve

# Option 3: IDE integration
# Configure your IDE to run both commands simultaneously
```

## 🚀 Advanced Configuration

### Custom Development Script

Create `scripts/dev.sh`:
```bash
#!/bin/bash
echo "Starting Blazz Development Environment..."

# Start Vite server in background
npm run dev &
VITE_PID=$!

# Start Laravel server in background
php artisan serve &
LARAVEL_PID=$!

echo "✅ Vite server: http://localhost:5173 (PID: $VITE_PID)"
echo "✅ Laravel server: http://127.0.0.1:8000 (PID: $LARAVEL_PID)"
echo "🌐 Access application: http://127.0.0.1:8000"
echo ""
echo "Press Ctrl+C to stop both servers"

# Wait for interrupt
trap 'kill $VITE_PID $LARAVEL_PID' INT
wait
```

Usage:
```bash
chmod +x scripts/dev.sh
./scripts/dev.sh
```

### Docker Configuration (Optional)

For containerized development:
```dockerfile
# docker-compose.yml
version: '3.8'
services:
  laravel:
    build: .
    ports:
      - "8000:8000"
    command: php artisan serve --host=0.0.0.0

  vite:
    build: .
    ports:
      - "5173:5173"
    command: npm run dev -- --host 0.0.0.0
```

## 📚 Best Practices

### Development Workflow
1. **Always start both servers** before development
2. **Monitor both terminal outputs** for errors
3. **Use browser dev tools** to verify asset loading
4. **Clear caches** if switching between environments
5. **Test in multiple browsers** for compatibility

### Performance Tips
- Keep Vite server running throughout development session
- Use hot module replacement for faster development
- Monitor memory usage of both servers
- Regularly clear browser cache during development

### Security Considerations
- Development servers should only bind to localhost/127.0.0.1
- Never expose Vite development server to public networks
- Use proper HTTPS configuration for production

---

**🎯 Result**: With dual server setup, Blazz delivers optimal development experience with instant asset updates and full Laravel 12 functionality!