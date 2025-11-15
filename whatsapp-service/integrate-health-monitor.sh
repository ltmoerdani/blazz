#!/bin/bash

# Script untuk menerapkan Session Health Monitor ke WhatsApp Service
# Date: 2025-11-15

echo "=========================================="
echo "Integrating Session Health Monitor"
echo "=========================================="
echo ""

cd /Applications/MAMP/htdocs/blazz/whatsapp-service

# Backup server.js
echo "1. Creating backup of server.js..."
cp server.js server.js.backup.$(date +%Y%m%d_%H%M%S)
echo "✅ Backup created"
echo ""

# Create monitors directory if not exists
echo "2. Ensuring monitors directory exists..."
mkdir -p monitors
echo "✅ Monitors directory ready"
echo ""

# Add to .env if not exists
echo "3. Adding health monitor configuration to .env..."
grep -q "HEALTH_CHECK_INTERVAL" .env || cat >> .env << 'EOF'

# Session Health Monitoring
HEALTH_CHECK_INTERVAL=300000
INACTIVITY_THRESHOLD=1800000
MAX_RESTART_ATTEMPTS=3
MEMORY_THRESHOLD=524288000
EOF
echo "✅ Configuration added to .env"
echo ""

echo "=========================================="
echo "Integration Complete!"
echo "=========================================="
echo ""
echo "Next Steps:"
echo "1. Review the health monitor configuration in .env"
echo "2. Modify server.js to integrate the health monitor"
echo "3. Restart the WhatsApp service"
echo ""
echo "To integrate manually, add this to server.js after SessionManager initialization:"
echo ""
echo "  const SessionHealthMonitor = require('./monitors/sessionHealthMonitor');"
echo "  const healthMonitor = new SessionHealthMonitor(sessionManager);"
echo "  healthMonitor.start();"
echo ""
echo "And add this endpoint for manual health check:"
echo ""
echo "  app.get('/health/sessions', authMiddleware, async (req, res) => {"
echo "    const status = healthMonitor.getHealthStatus();"
echo "    res.json(status);"
echo "  });"
echo ""
echo "  app.post('/accounts/:sessionId/restart', authMiddleware, async (req, res) => {"
echo "    const { sessionId } = req.params;"
echo "    const metadata = sessionManager.metadata.get(sessionId);"
echo "    if (!metadata) {"
echo "      return res.status(404).json({ error: 'Session not found' });"
echo "    }"
echo "    await healthMonitor.handleStuckSession(sessionId, metadata);"
echo "    res.json({ success: true, message: 'Session restart initiated' });"
echo "  });"
echo ""
