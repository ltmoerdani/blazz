#!/bin/bash

# WhatsApp Service Monitoring Setup Script
# Sets up monitoring infrastructure for production

echo "📊 Setting up WhatsApp Service Monitoring..."

# Check if PM2 is running
if ! pm2 status whatsapp-service &> /dev/null; then
    echo "❌ WhatsApp service is not running. Please start it first:"
    echo "  cd whatsapp-service && ./start-production.sh"
    exit 1
fi

# Create monitoring directories
mkdir -p monitoring/grafana/dashboards
mkdir -p monitoring/prometheus
mkdir -p monitoring/alertmanager

# Create Prometheus configuration
cat > monitoring/prometheus/prometheus.yml << 'EOF'
global:
  scrape_interval: 15s
  evaluation_interval: 15s

rule_files:
  - "alert_rules.yml"

scrape_configs:
  - job_name: 'whatsapp-service'
    static_configs:
      - targets: ['localhost:3000']
    metrics_path: '/metrics'
    scrape_interval: 30s

  - job_name: 'laravel'
    static_configs:
      - targets: ['localhost:8000']
    metrics_path: '/metrics'
    scrape_interval: 30s

  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']
EOF

# Create Prometheus alert rules
cat > monitoring/prometheus/alert_rules.yml << 'EOF'
groups:
  - name: whatsapp_alerts
    rules:
      - alert: WhatsAppServiceDown
        expr: up{job="whatsapp-service"} == 0
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "WhatsApp service is down"
          description: "WhatsApp service has been down for more than 1 minute"

      - alert: HighMemoryUsage
        expr: (process_memory_usage_bytes / process_memory_total_bytes) * 100 > 80
        for: 2m
        labels:
          severity: warning
        annotations:
          summary: "High memory usage detected"
          description: "Memory usage is above 80% for more than 2 minutes"

      - alert: SessionDisconnected
        expr: whatsapp_account_status == 0
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "WhatsApp session disconnected"
          description: "WhatsApp session has been disconnected for more than 5 minutes"

      - alert: HighBanRisk
        expr: whatsapp_ban_risk_score > 80
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "High ban risk detected"
          description: "WhatsApp session has high ban risk score (>80)"

      - alert: StorageUsageHigh
        expr: (disk_usage_bytes / disk_total_bytes) * 100 > 85
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High storage usage"
          description: "Storage usage is above 85% for more than 5 minutes"
EOF

# Create Grafana dashboard configuration
cat > monitoring/grafana/dashboards/whatsapp-overview.json << 'EOF'
{
  "dashboard": {
    "title": "WhatsApp Service Overview",
    "tags": ["whatsapp", "messaging"],
    "timezone": "browser",
    "panels": [
      {
        "title": "Service Status",
        "type": "stat",
        "targets": [
          {
            "expr": "up{job=\"whatsapp-service\"}",
            "legendFormat": "Status"
          }
        ],
        "fieldConfig": {
          "defaults": {
            "color": {
              "mode": "thresholds",
              "thresholds": [
                { "color": "red", "value": 0 },
                { "color": "green", "value": 1 }
              ]
            }
          }
        }
      },
      {
        "title": "Active Sessions",
        "type": "stat",
        "targets": [
          {
            "expr": "whatsapp_account_status",
            "legendFormat": "{{session_id}}"
          }
        ]
      },
      {
        "title": "Memory Usage",
        "type": "gauge",
        "targets": [
          {
            "expr": "(process_memory_usage_bytes / process_memory_total_bytes) * 100",
            "legendFormat": "Memory %"
          }
        ],
        "fieldConfig": {
          "defaults": {
            "max": 100,
            "min": 0,
            "thresholds": [
              { "color": "green", "value": 0 },
              { "color": "yellow", "value": 70 },
              { "color": "red", "value": 85 }
            ]
          }
        }
      },
      {
        "title": "Messages Sent (24h)",
        "type": "graph",
        "targets": [
          {
            "expr": "increase(whatsapp_messages_sent_total[24h])",
            "legendFormat": "{{session_id}}"
          }
        ]
      },
      {
        "title": "Ban Risk Score",
        "type": "heatmap",
        "targets": [
          {
            "expr": "whatsapp_ban_risk_score",
            "legendFormat": "{{session_id}}"
          }
        ]
      }
    ],
    "time": {
      "from": "now-24h",
      "to": "now"
    },
    "refresh": "30s"
  }
}
EOF

# Create Docker Compose for monitoring stack (optional)
cat > monitoring/docker-compose.yml << 'EOF'
version: '3.8'
services:
  prometheus:
    image: prom/prometheus:latest
    ports:
      - "9090:9090"
    volumes:
      - ./prometheus:/etc/prometheus
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.path=/prometheus'
      - '--web.console.libraries=/etc/prometheus/console_libraries'
      - '--web.console.templates=/etc/prometheus/consoles'
      - '--storage.tsdb.retention.time=200h'
      - '--web.enable-lifecycle'

  grafana:
    image: grafana/grafana:latest
    ports:
      - "3001:3000"
    volumes:
      - grafana_data:/var/lib/grafana
      - ./grafana/dashboards:/var/lib/grafana/dashboards
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin

  alertmanager:
    image: prom/alertmanager:latest
    ports:
      - "9093:9093"
    volumes:
      - ./alertmanager:/etc/alertmanager
    command:
      - '--config.file=/etc/alertmanager/config.yml'

volumes:
  grafana_data:
EOF

# Create AlertManager configuration
cat > monitoring/alertmanager/config.yml << 'EOF'
global:
  smtp_smarthost: 'localhost:587'
  smtp_from: 'alerts@yourdomain.com'

route:
  group_by: ['alertname']
  group_wait: 10s
  group_interval: 10s
  repeat_interval: 1h
  receiver: 'email'

receivers:
  - name: 'email'
    email_configs:
      - to: 'admin@yourdomain.com'
        send_resolved: true

  - name: 'slack'
    slack_configs:
      - api_url: 'YOUR_SLACK_WEBHOOK_URL'
        channel: '#alerts'
        send_resolved: true
EOF

echo "✅ Monitoring setup completed!"
echo ""
echo "📊 Monitoring Stack Setup:"
echo "  📈 Prometheus: http://localhost:9090"
echo "  🎨 Grafana: http://localhost:3001 (admin/admin)"
echo "  🚨 AlertManager: http://localhost:9093"
echo ""
echo "🔧 Quick Start:"
echo "  # Start monitoring stack with Docker:"
echo "  cd monitoring && docker-compose up -d"
echo ""
echo "  # Or start individual services:"
echo "  # Prometheus: docker run -d -p 9090:9090 -v \$(pwd)/prometheus:/etc/prometheus prom/prometheus"
echo "  # Grafana: docker run -d -p 3001:3000 grafana/grafana"
echo ""
echo "📝 Configuration Files Created:"
echo "  ✅ monitoring/prometheus/prometheus.yml"
echo "  ✅ monitoring/prometheus/alert_rules.yml"
echo "  ✅ monitoring/grafana/dashboards/whatsapp-overview.json"
echo "  ✅ monitoring/alertmanager/config.yml"
echo "  ✅ monitoring/docker-compose.yml"
echo ""
echo "🎯 Next Steps:"
echo "  1. Update email/Slack configuration in alertmanager/config.yml"
echo "  2. Import Grafana dashboard from dashboards/whatsapp-overview.json"
echo "  3. Configure alerts in Prometheus"
echo "  4. Set up log aggregation"
echo ""
echo "Monitoring setup complete! 📊"
