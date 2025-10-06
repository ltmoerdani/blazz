# Data Patterns & Best Practices - Blazz Database

> **Implementation Patterns untuk Consistent Database Design**  
> **Version:** MySQL 8.0+  
> **Last Updated:** October 2025

---

## 📋 TABLE OF CONTENTS

1. [Soft Delete Pattern](#soft-delete-pattern)
2. [Audit Trail Strategy](#audit-trail-strategy)
3. [UUID Strategy](#uuid-strategy)
4. [Multi-Tenancy Implementation](#multi-tenancy-implementation)
5. [File Handling Patterns](#file-handling-patterns)
6. [JSON Metadata Pattern](#json-metadata-pattern)
7. [Timestamp Conventions](#timestamp-conventions)
8. [Enum Usage Guidelines](#enum-usage-guidelines)

---

## 🗑️ SOFT DELETE PATTERN

### Implementation Standard

**Pattern:** Add `deleted_at` timestamp column + optional `deleted_by` foreign key.

```sql
-- Standard soft delete columns
ALTER TABLE contacts ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE contacts ADD COLUMN deleted_by INT NULL;
ALTER TABLE contacts ADD INDEX idx_deleted_at (deleted_at);
```

### Laravel Eloquent Implementation

```php
// Model definition
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use SoftDeletes;
    
    protected $dates = ['deleted_at'];
    
    // Optional: Track who deleted
    protected static function boot()
    {
        parent::boot();
        
        static::deleting(function ($model) {
            $model->deleted_by = auth()->id();
            $model->save();
        });
    }
}
```

### Query Behavior

```php
// Default: Excludes soft-deleted records
Contact::where('workspace_id', 123)->get();
// SELECT * FROM contacts WHERE workspace_id = 123 AND deleted_at IS NULL;

// Include soft-deleted
Contact::withTrashed()->where('workspace_id', 123)->get();
// SELECT * FROM contacts WHERE workspace_id = 123;

// Only soft-deleted
Contact::onlyTrashed()->where('workspace_id', 123)->get();
// SELECT * FROM contacts WHERE workspace_id = 123 AND deleted_at IS NOT NULL;

// Restore soft-deleted record
Contact::withTrashed()->find(456)->restore();
// UPDATE contacts SET deleted_at = NULL WHERE id = 456;

// Permanent delete (force delete)
Contact::find(456)->forceDelete();
// DELETE FROM contacts WHERE id = 456;
```

### Business Rules

✅ **Use Soft Deletes When:**
- Data needed untuk audit trails
- Related records exist (foreign key integrity)
- Undo functionality required
- Legal compliance (data retention policies)
- Historical reporting needs

❌ **Don't Use Soft Deletes When:**
- Sensitive data (GDPR "right to be forgotten")
- Temporary/cache data
- Session/token data
- System logs (use archival instead)

### Tables Using Soft Delete

```
✅ SOFT DELETE ENABLED:
- users (audit compliance)
- workspaces (subscription history)
- contacts (CRM historical data)
- contact_groups (campaign history)
- campaigns (reporting needs)
- templates (template history)
- auto_replies (rule history)
- teams (membership history)
- subscription_plans (pricing history)
- tickets (support history)

❌ NO SOFT DELETE:
- chats (use deleted_by flag instead - keep for legal)
- campaign_logs (archive to cold storage)
- audit_logs (permanent record)
- billing_transactions (financial audit requirement)
- authentication_events (security audit)
```

### Soft Delete Cleanup Strategy

```php
// Automated cleanup job (monthly cron)
Schedule::command('cleanup:soft-deleted')->monthly();

// Command implementation
Artisan::command('cleanup:soft-deleted', function () {
    $cutoffDate = now()->subDays(90); // 90-day retention
    
    // Permanent delete old soft-deleted records
    Contact::onlyTrashed()
        ->where('deleted_at', '<', $cutoffDate)
        ->forceDelete();
    
    ContactGroup::onlyTrashed()
        ->where('deleted_at', '<', $cutoffDate)
        ->forceDelete();
        
    // Log cleanup activity
    Log::info("Soft delete cleanup completed", [
        'contacts_deleted' => $contactsDeleted,
        'groups_deleted' => $groupsDeleted,
        'cutoff_date' => $cutoffDate
    ]);
});
```

---

## 📝 AUDIT TRAIL STRATEGY

### Standard Audit Columns

**Pattern:** Every table includes `created_at`, `updated_at`, and `created_by`.

```sql
CREATE TABLE example_table (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid CHAR(50) NOT NULL UNIQUE,
    
    -- Data columns
    name VARCHAR(255),
    status VARCHAR(50),
    
    -- Audit trail columns
    created_by BIGINT UNSIGNED NOT NULL,  -- Who created
    created_at TIMESTAMP NULL DEFAULT NULL,  -- When created
    updated_at TIMESTAMP NULL DEFAULT NULL,  -- When last modified
    deleted_at TIMESTAMP NULL DEFAULT NULL,  -- When soft deleted
    deleted_by INT NULL,  -- Who soft deleted
    
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_created_by_at (created_by, created_at),
    INDEX idx_deleted (deleted_at)
);
```

### Laravel Timestamps Convention

```php
class Workspace extends Model
{
    // Laravel automatically handles created_at & updated_at
    protected $fillable = ['name', 'identifier', 'timezone'];
    
    // Manually track creator
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });
    }
    
    // Relationship to creator
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

### Comprehensive Audit Logging

**Separate `audit_logs` table for detailed tracking:**

```sql
CREATE TABLE audit_logs (
    id VARCHAR(100) PRIMARY KEY,  -- Request ID
    event_type VARCHAR(50) NOT NULL,  -- create/update/delete/view
    user_id BIGINT UNSIGNED,
    workspace_id BIGINT UNSIGNED,
    auditable_type VARCHAR(100),  -- Model class
    auditable_id BIGINT UNSIGNED,  -- Model ID
    old_values JSON,  -- Before state
    new_values JSON,  -- After state
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP,
    
    INDEX idx_user_activity (user_id, created_at),
    INDEX idx_model_audit (auditable_type, auditable_id, created_at)
);
```

### Audit Logging Implementation

```php
// Middleware untuk automatic audit logging
class AuditLogMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        
        // Log setelah request processed
        AuditLog::create([
            'id' => $request->id(),
            'event_type' => $this->determineEventType($request),
            'user_id' => auth()->id(),
            'workspace_id' => session('workspace_id'),
            'endpoint' => $request->route()->getName(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_data' => $request->except(['password', 'password_confirmation']),
            'status_code' => $response->getStatusCode(),
            'execution_time' => microtime(true) - LARAVEL_START,
            'created_at' => now()
        ]);
        
        return $response;
    }
}
```

### Model-Level Audit Trail (Eloquent Events)

```php
// Observer pattern untuk model changes
class CampaignObserver
{
    public function created(Campaign $campaign)
    {
        AuditLog::create([
            'event_type' => 'campaign_created',
            'user_id' => auth()->id(),
            'auditable_type' => Campaign::class,
            'auditable_id' => $campaign->id,
            'new_values' => $campaign->toArray(),
            'old_values' => null,
            'created_at' => now()
        ]);
    }
    
    public function updated(Campaign $campaign)
    {
        AuditLog::create([
            'event_type' => 'campaign_updated',
            'user_id' => auth()->id(),
            'auditable_type' => Campaign::class,
            'auditable_id' => $campaign->id,
            'old_values' => $campaign->getOriginal(),
            'new_values' => $campaign->getChanges(),
            'created_at' => now()
        ]);
    }
}

// Register observer in AppServiceProvider
Campaign::observe(CampaignObserver::class);
```

---

## 🔑 UUID STRATEGY

### Hybrid Primary Key Pattern

**Pattern:** Auto-increment `id` (internal) + `uuid` (external).

```sql
CREATE TABLE campaigns (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,  -- Internal FK references
    uuid CHAR(50) NOT NULL UNIQUE,  -- External API exposure
    name VARCHAR(255),
    ...
    INDEX idx_uuid (uuid)
);
```

### Why Hybrid Approach?

| Aspect | Auto-Increment ID | UUID |
|--------|-------------------|------|
| **Storage** | 8 bytes (BIGINT) | 36 bytes (CHAR) |
| **Index Size** | Smaller, faster | Larger, slower |
| **Join Performance** | Excellent (integer) | Good (string) |
| **Security** | Predictable (enumeration attack) | Random (secure) |
| **Distribution** | Sequential (single server) | Globally unique (distributed) |
| **Public Exposure** | ❌ Security risk | ✅ Safe for APIs |

**Decision:**
- Use `id` for internal foreign keys (performance)
- Use `uuid` for public URLs, API responses, frontend (security)

### UUID Generation (Laravel)

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

// Model implementation
class Campaign extends Model
{
    use HasUuid;
    
    // Route model binding by UUID
    public function getRouteKeyName()
    {
        return 'uuid';  // Use UUID in URLs instead of ID
    }
}
```

### API Response Pattern

```php
// ❌ BAD: Expose internal ID
return response()->json([
    'campaign' => [
        'id' => 12345,  // Sequential ID reveals business volume
        'name' => 'Summer Sale'
    ]
]);

// ✅ GOOD: Expose UUID
return response()->json([
    'campaign' => [
        'id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',  // UUID
        'uuid' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',  // Explicit
        'name' => 'Summer Sale'
    ]
]);
```

### Database Query Pattern

```php
// Frontend passes UUID
$uuid = $request->input('campaign_uuid');

// Query by UUID
$campaign = Campaign::where('uuid', $uuid)
    ->where('workspace_id', session('workspace_id'))
    ->firstOrFail();

// Internal joins still use id (performance)
$logs = CampaignLog::where('campaign_id', $campaign->id)->get();
```

---

## 🏢 MULTI-TENANCY IMPLEMENTATION

### Workspace-Scoped Pattern

**Pattern:** Every tenant-scoped table includes `workspace_id` foreign key.

```sql
CREATE TABLE contacts (
    id BIGINT UNSIGNED PRIMARY KEY,
    uuid CHAR(50) NOT NULL UNIQUE,
    workspace_id BIGINT UNSIGNED NOT NULL,  -- Tenant scoping
    first_name VARCHAR(255),
    phone VARCHAR(255),
    ...
    INDEX idx_workspace_contacts (workspace_id, created_at),
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
);
```

### Middleware Enforcement

```php
// Middleware ensures workspace_id is set in session
class EnsureWorkspaceContext
{
    public function handle($request, Closure $next)
    {
        $workspaceId = session('workspace_id');
        
        if (!$workspaceId) {
            return redirect()->route('workspace.select');
        }
        
        // Set global config untuk access throughout request
        config(['app.workspace_id' => $workspaceId]);
        
        return $next($request);
    }
}
```

### Global Query Scope (Automatic Filtering)

```php
// Apply workspace scoping automatically to all queries
class Contact extends Model
{
    protected static function booted()
    {
        static::addGlobalScope('workspace', function (Builder $query) {
            if ($workspaceId = config('app.workspace_id')) {
                $query->where('workspace_id', $workspaceId);
            }
        });
        
        // Auto-set workspace_id on create
        static::creating(function ($model) {
            if (empty($model->workspace_id) && $workspaceId = config('app.workspace_id')) {
                $model->workspace_id = $workspaceId;
            }
        });
    }
}

// Usage - workspace_id automatically scoped
Contact::all();  
// SELECT * FROM contacts WHERE workspace_id = 123;

Contact::create(['first_name' => 'John', 'phone' => '+6281234567890']);
// INSERT INTO contacts (workspace_id, first_name, phone) VALUES (123, 'John', '+6281234567890');
```

### Cross-Workspace Queries (Admin)

```php
// Remove workspace scope for admin queries
$allContacts = Contact::withoutGlobalScope('workspace')->get();

// Or query specific workspace
$contactsInWorkspace = Contact::withoutGlobalScope('workspace')
    ->where('workspace_id', 456)
    ->get();
```

### Multi-Tenancy Security Checklist

✅ **Must Have:**
- [ ] `workspace_id` column on all tenant-scoped tables
- [ ] Foreign key constraint with CASCADE DELETE
- [ ] Global query scope auto-applying workspace filter
- [ ] Middleware enforcing workspace context
- [ ] Index including workspace_id (e.g., `idx_workspace_created`)
- [ ] Form requests validating workspace ownership
- [ ] API authentication checking workspace access

❌ **Security Risks:**
- Forgetting to scope queries (data leak to other tenants)
- Missing workspace_id in INSERT (orphan records)
- Direct ID manipulation in URLs (access other workspace data)
- Missing authorization checks in controllers

---

## 📁 FILE HANDLING PATTERNS

### Storage Strategy

**Pattern:** Store file metadata in database, actual files in filesystem atau S3.

```sql
CREATE TABLE chat_media (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255),  -- Original filename
    path VARCHAR(255),  -- Relative path or S3 key
    location ENUM('local', 'amazon') DEFAULT 'local',
    type VARCHAR(255),  -- MIME type
    size VARCHAR(128),  -- File size in bytes
    workspace_id BIGINT UNSIGNED,  -- Tenant scoping
    created_at TIMESTAMP
);
```

### File Upload Implementation

```php
class ChatMediaService
{
    public function storeMedia($file, $workspaceId)
    {
        // Generate unique filename
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        
        // Store file locally or S3
        $path = $file->storeAs(
            "workspace_{$workspaceId}/chat_media",
            $filename,
            'public'  // or 's3' for cloud storage
        );
        
        // Save metadata to database
        return ChatMedia::create([
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'location' => config('filesystems.default') === 's3' ? 'amazon' : 'local',
            'type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'workspace_id' => $workspaceId,
            'created_at' => now()
        ]);
    }
}
```

### File Retrieval

```php
// Get file URL
$media = ChatMedia::find(123);

if ($media->location === 'amazon') {
    // S3 signed URL (temporary access)
    $url = Storage::disk('s3')->temporaryUrl($media->path, now()->addMinutes(5));
} else {
    // Local storage public URL
    $url = Storage::url($media->path);
}

return response()->json(['url' => $url]);
```

### File Cleanup Strategy

```php
// Delete orphaned files (no database reference)
Schedule::command('cleanup:orphaned-files')->weekly();

// Delete old media files
Schedule::command('cleanup:old-media')->daily();

Artisan::command('cleanup:old-media', function () {
    // Find media older than 90 days from deleted chats
    $oldMedia = ChatMedia::whereHas('chat', function ($query) {
        $query->onlyTrashed()->where('deleted_at', '<', now()->subDays(90));
    })->get();
    
    foreach ($oldMedia as $media) {
        // Delete physical file
        Storage::delete($media->path);
        
        // Delete database record
        $media->delete();
    }
});
```

### File Size Limits & Validation

```php
// Request validation
public function rules()
{
    return [
        'media' => [
            'required',
            'file',
            'max:10240',  // 10MB max
            'mimes:jpg,jpeg,png,pdf,doc,docx,mp4,mp3'  // Allowed types
        ]
    ];
}

// Storage quota check (workspace-level)
public function checkStorageQuota($workspaceId, $fileSize)
{
    $workspace = Workspace::find($workspaceId);
    $subscription = $workspace->subscription;
    $limits = json_decode($subscription->plan->metadata, true);
    
    $currentUsage = ChatMedia::where('workspace_id', $workspaceId)
        ->sum('size');
    
    $quotaLimit = $limits['storage_quota_bytes'] ?? 5 * 1024 * 1024 * 1024; // 5GB default
    
    if (($currentUsage + $fileSize) > $quotaLimit) {
        throw new \Exception('Storage quota exceeded');
    }
}
```

---

## 🗂️ JSON METADATA PATTERN

### Use Cases untuk JSON Columns

**Pattern:** Use JSON columns untuk flexible, non-queryable metadata.

✅ **Good Use Cases:**
- Configuration settings (varying structure)
- API response caching
- Flexible custom fields
- Template parameters
- Feature flags
- External integration metadata

❌ **Bad Use Cases:**
- Frequently queried data (use regular columns + indexes)
- Critical business data (use normalized tables)
- Data requiring foreign key constraints
- Data with strict validation requirements

### JSON Column Implementation

```sql
CREATE TABLE campaigns (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255),
    metadata TEXT,  -- JSON column (using TEXT for MySQL 5.7 compatibility)
    ...
);

-- MySQL 8.0+ native JSON type
CREATE TABLE workspaces (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255),
    metadata JSON,  -- Native JSON with validation
    ...
);
```

### Laravel JSON Casting

```php
class Campaign extends Model
{
    protected $casts = [
        'metadata' => 'array',  // Auto serialize/deserialize JSON
    ];
}

// Usage
$campaign = Campaign::create([
    'name' => 'Summer Sale',
    'metadata' => [
        'variables' => ['name', 'discount'],
        'send_rate' => '100_per_minute',
        'target_audience' => 'premium_customers'
    ]
]);

// Access
echo $campaign->metadata['send_rate'];  // "100_per_minute"
$campaign->metadata['statistics']['sent'] = 1500;
$campaign->save();
```

### JSON Query Operations (MySQL 8.0+)

```sql
-- Query by JSON field
SELECT * FROM campaigns
WHERE JSON_EXTRACT(metadata, '$.send_rate') = '100_per_minute';

-- Update JSON field
UPDATE campaigns
SET metadata = JSON_SET(metadata, '$.statistics.sent', 1500)
WHERE id = 123;

-- Check JSON key existence
SELECT * FROM campaigns
WHERE JSON_CONTAINS_PATH(metadata, 'one', '$.statistics');
```

### Laravel JSON Query Builder

```php
// Query JSON columns
Campaign::whereJsonContains('metadata->variables', 'name')->get();

Campaign::where('metadata->send_rate', '100_per_minute')->get();

// Update JSON path
Campaign::find(123)->update([
    'metadata->statistics->sent' => 1500
]);
```

---

## ⏰ TIMESTAMP CONVENTIONS

### Standard Timestamp Columns

```sql
-- Laravel convention (automatic management)
created_at TIMESTAMP NULL DEFAULT NULL,
updated_at TIMESTAMP NULL DEFAULT NULL,

-- Additional audit timestamps
deleted_at TIMESTAMP NULL DEFAULT NULL,  -- Soft delete
email_verified_at TIMESTAMP NULL DEFAULT NULL,  -- Email verification
last_login_at TIMESTAMP NULL DEFAULT NULL,  -- User activity tracking
valid_until DATETIME NULL,  -- Subscription expiry
scheduled_at DATETIME NULL,  -- Campaign scheduling
```

### Timezone Handling

**Strategy:** Store all timestamps in UTC, convert to user timezone on display.

```php
// Config
'timezone' => 'UTC',  // Database timezone always UTC

// Model accessor for user timezone
class Campaign extends Model
{
    public function getScheduledAtFormattedAttribute()
    {
        if (!$this->scheduled_at) {
            return null;
        }
        
        $workspace = $this->workspace;
        $timezone = $workspace->timezone ?? 'UTC';
        
        return $this->scheduled_at
            ->timezone($timezone)
            ->format('Y-m-d H:i:s T');
    }
}

// Usage in controller
return response()->json([
    'scheduled_at' => $campaign->scheduled_at,  // UTC ISO 8601
    'scheduled_at_formatted' => $campaign->scheduled_at_formatted,  // User timezone
]);
```

### Date Helper Trait

```php
trait DateTimeHelper
{
    public function toUserTimezone($datetime, $format = 'Y-m-d H:i:s')
    {
        if (!$datetime) return null;
        
        $workspace = $this->workspace ?? Workspace::find(session('workspace_id'));
        $timezone = $workspace->timezone ?? 'UTC';
        
        return Carbon::parse($datetime)
            ->timezone($timezone)
            ->format($format);
    }
    
    public function fromUserTimezone($datetime)
    {
        $workspace = $this->workspace ?? Workspace::find(session('workspace_id'));
        $timezone = $workspace->timezone ?? 'UTC';
        
        return Carbon::parse($datetime, $timezone)->utc();
    }
}
```

---

## 🔢 ENUM USAGE GUIDELINES

### When to Use ENUM vs VARCHAR

**✅ Use ENUM When:**
- Fixed set of values (won't change frequently)
- Small number of options (< 10)
- Database-level validation beneficial
- Performance-critical (ENUM stored as TINYINT internally)

```sql
-- Good ENUM usage
type ENUM('inbound', 'outbound')
status ENUM('active', 'suspended')
period ENUM('monthly', 'yearly')
```

**❌ Use VARCHAR When:**
- Values change frequently (requires ALTER TABLE for ENUM)
- Large number of options
- Need flexibility for future values
- Application-level validation sufficient

```sql
-- Better as VARCHAR
role VARCHAR(191) DEFAULT 'user'  -- user/admin/super_admin/future_roles
status VARCHAR(128) DEFAULT 'pending'  -- pending/processing/completed/failed/cancelled
```

### ENUM Migration Strategy

```php
// Bad: ALTER TABLE for ENUM changes (locks table)
DB::statement("ALTER TABLE campaigns MODIFY status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled')");

// Good: Use VARCHAR with validation
Schema::table('campaigns', function (Blueprint $table) {
    $table->string('status', 128)->default('pending')->change();
});

// Validation in model
class Campaign extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    
    public static function statuses()
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ];
    }
}

// Form request validation
public function rules()
{
    return [
        'status' => ['required', Rule::in(Campaign::statuses())]
    ];
}
```

---

## 📊 SUMMARY: Data Pattern Decision Matrix

| Pattern | Use When | Example Tables | Benefits | Trade-offs |
|---------|----------|----------------|----------|------------|
| **Soft Delete** | Audit trails needed, undo functionality | users, contacts, campaigns | Data recovery, compliance | Query complexity, storage overhead |
| **UUID** | Public API exposure, security | All tables (hybrid with ID) | Security, distribution-ready | Larger index size |
| **JSON Metadata** | Flexible schema, config data | workspaces, campaigns, templates | Schema flexibility | Query limitations |
| **Audit Logging** | Compliance, activity tracking | audit_logs, authentication_events | Complete audit trail | Storage overhead |
| **Multi-Tenancy** | SaaS platform | contacts, chats, campaigns | Cost-effective isolation | Query complexity, security responsibility |
| **Timestamps** | Track changes, timezone handling | All tables | Audit trail, user experience | Storage overhead |
| **ENUM** | Fixed value sets, performance | chat.type, team.role | Performance, validation | Migration complexity |

---

**Next Document:** [Migration & Seeding Strategy →](./05-migration-seeding-strategy.md)
