# Production Services Architecture - Blazz

> **Dokumentasi lengkap arsitektur service, port mapping, dan konfigurasi production**

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Service Port Mapping](#2-service-port-mapping)
3. [Service Configuration](#3-service-configuration)
4. [Supervisor Configuration](#4-supervisor-configuration)
5. [Nginx Configuration](#5-nginx-configuration)
6. [Startup & Management](#6-startup--management)
7. [Health Checks](#7-health-checks)
8. [Troubleshooting](#8-troubleshooting)

---

## 1. Architecture Overview

### 1.1 System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              INTERNET                                        │
│                                  │                                           │
│                                  ▼                                           │
│                    ┌─────────────────────────┐                              │
│                    │     Nginx (Port 443)     │                              │
│                    │   SSL Termination        │                              │
│                    │   blazz.id               │                              │
│                    └───────────┬─────────────┘                              │
│                                │                                             │
│         ┌──────────────────────┼──────────────────────┐                     │
│         │                      │                      │                      │
│         ▼                      ▼                      ▼                      │
│  ┌─────────────┐      ┌─────────────┐       ┌─────────────┐                 │
│  │ Laravel App │      │   Reverb    │       │  WhatsApp   │                 │
│  │ (PHP-FPM)   │      │  WebSocket  │       │   Service   │                 │
│  │   :8000*    │      │   :8080     │       │   :3001     │                 │
│  └──────┬──────┘      └─────────────┘       └──────┬──────┘                 │
│         │                                          │                         │
│         │         ┌─────────────────────┐         │                         │
│         │         │   Queue Workers     │         │                         │
│         └─────────►  (Supervisor)       ◄─────────┘                         │
│                   │   campaign-conflict │                                    │
│                   │   default           │                                    │
│                   └─────────┬───────────┘                                    │
│                             │                                                │
│         ┌───────────────────┼───────────────────┐                           │
│         ▼                   ▼                   ▼                            │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐                      │
│  │   MySQL     │    │    Redis    │    │  S3 Storage │                      │
│  │   :3306     │    │    :6379    │    │ (IDCloudHost)│                      │
│  └─────────────┘    └─────────────┘    └─────────────┘                      │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘

* Laravel menggunakan PHP-FPM, tidak listen pada port 8000 di production
```

### 1.2 Service Components

| Service | Description | Technology | Port |
|---------|-------------|------------|------|
| **Laravel App** | Main application backend | PHP 8.3 + Laravel 11 | PHP-FPM (sock) |
| **Reverb** | WebSocket broadcasting server | PHP + Ratchet | 8080 |
| **WhatsApp Service** | WhatsApp Web.js integration | Node.js + Express | 3001 |
| **Queue Workers** | Background job processing | PHP (Supervisor) | - |
| **Scheduler** | Cron job manager | PHP (Cron) | - |
| **MySQL** | Primary database | MySQL 8.x | 3306 |
| **Redis** | Cache & session auth | Redis 7.x | 6379 |

---

## 2. Service Port Mapping

### 2.1 Development vs Production

| Service | Development | Production | Notes |
|---------|-------------|------------|-------|
| Laravel App | `127.0.0.1:8000` | PHP-FPM socket | Via Nginx |
| Reverb WebSocket | `127.0.0.1:8080` | `0.0.0.0:8080` | Proxied by Nginx |
| WhatsApp Service | `127.0.0.1:3001` | `127.0.0.1:3001` | Internal only |
| MySQL | `127.0.0.1:3306` | `127.0.0.1:3306` | Local only |
| Redis | `127.0.0.1:6379` | `127.0.0.1:6379` | Local only |

### 2.2 External Endpoints (Production)

| Endpoint | URL | Service |
|----------|-----|---------|
| Web Application | `https://blazz.id` | Laravel + Vue.js |
| API | `https://blazz.id/api/v1/*` | Laravel API |
| WebSocket | `wss://blazz.id:8080` | Reverb |
| WhatsApp Webhook | `https://blazz.id/api/v1/whatsapp/webhook` | Laravel |

### 2.3 Internal Endpoints

| Endpoint | URL | Purpose |
|----------|-----|---------|
| WhatsApp Health | `http://127.0.0.1:3001/health` | Service health check |
| WhatsApp Sessions | `http://127.0.0.1:3001/health/sessions` | Session status |
| Laravel API (Internal) | `http://127.0.0.1/api/v1/*` | Internal API calls |

---

## 3. Service Configuration

### 3.1 Laravel Reverb (WebSocket)

**File: `.env`**
```env
BROADCAST_DRIVER=reverb

REVERB_APP_ID=blazz-prod-reverb
REVERB_APP_KEY=blazz-prod-key-2025
REVERB_APP_SECRET=blazz-prod-secret-2025
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=https

# Frontend config (public)
VITE_REVERB_APP_KEY=blazz-prod-key-2025
VITE_REVERB_HOST=blazz.id
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=https
```

### 3.2 WhatsApp Node.js Service

**File: `whatsapp-service/.env`**
```env
# Server Configuration
NODE_ENV=production
PORT=3001
LOG_LEVEL=warn

# Laravel Integration
LARAVEL_URL=https://blazz.id
LARAVEL_API_TOKEN=your-secure-api-token

# API Security
API_KEY=your-secure-api-key
HMAC_SECRET=your-secure-hmac-secret

# Redis (untuk RemoteAuth)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

# WhatsApp Sync Configuration
WHATSAPP_SYNC_BATCH_SIZE=50
WHATSAPP_SYNC_MAX_CONCURRENT=3
WHATSAPP_SYNC_WINDOW_DAYS=30
WHATSAPP_SYNC_MAX_CHATS=500

# Puppeteer Configuration
PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser
PUPPETEER_HEADLESS=true
PUPPETEER_NO_SANDBOX=true
```

### 3.3 Queue Configuration

**File: `.env`**
```env
QUEUE_CONNECTION=database

# Campaign Queue
CAMPAIGN_CONFLICT_ENABLED=true
CAMPAIGN_CONFLICT_QUEUE=campaign-conflict
CAMPAIGN_CONFLICT_DEFAULT_COOLDOWN=30
CAMPAIGN_CONFLICT_MAX_ATTEMPTS=5
```

---

## 4. Supervisor Configuration

### 4.1 Laravel Queue Workers

**File: `/etc/supervisor/conf.d/blazz-queue.conf`**
```ini
[program:blazz-queue-default]
process_name=%(program_name)s_%(process_num)02d
command=php /www/wwwroot/blazz/artisan queue:work database --queue=default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www
numprocs=2
redirect_stderr=true
stdout_logfile=/www/wwwroot/blazz/storage/logs/queue-default.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600

[program:blazz-queue-campaign]
process_name=%(program_name)s_%(process_num)02d
command=php /www/wwwroot/blazz/artisan queue:work database --queue=campaign-conflict --sleep=3 --tries=5 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www
numprocs=2
redirect_stderr=true
stdout_logfile=/www/wwwroot/blazz/storage/logs/queue-campaign.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600
```

### 4.2 Laravel Reverb WebSocket

**File: `/etc/supervisor/conf.d/blazz-reverb.conf`**
```ini
[program:blazz-reverb]
process_name=%(program_name)s
command=php /www/wwwroot/blazz/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www
redirect_stderr=true
stdout_logfile=/www/wwwroot/blazz/storage/logs/reverb.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=10
```

### 4.3 WhatsApp Node.js Service (PM2)

**File: `/etc/supervisor/conf.d/blazz-whatsapp.conf`**
```ini
[program:blazz-whatsapp]
process_name=%(program_name)s
command=/www/server/nodejs/v24.11.1/bin/node /www/wwwroot/blazz/whatsapp-service/server.js
directory=/www/wwwroot/blazz/whatsapp-service
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www
environment=NODE_ENV="production",PORT="3001"
redirect_stderr=true
stdout_logfile=/www/wwwroot/blazz/storage/logs/whatsapp-service.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=10
```

### 4.4 Laravel Scheduler

**File: `/etc/crontab` atau `crontab -e` untuk user www**
```cron
* * * * * cd /www/wwwroot/blazz && php artisan schedule:run >> /dev/null 2>&1
```

### 4.5 Supervisor Group

**File: `/etc/supervisor/conf.d/blazz-group.conf`**
```ini
[group:blazz]
programs=blazz-queue-default,blazz-queue-campaign,blazz-reverb,blazz-whatsapp
priority=999
```

---

## 5. Nginx Configuration

### 5.1 Main Site Configuration

**File: `/www/server/panel/vhost/nginx/blazz.id.conf`**
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name blazz.id www.blazz.id;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name blazz.id www.blazz.id;

    # SSL Configuration
    ssl_certificate /www/server/panel/vhost/cert/blazz.id/fullchain.pem;
    ssl_certificate_key /www/server/panel/vhost/cert/blazz.id/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:50m;

    # Root directory
    root /www/wwwroot/blazz/public;
    index index.php index.html;

    # Logging
    access_log /www/wwwlogs/blazz.id.log;
    error_log /www/wwwlogs/blazz.id.error.log;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/xml;

    # Laravel application
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/tmp/php-cgi-83.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_read_timeout 300;
    }

    # Static files caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }

    # Deny access to sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 5.2 WebSocket Proxy Configuration

**Add to main nginx config or separate file:**
```nginx
# WebSocket proxy for Reverb (Port 8080)
server {
    listen 8080 ssl http2;
    listen [::]:8080 ssl http2;
    server_name blazz.id;

    ssl_certificate /www/server/panel/vhost/cert/blazz.id/fullchain.pem;
    ssl_certificate_key /www/server/panel/vhost/cert/blazz.id/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
        proxy_send_timeout 86400;
    }
}
```

---

## 6. Startup & Management

### 6.1 Start All Services

```bash
# Reload supervisor configuration
sudo supervisorctl reread
sudo supervisorctl update

# Start all Blazz services
sudo supervisorctl start blazz:*

# Or start individually
sudo supervisorctl start blazz-queue-default:*
sudo supervisorctl start blazz-queue-campaign:*
sudo supervisorctl start blazz-reverb
sudo supervisorctl start blazz-whatsapp
```

### 6.2 Stop All Services

```bash
# Stop all Blazz services
sudo supervisorctl stop blazz:*
```

### 6.3 Restart Services

```bash
# Restart all
sudo supervisorctl restart blazz:*

# Restart specific service
sudo supervisorctl restart blazz-reverb
sudo supervisorctl restart blazz-whatsapp
```

### 6.4 Check Status

```bash
# Check all services
sudo supervisorctl status

# Check specific group
sudo supervisorctl status blazz:*
```

### 6.5 View Logs

```bash
# Queue logs
tail -f /www/wwwroot/blazz/storage/logs/queue-default.log
tail -f /www/wwwroot/blazz/storage/logs/queue-campaign.log

# Reverb logs
tail -f /www/wwwroot/blazz/storage/logs/reverb.log

# WhatsApp service logs
tail -f /www/wwwroot/blazz/storage/logs/whatsapp-service.log

# Laravel logs
tail -f /www/wwwroot/blazz/storage/logs/laravel.log
```

---

## 7. Health Checks

### 7.1 Quick Health Check Script

**File: `/www/wwwroot/blazz/scripts/health-check.sh`**
```bash
#!/bin/bash

echo "🏥 Blazz Health Check"
echo "====================="

# Check Laravel
echo -n "Laravel App: "
curl -s -o /dev/null -w "%{http_code}" https://blazz.id/login
echo ""

# Check Reverb
echo -n "Reverb WebSocket: "
if nc -z 127.0.0.1 8080 2>/dev/null; then
    echo "✅ Running"
else
    echo "❌ Not running"
fi

# Check WhatsApp Service
echo -n "WhatsApp Service: "
HEALTH=$(curl -s http://127.0.0.1:3001/health 2>/dev/null)
if [ $? -eq 0 ]; then
    echo "✅ Running"
    echo "   Sessions: $(echo $HEALTH | grep -o '"activeSessions":[0-9]*' | cut -d':' -f2)"
else
    echo "❌ Not running"
fi

# Check MySQL
echo -n "MySQL: "
if mysqladmin ping -h 127.0.0.1 -u root --silent 2>/dev/null; then
    echo "✅ Running"
else
    echo "❌ Not running"
fi

# Check Redis
echo -n "Redis: "
if redis-cli ping 2>/dev/null | grep -q PONG; then
    echo "✅ Running"
else
    echo "❌ Not running"
fi

# Check Queue Workers
echo -n "Queue Workers: "
QUEUE_COUNT=$(supervisorctl status | grep blazz-queue | grep RUNNING | wc -l)
echo "$QUEUE_COUNT workers running"

echo "====================="
```

### 7.2 Laravel Artisan Health Commands

```bash
# Check WhatsApp service health
php artisan whatsapp:health-check

# Check queue status
php artisan queue:monitor

# Check scheduled tasks
php artisan schedule:list
```

---

## 8. Troubleshooting

### 8.1 Common Issues

#### Reverb Not Starting
```bash
# Check if port is in use
sudo lsof -i :8080

# Kill process on port
sudo fuser -k 8080/tcp

# Restart Reverb
sudo supervisorctl restart blazz-reverb
```

#### WhatsApp Service Issues
```bash
# Check Node.js version
/www/server/nodejs/v24.11.1/bin/node --version

# Check if Chromium is installed
which chromium-browser

# Install Chromium if missing (Debian/Ubuntu)
sudo apt-get install chromium-browser

# Check WhatsApp logs
tail -100 /www/wwwroot/blazz/storage/logs/whatsapp-service.log
```

#### Queue Not Processing
```bash
# Check queue table
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear queue
php artisan queue:flush

# Restart workers
sudo supervisorctl restart blazz-queue-default:*
sudo supervisorctl restart blazz-queue-campaign:*
```

#### Permission Issues
```bash
# Fix ownership
sudo chown -R www:www /www/wwwroot/blazz
sudo chown -R www:www /www/wwwroot/blazz/storage
sudo chown -R www:www /www/wwwroot/blazz/bootstrap/cache

# Fix permissions
chmod -R 755 /www/wwwroot/blazz/storage
chmod -R 755 /www/wwwroot/blazz/bootstrap/cache
```

### 8.2 Log Locations

| Service | Log File |
|---------|----------|
| Laravel | `/www/wwwroot/blazz/storage/logs/laravel.log` |
| Nginx Access | `/www/wwwlogs/blazz.id.log` |
| Nginx Error | `/www/wwwlogs/blazz.id.error.log` |
| Queue Default | `/www/wwwroot/blazz/storage/logs/queue-default.log` |
| Queue Campaign | `/www/wwwroot/blazz/storage/logs/queue-campaign.log` |
| Reverb | `/www/wwwroot/blazz/storage/logs/reverb.log` |
| WhatsApp | `/www/wwwroot/blazz/storage/logs/whatsapp-service.log` |
| Supervisor | `/var/log/supervisor/supervisord.log` |

---

## Quick Reference Card

### Service Control
```bash
# Start all
sudo supervisorctl start blazz:*

# Stop all
sudo supervisorctl stop blazz:*

# Restart all
sudo supervisorctl restart blazz:*

# Status
sudo supervisorctl status
```

### Deploy Commands
```bash
cd /www/wwwroot/blazz

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader

# Clear & rebuild cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force

# Restart services
sudo supervisorctl restart blazz:*
```

### Useful URLs
- **Application**: https://blazz.id
- **WebSocket**: wss://blazz.id:8080
- **WhatsApp Health**: http://127.0.0.1:3001/health

---

## Changelog

| Date | Changes |
|------|---------|
| 2025-12-04 | Initial production services architecture documentation |
