# 🚀 Quick Start Guide: Cloudflare R2 untuk WhatsApp Sessions

## Step 1: Buat Cloudflare Account & R2 Bucket

### 1.1 Daftar/Login Cloudflare
```
https://dash.cloudflare.com/sign-up/r2
```

### 1.2 Buat R2 Bucket
1. Go to **R2** di sidebar
2. Click **Create bucket**
3. Bucket name: `blazz-wa-sessions`
4. Location: **Automatic** (atau pilih Asia Pacific)
5. Click **Create bucket**

### 1.3 Generate API Token
1. Go to **R2** → **Manage R2 API Tokens**
2. Click **Create API token**
3. Settings:
   - Token name: `blazz-whatsapp`
   - Permissions: **Object Read & Write**
   - Specify bucket: `blazz-wa-sessions`
   - TTL: No expiration
4. Click **Create API Token**
5. **IMPORTANT**: Salin credentials sekarang (tidak akan ditampilkan lagi!)

```
Account ID: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Access Key ID: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Secret Access Key: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

---

## Step 2: Setup Environment

### 2.1 Tambah ke .env (whatsapp-service)

```env
# Cloudflare R2
R2_ACCOUNT_ID=your_account_id_here
R2_ACCESS_KEY_ID=your_access_key_here
R2_SECRET_ACCESS_KEY=your_secret_key_here
R2_BUCKET_NAME=blazz-wa-sessions

# Session Backup
SESSION_BACKUP_ENABLED=true
SESSION_BACKUP_INTERVAL_MS=600000

# Debug (optional)
R2_DEBUG=false
```

### 2.2 Install Dependencies

```bash
cd whatsapp-service
npm install @aws-sdk/client-s3 archiver unzipper
```

---

## Step 3: Copy Implementation Files

Copy file-file berikut ke whatsapp-service:

```bash
# Dari folder docs/architecture/cloudflare-r2/
cp R2Store.js ../../../whatsapp-service/src/stores/
cp SessionManagerR2.js ../../../whatsapp-service/src/managers/
```

---

## Step 4: Test Connection

### 4.1 Buat test script

```javascript
// test-r2.js
require('dotenv').config();
const { R2Store } = require('./src/stores/R2Store');

async function test() {
    const store = new R2Store({
        accountId: process.env.R2_ACCOUNT_ID,
        accessKeyId: process.env.R2_ACCESS_KEY_ID,
        secretAccessKey: process.env.R2_SECRET_ACCESS_KEY,
        bucketName: process.env.R2_BUCKET_NAME,
        debug: true
    });
    
    // Health check
    const health = await store.healthCheck();
    console.log('Health:', health);
    
    // List sessions
    const sessions = await store.listSessions();
    console.log('Sessions:', sessions);
}

test().catch(console.error);
```

### 4.2 Run test

```bash
node test-r2.js
```

Expected output:
```
[R2Store] R2Store initialized { bucket: 'blazz-wa-sessions' }
[R2Store] Listed sessions { count: 0 }
Health: { status: 'healthy', bucket: 'blazz-wa-sessions', connected: true }
Sessions: []
```

---

## Step 5: Update WhatsApp Service

### 5.1 Update server.js untuk menggunakan SessionManagerR2

```javascript
// server.js
const { SessionManagerR2 } = require('./src/managers/SessionManagerR2');

const sessionManager = new SessionManagerR2({
    redisUrl: process.env.REDIS_URL,
    r2AccountId: process.env.R2_ACCOUNT_ID,
    r2AccessKeyId: process.env.R2_ACCESS_KEY_ID,
    r2SecretAccessKey: process.env.R2_SECRET_ACCESS_KEY,
    r2BucketName: process.env.R2_BUCKET_NAME,
    backupEnabled: true,
    backupIntervalMs: 600000
});

// ... rest of your server code
```

---

## Step 6: Docker Configuration

### 6.1 Update docker-compose.yaml

```yaml
services:
  whatsapp:
    environment:
      - R2_ACCOUNT_ID=${R2_ACCOUNT_ID}
      - R2_ACCESS_KEY_ID=${R2_ACCESS_KEY_ID}
      - R2_SECRET_ACCESS_KEY=${R2_SECRET_ACCESS_KEY}
      - R2_BUCKET_NAME=blazz-wa-sessions
      - SESSION_BACKUP_ENABLED=true
```

### 6.2 Update .env di root project

```env
R2_ACCOUNT_ID=your_account_id
R2_ACCESS_KEY_ID=your_access_key
R2_SECRET_ACCESS_KEY=your_secret_key
```

---

## ✅ Verification Checklist

- [ ] Cloudflare account created
- [ ] R2 bucket `blazz-wa-sessions` created
- [ ] API token generated and saved
- [ ] Environment variables added
- [ ] npm dependencies installed
- [ ] R2Store.js copied to whatsapp-service
- [ ] SessionManagerR2.js copied to whatsapp-service
- [ ] Test script passes
- [ ] Docker compose updated

---

## 📊 Expected Storage Usage

| Users | R2 Storage | Monthly Cost |
|-------|------------|--------------|
| 1-3,333 | ≤10GB | **$0** (free tier) |
| 5,000 | 15GB | ~$0.08 |
| 10,000 | 30GB | ~$0.30 |

---

## 🆘 Troubleshooting

### Error: `AccessDenied`
- Check API token permissions
- Verify bucket name matches

### Error: `NoSuchBucket`
- Create bucket in Cloudflare dashboard
- Check bucket name spelling

### Error: `NetworkingError`
- Check internet connection
- Verify R2_ACCOUNT_ID is correct

### Sessions not restoring after restart
- Check R2_DEBUG=true for detailed logs
- Verify backup files exist in R2 bucket
- Check Redis is running

---

*Last updated: December 2025*
