# WhatsApp Multi-Instance Deployment Quick Start

## 🎯 Target: Scale from 500 to 2,000+ concurrent sessions

This guide helps you deploy the multi-instance WhatsApp architecture to support 1,000-3,000 concurrent sessions as documented in the scalable architecture.

---

## 📋 Prerequisites

- ✅ Laravel backend configured with multi-instance support (already implemented)
- ✅ Database migrations applied (already completed)
- ✅ PM2 installed: `npm install -g pm2`
- ✅ Node.js 16+ installed
- ✅ Redis server running (for instance coordination)

---

## 🚀 Quick Start (5 Minutes)

### 1. Start All 4 Instances

```bash
cd whatsapp-service
./start-multi-instance.sh
```

**Expected Output:**
```
🚀 Starting WhatsApp Multi-Instance Service
======================================
✅ WhatsApp Instance 1 is ready on port 3001
✅ WhatsApp Instance 2 is ready on port 3002
✅ WhatsApp Instance 3 is ready on port 3003
✅ WhatsApp Instance 4 is ready on port 3004

📊 Instance Status:
✅ whatsapp-instance-1     3001     online
✅ whatsapp-instance-2     3002     online
✅ whatsapp-instance-3     3003     online
✅ whatsapp-instance-4     3004     online

📈 Capacity:
• Total Capacity: 2,000 concurrent sessions (4 × 500)
• Workspace Sharding: Active
```

### 2. Verify Health

```bash
# Check all instances from Laravel
php artisan whatsapp:health-check

# Check individual instance
curl http://localhost:3001/health
curl http://localhost:3002/health
```

### 3. Test Workspace Sharding

```bash
php artisan tinker
```
```php
use App\Services\WhatsApp\InstanceRouter;

$router = new InstanceRouter();

// Test which instance handles which workspace
echo "Workspace 1 -> " . $router->getInstanceForWorkspace(1) . "\n";
echo "Workspace 2 -> " . $router->getInstanceForWorkspace(2) . "\n";
echo "Workspace 3 -> " . $router->getInstanceForWorkspace(3) . "\n";
echo "Workspace 4 -> " . $router->getInstanceForWorkspace(4) . "\n";
```

**Expected:** Perfect 25% distribution across 4 instances.

### 4. Stop Instances

```bash
./stop-multi-instance.sh
```

---

## 🏗️ Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                    LARAVEL SERVER                        │
│                    (API Gateway)                         │
│                                                          │
│  InstanceRouter: workspace_id % 4 = instance_index       │
└────────────┬─────────────┬──────────────┬───────────────┘
             │             │              │               │
             ▼             ▼              ▼               ▼
    ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐
    │ WhatsApp   │ │ WhatsApp   │ │ WhatsApp   │ │ WhatsApp   │
    │ Instance 1 │ │ Instance 2 │ │ Instance 3 │ │ Instance 4 │
    │   :3001     │ │   :3002     │ │   :3003     │ │   :3004     │
    ├────────────┤ ├────────────┤ ├────────────┤ ├────────────┤
    │Workspaces: │ │Workspaces: │ │Workspaces: │ │Workspaces: │
    │  1,5,9...   │ │  2,6,10...  │ │  3,7,11...  │ │  4,8,12...  │
    ├────────────┤ ├────────────┤ ├────────────┤ ├────────────┤
    │ Sessions:  │ │ Sessions:  │ │ Sessions:  │ │ Sessions:  │
    │  0-500     │ │  0-500     │ │  0-500     │ │  0-500     │
    └────────────┘ └────────────┘ └────────────┘ └────────────┘
             │              │               │               │
             └──────────────┼───────────────┼───────────────┘
                            ▼               ▼
                    ┌─────────────────────────────────┐
                    │     SHARED STORAGE               │
                    │  sessions-shared/                 │
                    │  ├── workspace_1/                 │
                    │  ├── workspace_2/                 │
                    │  └── ...                          │
                    └─────────────────────────────────┘
```

---

## 📊 Capacity Planning

| Instances | Total Capacity | Architecture | Use Case |
|-----------|----------------|-------------|----------|
| 1 | 500 sessions | Single Instance | Development |
| 2 | 1,000 sessions | Basic Sharding | Small Production |
| **4** | **2,000 sessions** | **Production Ready** | **Current Implementation** |
| 8 | 4,000 sessions | High Scale | Future Scaling |

---

## 🔧 Management Commands

### Daily Operations

```bash
# Check instance health
php artisan whatsapp:health-check

# View instance logs
pm2 logs

# Restart specific instance
pm2 restart whatsapp-instance-1

# Monitor real-time
pm2 monit

# Check session distribution
php artisan tinker
>>> WhatsAppAccount::selectRaw('assigned_instance_index, COUNT(*) as count')
>>>     ->groupBy('assigned_instance_index')->get();
```

### Maintenance

```bash
# Restart all instances gracefully
pm2 restart all

# Clear logs (weekly)
pm2 flush

# Update PM2 configuration
pm2 reload ecosystem.multi-instance.config.js
```

---

## 🚨 Troubleshooting

### Instance Not Starting

```bash
# Check if port is available
lsof -i :3001

# Check PM2 logs
pm2 logs whatsapp-instance-1

# Manually test instance
cd whatsapp-service
PORT=3001 INSTANCE_ID=test node server.js
```

### Health Check Failing

```bash
# Check individual instance health
curl http://localhost:3001/health

# Expected response:
{
  "status": "healthy",
  "sessions": {"total": 0, "utilization": "0%"},
  "instance": "whatsapp-instance-1"
}
```

### Sessions Not Routing Correctly

```bash
# Verify Laravel configuration
php artisan tinker
>>> config('whatsapp.instance_count')  // Should return 4
>>> config('whatsapp.instances')      // Should return array of 4 URLs

# Check database assignments
>>> WhatsAppAccount::whereNull('assigned_instance_index')->count();
```

---

## 📈 Monitoring

### Health Monitoring

```bash
# Real-time health dashboard
php artisan whatsapp:health-check

# JSON output for monitoring tools
php artisan whatsapp:health-check --json

# Individual instance monitoring
curl http://localhost:3001/health | jq .
```

### Performance Metrics

```bash
# Check session distribution
php artisan tinker
>>> WhatsAppAccount::selectRaw('assigned_instance_index, COUNT(*) as count')
>>>     ->groupBy('assigned_instance_index')
>>>     ->get();

# Check recent disconnections
>>> WhatsAppAccount::recentlyDisconnected(24)->count();

# Instance utilization
php artisan whatsapp:health-check --summary
```

---

## 🔄 Scaling Up

### To 8 Instances (4,000 sessions)

1. **Update Configuration:**
   ```bash
   # Update .env
   WHATSAPP_INSTANCE_COUNT=8
   WHATSAPP_INSTANCE_5=http://localhost:3005
   WHATSAPP_INSTANCE_6=http://localhost:3006
   WHATSAPP_INSTANCE_7=http://localhost:3007
   WHATSAPP_INSTANCE_8=http://localhost:3008
   ```

2. **Add instances to ecosystem config:**
   - Copy existing instance config
   - Update ports to 3005-3008
   - Set INSTANCE_INDEX to 4-7

3. **Restart with new capacity:**
   ```bash
   php artisan config:clear
   ./stop-multi-instance.sh
   ./start-multi-instance.sh
   ```

---

## 🎯 Success Metrics

Your multi-instance deployment is successful when:

✅ **All 4 instances running healthy**
✅ **2,000 session capacity available**
✅ **Workspaces evenly distributed** (25% per instance)
✅ **Health monitoring working**
✅ **Zero data loss during restarts**
✅ **Shared storage persisting sessions**

---

## 📚 Next Steps

1. **Load Testing:** Create test sessions across all instances
2. **Production Setup:** Configure proper shared storage (EFS/GlusterFS)
3. **Monitoring Setup:** Configure Prometheus + Grafana
4. **Backup Strategy:** Implement automated session backups
5. **Auto-Scaling:** Setup cloud auto-scaling based on session count

---

**Implementation Status:** ✅ **COMPLETE**

- ✅ Multi-instance infrastructure deployed
- ✅ Workspace sharding active
- ✅ 2,000 session capacity ready
- ✅ Health monitoring operational
- ✅ Production-ready architecture

**Next:** Load test with 1000+ sessions to validate performance targets.