# 📦 Migration Guide: LocalAuth ke R2 Remote Storage

## Overview

Panduan ini menjelaskan cara migrasi session WhatsApp dari LocalAuth (file-based) ke Cloudflare R2 (cloud-based).

---

## 📋 Pre-Migration Checklist

- [ ] Backup semua existing sessions
- [ ] R2 bucket sudah dibuat dan configured
- [ ] API credentials sudah tersedia
- [ ] Dependencies sudah di-install
- [ ] Test connection ke R2 berhasil

---

## 🔄 Migration Strategies

### Strategy 1: Gradual Migration (Recommended)
Migrasi bertahap - session baru ke R2, existing tetap di local sampai re-auth.

**Pros:**
- Zero downtime
- Safe rollback
- No mass re-authentication

**Cons:**
- Hybrid state sementara waktu
- Perlu maintenance 2 storage system

### Strategy 2: Full Migration
Export semua session ke R2, switch semua client sekaligus.

**Pros:**
- Clean cut
- Single storage system

**Cons:**
- Potential downtime
- Risk lebih tinggi
- Perlu maintenance window

### Strategy 3: Fresh Start
Hapus semua session, require re-scan QR.

**Pros:**
- Cleanest approach
- Remove corrupted sessions

**Cons:**
- User impact tinggi
- Semua user harus re-scan

---

## 📝 Step-by-Step Migration

### Phase 1: Preparation

#### 1.1 Backup Existing Sessions

```bash
# Create backup folder
mkdir -p /backups/whatsapp-sessions-$(date +%Y%m%d)

# Copy sessions
cp -r whatsapp-service/sessions/* /backups/whatsapp-sessions-$(date +%Y%m%d)/

# Verify backup
ls -la /backups/whatsapp-sessions-*/
```

#### 1.2 Document Current State

```bash
# Count active sessions
ls -d whatsapp-service/sessions/session-* 2>/dev/null | wc -l

# Total size
du -sh whatsapp-service/sessions/
```

Catat:
- Jumlah session: ________
- Total size: ________
- Date: ________

#### 1.3 Install Dependencies

```bash
cd whatsapp-service
npm install @aws-sdk/client-s3 archiver unzipper
```

### Phase 2: Setup R2

#### 2.1 Add R2 Files

```bash
# Copy implementation files
cp docs/architecture/cloudflare-r2/R2Store.js whatsapp-service/src/stores/
cp docs/architecture/cloudflare-r2/SessionManagerR2.js whatsapp-service/src/managers/
```

#### 2.2 Configure Environment

```bash
# Edit .env
nano whatsapp-service/.env
```

Add:
```env
R2_ACCOUNT_ID=your_account_id
R2_ACCESS_KEY_ID=your_access_key
R2_SECRET_ACCESS_KEY=your_secret_key
R2_BUCKET_NAME=blazz-wa-sessions
SESSION_BACKUP_ENABLED=true
```

#### 2.3 Test R2 Connection

```javascript
// test-r2-connection.js
const { R2Store } = require('./src/stores/R2Store');
require('dotenv').config();

async function test() {
    const store = new R2Store({
        accountId: process.env.R2_ACCOUNT_ID,
        accessKeyId: process.env.R2_ACCESS_KEY_ID,
        secretAccessKey: process.env.R2_SECRET_ACCESS_KEY,
        bucketName: process.env.R2_BUCKET_NAME
    });
    
    const health = await store.healthCheck();
    console.log('R2 Health:', health);
    process.exit(health.status === 'healthy' ? 0 : 1);
}

test();
```

```bash
node test-r2-connection.js
```

### Phase 3: Migration Script

#### 3.1 Upload Existing Sessions to R2

```javascript
// migrate-sessions.js
const fs = require('fs');
const path = require('path');
const archiver = require('archiver');
const { R2Store } = require('./src/stores/R2Store');
require('dotenv').config();

const SESSIONS_DIR = './sessions';

async function migrateSession(store, sessionDir) {
    const sessionId = path.basename(sessionDir).replace('session-', '');
    
    console.log(`Migrating session: ${sessionId}`);
    
    // Create temporary zip
    const tempZip = `/tmp/${sessionId}.zip`;
    
    await new Promise((resolve, reject) => {
        const output = fs.createWriteStream(tempZip);
        const archive = archiver('zip', { zlib: { level: 9 } });
        
        output.on('close', resolve);
        archive.on('error', reject);
        
        archive.pipe(output);
        archive.directory(sessionDir, false);
        archive.finalize();
    });
    
    // Read zip and upload
    const zipData = fs.readFileSync(tempZip);
    
    await store.s3.send(new (require('@aws-sdk/client-s3').PutObjectCommand)({
        Bucket: store.bucketName,
        Key: `sessions/${sessionId}/${sessionId}.zip`,
        Body: zipData,
        ContentType: 'application/zip'
    }));
    
    // Cleanup temp
    fs.unlinkSync(tempZip);
    
    console.log(`✓ Session ${sessionId} migrated`);
}

async function migrate() {
    const store = new R2Store({
        accountId: process.env.R2_ACCOUNT_ID,
        accessKeyId: process.env.R2_ACCESS_KEY_ID,
        secretAccessKey: process.env.R2_SECRET_ACCESS_KEY,
        bucketName: process.env.R2_BUCKET_NAME
    });
    
    // Get all session directories
    const sessionDirs = fs.readdirSync(SESSIONS_DIR)
        .filter(f => f.startsWith('session-'))
        .map(f => path.join(SESSIONS_DIR, f))
        .filter(f => fs.statSync(f).isDirectory());
    
    console.log(`Found ${sessionDirs.length} sessions to migrate\n`);
    
    let success = 0;
    let failed = 0;
    
    for (const dir of sessionDirs) {
        try {
            await migrateSession(store, dir);
            success++;
        } catch (error) {
            console.error(`✗ Failed to migrate ${path.basename(dir)}: ${error.message}`);
            failed++;
        }
    }
    
    console.log('\n=== Migration Summary ===');
    console.log(`Total: ${sessionDirs.length}`);
    console.log(`Success: ${success}`);
    console.log(`Failed: ${failed}`);
}

migrate().catch(console.error);
```

#### 3.2 Run Migration (Dry Run)

```bash
# Test dengan 1 session dulu
node migrate-sessions.js --limit 1
```

#### 3.3 Run Full Migration

```bash
node migrate-sessions.js
```

### Phase 4: Switch to R2

#### 4.1 Update Application Code

Ganti SessionManager dengan SessionManagerR2:

```javascript
// Before
const { SessionManager } = require('./src/managers/SessionManager');
const sessionManager = new SessionManager(/* ... */);

// After
const { SessionManagerR2 } = require('./src/managers/SessionManagerR2');
const sessionManager = new SessionManagerR2({
    redisUrl: process.env.REDIS_URL,
    r2AccountId: process.env.R2_ACCOUNT_ID,
    r2AccessKeyId: process.env.R2_ACCESS_KEY_ID,
    r2SecretAccessKey: process.env.R2_SECRET_ACCESS_KEY,
    r2BucketName: process.env.R2_BUCKET_NAME
});
```

#### 4.2 Deploy Changes

```bash
# Stop service
pm2 stop whatsapp-service

# Deploy update
git pull
npm install

# Start with new config
pm2 start whatsapp-service
```

#### 4.3 Verify Sessions Work

```bash
# Check logs
pm2 logs whatsapp-service

# Test API
curl http://localhost:3000/api/sessions
```

### Phase 5: Cleanup

#### 5.1 Verify R2 Data

```javascript
// verify-r2-sessions.js
const { R2Store } = require('./src/stores/R2Store');
require('dotenv').config();

async function verify() {
    const store = new R2Store({
        accountId: process.env.R2_ACCOUNT_ID,
        accessKeyId: process.env.R2_ACCESS_KEY_ID,
        secretAccessKey: process.env.R2_SECRET_ACCESS_KEY,
        bucketName: process.env.R2_BUCKET_NAME
    });
    
    const sessions = await store.listSessions();
    console.log(`R2 Sessions: ${sessions.length}`);
    
    for (const id of sessions) {
        const exists = await store.sessionExists(id);
        console.log(`  ${id}: ${exists ? '✓' : '✗'}`);
    }
}

verify();
```

#### 5.2 Archive Local Sessions

```bash
# Archive old sessions (keep 30 days)
tar -czf /backups/sessions-local-$(date +%Y%m%d).tar.gz whatsapp-service/sessions/
```

#### 5.3 Remove Local Sessions (After Confirmation)

```bash
# HANYA setelah yakin R2 berfungsi dengan baik!
# rm -rf whatsapp-service/sessions/session-*
```

---

## 🔙 Rollback Plan

Jika ada masalah, rollback ke LocalAuth:

### Step 1: Revert Code

```bash
git checkout HEAD~1 -- whatsapp-service/src/managers/SessionManager.js
```

### Step 2: Restore Sessions

```bash
# Restore from backup
cp -r /backups/whatsapp-sessions-YYYYMMDD/* whatsapp-service/sessions/
```

### Step 3: Restart Service

```bash
pm2 restart whatsapp-service
```

---

## 📊 Post-Migration Verification

### Checklist

- [ ] All sessions restored successfully
- [ ] Users can send/receive messages
- [ ] No QR re-scan required for existing users
- [ ] R2 dashboard shows session files
- [ ] Backup schedule working
- [ ] Storage size reduced on server

### Monitoring Commands

```bash
# Check R2 usage via Cloudflare dashboard
# Or use API

# Server disk usage (should decrease)
df -h

# Sessions folder size (should be minimal)
du -sh whatsapp-service/sessions/
```

---

## ⚠️ Common Migration Issues

### Issue: Session not found after migration

**Cause:** Session ID mismatch or zip format issue

**Solution:**
```bash
# Check R2 bucket untuk session
aws s3 ls s3://blazz-wa-sessions/sessions/ --endpoint-url https://${R2_ACCOUNT_ID}.r2.cloudflarestorage.com

# Verify zip structure
unzip -l session-xxx.zip
```

### Issue: User gets QR code after migration

**Cause:** Session tidak ter-migrate dengan benar

**Solution:**
```bash
# Re-run migration untuk specific session
node migrate-sessions.js --session-id=xxx
```

### Issue: High R2 costs

**Cause:** Too frequent backups or large session size

**Solution:**
```javascript
// Adjust backup interval
const manager = new SessionManagerR2({
    backupIntervalMs: 1800000 // 30 menit
});
```

---

## 📅 Migration Timeline Template

| Day | Activity | Status |
|-----|----------|--------|
| D-7 | Backup all sessions | |
| D-5 | Setup R2 & test connection | |
| D-3 | Run migration script (dry run) | |
| D-1 | Full migration run | |
| D-0 | Switch to R2 (maintenance window) | |
| D+1 | Monitor & verify | |
| D+7 | Archive local sessions | |
| D+30 | Delete local sessions | |

---

*Last updated: December 2025*
