# Environment Configuration - Blazz

> **Template dan panduan konfigurasi .env untuk berbagai environment**

---

## Table of Contents

1. [Production Environment](#1-production-environment)
2. [Development Environment](#2-development-environment)
3. [Environment Variables Reference](#3-environment-variables-reference)
4. [Secrets Management](#4-secrets-management)

---

## 1. Production Environment

### 1.1 Full .env Template for Production

```env
#----------------------------------------------
# APPLICATION SETTINGS
#----------------------------------------------
APP_NAME=Blazz
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://blazz.id
APP_TIMEZONE=Asia/Jakarta

#----------------------------------------------
# LOGGING
#----------------------------------------------
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

#----------------------------------------------
# DATABASE
#----------------------------------------------
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=blazz
DB_USERNAME=blazz_user
DB_PASSWORD=YOUR_SECURE_PASSWORD
DB_PREFIX=

#----------------------------------------------
# CACHE & SESSION
#----------------------------------------------
BROADCAST_DRIVER=reverb
CACHE_DRIVER=file
FILESYSTEM_DISK=s3
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120

#----------------------------------------------
# REDIS (Optional - untuk high performance)
#----------------------------------------------
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

#----------------------------------------------
# MAIL CONFIGURATION
#----------------------------------------------
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@blazz.id"
MAIL_FROM_NAME="${APP_NAME}"

#----------------------------------------------
# S3 STORAGE (IDCloudHost)
#----------------------------------------------
AWS_ACCESS_KEY_ID=YOUR_ACCESS_KEY
AWS_SECRET_ACCESS_KEY=YOUR_SECRET_KEY
AWS_DEFAULT_REGION=id-jkt-1
AWS_BUCKET=s3-blazz
AWS_ENDPOINT=https://is3.cloudhost.id
AWS_USE_PATH_STYLE_ENDPOINT=true
MEDIA_STORAGE_DISK=s3

#----------------------------------------------
# PUSHER/REVERB CONFIGURATION
#----------------------------------------------
PUSHER_APP_ID=blazz-prod-app
PUSHER_APP_KEY=blazz-prod-key-2025
PUSHER_APP_SECRET=blazz-prod-secret-2025
PUSHER_HOST=blazz.id
PUSHER_PORT=8080
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

#----------------------------------------------
# VITE FRONTEND CONFIGURATION
#----------------------------------------------
VITE_APP_NAME="${APP_NAME}"
VITE_PUSHER_APP_KEY=blazz-prod-key-2025
VITE_PUSHER_HOST=blazz.id
VITE_PUSHER_PORT=8080
VITE_PUSHER_SCHEME=https
VITE_PUSHER_APP_CLUSTER=mt1

#----------------------------------------------
# REVERB WEBSOCKET SERVER
#----------------------------------------------
REVERB_APP_ID=blazz-prod-reverb
REVERB_APP_KEY=blazz-prod-key-2025
REVERB_APP_SECRET=blazz-prod-secret-2025
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=https

VITE_REVERB_APP_KEY=blazz-prod-key-2025
VITE_REVERB_HOST=blazz.id
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=https

#----------------------------------------------
# RECAPTCHA (Optional)
#----------------------------------------------
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=

#----------------------------------------------
# WHATSAPP NODE.JS SERVICE
#----------------------------------------------
WHATSAPP_NODE_URL=http://127.0.0.1:3001
WHATSAPP_NODE_SERVICE_URL=http://127.0.0.1:3001
WHATSAPP_NODE_API_TOKEN=your-secure-api-token
WHATSAPP_NODE_API_KEY=your-secure-api-key
WHATSAPP_NODE_API_SECRET=your-secure-hmac-secret
WHATSAPP_HMAC_ENABLED=true
WHATSAPP_HMAC_SECRET=your-secure-hmac-secret
HMAC_SECRET=your-secure-hmac-secret
LARAVEL_API_TOKEN=your-secure-api-token
WHATSAPP_API_KEY=your-secure-api-key
WHATSAPP_NODEJS_URL=http://127.0.0.1:3001

#----------------------------------------------
# WHATSAPP MULTI-INSTANCE
#----------------------------------------------
WHATSAPP_INSTANCE_COUNT=4
WHATSAPP_INSTANCE_1=http://localhost:3001
WHATSAPP_INSTANCE_2=http://localhost:3002
WHATSAPP_INSTANCE_3=http://localhost:3003
WHATSAPP_INSTANCE_4=http://localhost:3004
WHATSAPP_INTERNAL_TOKEN=secret-internal-token
WHATSAPP_MAX_SESSIONS_PER_INSTANCE=500

#----------------------------------------------
# CAMPAIGN SETTINGS
#----------------------------------------------
CAMPAIGN_CONFLICT_ENABLED=true
CAMPAIGN_CONFLICT_QUEUE=campaign-conflict
CAMPAIGN_CONFLICT_DEFAULT_COOLDOWN=30
CAMPAIGN_CONFLICT_MAX_ATTEMPTS=5

#----------------------------------------------
# FFMPEG (Media Processing)
#----------------------------------------------
FFMPEG_PATH=/usr/bin/ffmpeg
FFPROBE_PATH=/usr/bin/ffprobe
FFMPEG_TIMEOUT=600
```

### 1.2 Generate Secure Keys

```bash
# Generate APP_KEY
php artisan key:generate --show

# Generate random token (64 chars)
openssl rand -hex 32

# Generate HMAC secret (128 chars)
openssl rand -hex 64
```

---

## 2. Development Environment

### 2.1 Local Development .env

```env
APP_NAME=Blazz
APP_ENV=local
APP_KEY=base64:YOUR_KEY_HERE
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=blazz
DB_USERNAME=root
DB_PASSWORD=
DB_PREFIX=

BROADCAST_DRIVER=reverb
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

# Local S3 (optional - atau gunakan local storage)
AWS_ACCESS_KEY_ID=YOUR_KEY
AWS_SECRET_ACCESS_KEY=YOUR_SECRET
AWS_DEFAULT_REGION=id-jkt-1
AWS_BUCKET=s3-blazz
AWS_ENDPOINT=https://is3.cloudhost.id
AWS_USE_PATH_STYLE_ENDPOINT=true
MEDIA_STORAGE_DISK=s3

PUSHER_APP_ID=blazz-local-app
PUSHER_APP_KEY=blazz-echo-key-2025
PUSHER_APP_SECRET=blazz-echo-secret-2025
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_APP_CLUSTER=mt1

VITE_APP_NAME="${APP_NAME}"
VITE_PUSHER_APP_KEY=blazz-echo-key-2025
VITE_PUSHER_HOST=127.0.0.1
VITE_PUSHER_PORT=6001
VITE_PUSHER_SCHEME=http
VITE_PUSHER_APP_CLUSTER=mt1

REVERB_APP_ID=526180
REVERB_APP_KEY=ohrtagckj2hqoiocg7wz
REVERB_APP_SECRET=ag0aapako3p6n90f6etl
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY=ohrtagckj2hqoiocg7wz
VITE_REVERB_HOST=127.0.0.1
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http

WHATSAPP_NODE_URL=http://127.0.0.1:3001
WHATSAPP_NODE_SERVICE_URL=http://127.0.0.1:3001
WHATSAPP_NODE_API_TOKEN=dev-token
WHATSAPP_NODE_API_KEY=dev-api-key
WHATSAPP_HMAC_ENABLED=true

WHATSAPP_INSTANCE_COUNT=1
WHATSAPP_INSTANCE_1=http://localhost:3001

FFMPEG_PATH=/opt/homebrew/bin/ffmpeg
FFPROBE_PATH=/opt/homebrew/bin/ffprobe
FFMPEG_TIMEOUT=600
```

---

## 3. Environment Variables Reference

### 3.1 Core Application

| Variable | Description | Production | Development |
|----------|-------------|------------|-------------|
| APP_ENV | Environment mode | `production` | `local` |
| APP_DEBUG | Debug mode | `false` | `true` |
| APP_URL | Base URL | `https://domain.com` | `http://127.0.0.1:8000` |
| LOG_LEVEL | Logging level | `error` | `debug` |

### 3.2 Database

| Variable | Description |
|----------|-------------|
| DB_CONNECTION | Database driver (mysql, pgsql, sqlite) |
| DB_HOST | Database server address |
| DB_PORT | Database port (default: 3306) |
| DB_DATABASE | Database name |
| DB_USERNAME | Database username |
| DB_PASSWORD | Database password |

### 3.3 Cache & Queue

| Variable | Options | Recommended |
|----------|---------|-------------|
| CACHE_DRIVER | file, redis, memcached | file (or redis) |
| QUEUE_CONNECTION | sync, database, redis | database |
| SESSION_DRIVER | file, database, redis | file |

### 3.4 Storage

| Variable | Description |
|----------|-------------|
| FILESYSTEM_DISK | Default storage disk (local, s3) |
| MEDIA_STORAGE_DISK | Media files storage |
| AWS_* | S3 compatible storage configuration |

### 3.5 WebSocket (Reverb)

| Variable | Description |
|----------|-------------|
| REVERB_HOST | Server bind address (0.0.0.0 for production) |
| REVERB_PORT | WebSocket port (default: 8080) |
| REVERB_SCHEME | Protocol (http/https) |
| VITE_REVERB_HOST | Client-facing host (domain name) |

### 3.6 WhatsApp Service

| Variable | Description |
|----------|-------------|
| WHATSAPP_NODE_URL | WhatsApp Node.js service URL |
| WHATSAPP_NODE_API_TOKEN | API authentication token |
| WHATSAPP_HMAC_ENABLED | Enable HMAC validation |
| WHATSAPP_INSTANCE_COUNT | Number of WhatsApp instances |

---

## 4. Secrets Management

### 4.1 Sensitive Variables

**NEVER commit these to git:**
- APP_KEY
- DB_PASSWORD
- MAIL_PASSWORD
- AWS_SECRET_ACCESS_KEY
- *_SECRET (all secret keys)
- *_PASSWORD (all passwords)
- *_TOKEN (all tokens)

### 4.2 .gitignore

Pastikan `.env` ada di `.gitignore`:
```
.env
.env.backup
.env.production
```

### 4.3 Generating Secure Secrets

```bash
# APP_KEY (Laravel)
php artisan key:generate

# Random 32 char hex
openssl rand -hex 16

# Random 64 char hex
openssl rand -hex 32

# Random base64 (32 bytes)
openssl rand -base64 32

# UUID
uuidgen
```

### 4.4 Environment per Server

Untuk multi-server deployment, gunakan environment-specific files:
- `.env.production` - Production template
- `.env.staging` - Staging template
- `.env` - Actual file (not committed)

---

## Changelog

| Date | Changes |
|------|---------|
| 2025-12-04 | Initial environment configuration guide |
