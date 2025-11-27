# Hybrid Campaign System - Rollback Plan

## Overview

This document provides comprehensive rollback procedures for the Hybrid Campaign System deployment, including emergency procedures, validation steps, and recovery protocols.

## Rollback Triggers

### Automatic Rollback Triggers
- **Error Rate > 5%**: System error rate exceeds 5% for 10+ minutes
- **Response Time > 10 seconds**: Average API response time exceeds 10 seconds
- **System Availability < 95%**: Downtime exceeds 5% for 5+ minutes
- **Database Corruption**: Data integrity issues detected
- **Memory Usage > 90%**: System memory consumption critical

### Manual Rollback Triggers
- **Critical Bugs**: Production bugs affecting core functionality
- **Performance Degradation**: Significant performance impact
- **User Reports**: Widespread user complaints about issues
- **Security Concerns**: Potential security vulnerabilities
- **Business Impact**: Campaign failures affecting business operations

## Pre-Rollback Checklist

### ✅ Assessment Phase

#### Impact Analysis
```bash
# Assess current system status
curl -I https://your-domain.com/api/health
curl -X GET https://your-domain.com/api/campaigns/status

# Check error rates in logs
grep -i "error\|exception" storage/logs/laravel.log | tail -20
grep "HybridCampaign\|campaign.*error" storage/logs/laravel.log | wc -l

# Monitor active campaigns
php artisan tinker
>>> \App\Models\Campaign::where('status', 'ongoing')->count();
```

#### User Impact Assessment
```sql
-- Check active campaigns count
SELECT
    campaign_type,
    status,
    COUNT(*) as count
FROM campaigns
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY campaign_type, status;

-- Check failed campaigns in last hour
SELECT
    COUNT(*) as failed_count,
    AVG(TIMESTAMPDIFF(MICROSECOND, created_at, updated_at) / 1000) as avg_error_time_ms
FROM campaigns
WHERE status = 'failed'
  AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

#### Backup Verification
```bash
# Verify database backup exists and is valid
ls -la /backups/database/backup_$(date +%Y%m%d)*.sql

# Test backup integrity
mysql -u username -p -e "SHOW TABLES;" blazz < /backups/database/latest_backup.sql

# Verify code backup
git log --oneline -5
git status
```

## Emergency Rollback Procedures

### 🚨 Level 1: Immediate Rollback (< 5 minutes)

#### Frontend Rollback
```bash
# Revert to previous frontend build
cd /path/to/app
git checkout PREVIOUS_COMMIT_HASH
git checkout HEAD~1 public/build/

# Restart web server
sudo systemctl restart nginx
sudo systemctl restart php-fpm

# Clear frontend caches
rm -rf public/build/*
npm run build
```

#### API Response Rollback
```bash
# Disable hybrid campaign endpoints temporarily
# Add to routes/api.php or use maintenance mode
php artisan down --message="Emergency maintenance" --retry=60

# Verify rollback
curl -I https://your-domain.com/api/campaigns/hybrid
# Expected: 503 Service Unavailable
```

### 🚨 Level 2: Partial Rollback (< 30 minutes)

#### Database Rollback
```bash
# Create emergency backup before rollback
mysqldump -u username -p blazz > emergency_backup_$(date +%Y%m%d_%H%M%S).sql

# Rollback migration
php artisan migrate:rollback --step=1

# Verify rollback completion
php artisan migrate:status | grep hybrid

# Restore campaign data if needed
mysql -u username -p blazz < rollback_data.sql
```

#### Service Restart
```bash
# Restart application services
sudo systemctl restart nginx php-fpm

# Restart queue workers
php artisan queue:restart

# Restart WhatsApp service
cd /path/to/whatsapp-service
pm2 restart all
pm2 status
```

### 🚨 Level 3: Full Rollback (< 2 hours)

#### Complete System Restore
```bash
# Restore database from backup
mysql -u username -p blazz < /backups/database/latest_working_backup.sql

# Restore application code
git checkout WORKING_COMMIT_HASH

# Rebuild frontend
npm run build

# Update environment if needed
# Restore .env file from backup
cp /backups/config/.env.backup .env

# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Restart all services
sudo systemctl restart nginx php-fpm mysql redis
pm2 restart all
```

## Rollback Validation

### ✅ Functional Validation

#### Basic Functionality Tests
```bash
# Test basic application functionality
curl -I https://your-domain.com/
curl -I https://your-domain.com/login

# Test existing campaign functionality (pre-hybrid)
curl -X GET https://your-domain.com/api/campaigns \
  -H "Authorization: Bearer TEST_TOKEN"

# Verify hybrid features are disabled
curl -X POST https://your-domain.com/api/campaigns/hybrid
# Expected: 404 Not Found
```

#### Database Integrity Checks
```sql
-- Verify campaign table structure
DESCRIBE campaigns;

-- Check data consistency
SELECT
    COUNT(*) as total_campaigns,
    COUNT(CASE WHEN campaign_type IS NULL THEN 1 END) as pre_hybrid_campaigns
FROM campaigns;

-- Verify no orphaned data
SELECT
    COUNT(*) as orphaned_logs
FROM campaign_logs cl
LEFT JOIN campaigns c ON cl.campaign_id = c.id
WHERE c.id IS NULL;
```

#### Frontend Validation
```bash
# Test frontend forms
# Navigate to /campaigns/create
# Verify hybrid options are not present
# Verify template selection still works
# Verify campaign creation works normally
```

### ✅ Performance Validation

#### Response Time Checks
```bash
# Test API response times
time curl -X GET https://your-domain.com/api/campaigns

# Test database query performance
php artisan tinker
>>> $campaigns = \App\Models\Campaign::take(10)->get();
```

#### System Resource Monitoring
```bash
# Check system resources
top -p $(pgrep php-fpm)
top -p $(pgrep nginx)

# Check database connections
mysql -u username -p -e "SHOW PROCESSLIST;"
mysql -u username -p -e "SHOW STATUS LIKE 'Threads_connected';"
```

## Post-Rollback Actions

### ✅ Communication Plan

#### Internal Communication
```markdown
Subject: EMERGENCY ROLLBACK - Hybrid Campaign System

**Status**: ROLLBACK COMPLETED
**Time**: [Timestamp]
**Duration**: [X minutes]
**Impact**: [Description of impact]
**Current State**: [System status]

**Actions Taken**:
- [List of rollback actions performed]
- [Services restarted]
- [Data restored]

**Validation**:
- [Basic functionality verified]
- [Database integrity confirmed]
- [Performance baseline restored]

**Next Steps**:
- [Investigation plan]
- [User communication]
- [Issue resolution timeline]

**Contact**: [Team contact information]
```

#### External Communication
```markdown
Subject: Temporary System Issue - Resolved

Dear Users,

We experienced a technical issue with our campaign system that has been resolved.

**What Happened**:
[Brief, non-technical explanation]

**Impact**:
[What was affected and for how long]

**Current Status**:
✅ All systems are operational
✅ Campaign functionality restored
✅ No data loss

**Next Steps**:
- We are investigating the root cause
- Additional monitoring has been implemented
- We will provide updates as needed

**Contact Support**:
- Email: support@your-domain.com
- Phone: [Support Number]

Thank you for your patience.

Best regards,
[Company] Team
```

### ✅ Incident Report

#### Incident Documentation Template
```markdown
# Incident Report - Hybrid Campaign Rollback

## Executive Summary
- **Incident ID**: [Unique ID]
- **Date**: [Date]
- **Duration**: [X hours]
- **Impact**: [Severity level]
- **Root Cause**: [Brief description]

## Timeline
| Time | Event | Owner |
|------|-------|-------|
| [Start] | Issue detected | [Person] |
| [Time] | Rollback initiated | [Person] |
| [Time] | Rollback completed | [Person] |
| [Time] | Validation complete | [Person] |

## Impact Assessment
### Business Impact
- Campaigns affected: [Number]
- Users affected: [Number]
- Revenue impact: [Amount]
- SLA impact: [Description]

### Technical Impact
- Systems affected: [List]
- Data loss: [Yes/No]
- Performance degradation: [Description]

## Root Cause Analysis
### What Happened
[Detailed description of events]

### Why It Happened
[Root cause analysis]

### Contributing Factors
[Secondary factors]

## Resolution Actions
### Immediate Actions
- [List of immediate fixes]
- [Rollback procedures]

### Long-term Actions
- [Prevention measures]
- [Process improvements]
- [Technical improvements]

## Lessons Learned
### What Went Well
[Positive aspects of response]

### What Could Be Improved
[Areas for improvement]

### Action Items
- [List of follow-up actions with owners]
```

## Data Recovery Procedures

### Campaign Data Recovery
```sql
-- Recover campaign data from backup
CREATE TABLE campaigns_recovered AS
SELECT * FROM campaigns_backup_YYYYMMDD;

-- Compare with current data
SELECT
    'deleted' as status,
    COUNT(*) as count
FROM campaigns_backup_YYYYMMDD cb
LEFT JOIN campaigns c ON cb.id = c.id
WHERE c.id IS NULL

UNION ALL

SELECT
    'new' as status,
    COUNT(*) as count
FROM campaigns c
LEFT JOIN campaigns_backup_YYYYMMDD cb ON c.id = cb.id
WHERE cb.id IS NULL;

-- Restore specific campaigns if needed
INSERT INTO campaigns (
    SELECT * FROM campaigns_backup_YYYYMMDD
    WHERE id IN (list_of_ids_to_restore)
);
```

### WhatsApp account Recovery
```bash
# Check WhatsApp account status
curl -X GET https://your-domain.com/api/whatsapp/sessions

# Restart failed sessions
curl -X POST https://your-domain.com/api/whatsapp/sessions/restart

# Reconnect to WhatsApp if needed
cd /path/to/whatsapp-service
pm2 restart all
```

## Testing Post-Rollback

### Automated Testing
```bash
# Run automated test suite
php artisan test tests/Feature/CampaignTest.php
php artisan test tests/Unit/CampaignServiceTest.php

# Performance tests
php artisan test:performance

# Integration tests
php artisan test:integration
```

### Manual Testing Checklist
- [ ] Campaign creation works normally
- [ ] Template selection functions properly
- [ ] Contact group assignment works
- [ ] Campaign listing displays correctly
- [ ] Campaign statistics are accurate
- [ ] WhatsApp integration is functional
- [ ] Queue processing is working
- [ ] Frontend forms operate correctly

## Prevention Measures

### Pre-Deployment Improvements
```bash
# Enhanced pre-deployment checklist
./scripts/pre-deployment-check.sh

# Automated rollback triggers
./scripts/deployment-monitor.sh

# Performance baseline testing
./scripts/performance-baseline.sh
```

### Monitoring Enhancements
```bash
# Custom monitoring alerts
./scripts/monitoring-alerts.sh

# Health check endpoints
curl -X GET https://your-domain.com/api/health/detailed

# Real-time monitoring dashboard
# Access: https://your-domain.com/monitoring
```

## Team Roles and Responsibilities

### Emergency Response Team
| Role | Primary | Backup | Contact |
|------|---------|--------|---------|
| **Incident Commander** | [Name] | [Name] | [Email/Phone] |
| **Technical Lead** | [Name] | [Name] | [Email/Phone] |
| **Database Admin** | [Name] | [Name] | [Email/Phone] |
| **DevOps Engineer** | [Name] | [Name] | [Email/Phone] |
| **QA Lead** | [Name] | [Name] | [Email/Phone] |
| **Communications** | [Name] | [Name] | [Email/Phone] |

### Escalation Matrix
| Severity | Response Time | Escalation Level | Notification Method |
|----------|---------------|------------------|-------------------|
| **Critical** | < 5 minutes | Level 1 | Phone + SMS + Email |
| **High** | < 15 minutes | Level 2 | Phone + Email |
| **Medium** | < 1 hour | Level 3 | Email |
| **Low** | < 4 hours | Level 4 | Email |

## Emergency Contact Information

### Primary Contacts
- **On-call Engineer**: [Phone], [Email]
- **System Administrator**: [Phone], [Email]
- **Database Administrator**: [Phone], [Email]
- **WhatsApp Service Admin**: [Phone], [Email]

### External Support
- **Hosting Provider**: [Phone], [Email]
- **Database Support**: [Phone], [Email]
- **CDN Support**: [Phone], [Email]

### Service Providers
- **WhatsApp Support**: [Support Channel]
- **Monitoring Service**: [Support Portal]
- **Backup Provider**: [Support Contact]

## Rollback Scripts Repository

### Automated Rollback Script
```bash
#!/bin/bash
# rollback.sh - Automated rollback script

ROLLBACK_TYPE=${1:-"partial"} # full, partial, frontend
BACKUP_DATE=${2:-$(date +%Y%m%d)}
LOG_FILE="/var/log/rollback_$(date +%Y%m%d_%H%M%S).log"

echo "Starting rollback: $ROLLBACK_TYPE at $(date)" >> $LOG_FILE

case $ROLLBACK_TYPE in
    "frontend")
        echo "Rolling back frontend..." >> $LOG_FILE
        git checkout HEAD~1 public/build/
        sudo systemctl restart nginx php-fpm
        ;;

    "partial")
        echo "Rolling back database migration..." >> $LOG_FILE
        mysqldump -u username -p blazz > emergency_backup_$BACKUP_DATE.sql
        php artisan migrate:rollback --step=1
        php artisan queue:restart
        ;;

    "full")
        echo "Full system rollback..." >> $LOG_FILE
        mysql -u username -p blazz < /backups/database/backup_$BACKUP_DATE.sql
        git checkout PREVIOUS_WORKING_COMMIT
        npm run build
        sudo systemctl restart nginx php-fpm mysql redis
        pm2 restart all
        ;;

    *)
        echo "Unknown rollback type: $ROLLBACK_TYPE" >> $LOG_FILE
        exit 1
        ;;
esac

echo "Rollback completed at $(date)" >> $LOG_FILE

# Validation
if curl -f -s https://your-domain.com/api/health > /dev/null; then
    echo "Rollback validation successful" >> $LOG_FILE
else
    echo "Rollback validation failed" >> $LOG_FILE
    exit 1
fi
```

## Documentation Maintenance

### Version Control
- This rollback plan should be version-controlled
- Update after each rollback incident
- Review quarterly for accuracy
- Test rollback procedures quarterly

### Training Requirements
- All team members should be trained on rollback procedures
- Conduct quarterly rollback drills
- Maintain training documentation
- Keep contact information current

---

*Last Updated: 2025-11-14*
*Version: 1.0*
*Next Review Date: 2025-12-14*
*Approved by: [Management]*