# Port Configuration - Blazz WhatsApp Integration

## ✅ STANDARD PORT ASSIGNMENTS (FIXED - Oct 13, 2025)

### Production Ports
| Service | Port | Protocol | Purpose |
|---------|------|----------|---------|
| **Laravel Application** | 8000 | HTTP | Main web server (artisan serve) |
| **Laravel Reverb** | 8080 | WebSocket/HTTP | Real-time broadcasting server |
| **WhatsApp Web.js Service** | 3001 | HTTP | Node.js WhatsApp client service |
| **Vite Dev Server** | 5173 | HTTP | Frontend hot-reload (dev only) |
| **MySQL Database** | 3306 | TCP | Database server |
| **Redis** | 6379 | TCP | Cache & queue backend |

---

## 📝 Configuration Files Updated

### 1. **whatsapp-service/.env**
```env
PORT=3001
SOCKETIO_PORT=3001
LARAVEL_URL=http://127.0.0.1:8000
```

### 2. **.env** (Laravel root)
```env
REVERB_PORT=8080
VITE_REVERB_PORT="${REVERB_PORT}"
```

### 3. **start-dev.sh**
Updated references from port 3000 → 3001:
- Service startup message: "Port 3001"
- Health check URL: `http://127.0.0.1:3001/health`
- Service info display: `http://127.0.0.1:3001`

---

## 🔧 Service Communication Flow

```
Frontend (Browser)
    ↓ HTTP:5173 (dev) / HTTP:80 (prod)
Laravel App (:8000)
    ↓ WebSocket:8080
Laravel Reverb (:8080)
    ↑ Broadcast Events
Laravel App (:8000)
    ↑ HTTP Webhooks
WhatsApp Service (:3001)
```

---

## 🚀 Startup Commands

### Development Mode (All Services)
```bash
# Option 1: Using start script (recommended)
./start-dev.sh

# Option 2: Using npm concurrently
npm run dev

# Option 3: Individual services
npm run dev:vite    # Port 5173
npm run dev:laravel # Port 8000
npm run dev:reverb  # Port 8080
npm run dev:node    # Port 3001
npm run dev:queue   # Background worker
```

### Production Mode
```bash
# Start all services with PM2
npm run start:full

# Or individually
php artisan serve --host=0.0.0.0 --port=8000
php artisan reverb:start --host=0.0.0.0 --port=8080
cd whatsapp-service && npm start  # Port 3001 from .env
php artisan queue:work
```

---

## 🔍 Port Verification

### Check if ports are in use:
```bash
# macOS/Linux
lsof -i :8000 -i :8080 -i :3001 -i :5173 | grep LISTEN

# Check specific port
lsof -i :3001

# Kill process on port
lsof -ti :3001 | xargs kill -9
```

### Test service availability:
```bash
# Laravel App
curl http://127.0.0.1:8000

# Reverb WebSocket
curl http://127.0.0.1:8080

# WhatsApp Service Health
curl http://127.0.0.1:3001/health

# Vite Dev Server
curl http://127.0.0.1:5173
```

---

## ⚠️ Common Port Conflicts

### Issue 1: Port Already in Use
**Symptom**: Service fails to start with "EADDRINUSE" error

**Solution**:
```bash
# Find and kill the process
lsof -ti :3001 | xargs kill -9

# Or use the stop script
./stop-dev.sh
```

### Issue 2: Firewall Blocking Ports
**Symptom**: Services start but can't communicate

**Solution** (macOS):
```bash
# Allow incoming connections
sudo /usr/libexec/ApplicationFirewall/socketfilterfw --add /path/to/node
sudo /usr/libexec/ApplicationFirewall/socketfilterfw --unblock node
```

### Issue 3: Wrong Port in Configuration
**Symptom**: 404 errors, connection refused

**Check**:
1. `.env` files in both root and `whatsapp-service/`
2. `start-dev.sh` port references
3. Frontend Echo configuration (`resources/js/echo.js`)

---

## 📊 Port Usage Matrix

| Component | Connects To | Port | Direction |
|-----------|-------------|------|-----------|
| Browser | Vite Dev | 5173 | ← Incoming |
| Browser | Laravel App | 8000 | ← Incoming |
| Browser | Reverb WS | 8080 | ↔ Bidirectional |
| Laravel App | MySQL | 3306 | → Outgoing |
| Laravel App | Redis | 6379 | → Outgoing |
| Laravel App | Reverb | 8080 | → Outgoing |
| WhatsApp Service | Laravel App | 8000 | → Outgoing |
| Queue Worker | Laravel App | Internal | Internal |

---

## 🛠️ Troubleshooting Port Issues

### 1. Service Health Checks
```bash
# Test each service endpoint
echo "Testing Laravel..."
curl -s http://127.0.0.1:8000 > /dev/null && echo "✅ Laravel OK" || echo "❌ Laravel FAIL"

echo "Testing Reverb..."
curl -s http://127.0.0.1:8080 > /dev/null && echo "✅ Reverb OK" || echo "❌ Reverb FAIL"

echo "Testing WhatsApp Service..."
curl -s http://127.0.0.1:3001/health > /dev/null && echo "✅ WhatsApp OK" || echo "❌ WhatsApp FAIL"
```

### 2. View Service Logs
```bash
# All logs
tail -f logs/*.log

# Specific service
tail -f logs/whatsapp-service.log
tail -f logs/reverb.log
tail -f logs/laravel.log
```

### 3. Network Connectivity Test
```bash
# Test from WhatsApp service to Laravel
curl -X POST http://127.0.0.1:8000/api/whatsapp/webhooks/webjs \
  -H "Content-Type: application/json" \
  -d '{"event":"test","data":{"message":"port test"}}'
```

---

## 📝 Historical Changes

### October 13, 2025
- ✅ Fixed WhatsApp service port reference in `start-dev.sh` (3000 → 3001)
- ✅ Confirmed `.env` files have correct port assignments
- ✅ Updated service health check URLs
- ✅ Documented standard port assignments

### Original Configuration Issues
- ❌ `start-dev.sh` was checking port 3000 but service runs on 3001
- ❌ Port mismatch caused health check failures
- ❌ Service appeared not to start even though it was running

---

## 🎯 Quick Reference

**Remember these ports:**
- **8000** = Laravel backend
- **8080** = Reverb WebSocket  
- **3001** = WhatsApp service
- **5173** = Vite dev server (development only)

**All services must be running for full functionality:**
```bash
./start-dev.sh  # Starts all 4 services automatically
```

---

## 📞 Support

If port conflicts persist after following this guide:
1. Check for other applications using these ports
2. Consider changing ports in `.env` files (update all references)
3. Restart Docker containers if using containerization
4. Check system firewall settings

**Last Updated**: October 13, 2025  
**Status**: ✅ Port configuration verified and documented
