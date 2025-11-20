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
const SessionLock = require('./src/utils/SessionLock');

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

// Initialize session lock for cluster coordination
const sessionLock = new SessionLock(logger);

// Attach session lock to session manager
sessionManager.sessionLock = sessionLock;

// Initialize essential services only
const whatsAppRateLimiter = new WhatsAppRateLimiter();
const timeoutHandler = new TimeoutHandler();

// Apply timeout middleware
app.use(timeoutHandler.middleware());

// Setup API routes using extracted router (TASK-ARCH-3: Extract routes to dedicated module)
app.use('/', createRoutes(sessionManager, logger));

// Graceful shutdown
process.on('SIGTERM', async () => {
    logger.info('SIGTERM received, shutting down gracefully');

    // Release all session locks before shutdown
    await sessionLock.releaseAll();
    
    // Use the session manager's shutdown method
    await sessionManager.shutdownAllSessions();

    process.exit(0);
});

process.on('SIGINT', async () => {
    logger.info('SIGINT received, shutting down gracefully');

    // Release all session locks before shutdown
    await sessionLock.releaseAll();
    
    // Use the session manager's shutdown method
    await sessionManager.shutdownAllSessions();

    process.exit(0);
});

// Start server
app.listen(PORT, async () => {
    const workerId = process.env.pm_id || process.pid;
    const isCluster = process.env.NODE_APP_INSTANCE !== undefined || process.env.pm_id !== undefined;
    
    logger.info(`WhatsApp Service started on port ${PORT}`);
    logger.info(`Worker ID: ${workerId} | Cluster: ${isCluster}`);
    logger.info(`Laravel backend: ${process.env.LARAVEL_URL}`);
    logger.info(`Environment: ${process.env.NODE_ENV || 'development'}`);

    // CLUSTER-AWARE SESSION RESTORATION
    // Only worker 0 restores sessions to avoid conflicts
    if (!isCluster || process.env.pm_id === '0' || process.env.NODE_APP_INSTANCE === '0') {
        logger.info('🔄 Initiating session restoration (PRIMARY WORKER)...');
        
        // Wait 3 seconds for all workers to initialize
        await new Promise(resolve => setTimeout(resolve, 3000));
        
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
    } else {
        logger.info(`⏭️  Skipping session restoration (WORKER ${workerId})`);
    }
});

module.exports = { app, sessionManager };