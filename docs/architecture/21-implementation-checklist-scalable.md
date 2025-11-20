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

#### Week 2: Laravel Integration

**Day 1-2: Instance Router**
- [ ] Create `app/Services/WhatsApp/InstanceRouter.php`
  ```php
  public function getInstanceForWorkspace(int $workspaceId): string
  {
      $instanceCount = config('whatsapp.instance_count');
      $instanceIndex = $workspaceId % $instanceCount;
      return config("whatsapp.instances.{$instanceIndex}");
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

**Day 3: Proxy Controller**
- [ ] Create `app/Http/Controllers/WhatsApp/ProxyController.php`
  ```php
  public function createSession(Request $request)
  {
      $workspaceId = $request->workspace_id;
      $targetInstance = $this->router->getInstanceForWorkspace($workspaceId);
      
      $response = Http::post("{$targetInstance}/api/sessions/create", [
          'session_id' => $request->session_id,
          'workspace_id' => $workspaceId,
          'account_id' => $request->account_id,
      ]);
      
      return $response->json();
  }
  ```
- [ ] Implement for all WhatsApp endpoints:
  - POST `/api/sessions/create`
  - DELETE `/api/sessions/{id}`
  - POST `/api/messages/send`
  - GET `/api/sessions/{id}/status`

**Day 4-5: Testing**
- [ ] Test workspace sharding logic:
  - Workspace 1 → Instance 1
  - Workspace 2 → Instance 2
  - Workspace 3 → Instance 1
  - Workspace 4 → Instance 2
- [ ] Create 10 test sessions across both instances
- [ ] Verify sessions persist after restart
- [ ] Test message sending through proxy
- [ ] Load test: 50 concurrent session creations

**Phase 1 Deliverables**:
- [ ] ✅ 2 WhatsApp instances deployed and operational
- [ ] ✅ Shared storage working (sessions persist)
- [ ] ✅ Laravel routing to correct instance
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

**Day 4-5: Session Migration Tool**
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
          }
          
          return { migrated: sessions.length };
      }
  }
  ```
- [ ] Test migration:
  - Migrate workspace 5 from instance-1 to instance-2
  - Verify sessions work on new instance
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
  - [ ] Verify QR codes regenerate if needed
  - [ ] Test message sending
  - [ ] Monitor for 1 hour
- [ ] Document any issues encountered
- [ ] Update migration playbook

**Day 4-5: Migrate Medium-Traffic Workspaces**
- [ ] Select next batch (10-20 workspaces)
- [ ] Schedule during low-traffic hours
- [ ] Follow migration procedure
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
  5. Verify all sessions operational
  6. Re-enable new session creation
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
