# Implementation Checklist: Scalable Architecture Migration

**Based On**: `20-realistic-scalable-architecture-v2.md`  
**Target**: 1,000-3,000 concurrent WhatsApp sessions  
**Timeline**: 6 weeks  
**Status**: 🟡 Planning Phase

---

## 📋 Pre-Implementation Checklist

###Infrastructure Preparation

- [ ] **Decide on Shared Storage Solution**
  - [ ] Option A: AWS EFS (recommended for AWS)
  - [ ] Option B: GlusterFS  (on-premise/cloud-agnostic)
  - [ ] Option C: NFS Server (budget option)
  
- [ ] **Provision Infrastructure**
  - [ ] Shared storage volume (100GB minimum)
  - [ ] 2 WhatsApp Node.js instances (testing)
  - [ ] 1 Laravel server (existing can be used)
  - [ ] 1 Redis server (for instance registry)
  - [ ] Monitoring server (Prometheus + Grafana)

- [ ] **Network Configuration**
  - [ ] VPC/Network setup with proper security groups
  - [ ] Inter-instance communication allowed
  - [ ] NFS/EFS mount points configured
  - [ ] NAT Gateway for outbound WhatsApp connections

- [ ] **Database Preparation** 🔴 **CRITICAL**
  - [ ] Review database audit: `docs/architecture/13-database-schema-audit-multi-instance.md`
  - [ ] Backup production database before migrations
  - [ ] Verify migrations exist in `database/migrations/`:
    - [ ] `2025_11_20_151825_add_instance_tracking_to_whatsapp_accounts.php`
    - [ ] `2025_11_20_151833_add_disconnect_tracking_to_whatsapp_accounts.php`
    - [ ] `2025_11_20_151839_add_storage_metadata_to_whatsapp_accounts.php`
    - [ ] `2025_11_20_151846_add_failover_tracking_to_whatsapp_accounts.php`
  - [ ] Test migrations on staging environment
  - [ ] Verify rollback plan works
  - [ ] Check for unique constraints on `phone_number` field

---

## 🎯 Phase 1: Foundation (Week 1-2)

### Goal: Setup multi-instance infrastructure with 2 instances

#### Week 1: Infrastructure Setup

**Day 1-2: Shared Storage**
- [ ] Provision shared storage (EFS/GlusterFS/NFS)
- [ ] Mount storage on first test instance: `/mnt/whatsapp-sessions`
- [ ] Test read/write permissions
- [ ] Verify performance (latency < 10ms)
- [ ] Setup directory structure:
  ```
  /mnt/whatsapp-sessions/
  ├── workspace_1/
  ├── workspace_2/
  └── backups/
  ```

**Day 3-4: WhatsApp Instance Setup**
- [ ] Clone whatsapp-service code to new server
- [ ] Create instance-specific config:
  ```env
  INSTANCE_ID=instance-1
  MAX_SESSIONS_PER_INSTANCE=500
  SESSION_STORAGE_PATH=/mnt/whatsapp-sessions
  LARAVEL_API_URL=http://laravel-server:8000
  ```
- [ ] Mount shared storage on instance
- [ ] Test session creation with LocalAuth on mounted storage
- [ ] Verify session persists after service restart

**Day 5: Second Instance**
- [ ] Deploy second WhatsApp instance (instance-2)
- [ ] Mount same shared storage
- [ ] Configure with unique INSTANCE_ID
- [ ] Test both instances can access sessions folder
- [ ] Verify no file locking conflicts

**Weekend: Monitoring Setup**
- [ ] Install Prometheus on monitoring server
- [ ] Configure Prometheus to scrape:
  - Laravel metrics endpoint
  - WhatsApp instance-1 health endpoint
  - WhatsApp instance-2 health endpoint
- [ ] Install Grafana
- [ ] Import dashboard templates
- [ ] Setup basic alerts (email/Slack)

#### Week 2: Database Preparation & Laravel Integration

> **📋 Reference**: See `docs/architecture/13-database-schema-audit-multi-instance.md` for complete database requirements analysis.

**Day 1: Database Schema Updates** 🔴 **CRITICAL**

- [ ] **Review Database Audit Report**
  - [ ] Read `docs/architecture/13-database-schema-audit-multi-instance.md`
  - [ ] Understand 4 required migrations
  - [ ] Identify impact on existing code

- [ ] **Run Critical Migration 1: Instance Tracking** (MANDATORY)
  ```bash
  # Location: database/migrations/2025_11_20_151825_add_instance_tracking_to_whatsapp_accounts.php
  php artisan migrate --step
  ```
  - [ ] Verify migration success: `php artisan migrate:status`
  - [ ] Verify backfill: Check all records have `assigned_instance_index`
    ```bash
    php artisan tinker
    # WhatsAppAccount::whereNull('assigned_instance_index')->count()
    # Should return 0
    ```
  - [ ] Test query by instance:
    ```bash
    # WhatsAppAccount::where('assigned_instance_index', 0)->count()
    # Should return number of accounts
    ```

- [ ] **Run High Priority Migration 2: Disconnect Tracking** (RECOMMENDED)
  ```bash
  # Location: database/migrations/2025_11_20_151833_add_disconnect_tracking_to_whatsapp_accounts.php
  php artisan migrate --step
  ```
  - [ ] Verify new columns exist:
    ```sql
    DESCRIBE whatsapp_accounts;
    # Should show: disconnected_at, disconnect_reason, disconnect_details
    ```
  - [ ] Verify index created:
    ```sql
    SHOW INDEXES FROM whatsapp_accounts WHERE Key_name = 'idx_disconnect_analytics';
    ```

- [ ] **Optional: Run Migration 3 & 4** (Can defer to later)
  ```bash
  # Migration 3: Storage metadata
  php artisan migrate --step
  
  # Migration 4: Failover tracking
  php artisan migrate --step
  ```

- [ ] **Update WhatsAppAccount Model**
  - [ ] Add new fields to `$fillable`:
    ```php
    // app/Models/WhatsAppAccount.php
    'assigned_instance_index',
    'assigned_instance_url',
    'previous_instance_index',
    'disconnected_at',
    'disconnect_reason',
    'disconnect_details',
    // Optional (if migration 3-4 run):
    'session_storage_path',
    'session_file_size_bytes',
    'session_storage_verified_at',
    'instance_migration_count',
    'last_instance_migration_at',
    ```
  - [ ] Add to `$casts`:
    ```php
    'disconnected_at' => 'datetime',
    'session_storage_verified_at' => 'datetime',
    'last_instance_migration_at' => 'datetime',
    ```
  - [ ] Add helper methods:
    - [ ] `assignToInstance(int $index, string $url)`
    - [ ] `markDisconnected(string $reason, ?string $details = null)`
    - [ ] `scopeOnInstance($query, int $instanceIndex)`
    - [ ] `scopeRecentlyDisconnected($query, int $hours = 24)`

- [ ] **Verify Database Constraints** (CRITICAL)
  ```sql
  -- Check for problematic unique constraints on phone_number
  SHOW INDEXES FROM whatsapp_accounts WHERE Column_name = 'phone_number';
  ```
  - [ ] If unique constraint exists on `(phone_number, workspace_id, status)`:
    - [ ] Create migration to drop it
    - [ ] Run and verify

- [ ] **Test Database Changes**
  ```bash
  php artisan tinker
  ```
  ```php
  // Test instance assignment
  $account = WhatsAppAccount::first();
  $account->assignToInstance(1, 'http://instance-1:3001');
  
  // Test disconnect tracking
  $account->markDisconnected('user_initiated', 'Test disconnect');
  
  // Test scopes
  WhatsAppAccount::onInstance(0)->count();
  WhatsAppAccount::recentlyDisconnected(24)->count();
  ```

- [ ] **Rollback Plan Verification**
  ```bash
  # Test rollback capability (on dev environment only!)
  php artisan migrate:rollback --step=1
  # Verify data intact
  # Re-run migration
  php artisan migrate --step
  ```

**Day 2: Instance Router & Config**
- [ ] Create `app/Services/WhatsApp/InstanceRouter.php`
  ```php
  public function getInstanceForWorkspace(int $workspaceId): string
  {
      $instanceCount = config('whatsapp.instance_count');
      $instanceIndex = $workspaceId % $instanceCount;
      return config("whatsapp.instances.{$instanceIndex}");
  }
  
  public function getInstanceIndex(int $workspaceId): int
  {
      return $workspaceId % config('whatsapp.instance_count');
  }
  ```
- [ ] Add config file `config/whatsapp.php`:
  ```php
  return [
      'instance_count' => env('WHATSAPP_INSTANCE_COUNT', 2),
      'instances' => [
          0 => env('WHATSAPP_INSTANCE_1', 'http://localhost:3001'),
          1 => env('WHATSAPP_INSTANCE_2', 'http://localhost:3002'),
      ],
  ];
  ```
- [ ] Update `.env`:
  ```
  WHATSAPP_INSTANCE_COUNT=2
  WHATSAPP_INSTANCE_1=http://whatsapp-instance-1:3001
  WHATSAPP_INSTANCE_2=http://whatsapp-instance-2:3001
  ```

**Day 3: Proxy Controller with Database Integration**
- [ ] Create `app/Http/Controllers/WhatsApp/ProxyController.php`
  ```php
  public function createSession(Request $request)
  {
      $workspaceId = $request->workspace_id;
      
      // Get target instance
      $instanceIndex = $this->router->getInstanceIndex($workspaceId);
      $targetInstance = $this->router->getInstanceForWorkspace($workspaceId);
      
      // Create session via Node.js
      $response = Http::post("{$targetInstance}/api/sessions/create", [
          'session_id' => $request->session_id,
          'workspace_id' => $workspaceId,
          'account_id' => $request->account_id,
      ]);
      
      // Update database with instance assignment
      if ($response->successful()) {
          $account = WhatsAppAccount::where('session_id', $request->session_id)->first();
          if ($account) {
              $account->assignToInstance($instanceIndex, $targetInstance);
          }
      }
      
      return $response->json();
  }
  
  public function disconnect(Request $request, string $sessionId)
  {
      $account = WhatsAppAccount::where('session_id', $sessionId)->first();
      
      if (!$account) {
          return response()->json(['error' => 'Session not found'], 404);
      }
      
      // Call instance to disconnect
      $response = Http::delete("{$account->assigned_instance_url}/api/sessions/{$sessionId}");
      
      // Update database with disconnect reason
      if ($response->successful()) {
          $account->markDisconnected(
              $request->input('reason', 'user_initiated'),
              $request->input('details')
          );
      }
      
      return $response->json();
  }
  ```
- [ ] Implement for all WhatsApp endpoints with DB tracking:
  - POST `/api/sessions/create` → Update `assigned_instance_index`
  - DELETE `/api/sessions/{id}` → Update `disconnect_reason`, `disconnected_at`
  - POST `/api/messages/send` → Update `last_activity_at`
  - GET `/api/sessions/{id}/status` → Read from `assigned_instance_url`

**Day 4-5: Testing**
- [ ] Test workspace sharding logic:
  - Workspace 1 → Instance 1
  - Workspace 2 → Instance 2
  - Workspace 3 → Instance 1
  - Workspace 4 → Instance 2
- [ ] Create 10 test sessions across both instances
- [ ] **Verify Database Instance Assignment**:
  ```bash
  php artisan tinker
  ```
  ```php
  // Verify all sessions have instance assigned
  WhatsAppAccount::whereNull('assigned_instance_index')->count(); // Should be 0
  
  // Check distribution across instances
  WhatsAppAccount::selectRaw('assigned_instance_index, COUNT(*) as count')
      ->groupBy('assigned_instance_index')
      ->get();
  // Should show roughly equal distribution
  
  // Verify instance URLs populated
  WhatsAppAccount::whereNull('assigned_instance_url')->count(); // Should be 0
  ```
- [ ] **Test Disconnect Tracking**:
  ```php
  // Create a test account
  $account = WhatsAppAccount::factory()->create();
  
  // Test disconnect via controller
  $response = $this->deleteJson("/api/sessions/{$account->session_id}", [
      'reason' => 'testing',
      'details' => 'Test disconnect tracking'
  ]);
  
  // Verify database updated
  $account->refresh();
  assert($account->status === 'disconnected');
  assert($account->disconnect_reason === 'testing');
  assert($account->disconnected_at !== null);
  ```
- [ ] Verify sessions persist after restart
- [ ] Test message sending through proxy
- [ ] Load test: 50 concurrent session creations

**Phase 1 Deliverables**:
- [ ] ✅ 2 WhatsApp instances deployed and operational
- [ ] ✅ Shared storage working (sessions persist)
- [ ] ✅ Laravel routing to correct instance
- [ ] ✅ **Database migrations applied successfully** (NEW)
- [ ] ✅ **Instance assignment tracking functional** (NEW)
- [ ] ✅ **Disconnect tracking operational** (NEW)
- [ ] ✅ Monitoring dashboards showing metrics
- [ ] ✅ 100+ test sessions created successfully

---

## 🚀 Phase 2: Scaling Logic (Week 3-4)

### Goal: Implement production-ready sharding with 4 instances

#### Week 3: Expand to 4 Instances

**Day 1-2: Deploy Instances 3 & 4**
- [ ] Provision 2 more EC2/VMs for instances 3 and 4
- [ ] Deploy whatsapp-service code
- [ ] Mount shared storage on both
- [ ] Configure:
  ```env
  # Instance 3
  INSTANCE_ID=instance-3
  
  # Instance 4
  INSTANCE_ID=instance-4
  ```
- [ ] Update Laravel config:
  ```env
  WHATSAPP_INSTANCE_COUNT=4
  WHATSAPP_INSTANCE_3=http://whatsapp-instance-3:3001
  WHATSAPP_INSTANCE_4=http://whatsapp-instance-4:3001
  ```

**Day 3: Health Check System**
- [ ] Implement `app/Services/WhatsApp/HealthMonitor.php`
  ```php
  public function checkAllInstances(): array
  {
      $instances = config('whatsapp.instances');
      $health = [];
      
      foreach ($instances as $idx => $url) {
          try {
              $response = Http::timeout(5)->get("{$url}/health");
              $health[$idx] = [
                  'url' => $url,
                  'status' => $response->successful() ? 'healthy' : 'unhealthy',
                  'sessions' => $response->json('sessions.total'),
                  'utilization' => $response->json('sessions.utilization'),
              ];
          } catch (\Exception $e) {
              $health[$idx] = [
                  'url' => $url,
                  'status' => 'unreachable',
                  'error' => $e->getMessage(),
              ];
          }
      }
      
      return $health;
  }
  ```
- [ ] Create artisan command: `php artisan whatsapp:health-check`
- [ ] Add to scheduler (every 1 minute):
  ```php
  $schedule->command('whatsapp:health-check')->everyMinute();
  ```

**Day 4-5: Session Migration Tool with DB Tracking**
- [ ] Create `whatsapp-service/src/utils/SessionMigration.js`
  ```javascript
  class SessionMigration {
      async migrateWorkspace(workspaceId, fromInstance, toInstance) {
          // 1. Get all sessions for workspace from source instance
          const sessions = await this.listWorkspaceSessions(fromInstance, workspaceId);
          
          // 2. Stop sessions on source
          for (const session of sessions) {
              await this.stopSession(fromInstance, session.id);
          }
          
          // 3. Sessions auto-accessible from shared storage
          // 4. Initialize sessions on target instance
          for (const session of sessions) {
              await this.startSession(toInstance, session.id, workspaceId);
              
              // 5. Call Laravel API to update database assignment
              await this.updateDatabaseAssignment(session.id, toInstance);
          }
          
          return { migrated: sessions.length };
      }
  }
  ```
- [ ] Create Laravel Endpoint for Migration Update:
  - POST `/api/internal/session/migrated`
  - Controller Logic:
    ```php
    public function migrated(Request $request)
    {
        $account = WhatsAppAccount::where('session_id', $request->session_id)->first();
        $account->assignToInstance(
            $request->target_instance_index,
            $request->target_instance_url
        );
        return response()->json(['success' => true]);
    }
    ```
- [ ] Test migration:
  - Migrate workspace 5 from instance-1 to instance-2
  - Verify sessions work on new instance
  - **Verify Database Updated**:
    ```php
    $account = WhatsAppAccount::where('workspace_id', 5)->first();
    assert($account->assigned_instance_index === 1);
    assert($account->previous_instance_index === 0);
    assert($account->instance_migration_count === 1);
    ```
  - Verify no data loss

#### Week 4: Backup & Reliability

**Day 1-2: Automated Backups**
- [ ] Create backup script `/usr/local/bin/backup-sessions.sh`
- [ ] Setup S3 bucket or backup storage
- [ ] Configure AWS CLI or backup tool
- [ ] Test backup:
  ```bash
  ./backup-sessions.sh
  # Verify tar.gz created
  # Verify upload to S3
  ```
- [ ] Add to cron:
  ```
  0 * * * * /usr/local/bin/backup-sessions.sh >> /var/log/session-backup.log 2>&1
  ```

**Day 3: Disaster Recovery Test**
- [ ] Create restore script `/usr/local/bin/restore-sessions.sh`
- [ ] Simulate disaster:
  ```bash
  # Delete all sessions
  rm -rf /mnt/whatsapp-sessions/workspace_*
  
  # Restore from backup
  ./restore-sessions.sh sessions-20251120-1400.tar.gz
  
  # Restart instances
  # Verify sessions restored
  ```
- [ ] Document DR procedures in runbook
- [ ] Time the recovery (target: < 30 minutes)

**Day 4-5: Load Testing**
- [ ] Setup load testing tool (k6, Artillery, or custom)
- [ ] Create test scenarios:
  - 1000 concurrent session creations
  - 1000 concurrent message sends
  - Mixed operations (create, send, status check)
- [ ] Run tests:
  ```bash
  # 1000 sessions across 4 instances (250 each)
  k6 run --vus 1000 --duration 30m load-test.js
  ```
- [ ] Monitor:
  - CPU usage per instance
  - Memory usage
  - Response times
  - Error rates
- [ ] Document results and optimize if needed

**Phase 2 Deliverables**:
- [ ] ✅ 4 instances operational (1000 session capacity)
- [ ] ✅ Health monitoring active with alerts
- [ ] ✅ Automated backups running hourly
- [ ] ✅ Session migration tool tested
- [ ] ✅ Load test passed (1000 concurrent sessions)
- [ ] ✅ DR procedures documented and tested

---

## 🌐 Phase 3: Production Deployment (Week 5-6)

### Goal: Migrate production workspaces to sharded architecture

#### Week 5: Gradual Migration

**Day 1: Pre-Migration Audit**
- [ ] List all current active sessions
- [ ] Group by workspace
- [ ] Identify high-traffic vs low-traffic workspaces
- [ ] Create migration plan spreadsheet:
  ```
  Workspace ID | Current Sessions | Traffic Level | Target Instance | Migration Date
  1            | 45               | High          | Instance-1      | Nov 25
  2            | 12               | Low           | Instance-2      | Nov 21
  ...
  ```

**Day 2-3: Migrate Low-Traffic Workspaces**
- [ ] Select 5-10 low-traffic workspaces
- [ ] For each workspace:
  - [ ] Backup current sessions
  - [ ] Stop sessions on old server
  - [ ] Copy session data to shared storage
  - [ ] Start sessions on assigned instance
  - [ ] **Update Database Assignment**:
    ```bash
    # Use the migration tool or API
    curl -X POST /api/internal/session/migrated \
      -d "session_id=..." -d "target_instance_index=..."
    ```
  - [ ] **Verify Routing**:
    ```php
    // Ensure InstanceRouter returns new instance
    assert($router->getInstanceForWorkspace($wsId) === $newInstanceUrl);
    ```
  - [ ] Verify QR codes regenerate if needed
  - [ ] Test message sending
  - [ ] Monitor for 1 hour
- [ ] Document any issues encountered
- [ ] Update migration playbook

**Day 4-5: Migrate Medium-Traffic Workspaces**
- [ ] Select next batch (10-20 workspaces)
- [ ] Schedule during low-traffic hours
- [ ] Follow migration procedure (including DB update)
- [ ] Longer monitoring period (4 hours)
- [ ] Performance comparison (before/after)

#### Week 6: Complete Migration & Optimization

**Day 1-2: Migrate High-Traffic Workspaces**
- [ ] Final batch: high-traffic workspaces
- [ ] Schedule maintenance window
- [ ] Migration steps:
  1. Notify users (1 hour advance)
  2. Stop new sessions on old server
  3. Migrate existing sessions
  4. Start on new instances
  5. **Update Database Assignments** (Batch update)
  6. Verify all sessions operational
  7. **Verify Instance Distribution**:
     ```php
     WhatsAppAccount::selectRaw('assigned_instance_index, count(*)')->groupBy('assigned_instance_index')->get();
     ```
  8. Re-enable new session creation
- [ ] Extended monitoring (24 hours)

**Day 3: Decommission Old Server**
- [ ] Verify all workspaces migrated
- [ ] Final backup of old server sessions
- [ ] Stop WhatsApp service on old server
- [ ] Archive session data
- [ ] Update DNS/load balancer if applicable
- [ ] Document old server specs for reference

**Day 4-5: Optimization & Tuning**
- [ ] Review metrics from first week:
  - Average response times
  - CPU/memory utilization per instance
  - Session distribution balance
- [ ] Rebalance if needed:
  - Move workspaces between instances
  - Adjust MAX_SESSIONS_PER_INSTANCE
- [ ] Fine-tune alerts:
  - CPU > 80% for 10 min
  - Memory > 85% for 5 min
  - Session count > 90% capacity
  - Instance unreachable for 2 min
- [ ] Update documentation with learnings

**Phase 3 Deliverables**:
- [ ] ✅ All production workspaces on sharded architecture
- [ ] ✅ Old single-server decommissioned
- [ ] ✅ 1000+ concurrent sessions running stably
- [ ] ✅ Monitoring and alerts optimized
- [ ] ✅ Team trained on new architecture
- [ ] ✅ Operations runbook complete and tested

---

## Phase 4: Scaling to 3,000 Sessions (Future)

### Triggers
- Active session count > 800 (80% of 1000 capacity)
- Consistent growth trend
- Business forecast indicates scaling needed

### Checklist
- [ ] Provision 4 additional instances (8 total)
- [ ] Update Laravel config: `WHATSAPP_INSTANCE_COUNT=8`
- [ ] Rebalance existing workspaces across 8 instances
- [ ] Load test with 3000 concurrent sessions
- [ ] Monitor performance for 1 week
- [ ] Update cost projections

---

## 📊 Success Metrics

### Technical Metrics

- [ ] **Capacity**: Support 1,000+ concurrent sessions
- [ ] **Performance**: 
  - QR generation < 10s (95th percentile)
  - Message send < 2s (95th percentile)
  - API response < 1s (median)
- [ ] **Reliability**:
  - 99.5% uptime for sessions
  - Instance failure recovery < 5 min
  - Zero data loss on failures
- [ ] **Scalability**:
  - Linear scaling validated (add instance = +250-500 sessions)
  - Can scale to 3,000 sessions by adding instances

### Operational Metrics

- [ ] **Automation**:
  - Backups running hourly (0 manual interventions)
  - Health checks automated (every minute)
  - Alerts configured and tested
- [ ] **Documentation**:
  - Runbook complete with all procedures
  - Architecture diagrams up to date
  - DR procedures tested quarterly
- [ ] **Team Readiness**:
  - All team members trained
  - On-call rotation established
  - Escalation procedures defined

---

## 🚨 Rollback Plan

### If Migration Fails

**Scenario**: Issues discovered during Phase 3 migration

**Actions**:
1. **Stop Migration**
   - Halt migration of remaining workspaces
   - Assess scope of issues

2. **Rollback Migrated Workspaces**
   - Restore from backup to old server
   - Update routing to point to old server
   - Verify sessions functional

3. **Root Cause Analysis**
   - Identify what went wrong
   - Document lessons learned
   - Update procedures

4. **Plan Re-attempt**
   - Fix identified issues
   - Retest in staging
   - Schedule new migration window

### Emergency Procedures

**Total System Failure**:
```bash
# 1. Restore from latest backup
aws s3 cp s3://blazz-backups/hourly/latest.tar.gz /tmp/

# 2. Stop all instances
systemctl stop whatsapp-instance-*

# 3. Restore data
tar -xzf /tmp/latest.tar.gz -C /mnt/whatsapp-sessions/

# 4. Start instances one by one
systemctl start whatsapp-instance-1
# Wait 2 min, verify health
systemctl start whatsapp-instance-2
# Continue...

# 5. Verify all workspaces operational
php artisan whatsapp:verify-all-sessions
```

---

## 📚 Required Documentation

### Before Starting
- [ ] `docs/architecture/20-realistic-scalable-architecture-v2.md` reviewed
- [ ] `docs/architecture/19-architecture-compliance-analysis.md` reviewed
- [ ] Team understands workspace sharding concept

### To Create During Implementation
- [ ] `docs/operations/whatsapp-instances-runbook.md`
  - Starting/stopping instances
  - Health check procedures
  - Session migration steps
  - Backup/restore procedures
  - Failover triggers
  - Troubleshooting guide
  
- [ ] `docs/operations/monitoring-playbook.md`
  - Grafana dashboard guide
  - Metrics to watch
  - Alert response procedures
  - Capacity planning formulas
  
- [ ] `docs/architecture/deployment-topology.md`
  - Instance IP addresses
  - Network diagram
  - Security group rules
  - Storage mount points
  - Service dependencies

---

## ✅ Final Checklist

### Pre-Production
- [ ] All 4 instances deployed and healthy
- [ ] Shared storage configured with redundancy
- [ ] Backups running and tested
- [ ] Monitoring dashboards complete
- [ ] Alerts configured and tested
- [ ] Load tests passed
- [ ] DR procedures validated
- [ ] Team trained
- [ ] Documentation complete

### Production Ready
- [ ] Migration plan approved
- [ ] Maintenance windows scheduled
- [ ] User communication prepared
- [ ] Rollback procedures tested
- [ ] Support team on standby
- [ ] All stakeholders notified

### Post-Migration
- [ ] All workspaces migrated successfully
- [ ] Performance metrics within targets
- [ ] No critical alerts in 48 hours
- [ ] User feedback positive
- [ ] Team confident with new system
- [ ] Post-mortem completed
- [ ] Lessons learned documented

---

**Checklist Version**: 1.0  
**Last Updated**: November 20, 2025  
**Owner**: DevOps + Backend Team  
**Review Frequency**: Weekly during implementation
