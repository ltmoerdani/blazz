/**
 * Routes Index
 *
 * Central route configuration for WhatsApp Service API.
 * Extracted from server.js for better organization and maintainability.
 *
 * TASK-ARCH-3: Extract API routes to dedicated router modules
 */

const express = require('express');
const SessionController = require('../controllers/SessionController');
const MessageController = require('../controllers/MessageController');
const HealthController = require('../controllers/HealthController');

/**
 * Create and configure all routes
 *
 * @param {SessionManager} sessionManager - Session manager instance
 * @param {Object} logger - Winston logger instance
 * @returns {Object} Configured Express router
 */
function createRoutes(sessionManager, logger) {
    const router = express.Router();

    // Initialize controllers
    const sessionController = new SessionController(sessionManager, logger);
    const messageController = new MessageController(sessionManager, logger);
    const healthController = new HealthController(sessionManager, logger);

    // ==========================================
    // Health Check Routes
    // ==========================================

    // Basic health check (for load balancers, monitoring)
    router.get('/health', healthController.basicHealth.bind(healthController));

    // Detailed health check (for admin dashboards)
    router.get('/health/detailed', healthController.detailedHealth.bind(healthController));

    // Readiness probe (for container orchestration)
    router.get('/health/ready', healthController.readiness.bind(healthController));

    // Liveness probe (for container orchestration)
    router.get('/health/live', healthController.liveness.bind(healthController));

    // Performance metrics (for monitoring)
    router.get('/health/metrics', healthController.performanceMetrics.bind(healthController));

    // Application information
    router.get('/info', healthController.appInfo.bind(healthController));

    // ==========================================
    // Session Management Routes
    // ==========================================

    // Create new WhatsApp session
    // POST /api/sessions
    router.post('/api/sessions', sessionController.createSession.bind(sessionController));

    // Get all sessions
    // GET /api/sessions
    router.get('/api/sessions', sessionController.getAllSessions.bind(sessionController));

    // Get session status
    // GET /api/sessions/:sessionId/status
    router.get('/api/sessions/:sessionId/status', sessionController.getSessionStatus.bind(sessionController));

    // Get session metadata
    // GET /api/sessions/:sessionId/metadata
    router.get('/api/sessions/:sessionId/metadata', sessionController.getSessionMetadata.bind(sessionController));

    // Update session metadata
    // PUT /api/sessions/:sessionId/metadata
    router.put('/api/sessions/:sessionId/metadata', sessionController.updateSessionMetadata.bind(sessionController));

    // Restore existing session
    // POST /api/sessions/:sessionId/restore
    router.post('/api/sessions/:sessionId/restore', sessionController.restoreSession.bind(sessionController));

    // Disconnect session
    // DELETE /api/sessions/:sessionId
    router.delete('/api/sessions/:sessionId', sessionController.disconnectSession.bind(sessionController));

    // Reconnect session (disconnect + create new)
    // POST /api/sessions/:sessionId/reconnect
    router.post('/api/sessions/:sessionId/reconnect', sessionController.reconnectSession.bind(sessionController));

    // Regenerate QR code for session
    // POST /api/sessions/:sessionId/regenerate-qr
    router.post('/api/sessions/:sessionId/regenerate-qr', sessionController.regenerateQR.bind(sessionController));

    // ==========================================
    // Message Management Routes
    // ==========================================

    // Send text message
    // POST /api/messages/send
    router.post('/api/messages/send', messageController.sendMessage.bind(messageController));

    // Send media message
    // POST /api/messages/send-media
    router.post('/api/messages/send-media', messageController.sendMediaMessage.bind(messageController));

    // Bulk send messages
    // POST /api/messages/bulk-send
    router.post('/api/messages/bulk-send', messageController.bulkSendMessages.bind(messageController));

    // Get message status
    // GET /api/messages/:sessionId/status?message_id=<id>
    router.get('/api/messages/:sessionId/status', messageController.getMessageStatus.bind(messageController));

    // Validate session for sending messages
    // GET /api/messages/:sessionId/validate
    router.get('/api/messages/:sessionId/validate', messageController.validateSessionForSending.bind(messageController));

    // ==========================================
    // Root Routes
    // ==========================================

    // API documentation / welcome endpoint
    router.get('/', (req, res) => {
        res.json({
            service: 'WhatsApp Web.js Service',
            version: '1.0.0',
            status: 'running',
            endpoints: {
                health_check: {
                    basic: '/health',
                    detailed: '/health/detailed',
                    readiness: '/health/ready',
                    liveness: '/health/live',
                    metrics: '/health/metrics'
                },
                session_management: {
                    create: 'POST /api/sessions',
                    get_all: 'GET /api/sessions',
                    get_status: 'GET /api/sessions/:sessionId/status',
                    get_metadata: 'GET /api/sessions/:sessionId/metadata',
                    update_metadata: 'PUT /api/sessions/:sessionId/metadata',
                    restore: 'POST /api/sessions/:sessionId/restore',
                    disconnect: 'DELETE /api/sessions/:sessionId',
                    reconnect: 'POST /api/sessions/:sessionId/reconnect',
                    regenerate_qr: 'POST /api/sessions/:sessionId/regenerate-qr'
                },
                message_management: {
                    send_text: 'POST /api/messages/send',
                    send_media: 'POST /api/messages/send-media',
                    bulk_send: 'POST /api/messages/bulk-send',
                    get_status: 'GET /api/messages/:sessionId/status',
                    validate_session: 'GET /api/messages/:sessionId/validate'
                }
            },
            documentation: 'See API documentation for request/response formats',
            timestamp: new Date().toISOString()
        });
    });

    // ==========================================
    // Error Handling Middleware
    // ==========================================

    // 404 handler for undefined routes
    router.use('*', (req, res) => {
        res.status(404).json({
            error: 'Endpoint not found',
            path: req.originalUrl,
            method: req.method,
            available_endpoints: [
                '/health',
                '/health/detailed',
                '/health/ready',
                '/health/live',
                '/health/metrics',
                '/api/sessions',
                '/api/messages/send',
                '/api/messages/send-media',
                '/api/messages/bulk-send'
            ],
            timestamp: new Date().toISOString()
        });
    });

    // Global error handler
    router.use((error, req, res, next) => {
        logger.error('Unhandled route error', {
            error: error.message,
            stack: error.stack,
            path: req.originalUrl,
            method: req.method
        });

        res.status(500).json({
            error: 'Internal server error',
            message: process.env.NODE_ENV === 'development' ? error.message : 'Something went wrong',
            timestamp: new Date().toISOString()
        });
    });

    return router;
}

module.exports = createRoutes;