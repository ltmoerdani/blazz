# 🚀 Blazz Chat System - Quick Start Guide

**Purpose:** Panduan instalasi dan konfigurasi lengkap sistem chat Blazz
**Focus:** Production deployment setup dengan enterprise features
**Status:** Production Ready - Full WhatsApp Web Experience
**Implementation:** 100% Complete Working System

---

## 📋 SYSTEM REQUIREMENTS

### **Server Requirements**
- **PHP:** 8.2+ dengan extensions: `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`
- **Node.js:** 18.0+ untuk WhatsApp service
- **Database:** MySQL 8.0+ dengan JSON support
- **Redis:** 6.0+ untuk caching dan queue processing
- **Web Server:** Nginx/Apache dengan HTTPS support
- **Memory:** Minimum 4GB RAM (8GB+ recommended untuk production)
- **Storage:** 50GB+ (untuk media files)

### **Software Dependencies**
```bash
# PHP Composer
composer --version  # 2.0+

# Node.js & NPM
node --version      # 18.0+
npm --version       # 9.0+

# Database & Cache
mysql --version     # 8.0+
redis-cli --version # 6.0+

# Process Management
pm2 --version       # 5.0+
```

---

## ⚡ INSTALLATION STEPS

### **Step 1: Clone Repository**
```bash
# Clone the project
git clone <repository-url> blazz-chat
cd blazz-chat

# Copy environment file
cp .env.example .env
```

### **Step 2: Backend Setup (Laravel)**
```bash
# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Generate application key
php artisan key:generate

# Configure environment variables
nano .env
```

### **Step 3: Database Configuration**
```bash
# Edit .env file
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=blazz_chat
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Run migrations
php artisan migrate

# Seed initial data
php artisan db:seed
```

### **Step 4: Frontend Setup**
```bash
# Install Node.js dependencies
npm install

# Build frontend assets
npm run build
```

### **Step 5: WhatsApp Service Setup**
```bash
# Navigate to WhatsApp service
cd whatsapp-service

# Install Node.js dependencies
npm install

# Configure service
cp .env.example .env
nano .env

# Install PM2 globally (if not installed)
npm install -g pm2

# Start WhatsApp service
pm2 start ecosystem.config.js
pm2 save
pm2 startup
```

---

## 🔧 ENVIRONMENT CONFIGURATION

### **Core Laravel Environment (.env)**
```bash
# Application
APP_NAME="Blazz Chat System"
APP_ENV=production
APP_KEY=base64:YOUR_APP_KEY_HERE
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=blazz_production
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Broadcasting (WebSocket)
BROADCAST_DRIVER=reverb
REVERB_APP_ID=your_app_id
REVERB_APP_KEY=your_app_key
REVERB_APP_SECRET=your_app_secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http

# Cache & Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# File Storage
FILESYSTEM_DISK=local
AWS_ACCESS_KEY_ID=your_aws_key
AWS_SECRET_ACCESS_KEY=your_aws_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_s3_bucket
AWS_USE_PATH_STYLE_ENDPOINT=false

# WhatsApp Service Integration
WHATSAPP_NODE_URL=http://localhost:3000
WHATSAPP_NODE_API_TOKEN=your_secure_api_token
WHATSAPP_NODE_API_SECRET=your_api_secret

# OpenAI Integration (Optional)
OPENAI_API_KEY=your_openai_key
OPENAI_ORGANIZATION=your_org_id

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### **WhatsApp Service Environment (whatsapp-service/.env)**
```bash
# Laravel Integration
LARAVEL_URL=http://127.0.0.1:8000
HMAC_SECRET=your_hmac_secret_key

# Service Configuration
PORT=3000
NODE_ENV=production

# WhatsApp Web.js Configuration
WEBJS_TIMEOUT=60000
WEBJS_AUTH_TIMEOUT=0
WEBJS_QR_REFRESH_INTERVAL=30000

# Logging
LOG_LEVEL=info
LOG_FILE=logs/whatsapp-service.log

# Security
CORS_ORIGIN=http://localhost:8000
RATE_LIMIT_WINDOW=60000
RATE_LIMIT_MAX=100
```

---

## 🚀 SERVICE DEPLOYMENT

### **Step 1: Start Laravel Services**
```bash
# Start queue workers
php artisan queue:work --queue=whatsapp-urgent --timeout=30 --sleep=1 --tries=3 &
php artisan queue:work --queue=whatsapp-high --timeout=60 --sleep=2 --tries=3 &
php artisan queue:work --queue=whatsapp-normal --timeout=120 --sleep=5 --tries=3 &
php artisan queue:work --queue=whatsapp-campaign --timeout=300 --sleep=10 --tries=5 &

# Start WebSocket server (Reverb)
php artisan reverb:start

# Optimize application
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### **Step 2: Start WhatsApp Service**
```bash
# Navigate to service directory
cd whatsapp-service

# Start with PM2
pm2 start ecosystem.config.js
pm2 save
pm2 startup

# Check service status
pm2 status
pm2 logs whatsapp-service
```

### **Step 3: Nginx Configuration**
```nginx
# /etc/nginx/sites-available/blazz-chat
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/blazz-chat/public;
    index index.php;

    ssl_certificate /path/to/your/cert.pem;
    ssl_certificate_key /path/to/your/private.key;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # WebSocket proxy for Reverb
    location /socket.io/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

---

## 📱 INITIAL SETUP & CONFIGURATION

### **Step 1: Create Admin Account**
```bash
# Run artisan command to create admin
php artisan make:admin

# Or register through web interface
# Visit: https://your-domain.com/register
```

### **Step 2: Create Workspace**
1. Login sebagai admin
2. Navigate ke **Settings → Workspaces**
3. Click **Create Workspace**
4. Isi workspace details:
   - **Name:** Your Company Name
   - **Domain:** Subdomain untuk workspace
   - **Settings:** Configure timezone, currency, dll.

### **Step 3: Setup WhatsApp Account**
1. Navigate ke **WhatsApp Settings**
2. Click **Add WhatsApp Account**
3. Choose **Provider Type:**
   - **WhatsApp Web.js** untuk personal accounts
   - **Meta Cloud API** untuk business accounts

#### **WhatsApp Web.js Setup:**
1. Start service
2. Scan QR code dengan WhatsApp mobile app
3. Wait untuk connection establishment
4. Verify status: "Connected"

#### **Meta Cloud API Setup:**
1. Configure Meta Business App
2. Add webhook URL: `https://your-domain.com/whatsapp/webhooks/meta`
3. Verify webhook dengan Meta
4. Configure phone number ID dan access token

---

## 🔍 TESTING & VERIFICATION

### **Basic Functionality Tests**
```bash
# Test Laravel application
curl -I https://your-domain.com

# Test WhatsApp service
curl -I http://localhost:3000/health

# Test WebSocket connection
curl -I https://your-domain.com/socket.io/
```

### **Manual Testing Checklist**
- [ ] **Web Interface:** Login dan dashboard loads properly
- [ ] **WebSocket Connection:** Real-time features working
- [ ] **WhatsApp Service:** Account connected dan status active
- [ ] **Send Message:** Text messages send successfully
- [ ] **Media Upload:** Files upload dengan preview
- [ ] **Real-time Updates:** Message status updates work
- [ ] **Notifications:** Badge updates dan typing indicators
- [ ] **Queue Processing:** Background jobs processing properly

### **API Testing Examples**
```bash
# Send test message
curl -X POST "https://your-domain.com/chats" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Test message",
    "type": "chat",
    "uuid": "CONTACT_UUID_HERE"
  }'

# Check WhatsApp service health
curl -X GET "http://localhost:3000/health"

# Test webhook endpoint
curl -X POST "https://your-domain.com/whatsapp/webhooks/webjs" \
  -H "Content-Type: application/json" \
  -H "X-HMAC-Signature: TEST_SIGNATURE" \
  -d '{"test": true}'
```

---

## 📊 MONITORING & MAINTENANCE

### **Service Monitoring**
```bash
# Check Laravel queues
php artisan queue:monitor

# Check PM2 processes
pm2 status

# Monitor logs
tail -f storage/logs/laravel.log
pm2 logs whatsapp-service

# Check Redis
redis-cli ping
redis-cli info memory
```

### **Health Check Endpoint**
```bash
# Laravel health check
curl https://your-domain.com/api/health

# Expected response:
{
    "status": "healthy",
    "timestamp": "2025-11-18T10:30:00Z",
    "services": {
        "database": "healthy",
        "redis": "healthy",
        "queue": "healthy",
        "whatsapp_service": "healthy"
    }
}
```

### **Performance Monitoring**
```bash
# Check database performance
php artisan db:show

# Monitor queue sizes
php artisan queue:monitor whatsapp-urgent whatsapp-high whatsapp-normal

# Check WebSocket connections
php artisan reverb:status
```

---

## 🚨 TROUBLESHOOTING

### **Common Issues & Solutions**

#### **WebSocket Not Connecting**
```bash
# Check Reverb status
php artisan reverb:status

# Restart WebSocket server
php artisan reverb:start

# Check firewall settings
sudo ufw status
sudo ufw allow 8080
```

#### **WhatsApp Service Not Starting**
```bash
# Check logs
pm2 logs whatsapp-service

# Restart service
pm2 restart whatsapp-service

# Check Node.js version
node --version  # Should be 18+
```

#### **Queue Jobs Not Processing**
```bash
# Check queue configuration
php artisan queue:failed

# Restart queue workers
php artisan queue:restart

# Clear failed jobs
php artisan queue:flush
```

#### **File Upload Issues**
```bash
# Check storage permissions
sudo chown -R www-data:www-data storage/
sudo chmod -R 775 storage/

# Check disk space
df -h
```

#### **Database Connection Issues**
```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo()

# Check MySQL service
sudo systemctl status mysql
```

---

## 📈 PERFORMANCE OPTIMIZATION

### **Production Optimizations**
```bash
# Optimize autoloader
composer install --optimize-autoloader --no-dev

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Precompile frontend assets
npm run build --production
```

### **Database Optimizations**
```sql
-- Add indexes for better performance
CREATE INDEX idx_chats_contact_timestamp ON chats(contact_id, created_at DESC);
CREATE INDEX idx_contacts_workspace_active ON contacts(workspace_id, is_active);
```

### **Redis Optimization**
```bash
# Configure Redis for production
redis-cli CONFIG SET maxmemory 2gb
redis-cli CONFIG SET maxmemory-policy allkeys-lru
```

---

## 🔄 BACKUP & RECOVERY

### **Automated Backups**
```bash
# Database backup script
#!/bin/bash
mysqldump -u username -p blazz_production > backup_$(date +%Y%m%d_%H%M%S).sql

# File backup
rsync -av /var/www/blazz-chat/storage/app/ /backup/files/
```

### **Recovery Procedures**
```bash
# Restore database
mysql -u username -p blazz_production < backup_20251118_103000.sql

# Restore files
rsync -av /backup/files/ /var/www/blazz-chat/storage/app/
```

---

## ✅ DEPLOYMENT CHECKLIST

### **Pre-deployment Checklist**
- [ ] Environment variables configured
- [ ] Database migrations executed
- [ ] Frontend assets compiled
- [ ] WhatsApp service installed
- [ ] SSL certificates installed
- [ ] Firewall configured
- [ ] Backup systems configured
- [ ] Monitoring setup completed

### **Post-deployment Verification**
- [ ] All services running correctly
- [ ] WebSocket connections working
- [ ] WhatsApp accounts connected
- [ ] Real-time features functional
- [ ] File uploads working
- [ ] Queue processing active
- [ ] Health checks passing

---

## 📞 SUPPORT & MAINTENANCE

### **Regular Maintenance Tasks**
- **Daily:** Monitor logs, check service health
- **Weekly:** Review queue performance, update dependencies
- **Monthly:** Database optimization, security updates
- **Quarterly:** Performance review, capacity planning

### **Support Resources**
- **Documentation:** `/docs/chats/` folder
- **Logs:** `storage/logs/laravel.log`
- **WhatsApp Service:** `whatsapp-service/logs/`
- **System Health:** `/api/health` endpoint

---

## 🎯 SUCCESS METRICS

### **System Performance Targets**
- **Response Time:** <200ms untuk page loads
- **Message Delivery:** <500ms untuk real-time updates
- **Uptime:** 99.9% availability
- **Queue Processing:** <5s untuk urgent messages

### **User Experience Targets**
- **WhatsApp-like Interface:** Professional chat experience
- **Real-time Updates:** Instant status notifications
- **File Sharing:** Smooth media upload/download
- **Cross-platform:** Mobile dan desktop compatibility

---

## 📋 CONCLUSION

Blazz Chat System siap untuk production deployment dengan:

✅ **Complete Feature Set** - Real-time messaging, WhatsApp integration, AI features
✅ **Enterprise Architecture** - Scalable, secure, multi-tenant platform
✅ **Production Ready** - Optimized untuk high-load environments
✅ **Professional UI** - WhatsApp Web-like user experience
✅ **Comprehensive APIs** - Full RESTful API untuk integrations
✅ **Monitoring Tools** - Built-in health checks dan performance monitoring

**System siap digunakan untuk enterprise-scale communication needs dengan professional WhatsApp-like experience.**

---

**Deployment Status:** ✅ Ready for Production
**Support Level:** Enterprise-grade dengan documentation lengkap
**Next Steps:** User training dan customization sesuai kebutuhan bisnis