# aaPanel Configuration Guide - Blazz

> **Panduan konfigurasi aaPanel untuk Blazz production server**
> 
> Server: 210.79.191.51
> Panel: aaPanel (BT Panel)

---

## Table of Contents

1. [Akses Panel](#1-akses-panel)
2. [Website Configuration](#2-website-configuration)
3. [PHP Configuration](#3-php-configuration)
4. [Node.js Configuration](#4-nodejs-configuration)
5. [Nginx Configuration](#5-nginx-configuration)
6. [SSL Certificate](#6-ssl-certificate)
7. [Supervisor (Background Services)](#7-supervisor-background-services)

---

## 1. Akses Panel

```
URL: http://210.79.191.51:8888
```

---

## 2. Website Configuration

### 2.1 Site Settings

| Setting | Value |
|---------|-------|
| Domain | blazz.id, www.blazz.id |
| Document Root | /www/wwwroot/blazz/public |
| PHP Version | PHP 8.3 |

### 2.2 Important Notes

- Document root harus mengarah ke folder `public`, bukan root project
- Jangan lupa setup redirect www ke non-www (atau sebaliknya)

---

## 3. PHP Configuration

### 3.1 Install Extensions

Lokasi: **App Store → PHP 8.3 → Setting → Install Extensions**

**Wajib Install:**
- ✅ mbstring
- ✅ fileinfo
- ✅ intl
- ✅ exif
- ✅ imagemagick (atau gd)
- ✅ bcmath
- ✅ zip

**Tidak Perlu Install:**
- ❌ pdo_sqlsrv (SQL Server)
- ❌ oci8, pdo_oci (Oracle)
- ❌ pgsql, pdo_pgsql (PostgreSQL)
- ❌ xdebug (debugging - development only)
- ❌ swoole (tidak digunakan)

### 3.2 Disabled Functions

Lokasi: **App Store → PHP 8.3 → Setting → Disabled functions**

**Hapus dari disabled functions list:**
- exec
- system
- putenv
- shell_exec
- popen
- proc_open
- symlink
- readlink
- pcntl_alarm
- pcntl_fork
- pcntl_waitpid
- pcntl_wait
- pcntl_wifexited
- pcntl_wifstopped
- pcntl_wifsignaled
- pcntl_wifcontinued
- pcntl_wexitstatus
- pcntl_wtermsig
- pcntl_wstopsig
- pcntl_signal
- pcntl_signal_dispatch
- pcntl_get_last_error
- pcntl_strerror
- pcntl_sigprocmask
- pcntl_sigwaitinfo
- pcntl_sigtimedwait
- pcntl_getpriority
- pcntl_setpriority

**Tetap Disabled (untuk keamanan):**
- passthru
- chroot
- chgrp
- chown
- ini_alter
- ini_restore
- dl
- openlog
- syslog
- imap_open
- apache_setenv

### 3.3 PHP-FPM Configuration

Lokasi: **App Store → PHP 8.3 → Setting → Performance**

Recommended settings untuk VPS 4GB RAM:
```
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

---

## 4. Node.js Configuration

### 4.1 Install Node.js

Lokasi: **App Store → Node.js Version Manager**

Install versi: **v24.11.1** (atau LTS terbaru)

### 4.2 Node.js Path

Node.js terinstall di:
```
/www/server/nodejs/v24.11.1/bin/
```

Binaries:
- node: `/www/server/nodejs/v24.11.1/bin/node`
- npm: `/www/server/nodejs/v24.11.1/bin/npm`
- npx: `/www/server/nodejs/v24.11.1/bin/npx`

### 4.3 Global Symlinks (Optional)

Untuk akses tanpa full path:
```bash
sudo ln -sf /www/server/nodejs/v24.11.1/bin/node /usr/local/bin/node
sudo ln -sf /www/server/nodejs/v24.11.1/bin/npm /usr/local/bin/npm
sudo ln -sf /www/server/nodejs/v24.11.1/bin/npx /usr/local/bin/npx
```

### 4.4 Kapan Menggunakan "Add Node Project"

**JANGAN gunakan** untuk Laravel/Vite build. Fitur ini untuk aplikasi Node.js yang perlu running terus seperti:

| Gunakan untuk | Port | Contoh |
|---------------|------|--------|
| WhatsApp Service | 3001 | `/www/wwwroot/blazz/whatsapp-service` |
| Custom Node.js API | varies | Express.js server |

**Laravel dengan Vite** hanya perlu build sekali:
```bash
cd /www/wwwroot/blazz
/www/server/nodejs/v24.11.1/bin/npm install
/www/server/nodejs/v24.11.1/bin/npm run build
```

---

## 5. Nginx Configuration

### 5.1 Site Nginx Config

Lokasi: **Website → [blazz.id] → Config**

```nginx
server {
    listen 80;
    listen 443 ssl http2;
    
    server_name blazz.id www.blazz.id;
    
    # Document root - PENTING: harus ke folder public
    root /www/wwwroot/blazz/public;
    index index.php index.html;
    
    # SSL Configuration
    ssl_certificate /www/server/panel/vhost/cert/blazz.id/fullchain.pem;
    ssl_certificate_key /www/server/panel/vhost/cert/blazz.id/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;
    
    # Redirect HTTP to HTTPS
    if ($scheme = http) {
        return 301 https://$host$request_uri;
    }
    
    # Laravel Routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP Processing
    location ~ \.php$ {
        fastcgi_pass unix:/tmp/php-cgi-83.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Deny access to sensitive files
    location ~ /\.(?!well-known) {
        deny all;
    }
    
    location ~ ^/(\.user.ini|\.htaccess|\.git|\.svn|\.project|LICENSE|README.md) {
        deny all;
    }
    
    # Static files caching
    location ~* \.(ico|css|js|gif|jpe?g|png|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
    
    # Gzip
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private auth;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml application/javascript application/json;
    
    # WebSocket untuk Reverb (jika menggunakan subdomain atau path)
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
    }
    
    # Access & Error logs
    access_log /www/wwwlogs/blazz.id.log;
    error_log /www/wwwlogs/blazz.id.error.log;
}
```

### 5.2 Reload Nginx

```bash
sudo nginx -t && sudo systemctl reload nginx
```

---

## 6. SSL Certificate

### 6.1 Via aaPanel (Recommended)

1. Buka **Website → [blazz.id] → SSL**
2. Pilih **Let's Encrypt**
3. Pilih domain: blazz.id, www.blazz.id
4. Klik **Apply**
5. Enable **Force HTTPS**

### 6.2 Manual dengan Certbot

```bash
certbot certonly --webroot -w /www/wwwroot/blazz/public -d blazz.id -d www.blazz.id
```

---

## 7. Supervisor (Background Services)

### 7.1 Install Supervisor

Lokasi: **App Store → Supervisor Manager**

### 7.2 Laravel Queue Worker

**Add Daemon:**

| Field | Value |
|-------|-------|
| Name | blazz-queue |
| Command | php artisan queue:work --sleep=3 --tries=3 --max-time=3600 |
| Directory | /www/wwwroot/blazz |
| User | www |
| Numprocs | 2 |

### 7.3 Laravel Scheduler

Tambahkan ke crontab (bukan supervisor):

```bash
# Edit crontab
crontab -e

# Tambahkan baris ini
* * * * * cd /www/wwwroot/blazz && php artisan schedule:run >> /dev/null 2>&1
```

### 7.4 Reverb WebSocket Server

**Add Daemon:**

| Field | Value |
|-------|-------|
| Name | blazz-reverb |
| Command | php artisan reverb:start |
| Directory | /www/wwwroot/blazz |
| User | www |
| Numprocs | 1 |

### 7.5 WhatsApp Node.js Service

**Add Daemon (via Node.js Project Manager atau Supervisor):**

| Field | Value |
|-------|-------|
| Name | blazz-whatsapp |
| Command | /www/server/nodejs/v24.11.1/bin/node index.js |
| Directory | /www/wwwroot/blazz/whatsapp-service |
| User | www |
| Numprocs | 1 |

Atau gunakan PM2:
```bash
cd /www/wwwroot/blazz/whatsapp-service
/www/server/nodejs/v24.11.1/bin/npx pm2 start index.js --name "blazz-whatsapp"
/www/server/nodejs/v24.11.1/bin/npx pm2 save
```

---

## Quick Reference

### File Permissions

```bash
# Owner: www
sudo chown -R www:www /www/wwwroot/blazz

# Directories: 755, Files: 644
sudo find /www/wwwroot/blazz -type d -exec chmod 755 {} \;
sudo find /www/wwwroot/blazz -type f -exec chmod 644 {} \;

# Writable directories
sudo chmod -R 775 /www/wwwroot/blazz/storage
sudo chmod -R 775 /www/wwwroot/blazz/bootstrap/cache
```

### Restart Services

```bash
# PHP-FPM
sudo /www/server/php/83/sbin/php-fpm restart

# Nginx
sudo systemctl reload nginx

# Supervisor (all)
sudo supervisorctl restart all

# Specific supervisor process
sudo supervisorctl restart blazz-queue
```

### Logs Location

| Service | Log Path |
|---------|----------|
| Laravel | `/www/wwwroot/blazz/storage/logs/laravel.log` |
| Nginx Access | `/www/wwwlogs/blazz.id.log` |
| Nginx Error | `/www/wwwlogs/blazz.id.error.log` |
| PHP-FPM | `/www/server/php/83/var/log/php-fpm.log` |
| Supervisor | `/www/server/panel/plugin/supervisor/log/` |

---

## Changelog

| Date | Changes |
|------|---------|
| 2025-12-04 | Initial aaPanel configuration guide |
