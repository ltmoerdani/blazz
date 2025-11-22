const express = require('express');
const winston = require('winston');
const dotenv = require('dotenv');

// Load environment variables
dotenv.config();

// Import extracted components (TASK-ARCH-1, TASK-ARCH-2, TASK-ARCH-3)
const SessionManager = require('./src/managers/SessionManager');
const createRoutes = require('./src/routes');

// Import essential services only
const WhatsAppRateLimiter = require('./src/services/WhatsAppRateLimiter');
const TimeoutHandler = require('./src/middleware/TimeoutHandler');

// Import session restoration and auto-reconnect services
const AccountRestoration = require('./src/services/AccountRestoration');
const AutoReconnect = require('./src/services/AutoReconnect');

const app = express();
const PORT = process.env.PORT || 3000;

// Configure Winston logger
const logger = winston.createLogger({
    level: process.env.LOG_LEVEL || 'info',
    format: winston.format.combine(
        winston.format.timestamp(),
        winston.format.errors({ stack: true }),
        winston.format.json()
    ),
    defaultMeta: { service: 'whatsapp-service' },
    transports: [
        new winston.transports.File({
            filename: process.env.LOG_FILE || './logs/whatsapp-service.log',
            maxsize: process.env.LOG_MAX_SIZE || 10485760, // 10MB
            maxFiles: process.env.LOG_MAX_FILES || 7
        }),
        new winston.transports.Console({
            format: winston.format.simple()
        })
    ],
});

// Middleware
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));

// Initialize session manager (TASK-ARCH-1: Extracted from server.js)
const sessionManager = new SessionManager(logger);

// Make sessionManager available to routes
app.set('sessionManager', sessionManager);

// Initialize essential services only
const whatsAppRateLimiter = new WhatsAppRateLimiter();
const timeoutHandler = new TimeoutHandler();

// Apply timeout middleware
app.use(timeoutHandler.middleware());

// RemoteAuth migration health check routes (MUST be before createRoutes to avoid 404 handler)
// These routes handle Redis, session storage, and migration monitoring
try {
    const remoteAuthHealthRoutes = require('./src/routes/healthRoutes');
    app.use('/remoteauth', remoteAuthHealthRoutes);
    logger.info('✅ RemoteAuth health routes registered at /remoteauth/*');
} catch (error) {
    logger.error('❌ Failed to load RemoteAuth health routes:', error.message);
}

// Session Cleanup routes (Week 2 Optional Enhancement)
try {
    const cleanupRoutes = require('./src/routes/cleanupRoutes');
    app.use('/cleanup', cleanupRoutes);
    logger.info('✅ Session cleanup routes registered at /cleanup/*');
} catch (error) {
    logger.error('❌ Failed to load cleanup routes:', error.message);
}

// Setup API routes using extracted router (TASK-ARCH-3: Extract routes to dedicated module)
app.use('/', createRoutes(sessionManager, logger));

// CRITICAL FIX: Global error handler for Protocol/Session errors
// Prevents entire Node.js process crash when WhatsApp Web.js encounters Protocol errors
// Issue #3904: Protocol errors after client.destroy() cause cascade failures
process.on('unhandledRejection', (error, promise) => {
    // Protocol errors from Puppeteer after session disconnect
    if (error.message && error.message.includes('Protocol error') && error.message.includes('Session closed')) {
        logger.error('🛡️ Suppressed Protocol error after disconnect (preventing crash)', {
            error: error.message,
            type: 'protocol_error_suppressed',
            timestamp: Date.now()
        });
        return; // Suppress error - don't crash process
    }
    
    // Target frame detached errors
    if (error.message && error.message.includes('Execution context was destroyed')) {
        logger.error('🛡️ Suppressed execution context error (preventing crash)', {
            error: error.message,
            type: 'context_error_suppressed'
        });
        return;
    }
    
    // Log other unhandled rejections but don't suppress
    logger.error('❌ Unhandled rejection detected', {
        error: error.message,
        stack: error.stack,
        promise: promise
    });
    
    // Don't throw - just log for monitoring
});

// Graceful shutdown
process.on('SIGTERM', async () => {
    logger.info('SIGTERM received, shutting down gracefully');

    // Use the session manager's shutdown method
    await sessionManager.shutdownAllSessions();

    process.exit(0);
});

process.on('SIGINT', async () => {
    logger.info('SIGINT received, shutting down gracefully');

    // Use the session manager's shutdown method
    await sessionManager.shutdownAllSessions();

    process.exit(0);
});

// Start server
app.listen(PORT, async () => {
    logger.info(`WhatsApp Service started on port ${PORT}`);
    logger.info(`Laravel backend: ${process.env.LARAVEL_URL}`);
    logger.info(`Environment: ${process.env.NODE_ENV || 'development'}`);
    logger.info(`Auth Strategy: ${process.env.AUTH_STRATEGY || 'localauth'}`);

    // NEW: Initialize RemoteAuth if enabled (Week 3 RemoteAuth Migration)
    if (process.env.AUTH_STRATEGY === 'remoteauth') {
        logger.info('🔄 Initializing RemoteAuth with Redis...');
        try {
            await sessionManager.initializeRemoteAuth();
            logger.info('✅ RemoteAuth initialized successfully');
        } catch (error) {
            logger.error('❌ RemoteAuth initialization failed:', {
                error: error.message,
                stack: error.stack
            });
            logger.warn('⚠️ Falling back to LocalAuth');
        }
    }

    // Restore all active sessions from database on startup
    logger.info('🔄 Initiating session restoration...');
    try {
        const result = await sessionManager.accountRestoration.restoreAllSessions();

        if (result.success) {
            logger.info(`✅ Session restoration completed: ${result.restored} restored, ${result.failed} failed, ${result.total || 0} total`);
        } else {
            logger.error('❌ Session restoration failed:', result.error);
        }
    } catch (error) {
        logger.error('❌ Session restoration error:', {
            error: error.message,
            stack: error.stack
        });
    }

    // Initialize Session Cleanup Service (Week 2 Optional Enhancement)
    const SessionCleanupService = require('./src/services/SessionCleanupService');
    const sessionCleanupService = new SessionCleanupService(sessionManager, logger);

    // Schedule cleanup jobs using node-cron
    const cron = require('node-cron');

    // Run cleanup every hour at :00
    cron.schedule('0 * * * *', async () => {
        logger.info('⏰ Starting scheduled session cleanup');
        try {
            const results = await sessionCleanupService.runCleanup();
            logger.info('✅ Scheduled cleanup completed', results);
        } catch (error) {
            logger.error('❌ Scheduled cleanup failed', {
                error: error.message
            });
        }
    });

    logger.info('✅ Session cleanup scheduler initialized (runs hourly at :00)');

    // Make cleanup service available globally for routes
    app.set('sessionCleanupService', sessionCleanupService);
});

module.exports = { app, sessionManager };