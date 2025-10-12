# WhatsApp Web.js Setup Guide

This guide explains how to set up and run the WhatsApp Web.js integration for the Blazz application.

## Problem Overview

If you're seeing the error "Preparing QR code..." indefinitely with console errors like:
- `POST http://127.0.0.1:8000/api/whatsapp-webjs/sessions/create net::ERR_EMPTY_RESPONSE`
- `initiateConnection failed Ce {message: 'Network Error', name: 'AxiosError', code: 'ERR_NETWORK'}`

This means the WhatsApp Node service is not running or not properly configured.

## Architecture

The WhatsApp integration consists of two services:
1. **Laravel Application** (port 8000) - Main web application
2. **WhatsApp Node Service** (port 3000) - WhatsApp Web.js service

The Laravel app proxies requests to the Node service, which handles WhatsApp Web connections.

## Prerequisites

- PHP 8.x+ with Laravel 12
- Node.js 18.x or higher
- Composer
- npm or yarn

## Setup Instructions

### Step 1: Configure Laravel Environment

1. Ensure your Laravel `.env` file has the following configuration:

```env
WHATSAPP_NODE_URL=http://127.0.0.1:3000
WHATSAPP_NODE_API_TOKEN=<your-api-token>
WHATSAPP_NODE_HMAC_SECRET=<your-hmac-secret>
WHATSAPP_NODE_SESSION_PATH=storage/app/whatsapp-sessions
WHATSAPP_NODE_TIMEOUT=30
```

2. Generate secure tokens if not already present:
```bash
# Generate API token
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"

# Generate HMAC secret
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

3. Update the values in `.env`

### Step 2: Configure WhatsApp Node Service

1. Navigate to the whatsapp-service directory:
```bash
cd whatsapp-service
```

2. Create `.env` file from example:
```bash
cp .env.example .env
```

3. **CRITICAL**: Update the `.env` file with the **SAME** tokens from your Laravel `.env`:
   - `API_TOKEN` must match `WHATSAPP_NODE_API_TOKEN` from Laravel
   - `HMAC_SECRET` must match `WHATSAPP_NODE_HMAC_SECRET` from Laravel

Example whatsapp-service/.env:
```env
NODE_ENV=development
PORT=3000
HOST=0.0.0.0

# MUST match Laravel .env values
API_TOKEN=<same-as-laravel-WHATSAPP_NODE_API_TOKEN>
HMAC_SECRET=<same-as-laravel-WHATSAPP_NODE_HMAC_SECRET>

SESSION_STORAGE_PATH=./sessions
MAX_CONCURRENT_SESSIONS=50

LARAVEL_URL=http://127.0.0.1:8000
WEBHOOK_ENDPOINT=/api/webhooks/whatsapp-webjs

HEALTH_CHECK_TIMEOUT=5000
SOCKETIO_CORS_ORIGIN=http://127.0.0.1:8000
```

4. Install Node dependencies:
```bash
npm install
```

### Step 3: Start the Services

#### Terminal 1 - Laravel Application
```bash
cd /path/to/blazz
php artisan serve
# Runs on http://127.0.0.1:8000
```

#### Terminal 2 - WhatsApp Node Service
```bash
cd /path/to/blazz/whatsapp-service
npm start
# or for development with auto-reload:
npm run dev
# Runs on http://127.0.0.1:3000
```

### Step 4: Verify Setup

1. Check Node service health:
```bash
curl http://127.0.0.1:3000/health
```
Expected response: `{"status":"ok","uptime":<seconds>}`

2. Access Laravel application:
```
http://127.0.0.1:8000/settings/whatsapp-number
```

3. Click "Connect WhatsApp" button
   - If configured correctly, a QR code should appear within a few seconds
   - Scan the QR code with your WhatsApp mobile app

## Production Deployment

For production, use PM2 to manage the Node service:

```bash
cd whatsapp-service

# Install PM2 globally
npm install -g pm2

# Start service with PM2
npm run pm2

# Check status
pm2 status

# View logs
pm2 logs whatsapp-service

# Restart service
pm2 restart whatsapp-service

# Stop service
pm2 stop whatsapp-service
```

## Troubleshooting

### Issue: "ERR_EMPTY_RESPONSE" or "Network Error"

**Cause**: WhatsApp Node service is not running

**Solution**:
1. Check if service is running:
   ```bash
   curl http://127.0.0.1:3000/health
   ```
2. If not running, start it:
   ```bash
   cd whatsapp-service
   npm start
   ```

### Issue: "Invalid API token" or "Invalid signature"

**Cause**: Mismatch between Laravel and Node service credentials

**Solution**:
1. Verify tokens match in both `.env` files:
   - Laravel `WHATSAPP_NODE_API_TOKEN` = Node `API_TOKEN`
   - Laravel `WHATSAPP_NODE_HMAC_SECRET` = Node `HMAC_SECRET`
2. Restart both services after changing .env

### Issue: QR code not appearing after clicking Connect

**Cause**: Webhook communication issues

**Solution**:
1. Check Node service logs for webhook errors
2. Verify Laravel webhook endpoint is accessible
3. Check that broadcasting is configured in Laravel:
   ```bash
   php artisan config:cache
   ```

### Issue: Port 3000 already in use

**Solution**:
1. Find the process using port 3000:
   ```bash
   lsof -i :3000
   ```
2. Kill the process or change the port in whatsapp-service/.env

### Issue: Puppeteer/Chrome errors

**Cause**: Missing system dependencies for Puppeteer

**Solution** (Ubuntu/Debian):
```bash
sudo apt-get install -y \
  chromium-browser \
  libxss1 \
  libnss3 \
  libasound2
```

## Service Communication Flow

1. User clicks "Connect WhatsApp" in Laravel UI
2. Laravel sends POST to `/api/whatsapp-webjs/sessions/create`
3. Laravel controller proxies request to Node service at `http://127.0.0.1:3000/api/sessions/create`
4. Node service creates WhatsApp Web client and generates QR code
5. Node service sends webhook back to Laravel at `/api/webhooks/whatsapp-webjs`
6. Laravel broadcasts QR code event via Laravel Echo/Reverb
7. Frontend receives QR code and displays it
8. User scans QR code with WhatsApp mobile app
9. Node service detects authentication and sends "session.ready" webhook
10. Laravel updates workspace metadata and broadcasts status change
11. Frontend shows "Connected" status

## Monitoring

### Check Service Status
```bash
# Laravel
curl http://127.0.0.1:8000

# Node service
curl http://127.0.0.1:3000/health
```

### View Logs

**Laravel logs:**
```bash
tail -f storage/logs/laravel.log
```

**Node service logs (development):**
```bash
# Logs are displayed in console
```

**Node service logs (PM2):**
```bash
pm2 logs whatsapp-service
```

## Security Notes

1. **Never commit `.env` files** - They contain sensitive credentials
2. **Use strong, unique tokens** - Generate using cryptographically secure methods
3. **Firewall rules** - In production, ensure port 3000 is not publicly accessible
4. **HTTPS in production** - Use HTTPS for both Laravel and configure accordingly
5. **Regular updates** - Keep whatsapp-web.js and dependencies updated

## Support

If you encounter issues not covered in this guide:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Check Node service logs (console or PM2)
3. Verify all environment variables are correctly set
4. Ensure both services can communicate (no firewall blocking)
5. Review the error messages carefully - they often indicate the exact problem

## Additional Resources

- WhatsApp Web.js documentation: https://wwebjs.dev/
- Laravel Broadcasting: https://laravel.com/docs/broadcasting
- PM2 documentation: https://pm2.keymetrics.io/
