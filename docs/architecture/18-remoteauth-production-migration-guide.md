# RemoteAuth Migration Production Guide

**Version:** 1.0  
**Created:** November 20, 2025  
**Status:** Ready for staging testing  
**Follows:** docs/architecture/17-database-compatibility-audit-and-implementation-guide.md

---

## 🎯 Overview

This guide provides step-by-step procedures for safely migrating from LocalAuth (file-based) to RemoteAuth (Redis-based) in production environment.

**Key Goal:** Enable PM2 cluster mode (8 workers) without file locking conflicts.

**Migration Strategy:** Gradual, session-by-session migration with rollback capability at each step.

---

## 📋 Pre-Migration Checklist

### 1. Infrastructure Requirements

**Redis Server:**
- ✅ Redis 6.0+ installed
- ✅ Redis running on 127.0.0.1:6379 (or configured host)
- ✅ Minimum 512MB RAM allocated for Redis
- ✅ Persistence enabled (`appendonly yes` in redis.conf)
- ✅ Password configured (recommended for production)

**WhatsApp Service:**
- ✅ Node.js 18.0.0+ running
- ✅ Package `ioredis` version 5.3.2 installed
- ✅ Disk space: Minimum 2GB free
- ✅ Backup storage configured

### 2. Backup Requirements

**CRITICAL: Take backups BEFORE migration:**

```bash
# Backup current sessions
cd /Applications/MAMP/htdocs/blazz/whatsapp-service
tar -czf backups/sessions-$(date +%Y%m%d-%H%M%S).tar.gz sessions/

# Backup current .env
cp .env backups/.env-$(date +%Y%m%d-%H%M%S)

# Backup Redis (if already has data)
redis-cli SAVE
cp /var/lib/redis/dump.rdb backups/redis-dump-$(date +%Y%m%d-%H%M%S).rdb
```

**Verify backups:**
```bash
ls -lh backups/
# Should see: sessions-*.tar.gz, .env-*, redis-dump-*.rdb
```

### 3. Health Check Endpoints

**Test health endpoints are accessible:**

```bash
# Overall health
curl http://localhost:3001/health

# Redis health
curl http://localhost:3001/health/redis

# Session status
curl http://localhost:3001/health/sessions

# Migration status
curl http://localhost:3001/health/migration
```

Expected: All return 200 OK (or 503 if Redis not connected yet).

---

## 🚀 Phase 1: Staging Environment Testing

**Duration:** 1-2 days  
**Risk Level:** Low (isolated environment)

### Step 1: Enable RemoteAuth on Staging

1. **Update `.env` on staging:**

```bash
# Edit .env
nano .env

# Change:
AUTH_STRATEGY=remoteauth

# Verify Redis config:
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=your_secure_password  # If configured
REDIS_DB=0
REDIS_SESSION_PREFIX=whatsapp:session:
REDIS_SESSION_TTL=604800  # 7 days
REDIS_ENABLE_BACKUP=true
REDIS_BACKUP_PATH=./session-backups
```

2. **Restart WhatsApp service:**

```bash
cd /Applications/MAMP/htdocs/blazz/whatsapp-service
pm2 restart whatsapp-service

# Monitor logs
pm2 logs whatsapp-service --lines 50
```

3. **Verify RemoteAuth initialization:**

Expected log output:
```
✅ RemoteAuth initialized successfully
🔄 Redis connected: 127.0.0.1:6379
📊 Redis health: OK
```

### Step 2: Create Test Session

1. **Create new session via API:**

```bash
curl -X POST http://localhost:3001/api/sessions/create \
  -H "Content-Type: application/json" \
  -d '{
    "sessionId": "test_remoteauth_001",
    "workspaceId": 1
  }'
```

2. **Scan QR code and authenticate.**

3. **Verify session stored in Redis:**

```bash
# Check health endpoint
curl http://localhost:3001/health/sessions

# Or check Redis directly
redis-cli
> KEYS whatsapp:session:*
> GET whatsapp:session:test_remoteauth_001
```

Expected: Session data in Redis, JSON format.

### Step 3: Test PM2 Cluster Mode

1. **Update PM2 config to cluster mode:**

```bash
nano ecosystem.config.js

# Change:
instances: 8,  # Enable cluster
exec_mode: "cluster"
```

2. **Restart with cluster mode:**

```bash
pm2 delete whatsapp-service
pm2 start ecosystem.config.js
pm2 status
```

Expected: 8 instances running.

3. **Test session access across workers:**

```bash
# Send 10 messages (should round-robin to different workers)
for i in {1..10}; do
  curl -X POST http://localhost:3001/api/messages/send \
    -H "Content-Type: application/json" \
    -d '{
      "sessionId": "test_remoteauth_001",
      "number": "62812345678",
      "message": "Test cluster message '$i'"
    }'
  sleep 1
done
```

Expected: All messages sent successfully, no file locking errors.

### Step 4: Test Session Persistence

1. **Restart PM2:**

```bash
pm2 restart whatsapp-service
```

2. **Verify session restored:**

```bash
curl http://localhost:3001/health/sessions

# Check specific session
curl http://localhost:3001/health/migration/test_remoteauth_001
```

Expected: Session exists in Redis, authenticated status preserved.

### Step 5: Test Fallback to LocalAuth

1. **Stop Redis:**

```bash
redis-cli shutdown
```

2. **Restart WhatsApp service:**

```bash
pm2 restart whatsapp-service
pm2 logs --lines 30
```

Expected log:
```
❌ RemoteAuth initialization failed
⚠️ Falling back to LocalAuth
✅ Using LocalAuth for session management
```

3. **Create session with LocalAuth:**

```bash
curl -X POST http://localhost:3001/api/sessions/create \
  -H "Content-Type: application/json" \
  -d '{
    "sessionId": "test_localauth_fallback",
    "workspaceId": 1
  }'
```

Expected: Session created in `./sessions/` directory.

4. **Restart Redis and verify RemoteAuth works again:**

```bash
redis-server /etc/redis/redis.conf --daemonize yes
pm2 restart whatsapp-service
```

---

## 📦 Phase 2: Production Migration Preparation

**Duration:** 1 day  
**Risk Level:** Low (preparation only, no changes)

### Step 1: Inventory Current Sessions

1. **List all active sessions:**

```bash
cd /Applications/MAMP/htdocs/blazz/whatsapp-service
node src/utils/SessionMigration.js migrate-all
```

Expected output:
```
Found 42 sessions to migrate
  - session_workspace1_001
  - session_workspace1_002
  ...
```

2. **Document session details:**

```bash
# Create migration plan
echo "Session Migration Plan - $(date)" > migration-plan.txt
echo "Total sessions: 42" >> migration-plan.txt
echo "" >> migration-plan.txt

# List sessions with workspace IDs
find sessions/ -type d -name 'session*' | while read session; do
  echo "- $session" >> migration-plan.txt
done
```

### Step 2: Identify Critical Sessions

**Prioritize sessions by usage:**

1. High-traffic sessions → Migrate LAST (most risk)
2. Low-traffic sessions → Migrate FIRST (testing)
3. Test/demo sessions → Migrate FIRST (safe to fail)

**Document priority:**
```
Priority 1 (Test first):
- session_demo_001
- session_test_workspace

Priority 2 (Low traffic):
- session_workspace3_005
- session_workspace7_002

Priority 3 (High traffic):
- session_workspace1_main
- session_workspace2_production
```

### Step 3: Schedule Migration Windows

**Recommended schedule:**

```
Day 1: 1 test session (Priority 1)
Day 2: 2-3 low-traffic sessions (Priority 2)
Day 3: Monitor for 24 hours
Day 4: 5-10 low-traffic sessions
Day 5: Monitor for 48 hours
Day 6: High-traffic sessions (one at a time)
Day 7: Complete remaining sessions
```

**Maintenance windows:**
- Best time: 2-4 AM (lowest traffic)
- Backup before each session migration
- Rollback window: 15 minutes per session

---

## 🔄 Phase 3: Production Migration Execution

**Duration:** 7-10 days  
**Risk Level:** Medium (gradual reduces risk)

### Day 1: Migrate First Test Session

**Session:** `session_demo_001` (Priority 1)

1. **Pre-migration check:**

```bash
# Verify session exists in LocalAuth
curl http://localhost:3001/health/migration/session_demo_001

# Expected:
{
  "sessionId": "session_demo_001",
  "localAuth": { "exists": true, "size": 15234 },
  "remoteAuth": { "exists": false },
  "match": false
}
```

2. **Export session (backup):**

```bash
cd /Applications/MAMP/htdocs/blazz/whatsapp-service
node src/utils/SessionMigration.js export session_demo_001
```

Expected:
```
✅ Session exported to: ./migration-backups/session_demo_001-1732123456789.json
```

3. **Import to Redis:**

```bash
node src/utils/SessionMigration.js import session_demo_001
```

Expected:
```
✅ Session imported to Redis: session_demo_001 (15234 bytes)
```

4. **Validate migration:**

```bash
node src/utils/SessionMigration.js validate session_demo_001
```

Expected:
```json
{
  "sessionId": "session_demo_001",
  "localAuth": { "exists": true, "size": 15234 },
  "remoteAuth": { "exists": true, "size": 15234 },
  "match": true
}
```

5. **Test session functionality:**

```bash
# Send test message
curl -X POST http://localhost:3001/api/messages/send \
  -H "Content-Type: application/json" \
  -d '{
    "sessionId": "session_demo_001",
    "number": "62812345678",
    "message": "Test post-migration"
  }'
```

Expected: Message sent successfully.

6. **Monitor for 24 hours:**

- Check logs every 2 hours
- Monitor message delivery rate
- Check Redis memory usage

```bash
# Monitor logs
pm2 logs whatsapp-service --lines 100

# Check Redis memory
redis-cli INFO memory
```

7. **Post-migration cleanup (after 24h stable):**

```bash
# Archive LocalAuth session (DO NOT DELETE YET)
mkdir -p sessions-archived
mv sessions/workspace1/session_demo_001 sessions-archived/
```

### Days 2-4: Low-Traffic Sessions

**Repeat Day 1 steps for Priority 2 sessions (2-3 per day).**

**Monitor metrics:**
- ✅ Message delivery rate
- ✅ Session reconnect frequency
- ✅ Redis memory usage
- ✅ PM2 worker CPU usage
- ✅ Error logs

**Stop migration if:**
- ❌ Message delivery drops >5%
- ❌ Sessions disconnect frequently
- ❌ Redis memory usage >80%
- ❌ Consistent errors in logs

### Days 5-7: High-Traffic Sessions

**Migrate Priority 3 sessions ONE AT A TIME.**

**Extended monitoring:**
- Monitor for 48 hours per session
- Check during peak traffic hours
- Compare metrics pre/post migration

**Metrics to track:**

| Metric | Pre-Migration | Post-Migration | Threshold |
|--------|---------------|----------------|-----------|
| Message delivery rate | 99.2% | 99.1% | >99% |
| Avg response time | 1.2s | 1.3s | <2s |
| Session reconnects/day | 0-1 | 0-1 | <3 |
| Redis memory | N/A | 125MB | <500MB |

---

## 🚨 Rollback Procedures

### Scenario 1: Single Session Fails

**Symptoms:**
- Session won't authenticate
- Messages fail to send
- Session disconnects repeatedly

**Rollback:**

```bash
# Stop using RemoteAuth for this session
cd /Applications/MAMP/htdocs/blazz/whatsapp-service

# 1. Delete session from active sessions
curl -X DELETE http://localhost:3001/api/sessions/session_failed_001

# 2. Restore from backup
cp -r sessions-archived/session_failed_001 sessions/workspace1/

# 3. Recreate session (will use LocalAuth)
curl -X POST http://localhost:3001/api/sessions/create \
  -H "Content-Type: application/json" \
  -d '{
    "sessionId": "session_failed_001",
    "workspaceId": 1
  }'

# 4. Re-authenticate if needed (scan QR)
```

### Scenario 2: Redis Complete Failure

**Symptoms:**
- Redis server crashes
- All sessions disconnected
- Service unavailable

**Rollback:**

```bash
# 1. Stop WhatsApp service
pm2 stop whatsapp-service

# 2. Switch to LocalAuth
nano .env
# Change: AUTH_STRATEGY=localauth

# 3. Restore all sessions from backup
cd /Applications/MAMP/htdocs/blazz/whatsapp-service
rm -rf sessions/
tar -xzf backups/sessions-20251120-020000.tar.gz

# 4. Restart service
pm2 start whatsapp-service

# 5. Verify all sessions restored
curl http://localhost:3001/health/sessions
```

### Scenario 3: Partial Migration Issues

**Symptoms:**
- Some sessions work, some don't
- Inconsistent behavior
- Mixed errors in logs

**Rollback:**

```bash
# 1. Identify failed sessions
curl http://localhost:3001/health/migration

# 2. Delete failed sessions from Redis
redis-cli
> DEL whatsapp:session:session_failed_001
> DEL whatsapp:session:session_failed_002

# 3. Restore from LocalAuth backups (per session)
cp -r sessions-archived/session_failed_001 sessions/workspace1/

# 4. Continue with working sessions
# Leave AUTH_STRATEGY=remoteauth for successful migrations
```

---

## 📊 Post-Migration Validation

### Week 1: Intensive Monitoring

**Daily checks:**

```bash
# 1. Check all health endpoints
curl http://localhost:3001/health
curl http://localhost:3001/health/redis
curl http://localhost:3001/health/sessions
curl http://localhost:3001/health/migration

# 2. Check Redis memory usage
redis-cli INFO memory | grep used_memory_human

# 3. Check PM2 cluster health
pm2 status

# 4. Check logs for errors
pm2 logs whatsapp-service --lines 100 | grep -i error
```

**Alert thresholds:**

| Metric | Warning | Critical | Action |
|--------|---------|----------|--------|
| Redis memory | >300MB | >500MB | Scale Redis |
| Session reconnects | >5/day | >10/day | Investigate |
| Message failures | >2% | >5% | Rollback |
| Worker crashes | >1/day | >3/day | Rollback |

### Week 2-4: Standard Monitoring

**Weekly checks:**
- Redis backup integrity
- Session count in Redis
- Disk space usage
- Log file rotation

**Monthly maintenance:**
- Review Redis memory optimization
- Clean up old backups (>30 days)
- Update Redis persistence config if needed

---

## 🔧 Troubleshooting

### Issue 1: Session Not Found in Redis

**Symptoms:**
```
Error: Session not found in Redis
```

**Solution:**
```bash
# Re-import from LocalAuth
node src/utils/SessionMigration.js import session_missing_001

# Or restore from backup
node src/utils/SessionMigration.js import session_missing_001 \
  ./migration-backups/session_missing_001-*.json
```

### Issue 2: Redis Connection Timeout

**Symptoms:**
```
Error: Redis connection timeout
```

**Solution:**
```bash
# Check Redis is running
redis-cli ping
# Expected: PONG

# Check connection config
redis-cli CONFIG GET timeout

# Increase timeout in .env
REDIS_SESSION_TTL=604800  # 7 days
```

### Issue 3: Session Size Mismatch

**Symptoms:**
```
Warning: size_mismatch - local: 15234, redis: 15100
```

**Solution:**
```bash
# Re-import session (overwrites Redis)
node src/utils/SessionMigration.js import session_mismatch_001

# Validate
node src/utils/SessionMigration.js validate session_mismatch_001
```

### Issue 4: PM2 Cluster File Locking

**Symptoms:**
```
Error: EBUSY: resource busy or locked
```

**Solution:**
```
This means session is still using LocalAuth.

Verify:
1. AUTH_STRATEGY=remoteauth in .env
2. Session imported to Redis
3. PM2 restarted after .env change

pm2 restart whatsapp-service
```

---

## ✅ Success Criteria

Migration is considered successful when:

1. **All sessions migrated:** `curl http://localhost:3001/health/migration` shows 100%
2. **Redis stable:** Memory usage <500MB, uptime >7 days
3. **PM2 cluster working:** 8 workers active, no file locking errors
4. **Message delivery:** Rate >99%, consistent with pre-migration
5. **No rollbacks:** Zero session rollbacks in last 7 days
6. **Monitoring green:** All health endpoints return 200 OK

---

## 📝 Final Checklist

**After complete migration:**

- [ ] All sessions in Redis verified
- [ ] LocalAuth backups archived (not deleted)
- [ ] Redis persistence configured
- [ ] Health monitoring automated
- [ ] Alert thresholds configured
- [ ] Documentation updated
- [ ] Team trained on new system
- [ ] Rollback procedures tested
- [ ] PM2 cluster mode stable (7+ days)
- [ ] Performance metrics documented

**Archive old sessions only after 30 days stable.**

---

## 📞 Support

**Issues during migration:**
- Check logs: `pm2 logs whatsapp-service`
- Check health: `curl http://localhost:3001/health`
- Rollback if critical: Follow "Rollback Procedures" above

**Contact:**
- DevOps team for Redis issues
- Backend team for session issues
- Refer to: docs/architecture/17-database-compatibility-audit-and-implementation-guide.md

---

**Document Version:** 1.0  
**Last Updated:** November 20, 2025  
**Next Review:** After staging testing complete
