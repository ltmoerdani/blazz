# Production Server Setup Guide - Blazz

> **Dokumentasi deployment Blazz ke production server**
> 
> Server: 210.79.191.51 (blazz-production)
> Panel: aaPanel (BT Panel)
> Date: December 4, 2025

---

## Table of Contents

1. [Server Information](#1-server-information)
2. [SSH Setup](#2-ssh-setup)
3. [Git Repository Setup](#3-git-repository-setup)
4. [PHP Configuration](#4-php-configuration)
5. [Composer Installation](#5-composer-installation)
6. [NPM & Vite Build](#6-npm--vite-build)
7. [Laravel Configuration](#7-laravel-configuration)
8. [Troubleshooting](#8-troubleshooting)
9. [Maintenance Commands](#9-maintenance-commands)

---

## 1. Server Information

| Item | Value |
|------|-------|
| **IP Address** | 210.79.191.51 |
| **Hostname** | blazz-production |
| **Username** | blazz |
| **Panel** | aaPanel (BT Panel) |
| **PHP Version** | 8.3.27 |
| **Node.js Version** | v24.11.1 |
| **Web Server** | Nginx |
| **Document Root** | `/www/wwwroot/blazz` |
| **Public Directory** | `/www/wwwroot/blazz/public` |

---

## 2. SSH Setup

### 2.1 Generate SSH Key (Local Machine)

```bash
# Generate SSH key untuk akses server
ssh-keygen -t ed25519 -f ~/.ssh/blazz_server -N "" -C "blazz-server-access"

# Copy public key ke server
ssh-copy-id -i ~/.ssh/blazz_server.pub blazz@210.79.191.51
```

### 2.2 Setup SSH Config

Tambahkan ke `~/.ssh/config`:

```bash
# Blazz Production Server
Host blazz-server
    HostName 210.79.191.51
    User blazz
    IdentityFile ~/.ssh/blazz_server
    IdentitiesOnly yes
```

### 2.3 Test Koneksi

```bash
ssh blazz-server
# Atau
ssh blazz@210.79.191.51
```

---

## 3. Git Repository Setup

### 3.1 Generate SSH Key di Server untuk GitHub

```bash
# Login ke server
ssh blazz-server

# Generate SSH key untuk GitHub
ssh-keygen -t ed25519 -f ~/.ssh/github_blazz -N '' -C 'blazz-server-github'

# Tampilkan public key
cat ~/.ssh/github_blazz.pub
```

### 3.2 Tambahkan SSH Key ke GitHub

1. Buka https://github.com/settings/keys
2. Klik "New SSH key"
3. Title: `blazz-production-server`
4. Key type: Authentication Key
5. Paste public key
6. Klik "Add SSH key"

### 3.3 Setup SSH Config di Server

```bash
cat >> ~/.ssh/config << 'EOF'
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/github_blazz
    IdentitiesOnly yes
EOF

chmod 600 ~/.ssh/config
```

### 3.4 Clone Repository

```bash
cd /www/wwwroot

# Backup folder lama jika ada
sudo mv blazz blazz_backup

# Buat folder baru
sudo mkdir blazz
sudo chown blazz:www blazz

# Clone repository
cd blazz
git clone --branch main git@github.com:ltmoerdani/blazz.git .

# Setup git config
git config user.name "Blazz Server"
git config user.email "deploy@blazz.id"
git config pull.rebase false
```

### 3.5 Fix Git Safe Directory (jika ada error)

```bash
git config --global --add safe.directory /www/wwwroot/blazz
```

---

## 4. PHP Configuration

### 4.1 Required PHP Extensions

Install melalui **aaPanel → App Store → PHP 8.3 → Setting → Install Extensions**:

| Extension | Status | Keterangan |
|-----------|--------|------------|
| **mbstring** | ✅ WAJIB | String manipulation |
| **fileinfo** | ✅ WAJIB | File type detection |
| **intl** | ✅ WAJIB | Internationalization |
| **exif** | ⚠️ Recommended | Image metadata |
| **imagemagick** | ⚠️ Recommended | Image processing |
| **bcmath** | ⚠️ Recommended | Arbitrary precision math |
| **gd** | ⚠️ Recommended | Image processing |
| **zip** | ⚠️ Recommended | ZIP file handling |

### 4.2 PHP Disabled Functions

Edit `/www/server/php/83/etc/php.ini` untuk enable functions yang diperlukan Laravel:

**Functions yang perlu di-ENABLE (hapus dari disable_functions):**
- `exec`
- `system`
- `putenv`
- `shell_exec`
- `popen`
- `proc_open`
- `symlink`
- `readlink`
- `pcntl_*` (semua pcntl functions)

**Contoh disable_functions yang aman:**
```ini
disable_functions = passthru,chroot,chgrp,chown,ini_alter,ini_restore,dl,openlog,syslog,imap_open,apache_setenv
```

### 4.3 Restart PHP-FPM

```bash
sudo /www/server/php/83/sbin/php-fpm restart
```

### 4.4 Verifikasi Extensions

```bash
php -m | grep -E "mbstring|fileinfo|intl|exif|imagick"
```

---

## 5. Composer Installation

### 5.1 Update Composer

```bash
sudo composer self-update
```

### 5.2 Install Dependencies

```bash
cd /www/wwwroot/blazz

# Fix permission dulu
sudo chown -R blazz:www /www/wwwroot/blazz

# Install dependencies (production mode)
composer install --no-dev --optimize-autoloader

# Atau jika ada permission issue
sudo -u www composer install --no-dev --optimize-autoloader
```

### 5.3 Troubleshooting Composer

**Error: composer-runtime-api version mismatch**
```bash
sudo composer self-update
```

**Error: ext-fileinfo missing**
```bash
# Install fileinfo extension via aaPanel
# Atau gunakan flag:
composer install --ignore-platform-req=ext-fileinfo
```

---

## 6. NPM & Vite Build

### 6.1 Node.js Path di aaPanel

Node.js terinstall di: `/www/server/nodejs/v24.11.1/bin/`

### 6.2 Create Symlinks (Optional)

```bash
sudo ln -sf /www/server/nodejs/v24.11.1/bin/node /usr/local/bin/node
sudo ln -sf /www/server/nodejs/v24.11.1/bin/npm /usr/local/bin/npm
sudo ln -sf /www/server/nodejs/v24.11.1/bin/npx /usr/local/bin/npx
```

### 6.3 Install NPM Dependencies

```bash
cd /www/wwwroot/blazz

# Fix permission
sudo chown -R www:www /www/wwwroot/blazz
sudo chown -R www:www /www/server/nodejs/cache

# Install dependencies
sudo -u www /www/server/nodejs/v24.11.1/bin/npm install
```

### 6.4 Build Assets

```bash
sudo -u www /www/server/nodejs/v24.11.1/bin/npm run build
```

### 6.5 Case-Sensitivity Issue (Linux vs macOS)

Linux adalah case-sensitive, macOS tidak. Jika ada error seperti:
```
Could not load /www/wwwroot/blazz/resources/js/composables/useRtl
```

Buat symlink untuk fix:
```bash
cd /www/wwwroot/blazz/resources/js
ln -sf Composables composables
```

**Daftar folder yang perlu symlink (jika ada masalah):**
```bash
# Di resources/js/
ln -sf Composables composables
```

---

## 7. Laravel Configuration

### 7.1 Environment File

```bash
cd /www/wwwroot/blazz

# Copy dari example
cp .env.example .env

# Edit sesuai kebutuhan
nano .env
```

### 7.2 Template .env Production

```env
APP_NAME=Blazz
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://blazz.id

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=blazz
DB_USERNAME=blazz_user
DB_PASSWORD=YOUR_PASSWORD
DB_PREFIX=

# Cache & Queue
BROADCAST_DRIVER=reverb
CACHE_DRIVER=file
FILESYSTEM_DISK=s3
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Redis (optional)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@blazz.id"
MAIL_FROM_NAME="${APP_NAME}"

# S3 Storage (IDCloudHost)
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=id-jkt-1
AWS_BUCKET=s3-blazz
AWS_ENDPOINT=https://is3.cloudhost.id
AWS_USE_PATH_STYLE_ENDPOINT=true
MEDIA_STORAGE_DISK=s3

# Reverb WebSocket
REVERB_APP_ID=blazz-prod-reverb
REVERB_APP_KEY=your-reverb-key
REVERB_APP_SECRET=your-reverb-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=https

VITE_REVERB_APP_KEY=your-reverb-key
VITE_REVERB_HOST=blazz.id
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=https

# WhatsApp Service
WHATSAPP_NODE_URL=http://127.0.0.1:3001
WHATSAPP_NODE_SERVICE_URL=http://127.0.0.1:3001
WHATSAPP_NODE_API_TOKEN=your-api-token
WHATSAPP_NODE_API_KEY=your-api-key
WHATSAPP_HMAC_ENABLED=true

# FFmpeg (untuk media processing)
FFMPEG_PATH=/usr/bin/ffmpeg
FFPROBE_PATH=/usr/bin/ffprobe
FFMPEG_TIMEOUT=600
```

### 7.3 Generate APP_KEY

```bash
cd /www/wwwroot/blazz
sudo -u www php artisan key:generate --force
```

### 7.4 Create 'installed' File

Laravel Blazz mengecek file `storage/installed` untuk menentukan apakah aplikasi sudah terinstall:

```bash
touch /www/wwwroot/blazz/storage/installed
```

### 7.5 Run Migrations & Seeders

```bash
cd /www/wwwroot/blazz

# Fresh migration dengan seeder
php artisan migrate:fresh --seed --force

# Atau seeder saja
php artisan db:seed --force
```

### 7.6 Set Permissions

```bash
sudo chown -R www:www /www/wwwroot/blazz
sudo chmod -R 755 /www/wwwroot/blazz
sudo chmod -R 775 /www/wwwroot/blazz/storage
sudo chmod -R 775 /www/wwwroot/blazz/bootstrap/cache
sudo chmod 664 /www/wwwroot/blazz/.env
```

### 7.7 Cache Configuration

```bash
cd /www/wwwroot/blazz

sudo -u www php artisan config:cache
sudo -u www php artisan route:cache
sudo -u www php artisan view:cache
```

### 7.8 Clear Cache

```bash
sudo -u www php artisan config:clear
sudo -u www php artisan cache:clear
sudo -u www php artisan route:clear
sudo -u www php artisan view:clear
```

---

## 8. Troubleshooting

### 8.1 Error: Class "App\Models\workspace" not found

**Penyebab:** Case-sensitivity di Linux. File adalah `Workspace.php` tapi import menggunakan `workspace`.

**Solusi:** Fix di file yang bermasalah:

```bash
# Fix WorkspaceHelper.php
sudo sed -i 's/use App\\Models\\workspace;/use App\\Models\\Workspace;/g' /www/wwwroot/blazz/app/Helpers/WorkspaceHelper.php
sudo sed -i 's/: workspace/: Workspace/g' /www/wwwroot/blazz/app/Helpers/WorkspaceHelper.php
sudo sed -i 's/return workspace::/return Workspace::/g' /www/wwwroot/blazz/app/Helpers/WorkspaceHelper.php
```

### 8.2 Error: No application encryption key

```bash
sudo -u www php artisan key:generate --force
sudo -u www php artisan config:cache
```

### 8.3 Error: Permission denied

```bash
sudo chown -R www:www /www/wwwroot/blazz
sudo chmod -R 775 /www/wwwroot/blazz/storage
sudo chmod -R 775 /www/wwwroot/blazz/bootstrap/cache
```

### 8.4 Error: mb_split() undefined

**Penyebab:** mbstring extension tidak terinstall atau di-load dua kali.

**Solusi:**
1. Install mbstring via aaPanel
2. Cek duplicate di php.ini:
```bash
grep -n 'extension=mbstring' /www/server/php/83/etc/php.ini
# Jika ada 2 baris, comment salah satu
```

### 8.5 Error: Redirect to /install

**Penyebab:** File `storage/installed` tidak ada.

**Solusi:**
```bash
touch /www/wwwroot/blazz/storage/installed
sudo chown www:www /www/wwwroot/blazz/storage/installed
```

### 8.6 Error: npm EACCES permission denied

```bash
# Fix npm cache
sudo chown -R www:www /www/server/nodejs/cache

# Fix project folder
sudo chown -R www:www /www/wwwroot/blazz

# Hapus node_modules dan install ulang
sudo rm -rf /www/wwwroot/blazz/node_modules
sudo rm -f /www/wwwroot/blazz/package-lock.json
sudo -u www /www/server/nodejs/v24.11.1/bin/npm install
```

### 8.7 Error: Vite build - composables/useRtl not found

**Penyebab:** Case-sensitivity. Folder `Composables` vs import `composables`.

**Solusi:**
```bash
cd /www/wwwroot/blazz/resources/js
ln -sf Composables composables
```

### 8.8 Error: Git dubious ownership

```bash
git config --global --add safe.directory /www/wwwroot/blazz
```

---

## 9. Maintenance Commands

### 9.1 Update dari GitHub

```bash
cd /www/wwwroot/blazz

# Pull latest changes
git pull origin main

# Install new dependencies jika ada
composer install --no-dev --optimize-autoloader
sudo -u www /www/server/nodejs/v24.11.1/bin/npm install
sudo -u www /www/server/nodejs/v24.11.1/bin/npm run build

# Run migrations
php artisan migrate --force

# Clear & rebuild cache
sudo -u www php artisan config:cache
sudo -u www php artisan route:cache
sudo -u www php artisan view:cache

# Fix permissions
sudo chown -R www:www /www/wwwroot/blazz
```

### 9.2 Quick Deploy Script

Buat file `/www/wwwroot/blazz/deploy.sh`:

```bash
#!/bin/bash

echo "🚀 Starting deployment..."

cd /www/wwwroot/blazz

# Pull latest
echo "📥 Pulling latest changes..."
git pull origin main

# Composer
echo "📦 Installing composer dependencies..."
composer install --no-dev --optimize-autoloader

# NPM
echo "📦 Installing npm dependencies..."
sudo -u www /www/server/nodejs/v24.11.1/bin/npm install
sudo -u www /www/server/nodejs/v24.11.1/bin/npm run build

# Migrations
echo "🗄️ Running migrations..."
php artisan migrate --force

# Cache
echo "🔄 Clearing and rebuilding cache..."
sudo -u www php artisan config:cache
sudo -u www php artisan route:cache
sudo -u www php artisan view:cache

# Permissions
echo "🔐 Fixing permissions..."
sudo chown -R www:www /www/wwwroot/blazz
sudo chmod -R 775 /www/wwwroot/blazz/storage
sudo chmod -R 775 /www/wwwroot/blazz/bootstrap/cache

echo "✅ Deployment complete!"
```

```bash
chmod +x /www/wwwroot/blazz/deploy.sh
```

### 9.3 Service Ports Reference

| Service | Port | Keterangan |
|---------|------|------------|
| Laravel App | 80/443 | Via Nginx |
| MySQL | 3306 | Database |
| Redis | 6379 | Cache (optional) |
| Reverb WebSocket | 8080 | Real-time broadcasting |
| WhatsApp Instance 1 | 3001 | WhatsApp Node.js service |
| WhatsApp Instance 2 | 3002 | WhatsApp Node.js service |
| WhatsApp Instance 3 | 3003 | WhatsApp Node.js service |
| WhatsApp Instance 4 | 3004 | WhatsApp Node.js service |

---

## Changelog

| Date | Author | Changes |
|------|--------|---------|
| 2025-12-04 | System | Initial deployment documentation |

---

## Related Documents

- [Rollback Plan](./rollback-plan.md)
- [Hybrid Campaign Deployment](./hybrid-campaign-deployment.md)
