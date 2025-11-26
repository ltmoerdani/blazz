/**
 * RemoteAuth Health Check Routes
 * 
 * Comprehensive health monitoring endpoints for RemoteAuth migration.
 * Provides status checks for Redis, sessions, and system health.
 * 
 * Base Path: /remoteauth
 * 
 * Endpoints:
 * - GET /remoteauth/health              Overall RemoteAuth status
 * - GET /remoteauth/redis               Redis connection status
 * - GET /remoteauth/sessions            Session storage comparison
 * - GET /remoteauth/migration           Migration progress & metrics
 * - GET /remoteauth/migration/:sessionId  Per-session validation
 * 
 * @author Blazz Platform
 * @date November 20, 2025
 * @follows docs/architecture/17-database-compatibility-audit-and-implementation-guide.md
 */

const express = require('express');
const router = express.Router();
const redisConfig = require('../../config/redis');
const SessionMigration = require('../utils/SessionMigration');

/**
 * Overall system health check
 * 
 * GET /remoteauth/health
 * 
 * Response:
 * {
 *   "status": "healthy",
 *   "authStrategy": "remoteauth",
 *   "redis": { ... },
 *   "sessions": { ... },
 *   "timestamp": "2025-11-20T..."
 * }
 */
router.get('/health', async (req, res) => {
    try {
        const health = {
            status: 'healthy',
            authStrategy: process.env.AUTH_STRATEGY || 'localauth',
            timestamp: new Date().toISOString()
        };

        // Check Redis if using RemoteAuth
        if (health.authStrategy === 'remoteauth') {
            try {
                const redisHealth = await redisConfig.getHealthStatus();
                health.redis = {
                    connected: redisHealth.connected,
                    uptime: redisHealth.uptime,
                    memory: redisHealth.memoryUsage
                };
            } catch (error) {
                health.status = 'degraded';
                health.redis = {
                    connected: false,
                    error: error.message
                };
            }
        }

        // Check sessions
        try {
            const sessionManager = req.app.get('sessionManager');
            const activeSessions = sessionManager ? sessionManager.sessions.size : 0;
            
            health.sessions = {
                active: activeSessions,
                storageType: health.authStrategy
            };
        } catch (error) {
            health.sessions = {
                error: error.message
            };
        }

        const statusCode = health.status === 'healthy' ? 200 : 503;
        res.status(statusCode).json(health);

    } catch (error) {
        res.status(500).json({
            status: 'unhealthy',
            error: error.message,
            timestamp: new Date().toISOString()
        });
    }
});

/**
 * Redis-specific health check
 * 
 * GET /health/redis
 * 
 * Response:
 * {
 *   "status": "connected",
 *   "host": "127.0.0.1",
 *   "port": 6379,
 *   "uptime": 123456,
 *   "memoryUsage": "2.5MB",
 *   "connectedClients": 5,
 *   "dbSize": 42,
 *   "lastBackup": "2025-11-20T..."
 * }
 */
router.get('/redis', async (req, res) => {
    try {
        const authStrategy = process.env.AUTH_STRATEGY || 'localauth';

        if (authStrategy !== 'remoteauth') {
            return res.status(200).json({
                status: 'disabled',
                message: 'RemoteAuth not enabled. Current auth strategy: ' + authStrategy
            });
        }

        // Get comprehensive Redis health status
        const health = await redisConfig.getHealthStatus();

        // Add configuration info
        health.config = {
            host: process.env.REDIS_HOST || '127.0.0.1',
            port: process.env.REDIS_PORT || 6379,
            db: process.env.REDIS_DB || 0,
            prefix: process.env.REDIS_SESSION_PREFIX || 'whatsapp:session:',
            ttl: process.env.REDIS_SESSION_TTL || 604800
        };

        const statusCode = health.connected ? 200 : 503;
        res.status(statusCode).json(health);

    } catch (error) {
        res.status(500).json({
            status: 'error',
            error: error.message,
            timestamp: new Date().toISOString()
        });
    }
});

/**
 * Session storage status check
 * 
 * GET /health/sessions
 * 
 * Response:
 * {
 *   "storageType": "remoteauth",
 *   "active": 15,
 *   "localAuth": {
 *     "sessions": 20
 *   },
 *   "remoteAuth": {
 *     "sessions": 15,
 *     "storage": "redis"
 *   },
 *   "migration": {
 *     "completed": 15,
 *     "pending": 5
 *   }
 * }
 */
router.get('/sessions', async (req, res) => {
    try {
        const authStrategy = process.env.AUTH_STRATEGY || 'localauth';
        const sessionManager = req.app.get('sessionManager');

        const status = {
            storageType: authStrategy,
            active: sessionManager ? sessionManager.sessions.size : 0,
            timestamp: new Date().toISOString()
        };

        // Count LocalAuth sessions
        try {
            const migration = new SessionMigration();
            const localSessions = await migration.findAllSessions();
            
            status.localAuth = {
                sessions: localSessions.length,
                path: './sessions'
            };
        } catch (error) {
            status.localAuth = {
                error: error.message
            };
        }

        // Count RemoteAuth sessions (Redis)
        if (authStrategy === 'remoteauth') {
            try {
                const store = redisConfig.getStore();
                const redisSessions = await store.getAllSessions();
                
                status.remoteAuth = {
                    sessions: redisSessions.length,
                    storage: 'redis',
                    sessionIds: redisSessions.slice(0, 10) // Show first 10
                };

                // Calculate migration progress
                if (status.localAuth.sessions !== undefined) {
                    status.migration = {
                        completed: status.remoteAuth.sessions,
                        pending: status.localAuth.sessions - status.remoteAuth.sessions,
                        progress: Math.round((status.remoteAuth.sessions / status.localAuth.sessions) * 100) + '%'
                    };
                }
            } catch (error) {
                status.remoteAuth = {
                    error: error.message
                };
            }
        }

        res.status(200).json(status);

    } catch (error) {
        res.status(500).json({
            status: 'error',
            error: error.message,
            timestamp: new Date().toISOString()
        });
    }
});

/**
 * Migration status and metrics
 * 
 * GET /health/migration
 * 
 * Response:
 * {
 *   "status": "in-progress",
 *   "totalSessions": 20,
 *   "migratedSessions": 15,
 *   "pendingSessions": 5,
 *   "progress": "75%",
 *   "lastMigration": "2025-11-20T...",
 *   "issues": []
 * }
 */
router.get('/migration', async (req, res) => {
    try {
        const authStrategy = process.env.AUTH_STRATEGY || 'localauth';

        if (authStrategy !== 'remoteauth') {
            return res.status(200).json({
                status: 'not-started',
                message: 'RemoteAuth not enabled. Migration has not begun.',
                currentStrategy: authStrategy
            });
        }

        const migration = new SessionMigration();
        await migration.initializeRedis();

        // Find all local sessions
        const localSessions = await migration.findAllSessions();

        // Check which sessions exist in Redis
        const migratedSessions = [];
        const pendingSessions = [];
        const issues = [];

        for (const sessionId of localSessions) {
            const validation = await migration.validateSession(sessionId);

            if (validation.success) {
                if (validation.validation.remoteAuth.exists) {
                    migratedSessions.push(sessionId);

                    // Check for size mismatch
                    if (!validation.validation.match) {
                        issues.push({
                            sessionId,
                            issue: 'size_mismatch',
                            local: validation.validation.localAuth.size,
                            redis: validation.validation.remoteAuth.size
                        });
                    }
                } else {
                    pendingSessions.push(sessionId);
                }
            } else {
                issues.push({
                    sessionId,
                    issue: 'validation_error',
                    error: validation.error
                });
            }
        }

        const total = localSessions.length;
        const migrated = migratedSessions.length;
        const pending = pendingSessions.length;

        const status = {
            status: pending === 0 ? 'completed' : (migrated > 0 ? 'in-progress' : 'not-started'),
            totalSessions: total,
            migratedSessions: migrated,
            pendingSessions: pending,
            progress: total > 0 ? Math.round((migrated / total) * 100) + '%' : '0%',
            timestamp: new Date().toISOString()
        };

        if (issues.length > 0) {
            status.issues = issues;
            status.status = 'warning';
        }

        if (pending > 0) {
            status.pendingSessionIds = pendingSessions.slice(0, 10); // Show first 10
        }

        res.status(200).json(status);

    } catch (error) {
        res.status(500).json({
            status: 'error',
            error: error.message,
            timestamp: new Date().toISOString()
        });
    }
});

/**
 * Validate specific session
 * 
 * GET /health/migration/:sessionId
 * 
 * Response:
 * {
 *   "sessionId": "session_123",
 *   "localAuth": { ... },
 *   "remoteAuth": { ... },
 *   "match": true
 * }
 */
router.get('/migration/:sessionId', async (req, res) => {
    try {
        const { sessionId } = req.params;
        const migration = new SessionMigration();
        await migration.initializeRedis();

        const validation = await migration.validateSession(sessionId);

        if (!validation.success) {
            return res.status(404).json({
                error: validation.error,
                sessionId
            });
        }

        res.status(200).json(validation.validation);

    } catch (error) {
        res.status(500).json({
            status: 'error',
            error: error.message,
            sessionId: req.params.sessionId
        });
    }
});

module.exports = router;
