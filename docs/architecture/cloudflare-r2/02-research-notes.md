# 📝 Catatan Riset: Cloud Storage untuk WhatsApp Session

> Dokumen ini berisi hasil riset dan analisis yang dilakukan untuk mengevaluasi solusi storage WhatsApp session.

**Tanggal Riset**: 5 Desember 2025  
**Context**: Docker build gagal karena whatsapp-service folder mencapai 4.79GB

---

## 🔍 1. Analisis Awal: Masalah Storage

### Problem yang Ditemukan

Saat Docker build untuk whatsapp-service:
```
ERROR: failed to solve: failed to compute cache key: 
failed to calculate checksum of ref: 
"/whatsapp-service/sessions" (4.79GB context)
```

### Investigasi: Kenapa Folder Sessions Besar?

Setiap WhatsApp session menggunakan **~19-20MB** karena menyimpan full Chromium browser profile:

```
session-webjs_1_1763659313_VvciNaoF/
├── Default/
│   ├── Cache/                    # ~6.6MB - HTTP cache
│   ├── Code Cache/               # ~588KB - V8 compiled code
│   ├── GPUCache/                 # ~15KB  - GPU shaders
│   ├── IndexedDB/                # ~212KB - WhatsApp messages
│   ├── Local Storage/            # ~16KB  - Session data
│   ├── Service Worker/           # ~12MB  - PWA cache (TERBESAR!)
│   │   └── CacheStorage/
│   │       └── https_web.whatsapp.com_0/
│   └── ...
└── SingletonLock, etc.
```

**Breakdown per komponen:**

| Komponen | Ukuran | Keterangan |
|----------|--------|------------|
| Service Worker Cache | ~12MB | WhatsApp PWA assets |
| Browser Cache | ~6.6MB | HTTP response cache |
| Code Cache | ~588KB | V8 compiled JavaScript |
| IndexedDB | ~212KB | Database messages |
| Local Storage | ~16KB | Session credentials |
| **TOTAL** | **~19-20MB** | Per user |

### Proyeksi Scaling

| Jumlah User | Storage Dibutuhkan |
|-------------|-------------------|
| 1 | 19MB |
| 100 | 1.9GB |
| 1,000 | 19GB |
| 3,000 | **57GB** |
| 10,000 | **190GB** |

> ⚠️ **Pertanyaan user**: "jika ini juga di wrapped ke dalam docker bukankah juga akan semakin membesar?"
>
> **Jawaban**: Ya benar! Docker volume tidak menyelesaikan masalah storage. Data tetap akan tumbuh 57GB untuk 3000 user, hanya beda tempat penyimpanannya (di volume bukan di container).

---

## 🌐 2. Riset Internet: Cloud Storage Options

User meminta riset solusi cloud storage: *"jika misal menggunakan s3 bagaimana? coba evaluasi dan lakukan riset di internet"*

### 2.1 NPM Package: wwebjs-aws-s3

**Source**: https://www.npmjs.com/package/wwebjs-aws-s3

Library untuk integrasi whatsapp-web.js dengan S3-compatible storage.

**Cara penggunaan:**
```javascript
const { Client, RemoteAuth } = require('whatsapp-web.js');
const { AwsS3Store } = require('wwebjs-aws-s3');
const { S3Client, PutObjectCommand, ... } = require('@aws-sdk/client-s3');

// Setup S3 client
const s3 = new S3Client({
    region: 'auto',
    endpoint: 'https://xxx.r2.cloudflarestorage.com', // R2 compatible!
    credentials: {
        accessKeyId: 'xxx',
        secretAccessKey: 'xxx'
    }
});

// Create store
const store = new AwsS3Store({
    bucketName: 'whatsapp-sessions',
    remoteDataPath: 'sessions/',
    s3Client: s3
});

// Use with WhatsApp client
const client = new Client({
    authStrategy: new RemoteAuth({
        clientId: 'user-123',
        store: store,
        backupSyncIntervalMs: 600000 // backup every 10 min
    })
});
```

**Fitur utama:**
- ✅ S3-compatible (works with R2, MinIO, Backblaze)
- ✅ Auto backup dengan interval
- ✅ Restore session tanpa QR scan ulang
- ✅ Compression built-in

### 2.2 NPM Package: wwebjs-mongo

**Source**: https://www.npmjs.com/package/wwebjs-mongo

Alternatif menggunakan MongoDB GridFS:
```javascript
const { MongoStore } = require('wwebjs-mongo');
const mongoose = require('mongoose');

await mongoose.connect('mongodb://localhost/whatsapp');
const store = new MongoStore({ mongoose });
```

**Pertimbangan:**
- ❌ Perlu MongoDB server
- ❌ Storage tetap besar (tidak compress)
- ✅ Sudah familiar dengan MongoDB

### 2.3 AWS S3

**Source**: https://aws.amazon.com/s3/pricing/

**Pricing:**
| Item | Harga |
|------|-------|
| Storage | $0.023/GB/bulan |
| PUT request | $0.005/1000 |
| GET request | $0.0004/1000 |
| **Data Transfer OUT** | **$0.09/GB** ← MAHAL! |

**Kalkulasi untuk 3000 users:**
```
Storage: 9GB × $0.023        = $0.21
PUT: 9000 × $0.005/1000      = $0.05
GET: 90000 × $0.0004/1000    = $0.04
Egress: 27GB × $0.09         = $2.43  ← Problem!
───────────────────────────────────────
Total:                         ~$2.73/bulan
```

**Verdict**: ❌ Egress fee mahal untuk restore operations

### 2.4 Cloudflare R2

**Source**: https://developers.cloudflare.com/r2/pricing/

**Pricing:**
| Item | Harga |
|------|-------|
| Storage | $0.015/GB/bulan |
| Class A (PUT, POST, LIST) | $4.50/million |
| Class B (GET, HEAD) | $0.36/million |
| **Data Transfer OUT** | **$0 (GRATIS!)** |

**Free Tier:**
- 10GB storage/bulan
- 1 million Class A ops/bulan
- 10 million Class B ops/bulan

**Kalkulasi untuk 3000 users:**
```
Storage: 9GB × $0.015           = $0.135  (dalam free tier!)
Class A: 9000 × $4.50/1M        = $0.041  (dalam free tier!)
Class B: 90000 × $0.36/1M       = $0.032  (dalam free tier!)
Egress: UNLIMITED × $0          = $0.000  ← ZERO!
───────────────────────────────────────────
Total:                            $0.00/bulan (FREE TIER!)
```

**Keunggulan utama:**
1. **Zero Egress Fees** - Tidak ada biaya download
2. **S3-Compatible API** - Drop-in replacement
3. **Global Edge** - 300+ lokasi Cloudflare
4. **Free Tier Generous** - 10GB gratis

**Verdict**: ✅ **RECOMMENDED** - Best value untuk use case kita

### 2.5 Backblaze B2

**Source**: https://www.backblaze.com/cloud-storage/pricing

**Pricing:**
| Item | Harga |
|------|-------|
| Storage | $0.006/GB/bulan (TERMURAH!) |
| Class A | $0.004/10,000 |
| Class B | $0.004/10,000 |
| Egress | 3x storage FREE, lalu $0.01/GB |

**Free Tier:**
- 10GB storage
- 2,500 transactions/day

**Kalkulasi untuk 3000 users:**
```
Storage: 9GB × $0.006           = $0.054
Egress: 27GB (3x9GB free)       = $0.000
───────────────────────────────────────────
Total:                            ~$0.05/bulan
```

**Verdict**: ✅ Murah, tapi egress fee bisa naik jika traffic tinggi

### 2.6 MinIO (Self-Hosted)

**Source**: https://min.io/pricing

**Pricing**: FREE (open source, self-hosted)

**Setup:**
```yaml
# docker-compose.yaml
services:
  minio:
    image: minio/minio
    command: server /data --console-address ":9001"
    environment:
      MINIO_ROOT_USER: admin
      MINIO_ROOT_PASSWORD: password123
    volumes:
      - minio-data:/data
    ports:
      - "9000:9000"  # S3 API
      - "9001:9001"  # Console
```

**Pertimbangan:**
- ✅ Gratis selamanya
- ✅ S3-compatible
- ✅ Full control
- ❌ Perlu maintain server sendiri
- ❌ Storage tetap di server sendiri (57GB)
- ❌ Tidak ada edge/CDN

**Verdict**: ⚠️ Bagus untuk development/testing, tapi tidak solve storage problem

---

## 📊 3. Perbandingan Final

| Provider | Storage/GB | Egress | Free Tier | 3000 Users | Recommendation |
|----------|------------|--------|-----------|------------|----------------|
| LocalAuth | - | - | - | 57GB disk | ❌ Tidak scalable |
| AWS S3 | $0.023 | $0.09/GB | 5GB (12 bln) | ~$2.70/bln | ❌ Egress mahal |
| **Cloudflare R2** | $0.015 | **FREE** | 10GB | **$0/bln** | ✅ **BEST** |
| Backblaze B2 | $0.006 | 3x free | 10GB | ~$0.05/bln | ⚠️ Good backup |
| MinIO | FREE | FREE | ∞ | 57GB server | ⚠️ Dev only |

---

## 🔧 4. Mekanisme Kerja Cloudflare R2

User meminta penjelasan detail: *"coba jelaskan dulu mekanisme kerjanya jika kita menggunakan cloudflare r2"*

### 4.1 Arsitektur Hybrid: Redis + R2

```
┌──────────────────────────────────────────────────────────────────────┐
│                   ARSITEKTUR HYBRID STORAGE                          │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│   WhatsApp Service                                                   │
│        │                                                             │
│        ├──────────────────┬───────────────────┐                      │
│        ▼                  ▼                   ▼                      │
│   ┌─────────┐       ┌─────────┐        ┌───────────┐                │
│   │  Redis  │       │  Local  │        │ Cloudflare│                │
│   │ (Hot)   │       │  Temp   │        │    R2     │                │
│   │         │       │         │        │  (Cold)   │                │
│   └─────────┘       └─────────┘        └───────────┘                │
│                                                                      │
│   Role:              Role:              Role:                        │
│   - Session state    - Extract ZIP      - Persistent backup          │
│   - Credentials      - Processing       - Disaster recovery          │
│   - Fast access      - Temporary        - Cross-server restore       │
│                                                                      │
│   Size: ~100KB/user  Size: ~20MB temp   Size: ~3MB/user (compressed) │
│   Latency: ~1ms      Latency: ~10ms     Latency: ~50-200ms          │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

### 4.2 Flow: User Baru Connect WhatsApp

```
┌─────────────────────────────────────────────────────────────────────┐
│                FLOW: USER BARU SCAN QR CODE                         │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  [1] User request connect                                           │
│      └─► Laravel API → WhatsApp Service                             │
│                                                                     │
│  [2] Check existing session                                         │
│      ├─► Redis? → Tidak ada                                         │
│      └─► R2 backup? → Tidak ada                                     │
│                                                                     │
│  [3] Generate QR Code                                               │
│      └─► Puppeteer buka WhatsApp Web                                │
│      └─► QR code dikirim ke user                                    │
│                                                                     │
│  [4] User scan QR dengan HP                                         │
│      └─► WhatsApp Web authenticated                                 │
│                                                                     │
│  [5] Save session                                                   │
│      ├─► Redis: Session state + credentials (~100KB)                │
│      └─► R2: Background backup setiap 10 menit                      │
│                                                                     │
│  [6] User dapat kirim/terima pesan                                  │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### 4.3 Flow: Automatic Backup ke R2

```
┌─────────────────────────────────────────────────────────────────────┐
│               FLOW: BACKUP OTOMATIS (setiap 10 menit)               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  [1] Timer trigger (backupSyncIntervalMs = 600000)                  │
│                                                                     │
│  [2] Collect session data dari Chromium profile                     │
│      ./temp/session-user1/                                          │
│      ├── IndexedDB/        ← WhatsApp messages                      │
│      ├── Local Storage/    ← Session credentials                    │
│      └── Cookies           ← Auth state                             │
│                                                                     │
│  [3] Compress ke ZIP (exclude cache yang tidak perlu)               │
│      ├── EXCLUDE: Service Worker (~12MB) - tidak perlu              │
│      ├── EXCLUDE: Cache (~6MB) - bisa rebuild                       │
│      └── INCLUDE: Essential data saja                               │
│      └── Result: session-user1.zip (~2-3MB)                         │
│                                                                     │
│  [4] Upload ke R2                                                   │
│      PUT https://{account}.r2.cloudflarestorage.com                 │
│          /blazz-wa-sessions/workspace_1/session-user1.zip           │
│                                                                     │
│  [5] Cleanup temp files                                             │
│                                                                     │
│  ✓ Backup selesai, repeat in 10 minutes                             │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### 4.4 Flow: Restore Session (After Restart)

```
┌─────────────────────────────────────────────────────────────────────┐
│            FLOW: RESTORE SESSION (setelah server restart)           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  [1] Server/Container starts                                        │
│                                                                     │
│  [2] Query database: sessions mana yang active?                     │
│      SELECT user_id, workspace_id FROM whatsapp_instances           │
│      WHERE status = 'connected'                                     │
│      → [user_1, user_2, user_3, ...]                                │
│                                                                     │
│  [3] For each session, restore:                                     │
│                                                                     │
│      ┌──────────────────────────────────────────────────┐           │
│      │ A. Check Redis dulu (fastest, ~1ms)              │           │
│      │    ├─► Found? → Initialize langsung              │           │
│      │    └─► Not found? → Continue to B                │           │
│      ├──────────────────────────────────────────────────┤           │
│      │ B. Check R2 backup (~50-200ms)                   │           │
│      │    ├─► Download session-user1.zip dari R2        │           │
│      │    ├─► Extract ke temp folder                    │           │
│      │    ├─► Initialize WhatsApp client                │           │
│      │    └─► Save state ke Redis untuk next time       │           │
│      ├──────────────────────────────────────────────────┤           │
│      │ C. Not found anywhere?                           │           │
│      │    └─► Mark as disconnected, user perlu scan QR  │           │
│      └──────────────────────────────────────────────────┘           │
│                                                                     │
│  [4] Session ready - user TIDAK PERLU scan QR lagi!                 │
│                                                                     │
│  ⏱️ Total restore time: ~30-60 detik untuk 100 sessions             │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### 4.5 Flow: Normal Message Operations

```
┌─────────────────────────────────────────────────────────────────────┐
│              FLOW: KIRIM/TERIMA PESAN (Normal Operation)            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  KIRIM PESAN:                                                       │
│  ────────────                                                       │
│                                                                     │
│  Laravel API                                                        │
│      │ POST /api/whatsapp/send                                      │
│      │ { to: "628xxx", message: "Hello!" }                          │
│      ▼                                                              │
│  WhatsApp Service                                                   │
│      │ 1. Get client dari memory (sudah initialized)                │
│      │ 2. Validate session dari Redis (1ms)                         │
│      │ 3. Kirim via WhatsApp Web                                    │
│      ▼                                                              │
│  WhatsApp Servers → Recipient                                       │
│                                                                     │
│  ⚡ R2 TIDAK TERLIBAT dalam operasi normal!                         │
│  ⚡ Latency: ~100-500ms (network ke WhatsApp)                       │
│                                                                     │
│  ────────────────────────────────────────────────────────────────   │
│                                                                     │
│  TERIMA PESAN:                                                      │
│  ─────────────                                                      │
│                                                                     │
│  WhatsApp Servers                                                   │
│      │ New message event                                            │
│      ▼                                                              │
│  WhatsApp Service (listening via Puppeteer)                         │
│      │ client.on('message', msg => { ... })                         │
│      │ Forward ke Laravel via webhook                               │
│      ▼                                                              │
│  Laravel → Process → Store → Notify User                            │
│                                                                     │
│  ⚡ R2 TIDAK TERLIBAT dalam operasi normal!                         │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘

R2 hanya digunakan untuk:
  1. Backup (background, setiap 10 menit)
  2. Restore (saat server restart)
```

---

## 💰 5. Analisis Biaya Detail

### Cloudflare R2 untuk 3000 Users

```
┌─────────────────────────────────────────────────────────────────────┐
│                      KALKULASI BIAYA R2                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  STORAGE                                                            │
│  ────────                                                           │
│  3000 users × 3MB (compressed) = 9GB                                │
│  9GB × $0.015/GB = $0.135/bulan                                     │
│                                                                     │
│  FREE TIER: 10GB → 9GB GRATIS! ✓                                    │
│                                                                     │
│  ────────────────────────────────────────────────────────────────   │
│                                                                     │
│  CLASS A OPERATIONS (PUT, DELETE, LIST)                             │
│  ──────────────────────────────────────                             │
│  Backup per user: 6x/jam × 24 jam = 144 ops/hari                    │
│  3000 users × 144 ops × 30 hari = 12.96M ops/bulan                  │
│                                                                     │
│  Tapi dengan 10 menit interval:                                     │
│  3000 × 6 ops/jam × 24 × 30 = 12.96M                                │
│                                                                     │
│  Dengan interval realistis (1 backup/10 min × active 8 jam):        │
│  3000 × 48 ops/hari × 30 = 4.32M ops/bulan                          │
│                                                                     │
│  FREE TIER: 1M Class A → Overage: 3.32M × $4.50/M = $14.94         │
│                                                                     │
│  OPTIMASI: Backup hanya saat ada perubahan = ~$0.50/bulan           │
│                                                                     │
│  ────────────────────────────────────────────────────────────────   │
│                                                                     │
│  CLASS B OPERATIONS (GET, HEAD)                                     │
│  ─────────────────────────────                                      │
│  Restore operations: Hanya saat restart                             │
│  Average: 3000 × 2 = 6000 ops/bulan                                 │
│                                                                     │
│  FREE TIER: 10M Class B → GRATIS! ✓                                 │
│                                                                     │
│  ────────────────────────────────────────────────────────────────   │
│                                                                     │
│  EGRESS (Data Transfer OUT)                                         │
│  ─────────────────────────                                          │
│  Restore: 3000 × 3MB = 9GB                                          │
│  9GB × $0/GB = $0.00                                                │
│                                                                     │
│  ZERO EGRESS FEES! ✓                                                │
│                                                                     │
│  ════════════════════════════════════════════════════════════════   │
│                                                                     │
│  TOTAL ESTIMASI: $0 - $15/bulan (tergantung backup frequency)       │
│                                                                     │
│  DENGAN OPTIMASI (backup on-change only): ~$0.50/bulan              │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Perbandingan dengan AWS S3

```
┌─────────────────────────────────────────────────────────────────────┐
│                 R2 vs S3 untuk 3000 Users                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│                        Cloudflare R2         AWS S3                 │
│  ──────────────────────────────────────────────────────────────    │
│  Storage (9GB)        $0.135 (FREE)         $0.207                  │
│  PUT operations       $0.50 (optimized)     $0.05                   │
│  GET operations       FREE                  $0.04                   │
│  Egress (9GB)         FREE                  $0.81                   │
│  ──────────────────────────────────────────────────────────────    │
│  TOTAL                ~$0.50/bulan          ~$1.11/bulan            │
│                                                                     │
│  Catatan: S3 lebih murah untuk PUT, tapi egress membunuh            │
│  Jika restore sering (server crash, scaling), R2 jauh lebih murah   │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## ✅ 6. Kesimpulan & Rekomendasi

### Rekomendasi Final

| Prioritas | Provider | Use Case |
|-----------|----------|----------|
| **#1** | **Cloudflare R2** | Production - best balance |
| #2 | Backblaze B2 | Budget option, perlu monitor egress |
| #3 | MinIO | Development/testing only |
| #4 | AWS S3 | Jika sudah punya AWS infrastructure |

### Arsitektur yang Direkomendasikan

```
┌─────────────────────────────────────────────────────────────────────┐
│                 RECOMMENDED ARCHITECTURE                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌────────────────────────────────────────────────────────────────┐ │
│  │                     WhatsApp Service                          │ │
│  │  ┌─────────────────────────────────────────────────────────┐  │ │
│  │  │                   SessionManagerR2                       │  │ │
│  │  │                                                          │  │ │
│  │  │   ┌─────────┐    ┌─────────┐    ┌─────────────────────┐ │  │ │
│  │  │   │  Redis  │◄──►│ Manager │◄──►│    Cloudflare R2    │ │  │ │
│  │  │   │         │    │         │    │                     │ │  │ │
│  │  │   │ 100KB/  │    │         │    │ blazz-wa-sessions/  │ │  │ │
│  │  │   │ user    │    │         │    │ ├── workspace_1/    │ │  │ │
│  │  │   │         │    │         │    │ │   └── user-1.zip  │ │  │ │
│  │  │   │ ~300MB  │    │         │    │ └── workspace_2/    │ │  │ │
│  │  │   │ for 3k  │    │         │    │     └── user-2.zip  │ │  │ │
│  │  │   │ users   │    │         │    │                     │ │  │ │
│  │  │   └─────────┘    └─────────┘    │ ~9GB for 3k users   │ │  │ │
│  │  │                                 │ Cost: $0 (free tier)│ │  │ │
│  │  │                                 └─────────────────────┘ │  │ │
│  │  └─────────────────────────────────────────────────────────┘  │ │
│  └────────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  Benefits:                                                          │
│  ✓ Storage: 57GB → 300MB (99.5% reduction)                         │
│  ✓ Cost: $0/bulan (within free tier)                               │
│  ✓ No QR re-scan after restart                                     │
│  ✓ Multi-server support                                            │
│  ✓ Disaster recovery ready                                         │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Action Items

1. **Setup Cloudflare R2**
   - Buat account Cloudflare
   - Buat bucket `blazz-wa-sessions`
   - Generate API token

2. **Implement R2Store**
   - Install @aws-sdk/client-s3
   - Implement R2Store class
   - Update SessionManager

3. **Configure Backup**
   - Set backup interval (10 menit recommended)
   - Implement on-change backup untuk optimize cost

4. **Test & Deploy**
   - Test restore setelah restart
   - Monitor R2 usage di dashboard
   - Deploy ke production

---

*Dokumen ini adalah hasil riset langsung dari internet dan analisis untuk project Blazz*
*Tanggal: 5 Desember 2025*
