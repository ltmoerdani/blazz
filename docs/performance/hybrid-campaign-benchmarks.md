# Hybrid Campaign System - Performance Benchmarks

## Overview

This document defines performance requirements, benchmarks, and monitoring guidelines for the Hybrid Campaign System to ensure optimal performance and user experience.

## Performance Requirements

### Response Time Requirements

| Endpoint Category | Target Response Time | Maximum Acceptable | Measurement Method |
|-------------------|---------------------|-------------------|-------------------|
| **Campaign Creation** | < 2 seconds | < 5 seconds | API request timing |
| **Campaign Preview** | < 1 second | < 3 seconds | Frontend load time |
| **Campaign Listing** | < 1.5 seconds | < 4 seconds | Database query time |
| **Campaign Statistics** | < 1 second | < 3 seconds | API response time |
| **Provider Selection** | < 500ms | < 1 second | Algorithm execution time |
| **Form Validation** | < 1 second | < 2 seconds | Frontend validation |

### Throughput Requirements

| Metric | Target | Minimum Acceptable | Measurement Period |
|--------|--------|-------------------|------------------|
| **Concurrent Users** | 500 users | 100 users | Peak hour |
| **Campaigns per Hour** | 1000 campaigns | 500 campaigns | Continuous load |
| **Message Send Rate** | 10,000 msg/min | 5,000 msg/min | Sustained period |
| **API Requests per Minute** | 3000 req/min | 1500 req/min | Average load |
| **Queue Processing Rate** | 500 jobs/min | 200 jobs/min | Background jobs |

### Database Performance

| Query Type | Target Time | Maximum Time | Optimization Notes |
|------------|-------------|--------------|-------------------|
| **Campaign Creation** | < 100ms | < 200ms | Use proper indexes |
| **Campaign Listing** | < 150ms | < 300ms | Implement pagination |
| **Contact Resolution** | < 200ms | < 500ms | Cache contact groups |
| **Template Lookup** | < 50ms | < 100ms | Cache templates |
| **Provider Selection** | < 100ms | < 200ms | Optimize session queries |

## Benchmark Testing

### Load Testing Scenarios

#### Scenario 1: Normal Load
- **Users**: 100 concurrent users
- **Duration**: 30 minutes
- **Requests per minute**: 500
- **Focus**: Average performance under normal conditions

#### Scenario 2: Peak Load
- **Users**: 500 concurrent users
- **Duration**: 15 minutes
- **Requests per minute**: 3000
- **Focus**: System behavior during peak usage

#### Scenario 3: Stress Test
- **Users**: 1000 concurrent users
- **Duration**: 5 minutes
- **Requests per minute**: 5000
- **Focus**: Breaking point and system limits

#### Scenario 4: Endurance Test
- **Users**: 200 concurrent users
- **Duration**: 4 hours
- **Requests per minute**: 1000
- **Focus**: Long-term stability and memory usage

### Testing Tools and Commands

#### Apache Bench (ab)
```bash
# Test campaign creation endpoint
ab -n 1000 -c 50 -H "Authorization: Bearer TOKEN" \
   -H "Content-Type: application/json" \
   -p campaign_data.json \
   https://your-domain.com/api/campaigns/hybrid

# Test campaign listing
ab -n 2000 -c 100 \
   -H "Authorization: Bearer TOKEN" \
   https://your-domain.com/api/campaigns

# Test preview endpoint
ab -n 500 -c 25 -p preview_data.json \
   -H "Authorization: Bearer TOKEN" \
   https://your-domain.com/api/campaigns/preview-message
```

#### Siege
```bash
# Siege configuration for sustained load
siege -c 200 -r 100 -d 30 \
  -H "Authorization: Bearer TOKEN" \
  https://your-domain.com/api/campaigns/hybrid

# Ramp-up test
siege -c 100 -t 5M -R 10 \
  -H "Authorization: Bearer TOKEN" \
  https://your-domain.com/api/campaigns
```

#### Locust (Python)
```python
# locustfile.py for hybrid campaign testing
import json
import random
from locust import HttpUser, task, between

class HybridCampaignUser(HttpUser):
    wait_time = between(1, 3)

    def on_start(self):
        """Login and get auth token"""
        response = self.client.post("/api/login", json={
            "email": "test@example.com",
            "password": "password"
        })
        if response.status_code == 200:
            self.token = response.json()["access_token"]
            self.headers = {
                "Authorization": f"Bearer {self.token}",
                "Content-Type": "application/json"
            }

    @task(3)
    def create_direct_campaign(self):
        """Create direct message campaign"""
        campaign_data = {
            "name": f"Test Campaign {random.randint(1000, 9999)}",
            "campaign_type": "direct",
            "contacts": "test-contact-group-uuid",
            "preferred_provider": "webjs",
            "skip_schedule": True,
            "header_type": "text",
            "body_text": f"Test message {random.randint(100, 999)}"
        }

        self.client.post("/api/campaigns/hybrid",
                        json=campaign_data,
                        headers=self.headers)

    @task(2)
    def create_template_campaign(self):
        """Create template-based campaign"""
        campaign_data = {
            "name": f"Template Campaign {random.randint(1000, 9999)}",
            "campaign_type": "template",
            "template": "test-template-uuid",
            "contacts": "test-contact-group-uuid",
            "preferred_provider": "webjs",
            "skip_schedule": True
        }

        self.client.post("/api/campaigns/hybrid",
                        json=campaign_data,
                        headers=self.headers)

    @task(1)
    def preview_message(self):
        """Preview campaign message"""
        preview_data = {
            "campaign_type": "direct",
            "header_type": "text",
            "header_text": "Preview Header",
            "body_text": "Preview message content"
        }

        self.client.post("/api/campaigns/preview-message",
                        json=preview_data,
                        headers=self.headers)

    @task(2)
    def list_campaigns(self):
        """List campaigns"""
        self.client.get("/api/campaigns", headers=self.headers)
```

## Database Performance Monitoring

### Key Performance Queries

```sql
-- Monitor campaign creation performance
SELECT
    DATE(created_at) as date,
    COUNT(*) as campaigns_created,
    AVG(TIMESTAMPDIFF(MICROSECOND, created_at, updated_at) / 1000) as avg_processing_time_ms
FROM campaigns
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Monitor slow queries
SELECT
    query_time,
    lock_time,
    rows_sent,
    rows_examined,
    sql_text
FROM mysql.slow_log
WHERE sql_text LIKE '%campaign%'
ORDER BY query_time DESC
LIMIT 10;

-- Check table indexes usage
SHOW INDEX FROM campaigns;
EXPLAIN SELECT * FROM campaigns WHERE campaign_type = 'direct' AND status = 'pending';

-- Monitor database connections
SHOW STATUS LIKE 'Threads_connected';
SHOW STATUS LIKE 'Max_used_connections';
```

### Query Optimization Benchmarks

#### Campaign Creation Query
```sql
-- Optimized campaign creation
EXPLAIN INSERT INTO campaigns (
    uuid, workspace_id, name, campaign_type, body_text,
    header_type, header_text, footer_text, preferred_provider,
    status, created_at, updated_at
) VALUES (
    UUID(), 1, 'Test Campaign', 'direct', 'Test message',
    'text', 'Header', 'Footer', 'webjs',
    'pending', NOW(), NOW()
);

-- Expected: Using index for workspace_id, no table scans
```

#### Campaign Listing Query
```sql
-- Optimized campaign listing with pagination
EXPLAIN SELECT
    uuid, name, campaign_type, status, created_at
FROM campaigns
WHERE workspace_id = 1
  AND campaign_type = 'direct'
ORDER BY created_at DESC
LIMIT 20 OFFSET 0;

-- Expected: Using index for workspace_id, campaign_type, created_at
```

## Frontend Performance

### JavaScript Performance Metrics

| Component | Target Load Time | Maximum Acceptable | Optimization Notes |
|-----------|------------------|-------------------|-------------------|
| **Campaign Form Load** | < 500ms | < 1 second | Lazy load templates |
| **Type Selection** | < 100ms | < 300ms | Use event delegation |
| **Template Loading** | < 300ms | < 800ms | Cache template data |
| **Real-time Preview** | < 200ms | < 500ms | Debounce input events |
| **Form Validation** | < 50ms | < 200ms | Client-side validation |

### Vue.js Performance Monitoring

```javascript
// Performance monitoring in CampaignForm.vue
export default {
  name: 'CampaignForm',
  data() {
    return {
      performanceMetrics: {
        formLoadTime: 0,
        templateLoadTime: 0,
        previewUpdateTime: 0
      }
    }
  },
  mounted() {
    // Measure component load time
    const loadStart = performance.now();

    this.$nextTick(() => {
      const loadEnd = performance.now();
      this.performanceMetrics.formLoadTime = loadEnd - loadStart;

      // Log performance metrics
      if (this.performanceMetrics.formLoadTime > 1000) {
        console.warn(`Form load time exceeded target: ${this.performanceMetrics.formLoadTime}ms`);
      }
    });
  },
  methods: {
    async loadTemplates() {
      const startTime = performance.now();

      try {
        await this.$http.get('/api/templates');
        const endTime = performance.now();
        this.performanceMetrics.templateLoadTime = endTime - startTime;

      } catch (error) {
        console.error('Template loading error:', error);
      }
    },

    updatePreview: _.debounce(function() {
      const startTime = performance.now();

      // Update preview logic
      this.generatePreview();

      const endTime = performance.now();
      this.performanceMetrics.previewUpdateTime = endTime - startTime;

    }, 300), // Debounce preview updates
  }
}
```

## Queue Performance

### Queue Worker Optimization

```php
// config/queue.php - Optimized configuration
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],

// config/horizon.php - Optimized worker settings
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'campaigns'],
            'balance' => 'auto',
            'processes' => 8, // Optimized based on CPU cores
            'tries' => 3,
            'timeout' => 60,
            'memory' => 128, // MB per worker
        ],
    ],
],
```

### Queue Performance Monitoring

```php
// Custom performance monitoring middleware
class QueuePerformanceMiddleware
{
    public function handle($job, $next)
    {
        $startTime = microtime(true);

        try {
            $next($job);

            $executionTime = microtime(true) - $startTime;

            // Log performance metrics
            Log::info('Queue job performance', [
                'job_class' => get_class($job),
                'execution_time' => $executionTime,
                'memory_usage' => memory_get_peak_usage(true),
            ]);

            // Alert if performance is degraded
            if ($executionTime > 30) { // 30 seconds threshold
                Log::warning('Slow queue job detected', [
                    'job_class' => get_class($job),
                    'execution_time' => $executionTime,
                ]);
            }

        } catch (Exception $e) {
            $executionTime = microtime(true) - $startTime;

            Log::error('Queue job failed', [
                'job_class' => get_class($job),
                'execution_time' => $executionTime,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
```

## Monitoring and Alerting

### Performance Monitoring Script

```bash
#!/bin/bash
# performance_monitor.sh - Continuous performance monitoring

API_BASE="https://your-domain.com/api"
AUTH_TOKEN="your-auth-token"
WORKSPACE_ID="your-workspace-id"

# Log file
LOG_FILE="/var/log/hybrid-campaign-performance.log"

echo "Starting performance monitoring at $(date)" >> $LOG_FILE

while true; do
    # Test campaign creation endpoint
    START_TIME=$(date +%s%N)
    RESPONSE=$(curl -s -w "%{http_code}" \
        -H "Authorization: Bearer $AUTH_TOKEN" \
        -H "Content-Type: application/json" \
        -d '{"name":"Perf Test","campaign_type":"direct","skip_schedule":true,"body_text":"test"}' \
        -o /dev/null \
        "$API_BASE/campaigns/hybrid")

    END_TIME=$(date +%s%N)
    RESPONSE_TIME=$(( ($END_TIME - $START_TIME) / 1000000 )) # Convert to milliseconds

    TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

    if [ "$RESPONSE" = "200" ]; then
        if [ $RESPONSE_TIME -gt 2000 ]; then
            echo "[$TIMESTAMP] WARNING: Campaign creation took ${RESPONSE_TIME}ms" >> $LOG_FILE
            # Send alert (e.g., email, Slack webhook)
            # curl -X POST -H 'Content-type: application/json' \
            #     --data '{"text":"🚨 Campaign creation slow: '${RESPONSE_TIME}'ms"}' \
            #     YOUR_SLACK_WEBHOOK_URL
        else
            echo "[$TIMESTAMP] OK: Campaign creation ${RESPONSE_TIME}ms" >> $LOG_FILE
        fi
    else
        echo "[$TIMESTAMP] ERROR: Campaign creation failed with HTTP $RESPONSE" >> $LOG_FILE
    fi

    # Wait between tests
    sleep 30
done
```

### Performance Dashboard Metrics

```php
// Performance Dashboard - Real-time metrics
class PerformanceMetricsController extends Controller
{
    public function index()
    {
        // Last 24 hours performance data
        $metrics = [
            'campaign_creation' => $this->getCampaignCreationMetrics(),
            'queue_performance' => $this->getQueuePerformanceMetrics(),
            'database_performance' => $this->getDatabasePerformanceMetrics(),
            'system_resources' => $this->getSystemResourceMetrics(),
        ];

        return response()->json($metrics);
    }

    private function getCampaignCreationMetrics()
    {
        return Cache::remember('campaign_creation_metrics', 300, function () {
            return Campaign::where('created_at', '>=', now()->subHours(24))
                ->selectRaw('
                    AVG(TIMESTAMPDIFF(MICROSECOND, created_at, updated_at) / 1000) as avg_processing_time_ms,
                    COUNT(*) as total_campaigns,
                    COUNT(CASE WHEN campaign_type = "direct" THEN 1 END) as direct_campaigns,
                    COUNT(CASE WHEN campaign_type = "template" THEN 1 END) as template_campaigns,
                    COUNT(CASE WHEN status = "failed" THEN 1 END) as failed_campaigns
                ')
                ->first();
        });
    }
}
```

## Performance Optimization Checklist

### Database Optimization
- [ ] Ensure proper indexes on campaign table
- [ ] Implement query result caching
- [ ] Optimize JOIN queries
- [ ] Use database connection pooling
- [ ] Monitor slow query logs

### API Optimization
- [ ] Implement response caching where appropriate
- [ ] Use database query optimization
- [ ] Implement API rate limiting
- [ ] Add compression for large responses
- [ ] Use proper HTTP caching headers

### Queue Optimization
- [ ] Optimize queue worker processes
- [ ] Implement queue job batching
- [ ] Monitor queue backlog
- [ ] Implement queue prioritization
- [ ] Use Redis for queue backend

### Frontend Optimization
- [ ] Implement lazy loading for templates
- [ ] Debounce real-time preview updates
- [ ] Optimize bundle size
- [ ] Use client-side caching
- [ ] Implement progressive loading

## Performance Testing Schedule

| Testing Type | Frequency | Duration | Responsible |
|--------------|-----------|----------|-------------|
| **Smoke Tests** | Daily | 5 minutes | DevOps Team |
| **Load Tests** | Weekly | 30 minutes | QA Team |
| **Stress Tests** | Monthly | 2 hours | Performance Team |
| **Endurance Tests** | Quarterly | 8 hours | Performance Team |
| **Full Scale Tests** | Before Major Releases | 24 hours | All Teams |

## Performance Targets and KPIs

### Primary KPIs
- **Campaign Creation Time**: Average < 2 seconds, P95 < 5 seconds
- **API Response Time**: Average < 1 second, P95 < 3 seconds
- **System Availability**: > 99.9% uptime
- **Error Rate**: < 1% of total requests
- **Queue Processing**: < 5 minute average backlog

### Secondary KPIs
- **Database Query Time**: Average < 100ms
- **Frontend Load Time**: < 1 second for campaign form
- **Queue Job Success Rate**: > 95%
- **Memory Usage**: < 70% of allocated resources
- **CPU Usage**: < 80% of allocated resources

---

*Last Updated: 2025-11-14*
*Version: 1.0*
*Next Review Date: 2025-12-14*