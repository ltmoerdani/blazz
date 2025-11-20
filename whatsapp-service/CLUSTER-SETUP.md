# PM2 Cluster Mode Setup Guide

## Quick Start

### 1. Install Redis
```bash
# macOS
brew install redis
brew services start redis

# Ubuntu/Debian
sudo apt install redis-server
sudo systemctl start redis
sudo systemctl enable redis

# Verify
redis-cli ping  # Should return PONG
```

### 2. Setup PM2 Cluster
```bash
cd /Applications/MAMP/htdocs/blazz/whatsapp-service
./setup-cluster.sh
```

### 3. Verify Cluster
```bash
pm2 status
pm2 monit
```

## Configuration

### Adjust Worker Count
Edit `ecosystem.config.js`:
```javascript
{
  "instances": 10,  // Change from 5 to 10 workers
  // Max capacity: 10 workers x 50 sessions = 500 sessions
}
```

Restart:
```bash
pm2 restart whatsapp-cluster
```

### Environment Variables
Edit `whatsapp-service/.env`:
```env
MAX_SESSIONS_PER_WORKER=50
REDIS_URL=redis://localhost:6379
```

## Monitoring

### View Status
```bash
pm2 status
```

### Real-time Monitoring
```bash
pm2 monit
```

### View Logs
```bash
pm2 logs whatsapp-cluster
pm2 logs whatsapp-cluster --lines 100
```

### Cluster Health
```bash
curl http://localhost:3001/health | jq
```

## Common Commands

### Restart Cluster
```bash
pm2 restart whatsapp-cluster
```

### Stop Cluster
```bash
pm2 stop whatsapp-cluster
```

### Delete Cluster
```bash
pm2 delete whatsapp-cluster
```

### Scale Up/Down
```bash
# Scale to 10 workers
pm2 scale whatsapp-cluster 10

# Scale down to 3 workers
pm2 scale whatsapp-cluster 3
```

## Capacity Planning

| Workers | Max Sessions | RAM Needed | CPU Cores |
|---------|--------------|------------|-----------|
| 5       | 250          | 50GB       | 20        |
| 10      | 500          | 100GB      | 40        |
| 20      | 1000         | 200GB      | 80        |

## Troubleshooting

### Workers not starting
```bash
# Check logs
pm2 logs whatsapp-cluster --err

# Check Redis
redis-cli ping

# Restart
pm2 restart whatsapp-cluster
```

### Memory issues
```bash
# PM2 auto-restarts at 10GB limit
# Check memory usage
pm2 monit

# Manual restart
pm2 restart whatsapp-cluster
```

### Redis connection issues
```bash
# Check Redis status
redis-cli ping

# Check Redis memory
redis-cli info memory

# Clear Redis (if needed)
redis-cli FLUSHALL
```

## Production Deployment

### 1. Setup PM2 Auto-Startup
```bash
pm2 startup
pm2 save
```

### 2. Enable Log Rotation
```bash
pm2 install pm2-logrotate
pm2 set pm2-logrotate:max_size 100M
pm2 set pm2-logrotate:retain 7
```

### 3. Monitoring Dashboard
```bash
pm2 web
# Access at http://localhost:9615
```

## Benefits Over Single Instance

✅ **Horizontal Scaling** - Add workers on demand  
✅ **Zero Downtime** - PM2 handles graceful restarts  
✅ **Auto-Recovery** - Workers restart on crash  
✅ **Load Balancing** - Automatic distribution  
✅ **Memory Isolation** - Worker crashes don't affect others  
✅ **Simple Monitoring** - Built-in PM2 dashboard  

## Migration from Single Instance

1. Stop current service: `./stop-dev.sh`
2. Install Redis: `brew install redis && brew services start redis`
3. Run setup: `./setup-cluster.sh`
4. Verify: `pm2 status && curl http://localhost:3001/health`

**That's it!** Your WhatsApp service now runs in cluster mode with auto-recovery! 🎉
