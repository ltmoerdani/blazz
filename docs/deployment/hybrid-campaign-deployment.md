# Hybrid Campaign System - Deployment Guide

## Overview

This guide provides comprehensive deployment instructions for the Hybrid Campaign System, including migration procedures, verification steps, and rollback plans.

## Pre-Deployment Checklist

### ✅ Environment Preparation

#### Database Backup
```bash
# Create full database backup
mysqldump -u username -p blazz > backup_$(date +%Y%m%d_%H%M%S).sql

# Verify backup integrity
mysql -u username -p -e "SHOW TABLES;" blazz < backup_$(date +%Y%m%d_%H%M%S).sql
```

#### Code Deployment
```bash
# Pull latest changes
git pull origin main

# Install new dependencies
composer install --no-dev --optimize-autoloader
npm install --production

# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

#### Environment Verification
```bash
# Check Laravel version
php artisan --version

# Verify environment
php artisan env
php artisan config:cache

# Test database connection
php artisan tinker
>>> \DB::connection()->getPdo();
```

### ✅ Feature Validation

#### Migration Status Check
```bash
# Verify hybrid campaign migration
php artisan migrate:status | grep hybrid

# Expected output:
# 2025_11_14_012521_add_hybrid_campaign_fields_to_campaigns_table .... [X] Ran
```

#### Route Verification
```bash
# Check hybrid campaign routes
php artisan route:list | grep hybrid

# Expected routes:
# POST        campaigns/hybrid ..................... User\CampaignController@storeHybrid
```

#### Configuration Check
```bash
# Verify queue configuration
php artisan config:show queue

# Check WhatsApp settings
php artisan config:show services.whatsapp
```

## Deployment Steps

### Step 1: Database Migration

#### Primary Migration
```bash
# Run migration with rollback capability
php artisan migrate --force

# Verify migration completion
php artisan migrate:status
```

#### Migration Verification
```sql
-- Check new columns in campaigns table
DESCRIBE campaigns;

-- Verify hybrid campaign fields exist
SHOW COLUMNS FROM campaigns WHERE Field LIKE '%campaign_type%';
SHOW COLUMNS FROM campaigns WHERE Field LIKE '%body_text%';
SHOW COLUMNS FROM campaigns WHERE Field LIKE '%header_%';
```

#### Sample Data Verification
```sql
-- Verify database structure
SELECT
    campaign_type,
    body_text,
    header_type,
    header_text,
    footer_text,
    buttons_data,
    metadata
FROM campaigns
WHERE campaign_type IS NOT NULL
LIMIT 5;
```

### Step 2: Code Deployment

#### Backend Deployment
```bash
# Update backend code
git checkout feature/hybrid-campaigns
git merge main
git push origin main

# Deploy to production (adjust based on your deployment method)
# Example for traditional deployment:
# rsync -avz --delete . user@server:/path/to/app/
```

#### Frontend Build
```bash
# Build Vue.js frontend
npm run build

# Verify build output
ls -la public/build/
```

### Step 3: Service Restart

#### Application Services
```bash
# Restart PHP-FPM
sudo systemctl restart php-fpm

# Restart web server
sudo systemctl restart nginx  # or apache2

# Restart queue workers
php artisan queue:restart
```

#### WhatsApp Services
```bash
# Restart WhatsApp service
cd whatsapp-service
pm2 restart all
pm2 status
```

## Post-Deployment Verification

### ✅ Health Checks

#### Application Health
```bash
# Test Laravel application
curl -I https://your-domain.com/

# Test API health endpoint
curl https://your-domain.com/api/health

# Check application logs
tail -f storage/logs/laravel.log
```

#### Database Connectivity
```bash
# Test database connection
php artisan tinker
>>> \DB::select('SELECT COUNT(*) FROM campaigns');

# Verify new fields
>>> $campaign = \App\Models\Campaign::first();
>>> $campaign->campaign_type;
```

#### WhatsApp Integration
```bash
# Test WhatsApp Web JS sessions
curl -X GET https://your-domain.com/api/campaigns/available-sessions \
  -H "Authorization: Bearer YOUR_TOKEN"

# Test provider selection
curl -X POST https://your-domain.com/api/campaigns/validate-template-provider \
  -H "Content-Type: application/json" \
  -d '{"template_uuid":"test","provider":"webjs"}'
```

### ✅ Functional Testing

#### Create Test Template Campaign
```bash
curl -X POST https://your-domain.com/api/campaigns/hybrid \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "Test Template Campaign",
    "campaign_type": "template",
    "template": "TEMPLATE_UUID_HERE",
    "contacts": "CONTACT_GROUP_UUID_HERE",
    "preferred_provider": "webjs",
    "skip_schedule": true
  }'
```

#### Create Test Direct Campaign
```bash
curl -X POST https://your-domain.com/api/campaigns/hybrid \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "Test Direct Campaign",
    "campaign_type": "direct",
    "contacts": "CONTACT_GROUP_UUID_HERE",
    "preferred_provider": "webjs",
    "skip_schedule": true,
    "header_type": "text",
    "header_text": "Test Header",
    "body_text": "Test message body",
    "footer_text": "Test footer"
  }'
```

#### Frontend Verification
1. **Access Campaign Creation Page**: Visit `/campaigns/create`
2. **Test Campaign Type Selection**: Verify dropdown works
3. **Test Template Selection**: Verify template loading
4. **Test Direct Message Form**: Verify form functionality
5. **Test Preview**: Verify real-time preview updates
6. **Test Submission**: Verify campaign creation works

### ✅ Performance Verification

#### Response Time Testing
```bash
# Test API response times
curl -w "@curl-format.txt" -o /dev/null -s https://your-domain.com/api/campaigns/available-sessions

# Test campaign creation performance
time curl -X POST https://your-domain.com/api/campaigns/hybrid \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","campaign_type":"direct","skip_schedule":true,"body_text":"test"}'
```

#### Database Performance
```sql
-- Test query performance
EXPLAIN SELECT * FROM campaigns WHERE campaign_type = 'direct';

-- Check indexes are being used
SHOW INDEX FROM campaigns;
```

## Monitoring Setup

### ✅ Application Monitoring

#### Log Monitoring
```bash
# Monitor Laravel logs
tail -f storage/logs/laravel.log | grep -i "hybrid\|campaign"

# Monitor queue worker logs
tail -f storage/logs/queue-worker.log
```

#### Error Tracking
```bash
# Check for recent errors
grep -i "error\|exception" storage/logs/laravel.log | tail -20

# Monitor campaign-specific errors
grep "campaign.*error" storage/logs/laravel.log
```

### ✅ Performance Monitoring

#### Database Monitoring
```sql
-- Monitor campaign creation performance
SELECT
    DATE(created_at) as date,
    campaign_type,
    COUNT(*) as campaigns_created
FROM campaigns
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY DATE(created_at), campaign_type;

-- Monitor provider selection performance
SELECT
    preferred_provider,
    COUNT(*) as usage_count
FROM campaigns
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY preferred_provider;
```

#### WhatsApp account Health
```bash
# Monitor WhatsApp account status
curl -X GET https://your-domain.com/api/whatsapp/sessions/status

# Check provider health scores
curl -X GET https://your-domain.com/api/campaigns/provider-health
```

## Rollback Plan

### 🔄 Emergency Rollback Procedures

#### Code Rollback
```bash
# Rollback to previous commit
git checkout PREVIOUS_COMMIT_HASH

# Revert frontend build
git checkout HEAD~1 public/build/

# Restart services
sudo systemctl restart nginx php-fpm
pm2 restart all
```

#### Database Rollback
```bash
# Rollback migration
php artisan migrate:rollback --step=1

# Verify rollback
php artisan migrate:status | grep hybrid

# Alternative: Restore from backup
mysql -u username -p blazz < backup_$(date +%Y%m%d_%H%M%S).sql
```

#### Configuration Rollback
```bash
# Restore previous configuration
git checkout PREVIOUS_COMMIT_HASH .env

# Clear caches
php artisan config:clear
php artisan route:clear
```

### 🔄 Rollback Verification

#### Application Recovery
```bash
# Verify old functionality works
curl -I https://your-domain.com/campaigns

# Check that hybrid features are disabled
curl -X POST https://your-domain.com/api/campaigns/hybrid
# Expected: 404 Not Found
```

#### Database Consistency
```sql
-- Verify campaign table structure
DESCRIBE campaigns;

-- Ensure data integrity
SELECT COUNT(*) FROM campaigns WHERE campaign_type IS NULL;
```

## Troubleshooting Guide

### Common Deployment Issues

#### Migration Failures
```bash
# Error: "Column already exists"
php artisan migrate:rollback --step=1
php artisan migrate

# Error: "Foreign key constraint"
# Check table dependencies
mysql -u username -p -e "SHOW CREATE TABLE campaigns;" blazz
```

#### Route Registration Issues
```bash
# Clear route cache
php artisan route:clear
php artisan route:cache

# Check route file syntax
php artisan route:list
```

#### Queue Worker Issues
```bash
# Restart queue workers
php artisan queue:restart
php artisan queue:work --daemon

# Check failed jobs
php artisan queue:failed
php artisan queue:retry all
```

#### Frontend Build Issues
```bash
# Clear npm cache
npm cache clean --force

# Reinstall dependencies
rm -rf node_modules package-lock.json
npm install

# Build with verbose output
npm run build -- --verbose
```

### Performance Issues

#### Slow API Response
```bash
# Check database queries
php artisan db:show

# Profile application
php artisan route:list --columns=uri,name | grep campaign
```

#### WhatsApp Connection Issues
```bash
# Check WhatsApp service logs
cd whatsapp-service
pm2 logs

# Restart WhatsApp accounts
curl -X POST https://your-domain.com/api/whatsapp/sessions/restart
```

## Post-Deployment Tasks

### ✅ Documentation Updates

1. **Update API Documentation**: Ensure latest endpoints are documented
2. **User Guide Updates**: Update user-facing documentation
3. **Internal Documentation**: Update team documentation

### ✅ Team Communication

1. **Deployment Announcement**: Notify team of successful deployment
2. **Feature Training**: Provide training on new hybrid campaign features
3. **Support Documentation**: Update support team documentation

### ✅ Success Metrics

Track the following metrics to measure deployment success:

- **System Uptime**: Should be >99.9%
- **API Response Time**: Should be <2 seconds
- **Error Rate**: Should be <1%
- **Campaign Creation Success Rate**: Should be >95%

---

## Deployment Timeline

| Phase | Duration | Activities |
|-------|----------|------------|
| **Preparation** | 1-2 hours | Backup, verification, preparation |
| **Migration** | 30 minutes | Database migration, verification |
| **Deployment** | 1-2 hours | Code deployment, service restart |
| **Testing** | 1-2 hours | Functional testing, verification |
| **Monitoring** | Ongoing | Performance monitoring, health checks |

**Total Estimated Time**: 3.5-7.5 hours

---

## Contact Information

**Deployment Team:**
- Lead Developer: [Name, Contact]
- Database Admin: [Name, Contact]
- DevOps Engineer: [Name, Contact]

**Emergency Contacts:**
- On-call Engineer: [Phone, Email]
- System Administrator: [Phone, Email]

---

*Last Updated: 2025-11-14*
*Version: 1.0*