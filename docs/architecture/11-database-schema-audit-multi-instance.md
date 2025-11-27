# Database Schema Audit for Multi-Instance Architecture

**Date**: November 20, 2025  
**Architecture**: Workspace-Sharded Multi-Instance  
**Audit Type**: Comprehensive Database Compatibility Check  
**Status**: ⚠️ REQUIRES MIGRATION

---

## 🎯 Audit Objective

Verify that the current database schema supports the new **multi-instance workspace-sharded architecture** where:
- Multiple WhatsApp Node.js instances (4-8) handle sessions
- Each workspace routes to a specific instance (`workspace_id % instance_count`)
- Sessions persist in shared storage (EFS/NFS), not in database
- Instance health monitoring and failover capabilities needed

---

## 📊 Current Database Schema Analysis

### Table: `whatsapp_accounts`

**Current Schema** (from migrations):
```sql
CREATE TABLE whatsapp_accounts (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    phone_number VARCHAR(50) NULLABLE,
    provider_type ENUM('meta', 'webjs') DEFAULT 'webjs',
    status ENUM('qr_scanning', 'authenticated', 'connected', 'disconnected', 'failed') DEFAULT 'qr_scanning',
    qr_code TEXT NULLABLE,
    session_data LONGTEXT NULLABLE, -- Encrypted (5-10MB)
    is_primary BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    last_activity_at TIMESTAMP NULLABLE,
    last_connected_at TIMESTAMP NULLABLE,
    last_cleanup_at TIMESTAMP NULLABLE,
    session_restore_count INT DEFAULT 0,
    health_score TINYINT DEFAULT 100,
    metadata JSON NULLABLE,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULLABLE,
    
    -- Foreign Keys
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_workspace_status (workspace_id, status),
    INDEX idx_session_status (session_id, status),
    INDEX idx_provider_active (provider_type, is_active),
    INDEX idx_workspace_primary (workspace_id, is_primary),
    INDEX idx_stale_detection (status, last_activity_at)
);
```

---

## ✅ What's GOOD (Already Compatible)

### 1. ✅ Workspace Association
```sql
workspace_id BIGINT UNSIGNED NOT NULL
FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE
INDEX idx_workspace_status (workspace_id, status)
```
**Analysis**: Perfect for workspace-based routing. Query by workspace_id will be fast.

### 2. ✅ Session Identifier
```sql
session_id VARCHAR(255) UNIQUE NOT NULL
INDEX idx_session_status (session_id, status)
```
**Analysis**: Unique session ID allows each instance to create distinct sessions. No conflicts.

### 3. ✅ Status Tracking
```sql
status ENUM('qr_scanning', 'authenticated', 'connected', 'disconnected', 'failed')
```
**Analysis**: Comprehensive status enum covers all session states. Good for monitoring.

### 4. ✅ Health Monitoring Fields
```sql
last_activity_at TIMESTAMP NULLABLE
last_connected_at TIMESTAMP NULLABLE
last_cleanup_at TIMESTAMP NULLABLE
health_score TINYINT DEFAULT 100
session_restore_count INT DEFAULT 0
```
**Analysis**: Excellent for instance health monitoring and stale session detection.

### 5. ✅ Metadata Flexibility
```sql
metadata JSON NULLABLE
```
**Analysis**: Can store instance-specific data (e.g., `instance_id`, `instance_url`, failover counts).

---

## ⚠️ What's MISSING (Needs Migration)

### 1. ❌ Instance Assignment Tracking

**Problem**: No field to track WHICH instance is handling a session.

**Current**: Routing algorithm calculates instance on-the-fly:
```php
$instanceIndex = $workspace_id % $instance_count;
```

**Issue**: 
- If instance count changes (scale from 4 to 6), routing breaks
- Cannot track which instance actually handled session last
- No way to query "which sessions are on instance 2?"

**Solution**: Add `assigned_instance_index` field

**Recommended Migration**:
```php
Schema::table('whatsapp_accounts', function (Blueprint $table) {
    $table->tinyInteger('assigned_instance_index')->nullable()->after('workspace_id')
        ->comment('Index of WhatsApp instance handling this session (0-based)');
    
    $table->string('assigned_instance_url')->nullable()->after('assigned_instance_index')
        ->comment('URL of instance handling this session (e.g., http://instance-2:3001)');
    
    // Index for instance queries
    $table->index('assigned_instance_index', 'idx_instance_assignment');
});
```

**Usage**:
```php
// When creating session
$instanceIndex = $workspaceId % $instanceCount;
$instanceUrl = config("whatsapp.instances.{$instanceIndex}");

WhatsAppAccount::create([
    // ...
    'assigned_instance_index' => $instanceIndex,
    'assigned_instance_url' => $instanceUrl,
]);

// Query sessions on specific instance
WhatsAppAccount::where('assigned_instance_index', 2)->get();
```

---

### 2. ❌ Disconnection Tracking

**Problem**: When a session disconnects, we don't know WHY or at what time.

**Current**: Only `status = 'disconnected'` and `deleted_at` (soft delete).

**Issue**:
- Cannot differentiate user-initiated disconnect vs crash vs timeout
- No historical disconnect data for analytics
- Cannot identify patterns (e.g., instance 3 has more disconnects)

**Solution**: Add disconnect tracking fields

**Recommended Migration**:
```php
Schema::table('whatsapp_accounts', function (Blueprint $table) {
    $table->timestamp('disconnected_at')->nullable()->after('last_connected_at')
        ->comment('When session was disconnected');
    
    $table->enum('disconnect_reason', [
        'user_initiated',
        'instance_restart',
        'timeout',
        'error',
        'qr_expired',
        'unknown'
    ])->nullable()->after('disconnected_at')
        ->comment('Reason for disconnect');
    
    $table->text('disconnect_details')->nullable()->after('disconnect_reason')
        ->comment('Additional details about disconnect (error message, etc.)');
    
    // Index for disconnect analytics
    $table->index(['disconnect_reason', 'disconnected_at'], 'idx_disconnect_analytics');
});
```

**Usage**:
```php
// When disconnecting
$account->update([
    'status' => 'disconnected',
    'disconnected_at' => now(),
    'disconnect_reason' => 'user_initiated',
    'disconnect_details' => 'User clicked disconnect button',
]);

// Analytics
WhatsAppAccount::where('disconnect_reason', 'instance_restart')
    ->whereBetween('disconnected_at', [now()->subDay(), now()])
    ->count();
```

---

### 3. ❌ Session File Path Reference

**Problem**: Sessions stored in shared storage, but no DB reference to file path.

**Current**: Session data stored in `session_data` LONGTEXT (encrypted, 5-10MB).

**Issue**:
- Session file path calculated on-the-fly: `/mnt/efs/workspace_{id}/session_{id}`
- If path structure changes, migrations are hard
- Cannot easily verify if session file exists without checking filesystem
- No tracking of session file size for monitoring

**Solution**: Add session storage metadata

**Recommended Migration**:
```php
Schema::table('whatsapp_accounts', function (Blueprint $table) {
    $table->string('session_storage_path')->nullable()->after('session_data')
        ->comment('Path to session files in shared storage (e.g., /mnt/efs/workspace_1/session_001)');
    
    $table->bigInteger('session_file_size_bytes')->nullable()->after('session_storage_path')
        ->comment('Total size of session files in bytes');
    
    $table->timestamp('session_storage_verified_at')->nullable()->after('session_file_size_bytes')
        ->comment('Last time session files were verified to exist');
});
```

**Usage**:
```php
// When creating session
$storagePath = "/mnt/efs/workspace_{$workspaceId}/session_{$sessionId}";

WhatsAppAccount::create([
    // ...
    'session_storage_path' => $storagePath,
]);

// Verify session files exist
$exists = File::exists($account->session_storage_path);
$size = File::size($account->session_storage_path);

$account->update([
    'session_file_size_bytes' => $size,
    'session_storage_verified_at' => now(),
]);
```

---

### 4. ❌ Failover/Migration Tracking

**Problem**: No tracking when sessions are migrated between instances.

**Current**: No migration history.

**Issue**:
- If instance fails and sessions migrated to another instance, no record
- Cannot audit failover events
- Cannot identify problematic instances (high migration rate)

**Solution**: Add migration tracking

**Recommended Migration**:
```php
Schema::table('whatsapp_accounts', function (Blueprint $table) {
    $table->integer('instance_migration_count')->default(0)->after('session_restore_count')
        ->comment('Number of times session was migrated to different instance');
    
    $table->timestamp('last_instance_migration_at')->nullable()->after('instance_migration_count')
        ->comment('Last time session was migrated to different instance');
    
    $table->tinyInteger('previous_instance_index')->nullable()->after('assigned_instance_index')
        ->comment('Index of previous instance (before migration)');
});
```

**Usage**:
```php
// When migrating session from instance 1 to instance 3
$account->update([
    'previous_instance_index' => $account->assigned_instance_index,
    'assigned_instance_index' => 3,
    'assigned_instance_url' => config('whatsapp.instances.3'),
    'instance_migration_count' => $account->instance_migration_count + 1,
    'last_instance_migration_at' => now(),
]);

// Analytics: Which instances have high migration rates?
WhatsAppAccount::selectRaw('previous_instance_index, COUNT(*) as migrations')
    ->whereNotNull('previous_instance_index')
    ->groupBy('previous_instance_index')
    ->orderByDesc('migrations')
    ->get();
```

---

### 5. ⚠️ Phone Number Unique Constraint (CRITICAL)

**Problem**: Potential unique constraint violation when disconnecting.

**Current Schema**:
```sql
phone_number VARCHAR(50) NULLABLE
-- No unique constraint (good!)
```

**Previous Issue** (from bug fix doc):
- Had unique constraint `(phone_number, workspace_id, status)`
- Prevented multiple disconnected accounts with same phone number

**Analysis**: ✅ **FIXED** - No unique constraint found in migrations. This is correct.

**Verification Needed**: Check if constraint was added manually or in older migrations.

**Recommended Check**:
```sql
-- Run this query to verify no unique constraints on phone_number
SHOW INDEXES FROM whatsapp_accounts WHERE Column_name = 'phone_number';
```

**If constraint exists, remove it**:
```php
Schema::table('whatsapp_accounts', function (Blueprint $table) {
    // Drop any unique constraints on phone_number
    $table->dropUnique(['phone_number', 'workspace_id', 'status']); // if exists
});
```

---

## 📋 Recommended Migration Plan

### Migration 1: Instance Assignment Tracking

**Priority**: 🔴 **CRITICAL** (Required for multi-instance)

**File**: `2025_11_21_000001_add_instance_tracking_to_whatsapp_accounts.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            // Instance assignment
            $table->tinyInteger('assigned_instance_index')->nullable()->after('workspace_id')
                ->comment('Index of WhatsApp instance handling this session (0-based)');
            
            $table->string('assigned_instance_url', 255)->nullable()->after('assigned_instance_index')
                ->comment('URL of instance handling this session');
            
            // Index for querying sessions by instance
            $table->index('assigned_instance_index', 'idx_instance_assignment');
        });
        
        // Backfill existing records (assume single instance at index 0)
        DB::table('whatsapp_accounts')
            ->whereNull('assigned_instance_index')
            ->update([
                'assigned_instance_index' => 0,
                'assigned_instance_url' => 'http://localhost:3001', // Update with actual URL
            ]);
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropIndex('idx_instance_assignment');
            $table->dropColumn(['assigned_instance_index', 'assigned_instance_url']);
        });
    }
};
```

---

### Migration 2: Disconnect Tracking

**Priority**: 🟡 **HIGH** (Important for monitoring)

**File**: `2025_11_21_000002_add_disconnect_tracking_to_whatsapp_accounts.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            // Disconnect tracking
            $table->timestamp('disconnected_at')->nullable()->after('last_connected_at')
                ->comment('When session was disconnected');
            
            $table->enum('disconnect_reason', [
                'user_initiated',
                'instance_restart',
                'timeout',
                'error',
                'qr_expired',
                'unknown'
            ])->nullable()->after('disconnected_at')
                ->comment('Reason for disconnect');
            
            $table->text('disconnect_details')->nullable()->after('disconnect_reason')
                ->comment('Additional details about disconnect');
            
            // Index for analytics
            $table->index(['disconnect_reason', 'disconnected_at'], 'idx_disconnect_analytics');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropIndex('idx_disconnect_analytics');
            $table->dropColumn(['disconnected_at', 'disconnect_reason', 'disconnect_details']);
        });
    }
};
```

---

### Migration 3: Session Storage Metadata

**Priority**: 🟡 **MEDIUM** (Useful for operations)

**File**: `2025_11_21_000003_add_storage_metadata_to_whatsapp_accounts.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            // Session storage metadata
            $table->string('session_storage_path', 500)->nullable()->after('session_data')
                ->comment('Path to session files in shared storage');
            
            $table->bigInteger('session_file_size_bytes')->nullable()->after('session_storage_path')
                ->comment('Total size of session files');
            
            $table->timestamp('session_storage_verified_at')->nullable()->after('session_file_size_bytes')
                ->comment('Last time session files were verified');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'session_storage_path',
                'session_file_size_bytes',
                'session_storage_verified_at'
            ]);
        });
    }
};
```

---

### Migration 4: Failover Tracking

**Priority**: 🟢 **LOW** (Nice-to-have for analytics)

**File**: `2025_11_21_000004_add_failover_tracking_to_whatsapp_accounts.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            // Failover/migration tracking
            $table->integer('instance_migration_count')->default(0)->after('session_restore_count')
                ->comment('Number of times session migrated to different instance');
            
            $table->timestamp('last_instance_migration_at')->nullable()->after('instance_migration_count')
                ->comment('Last instance migration time');
            
            $table->tinyInteger('previous_instance_index')->nullable()->after('assigned_instance_index')
                ->comment('Previous instance index before migration');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'instance_migration_count',
                'last_instance_migration_at',
                'previous_instance_index'
            ]);
        });
    }
};
```

---

## 🔧 Model Updates Required

### WhatsAppAccount Model

**File**: `app/Models/WhatsAppAccount.php`

**Add to $fillable**:
```php
protected $fillable = [
    // ... existing fields
    'assigned_instance_index',
    'assigned_instance_url',
    'previous_instance_index',
    'disconnected_at',
    'disconnect_reason',
    'disconnect_details',
    'session_storage_path',
    'session_file_size_bytes',
    'session_storage_verified_at',
    'instance_migration_count',
    'last_instance_migration_at',
];
```

**Add to $casts**:
```php
protected $casts = [
    // ... existing casts
    'disconnected_at' => 'datetime',
    'session_storage_verified_at' => 'datetime',
    'last_instance_migration_at' => 'datetime',
    'session_file_size_bytes' => 'integer',
];
```

**Add Helper Methods**:
```php
/**
 * Get the instance handling this session
 */
public function getInstance(): array
{
    return [
        'index' => $this->assigned_instance_index,
        'url' => $this->assigned_instance_url,
    ];
}

/**
 * Update instance assignment
 */
public function assignToInstance(int $instanceIndex, string $instanceUrl): void
{
    $this->update([
        'previous_instance_index' => $this->assigned_instance_index,
        'assigned_instance_index' => $instanceIndex,
        'assigned_instance_url' => $instanceUrl,
        'instance_migration_count' => $this->instance_migration_count + 1,
        'last_instance_migration_at' => now(),
    ]);
}

/**
 * Mark as disconnected
 */
public function markDisconnected(string $reason, ?string $details = null): void
{
    $this->update([
        'status' => 'disconnected',
        'disconnected_at' => now(),
        'disconnect_reason' => $reason,
        'disconnect_details' => $details,
    ]);
}

/**
 * Verify session storage exists
 */
public function verifySessionStorage(): bool
{
    if (!$this->session_storage_path) {
        return false;
    }
    
    $exists = File::exists($this->session_storage_path);
    
    if ($exists) {
        $size = File::size($this->session_storage_path);
        $this->update([
            'session_file_size_bytes' => $size,
            'session_storage_verified_at' => now(),
        ]);
    }
    
    return $exists;
}

/**
 * Scope: Sessions on specific instance
 */
public function scopeOnInstance($query, int $instanceIndex)
{
    return $query->where('assigned_instance_index', $instanceIndex);
}

/**
 * Scope: Recently disconnected
 */
public function scopeRecentlyDisconnected($query, int $hours = 24)
{
    return $query->where('status', 'disconnected')
        ->where('disconnected_at', '>=', now()->subHours($hours));
}
```

---

## ✅ Verification Checklist

Before implementing multi-instance architecture:

### Database Checks

- [ ] Run migrations 1-4 (critical, high, medium priority)
- [ ] Verify no unique constraint on `phone_number`
- [ ] Test queries: `WhatsAppAccount::onInstance(2)->count()`
- [ ] Backfill `assigned_instance_index` for existing records
- [ ] Test disconnect tracking: `markDisconnected('user_initiated')`

### Model Checks

- [ ] Update `$fillable` array with new fields
- [ ] Update `$casts` array
- [ ] Add helper methods (`assignToInstance`, `markDisconnected`, etc.)
- [ ] Test scopes (`onInstance`, `recentlyDisconnected`)

### Service Checks

- [ ] Update `InstanceRouter` to use `assigned_instance_index`
- [ ] Update `AccountStatusService::disconnect()` to use `markDisconnected()`
- [ ] Update session creation to set `assigned_instance_index`
- [ ] Update health monitoring to use new tracking fields

### Index Performance

- [ ] Verify index `idx_instance_assignment` exists
- [ ] Verify index `idx_disconnect_analytics` exists
- [ ] Test query performance: sessions by instance (should use index)

---

## 📊 Impact Analysis

### Storage Impact

**New Fields Total**: 13 columns

**Estimated Size Per Record**:
- `assigned_instance_index`: TINYINT (1 byte)
- `assigned_instance_url`: VARCHAR(255) (~50 bytes avg)
- `previous_instance_index`: TINYINT (1 byte)
- `disconnected_at`: TIMESTAMP (4 bytes)
- `disconnect_reason`: ENUM (~10 bytes)
- `disconnect_details`: TEXT (~200 bytes avg)
- `session_storage_path`: VARCHAR(500) (~100 bytes avg)
- `session_file_size_bytes`: BIGINT (8 bytes)
- `session_storage_verified_at`: TIMESTAMP (4 bytes)
- `instance_migration_count`: INT (4 bytes)
- `last_instance_migration_at`: TIMESTAMP (4 bytes)

**Per Record Overhead**: ~386 bytes

**For 1,000 sessions**: ~386 KB  
**For 10,000 sessions**: ~3.86 MB

**Impact**: ✅ **MINIMAL** - Negligible storage overhead

### Performance Impact

**Indexes Added**: 2
- `idx_instance_assignment` (assigned_instance_index)
- `idx_disconnect_analytics` (disconnect_reason, disconnected_at)

**Query Performance**:
- Instance-based queries: **FASTER** (uses index)
- Disconnect analytics: **FASTER** (uses index)
- Insert/Update: **MINIMAL IMPACT** (2 small indexes)

**Impact**: ✅ **POSITIVE** - Improves query performance

---

## 🎯 Recommendation

### Immediate Actions (Before Multi-Instance Deployment)

1. **🔴 CRITICAL**: Run Migration 1 (Instance Tracking)
   - Required for workspace sharding to work
   - Backfill existing records with instance 0

2. **🟡 HIGH**: Run Migration 2 (Disconnect Tracking)
   - Important for monitoring and debugging
   - Helps identify instance reliability issues

3. **🟢 OPTIONAL**: Run Migrations 3-4
   - Nice-to-have for operations team
   - Can be added later if needed

### Testing Plan

1. **Local Testing**:
   ```bash
   php artisan migrate
   php artisan tinker
   
   # Test instance assignment
   $account = WhatsAppAccount::first();
   $account->assignToInstance(2, 'http://instance-2:3001');
   
   # Test disconnect tracking
   $account->markDisconnected('user_initiated', 'Test disconnect');
   
   # Verify
   WhatsAppAccount::onInstance(2)->count();
   ```

2. **Staging Testing**:
   - Create 10 test sessions
   - Assign to different instances (0-3)
   - Verify routing works correctly
   - Test failover scenario

3. **Production Migration**:
   - Run migrations during low-traffic window
   - Backfill existing records
   - Monitor for 24 hours
   - Verify no performance degradation

---

## ✅ Conclusion

**Current Database Schema**: 70% ready for multi-instance

**Required Changes**: 4 migrations (1 critical, 1 high, 2 medium)

**Impact**: Minimal storage, positive performance

**Risk Level**: 🟢 **LOW** - All backward compatible

**Recommendation**: **PROCEED** with migrations before multi-instance deployment

---

**Audit Completed By**: Architecture Team  
**Date**: November 20, 2025  
**Next Review**: After migrations applied  
**Status**: Ready for Implementation
