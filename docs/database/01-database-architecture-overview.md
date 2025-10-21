# Database Architecture Overview - Blazz WhatsApp Business Platform

> **Last Updated:** October 2025  
> **Version:** 2.9+  
> **Database Engine:** MySQL 8.0+  
> **Multi-tenancy:** Workspace-Scoped Architecture

---

## 📊 KONTEKS BISNIS

### Domain Bisnis
**Blazz** adalah **WhatsApp Business Messaging SaaS Platform** yang menyediakan:

- **Bulk Messaging Campaigns** - Broadcast messages ke thousands of contacts
- **Real-time Chat Management** - Live customer conversations via WhatsApp
- **Contact Management** - CRM-like contact organization dengan groups
- **Template Management** - WhatsApp approved message templates
- **Auto-Reply System** - Automated response berdasarkan triggers
- **Team Collaboration** - Multi-user workspace dengan role-based access
- **Billing & Subscriptions** - SaaS subscription management dengan multiple payment gateways
- **Analytics & Reporting** - Campaign performance dan chat metrics

### Skala & Volume Data

**Current Production Scale:**
```
Users: 5,000+ registered users
Workspaces: 2,500+ active workspaces (tenant instances)
Contacts: 1.5M+ contact records
Chats: 10M+ chat messages (growing 500K+/month)
Campaigns: 50K+ campaign executions
Campaign Logs: 25M+ message delivery records
Templates: 15K+ WhatsApp templates
Daily Transactions: 100K+ chat messages, 5K+ campaigns

Peak Load:
- 500 concurrent users
- 10K messages/minute during campaign peaks
- 200 simultaneous campaigns processing
```

**Growth Projection (12 months):**
```
Users: 15,000+
Workspaces: 8,000+
Contacts: 5M+
Chats: 40M+
Campaign Logs: 100M+
Daily Transactions: 500K+ messages
```

### Tech Stack

**Backend:**
- **Language:** PHP 8.2+
- **Framework:** Laravel 12.29.0
- **ORM:** Eloquent with Query Builder optimization

**Database:**
- **Primary:** MySQL 8.0+ (utf8mb4_unicode_ci)
- **Caching:** Redis 6.0+ (sessions, cache, queue)
- **Queue:** Redis-backed Laravel Queue (async processing)

**Frontend:**
- **Framework:** Vue.js 3.x + Inertia.js 2.0
- **Real-time:** Pusher (WebSocket broadcasting)

**Infrastructure:**
- **Environment:** LAMP/LEMP stack
- **File Storage:** Local filesystem (with S3 capability)
- **Search:** MySQL Full-Text Search (with potential Elasticsearch migration)

---

## 🗄️ 1. DATABASE CHOICE & STRATEGY

### Database Engine: MySQL 8.0+

**Alasan Pemilihan MySQL:**

1. **Mature Ecosystem** - Laravel memiliki excellent MySQL support dengan advanced Eloquent features
2. **ACID Compliance** - Critical untuk financial transactions (billing, payments)
3. **JSON Support** - Modern MySQL 8.0+ provides native JSON columns untuk flexible metadata storage
4. **Full-Text Search** - Built-in full-text indexing untuk contact dan chat search
5. **Horizontal Scaling** - Read replicas dan sharding capabilities untuk future growth
6. **Cost-Effective** - Open source dengan affordable managed services (AWS RDS, Google Cloud SQL)
7. **Community Support** - Massive ecosystem, tools, dan developer expertise

**Why NOT PostgreSQL?**
- Project started dengan MySQL (migration cost tinggi)
- MySQL performance sufficient untuk current scale
- Team expertise lebih strong di MySQL ecosystem

**Why NOT MongoDB?**
- Relational data dengan complex foreign key relationships
- ACID transactions critical untuk billing system
- Laravel ecosystem primarily SQL-focused

### Single Database vs Multi-Database Approach

**Current Strategy: SINGLE DATABASE with Logical Separation**

```
blazz_production
├── Core Tables (users, workspaces, teams)
├── Messaging Tables (chats, campaigns, templates)
├── Contact Management (contacts, contact_groups)
├── Billing System (billing_*, subscriptions)
├── Support System (tickets, faqs, pages)
├── Security & Audit (audit_logs, security_incidents)
└── Background Jobs (jobs, failed_jobs, job_batches)
```

**Alasan Single Database:**

✅ **Simplicity** - Single connection pool, easier deployment  
✅ **Foreign Key Integrity** - Cross-schema constraints berfungsi dengan baik  
✅ **Transaction Support** - Atomic operations across all tables  
✅ **Backup Simplicity** - Single mysqldump untuk complete backup  
✅ **Cost Effective** - Single RDS instance lebih murah di early stage  
✅ **Query Performance** - Joins across tables tanpa cross-database overhead

**Future Multi-Database Consideration:**

Jika scale melebihi **50M records** atau **10K+ concurrent connections**, consider separation:

```
Strategy A: Functional Separation
├── blazz_core (users, workspaces, subscriptions)
├── blazz_messaging (chats, campaigns, templates)
├── blazz_analytics (logs, metrics, reporting)
└── blazz_archive (old data untuk compliance)

Strategy B: Tenant Sharding (Multi-tenant)
├── blazz_shard_01 (workspace_id 1-1000)
├── blazz_shard_02 (workspace_id 1001-2000)
└── blazz_shard_03 (workspace_id 2001-3000)
```

### Caching Layer Strategy

**Redis Implementation: ACTIVE & ESSENTIAL**

```php
Redis Configuration:
├── Database 0 (default) - Session storage
├── Database 1 (cache) - Application cache
└── Database 2 (queue) - Background job queue
```

**Kapan Redis Digunakan:**

#### 1. **Session Storage** (Database 0)
```php
// config/session.php
'driver' => env('SESSION_DRIVER', 'redis'),
'connection' => 'default',
```
**Why:** Scale across multiple web servers tanpa sticky sessions.

#### 2. **Application Cache** (Database 1)
```php
// Cache workspace metadata, user permissions, subscription limits
Cache::remember("workspace:{$workspaceId}:metadata", 3600, function() {
    return Workspace::with('subscription', 'owner')->find($workspaceId);
});

Cache::remember("user:{$userId}:permissions", 1800, function() {
    return Team::where('user_id', $userId)
        ->with('workspace')
        ->get();
});
```

**Cache Strategy:**
- **Workspace Data:** 1 hour TTL (low churn rate)
- **User Permissions:** 30 minutes TTL (moderate changes)
- **Subscription Limits:** 15 minutes TTL (critical for rate limiting)
- **Template Lists:** 2 hours TTL (rarely change)
- **Contact Counts:** 5 minutes TTL (frequently updated)

#### 3. **Queue System** (Database 2)
```php
// config/queue.php
'default' => env('QUEUE_CONNECTION', 'redis'),
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],
```

**Queue Usage:**
- Campaign message processing (bulk operations)
- WhatsApp API calls (rate limit management)
- Email sending (async notifications)
- File imports/exports (large CSV processing)
- Webhook dispatching (external integrations)

#### 4. **Rate Limiting** (Database 0)
```php
// API rate limiting menggunakan Redis atomic counters
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)
        ->by($request->user()?->id ?: $request->ip())
        ->response(function () {
            return response()->json(['message' => 'Too many requests'], 429);
        });
});
```

**Cache Invalidation Strategy:**

```php
// Event-based cache invalidation
Event::listen(WorkspaceUpdated::class, function ($event) {
    Cache::forget("workspace:{$event->workspace->id}:metadata");
});

Event::listen(SubscriptionUpdated::class, function ($event) {
    Cache::forget("workspace:{$event->subscription->workspace_id}:metadata");
    Cache::forget("workspace:{$event->subscription->workspace_id}:limits");
});
```

**When NOT to Cache:**
- Real-time chat messages (always fresh from database)
- Campaign execution status (critical accuracy)
- Billing transactions (financial accuracy)
- Audit logs (compliance requirements)

---

## 🏗️ 2. ARCHITECTURAL PATTERNS

### Multi-Tenancy Architecture: **Workspace-Scoped Isolation**

**Pattern: Shared Database, Tenant-Scoped Data**

```sql
-- Every tenant-scoped table has workspace_id foreign key
SELECT * FROM contacts WHERE workspace_id = 123;
SELECT * FROM campaigns WHERE workspace_id = 123;
SELECT * FROM chats WHERE workspace_id = 123;
```

**Tenant Identification Flow:**

```
User Login → Session → Workspace Selection → workspace_id scoping
```

**Implementation Pattern:**

```php
// Middleware ensures workspace_id is always set
class EnsureWorkspaceContext
{
    public function handle($request, $next)
    {
        $workspaceId = session('workspace_id');
        
        if (!$workspaceId) {
            return redirect()->route('workspace.select');
        }
        
        // Global scope untuk semua queries
        config(['app.workspace_id' => $workspaceId]);
        
        return $next($request);
    }
}

// Model-level scoping
class Contact extends Model
{
    protected static function booted()
    {
        static::addGlobalScope('workspace', function ($query) {
            if ($workspaceId = config('app.workspace_id')) {
                $query->where('workspace_id', $workspaceId);
            }
        });
    }
}
```

**Advantages:**
✅ **Cost-Effective** - Single database untuk semua tenants  
✅ **Simple Maintenance** - Single schema update applies to all  
✅ **Resource Sharing** - Connection pool shared efficiently  
✅ **Easy Backup** - One backup process untuk all tenant data  

**Considerations:**
⚠️ **Query Performance** - Indexes must include workspace_id  
⚠️ **Data Isolation** - Application-level enforcement (not database-level)  
⚠️ **Noisy Neighbors** - One tenant's heavy load affects others  

### Primary Key Strategy: **Auto-Increment + UUID Hybrid**

```sql
-- Pattern used across all tables
CREATE TABLE campaigns (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,  -- Internal reference (fast joins)
    uuid CHAR(50) NOT NULL UNIQUE,                  -- External reference (API exposure)
    workspace_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(128) NOT NULL,
    ...
    INDEX idx_workspace_uuid (workspace_id, uuid)
);
```

**Why Hybrid Approach?**

1. **Auto-Increment (id):**
   - Fast integer joins (index efficiency)
   - Sequential ordering (query optimizer friendly)
   - Storage efficiency (8 bytes vs 36 bytes)
   - Internal references antar tables

2. **UUID (uuid):**
   - Exposed di public APIs (security - no sequential guessing)
   - URL-safe identifiers untuk frontend routes
   - Prevents enumeration attacks
   - Distributed system compatibility (future sharding)

**UUID Generation:**

```php
use Illuminate\Support\Str;

trait HasUuid
{
    protected static function bootHasUuid()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
```

### Timestamp Strategy: **Comprehensive Audit Trail**

```sql
-- Standard timestamp pattern across all tables
CREATE TABLE workspaces (
    id BIGINT UNSIGNED PRIMARY KEY,
    uuid CHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255),
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,  -- Soft delete support
    created_by BIGINT UNSIGNED,              -- Who created?
    INDEX idx_timestamps (created_at, updated_at),
    INDEX idx_deleted (deleted_at)
);
```

**Audit Trail Components:**

1. **created_at** - Record creation timestamp
2. **updated_at** - Last modification timestamp (auto-updated by Laravel)
3. **deleted_at** - Soft delete timestamp (nullable)
4. **created_by** - User ID yang membuat record (foreign key ke users)
5. **deleted_by** - User ID yang soft delete record (untuk audit)

---

## 📈 3. SCALABILITY DESIGN DECISIONS

### Indexing Philosophy: **Query-Pattern Driven**

**Composite Index Strategy:**

```sql
-- Bad: Single column indexes
CREATE INDEX idx_workspace ON campaigns (workspace_id);
CREATE INDEX idx_created ON campaigns (created_at);

-- Good: Composite index covering common query patterns
CREATE INDEX idx_workspace_timeline ON campaigns (workspace_id, created_at, status);

-- Query optimization:
SELECT * FROM campaigns 
WHERE workspace_id = 123 
  AND created_at > '2025-01-01' 
  AND status = 'completed'
ORDER BY created_at DESC
LIMIT 20;
-- ✅ Uses idx_workspace_timeline efficiently
```

**Performance Indexes (Implemented in 2025_09_18_102755):**

```sql
-- Chats table (hot path - 10M+ records)
ALTER TABLE chats ADD INDEX idx_chat_timeline_performance (workspace_id, created_at, type);
ALTER TABLE chats ADD INDEX idx_chat_participants_opt (workspace_id, contact_id, status);

-- Contacts table (search optimization)
ALTER TABLE contacts ADD FULLTEXT INDEX idx_contacts_fulltext (first_name, last_name, phone, email);

-- Campaign logs (massive volume - 25M+ records)
ALTER TABLE campaign_logs ADD INDEX idx_campaign_timeline (campaign_id, created_at, status);
ALTER TABLE campaign_logs ADD INDEX idx_contact_campaigns (contact_id, status, created_at);
```

### Normalization Level: **Selective 3NF with Denormalization**

**Core Principle: 3rd Normal Form (3NF)**

Most tables follow strict 3NF:
- No transitive dependencies
- All non-key attributes depend solely on primary key
- Minimal redundancy

**Strategic Denormalization:**

```sql
-- Example: contacts table includes latest_chat_created_at
CREATE TABLE contacts (
    id BIGINT UNSIGNED PRIMARY KEY,
    workspace_id BIGINT UNSIGNED,
    first_name VARCHAR(255),
    latest_chat_created_at TIMESTAMP NULL,  -- Denormalized from chats table
    ...
);

-- Why? Sorting contacts by "most recent conversation" adalah frequent query:
SELECT * FROM contacts 
WHERE workspace_id = 123 
ORDER BY latest_chat_created_at DESC;
-- Without denormalization, this requires expensive JOIN dengan MAX(chats.created_at)
```

**Metadata JSON Columns:**

```sql
-- Flexible metadata storage untuk non-critical data
campaigns.metadata JSON:
{
    "variables": {"name": "Customer Name", "phone": "Phone Number"},
    "header_params": ["image_url"],
    "body_params": ["customer_name", "order_id"],
    "button_params": ["confirmation_url"],
    "statistics": {"sent": 1500, "delivered": 1450, "read": 800}
}
```

**Benefit:**
- Schema flexibility tanpa migrations
- Query optimization (no additional joins untuk metadata)
- JSON functions untuk conditional filtering

### Soft Delete Strategy: **Recovery + Compliance**

```sql
-- Soft delete implementation across critical tables
contacts.deleted_at TIMESTAMP NULL,
contacts.deleted_by BIGINT UNSIGNED NULL,

-- Query behavior:
SELECT * FROM contacts WHERE deleted_at IS NULL;  -- Active records only
SELECT * FROM contacts WHERE deleted_at IS NOT NULL;  -- Deleted records
```

**Why Soft Deletes:**

1. **Data Recovery** - User mistakes dapat di-restore
2. **Audit Compliance** - Historical data preserved untuk reporting
3. **Foreign Key Integrity** - Related records tidak orphaned
4. **GDPR Right to be Forgotten** - Soft delete + anonymization

**Permanent Delete Strategy:**

```php
// Automated cleanup job (monthly)
Schedule::command('cleanup:soft-deleted')
    ->monthly()
    ->runInBackground();

// Permanently delete records older than 90 days
Contact::onlyTrashed()
    ->where('deleted_at', '<', now()->subDays(90))
    ->forceDelete();
```

---

## 🔒 4. SECURITY & COMPLIANCE

### Audit Logging: **Enterprise-Grade Tracking**

```sql
-- audit_logs table (comprehensive activity tracking)
CREATE TABLE audit_logs (
    id VARCHAR(100) PRIMARY KEY,  -- Request ID
    event_type VARCHAR(50) NOT NULL,
    user_id BIGINT UNSIGNED,
    workspace_id BIGINT UNSIGNED,
    endpoint VARCHAR(100),
    method VARCHAR(10),
    url TEXT,
    ip_address VARCHAR(45),
    status_code INT,
    execution_time DECIMAL(10, 3),
    created_at TIMESTAMP,
    INDEX idx_user_activity (user_id, created_at),
    INDEX idx_workspace_activity (workspace_id, created_at)
);
```

**Coverage:**
- Authentication events (login, logout, failures)
- Data access (GDPR compliance)
- Configuration changes
- Payment transactions
- Campaign executions
- API calls

### Data Access Logs: **GDPR Compliance**

```sql
CREATE TABLE data_access_logs (
    id BIGINT UNSIGNED PRIMARY KEY,
    user_id BIGINT UNSIGNED,         -- Who accessed?
    target_user_id BIGINT UNSIGNED,  -- Whose data?
    data_type VARCHAR(50),            -- What type?
    access_type VARCHAR(20),          -- read/export/modify/delete
    consent_given BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    INDEX idx_target_audit (target_user_id, created_at)
);
```

---

## 📊 5. PERFORMANCE METRICS

### Current Performance Benchmarks

```
Query Type                    | Avg Response Time | Index Usage
------------------------------|-------------------|-------------
Workspace dashboard load      | 45ms              | 3 indexes
Contact list (paginated)      | 28ms              | 2 indexes
Chat history fetch            | 35ms              | 2 indexes
Campaign creation             | 120ms             | Transaction
Campaign log insertion (bulk) | 85ms              | Batch insert
Full-text contact search      | 65ms              | FULLTEXT index
```

### Optimization Wins (2025 Index Updates)

**Before Optimization:**
```sql
SELECT * FROM chats WHERE workspace_id = 123 ORDER BY created_at DESC LIMIT 50;
-- Query time: 1.8s (table scan on 10M records)
```

**After Optimization:**
```sql
-- Added composite index: idx_chat_timeline_performance (workspace_id, created_at, type)
-- Query time: 18ms (index range scan)
-- Performance gain: 100x faster
```

---

## 🚀 NEXT STEPS & ROADMAP

### Short-term (3 months)
- [ ] Implement query performance monitoring dashboard
- [ ] Optimize campaign_logs table partitioning by date
- [ ] Add read replicas untuk reporting queries
- [ ] Implement Redis Cluster untuk high availability

### Medium-term (6-12 months)
- [ ] Evaluate Elasticsearch untuk advanced search
- [ ] Implement database sharding strategy (workspace-based)
- [ ] Migrate file storage ke S3/CDN
- [ ] Add time-series database untuk analytics

### Long-term (12+ months)
- [ ] Multi-region database replication
- [ ] Separate read/write database instances
- [ ] Archive old data ke cold storage
- [ ] Implement CQRS pattern untuk analytics

---

**Next Document:** [Entity Relationship Design →](./02-entity-relationship-design.md)
