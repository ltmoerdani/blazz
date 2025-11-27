/**
 * Session Health Monitor
 * 
 * Monitors WhatsApp Web.js sessions for health issues and automatically
 * restarts stuck sessions that are connected but not receiving messages.
 * 
 * Common Issues Detected:
 * - Session connected but no messages received for extended period
 * - High memory usage indicating potential memory leaks
 * - Puppeteer page crashes or hangs
 * - Network connectivity issues
 * 
 * @module sessionHealthMonitor
 */

const logger = require('../utils/logger');

class SessionHealthMonitor {
    constructor(sessionManager) {
        this.sessionManager = sessionManager;
        this.healthChecks = new Map(); // sessionId -> lastActivityTimestamp
        this.restartAttempts = new Map(); // sessionId -> attemptCount
        
        // Configuration
        this.config = {
            checkInterval: parseInt(process.env.HEALTH_CHECK_INTERVAL) || 300000, // 5 minutes
            inactivityThreshold: parseInt(process.env.INACTIVITY_THRESHOLD) || 1800000, // 30 minutes
            maxRestartAttempts: parseInt(process.env.MAX_RESTART_ATTEMPTS) || 3,
            memoryThreshold: parseInt(process.env.MEMORY_THRESHOLD) || 500 * 1024 * 1024, // 500MB
        };

        logger.info('[SessionHealthMonitor] Initialized', {
            checkInterval: `${this.config.checkInterval / 1000}s`,
            inactivityThreshold: `${this.config.inactivityThreshold / 1000}s`,
            maxRestartAttempts: this.config.maxRestartAttempts
        });
    }

    /**
     * Start monitoring all sessions
     */
    start() {
        logger.info('[SessionHealthMonitor] Starting health monitoring');
        
        // Initial health check
        this.performHealthChecks();
        
        // Schedule periodic health checks
        this.intervalId = setInterval(() => {
            this.performHealthChecks();
        }, this.config.checkInterval);
    }

    /**
     * Stop monitoring
     */
    stop() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
            logger.info('[SessionHealthMonitor] Stopped health monitoring');
        }
    }

    /**
     * Perform health checks on all active sessions
     */
    async performHealthChecks() {
        const sessions = this.sessionManager.getActiveSessions();
        
        logger.info('[SessionHealthMonitor] Running health checks', {
            totalSessions: sessions.size,
            timestamp: new Date().toISOString()
        });

        for (const [sessionId, client] of sessions.entries()) {
            try {
                await this.checkSessionHealth(sessionId, client);
            } catch (error) {
                logger.error('[SessionHealthMonitor] Error checking session health', {
                    sessionId,
                    error: error.message
                });
            }
        }
    }

    /**
     * Check individual session health
     */
    async checkSessionHealth(sessionId, client) {
        const metadata = this.sessionManager.metadata.get(sessionId);
        
        if (!metadata) {
            logger.warn('[SessionHealthMonitor] No metadata found for session', { sessionId });
            return;
        }

        const now = Date.now();
        const lastActivity = metadata.lastActivity ? metadata.lastActivity.getTime() : metadata.connectedAt.getTime();
        const inactiveDuration = now - lastActivity;

        // Get session state
        const state = await this.getClientState(client);
        
        logger.debug('[SessionHealthMonitor] Session health check', {
            sessionId,
            workspaceId: metadata.workspaceId,
            state,
            lastActivity: metadata.lastActivity?.toISOString(),
            inactiveDuration: `${Math.floor(inactiveDuration / 1000)}s`,
            isHealthy: inactiveDuration < this.config.inactivityThreshold
        });

        // Check if session is stuck (connected but inactive)
        if (state === 'CONNECTED' && inactiveDuration > this.config.inactivityThreshold) {
            logger.warn('[SessionHealthMonitor] Session appears stuck - connected but inactive', {
                sessionId,
                workspaceId: metadata.workspaceId,
                inactiveDuration: `${Math.floor(inactiveDuration / 60000)} minutes`,
                lastActivity: metadata.lastActivity?.toISOString()
            });

            await this.handleStuckSession(sessionId, metadata);
        }

        // Check memory usage
        if (global.gc) {
            const memUsage = process.memoryUsage();
            if (memUsage.heapUsed > this.config.memoryThreshold) {
                logger.warn('[SessionHealthMonitor] High memory usage detected', {
                    sessionId,
                    heapUsed: `${Math.floor(memUsage.heapUsed / 1024 / 1024)}MB`,
                    threshold: `${Math.floor(this.config.memoryThreshold / 1024 / 1024)}MB`
                });
            }
        }

        // Update last check timestamp
        this.healthChecks.set(sessionId, now);
    }

    /**
     * Get client state safely
     */
    async getClientState(client) {
        try {
            const state = await client.getState();
            return state;
        } catch (error) {
            logger.error('[SessionHealthMonitor] Error getting client state', {
                error: error.message
            });
            return 'UNKNOWN';
        }
    }

    /**
     * Handle stuck session - attempt restart
     */
    async handleStuckSession(sessionId, metadata) {
        const restartCount = this.restartAttempts.get(sessionId) || 0;

        if (restartCount >= this.config.maxRestartAttempts) {
            logger.error('[SessionHealthMonitor] Max restart attempts reached', {
                sessionId,
                workspaceId: metadata.workspaceId,
                attempts: restartCount
            });

            // Mark session as failed
            await this.sessionManager.notifyLaravel('session_health_failed', {
                workspace_id: metadata.workspaceId,
                session_id: sessionId,
                reason: 'Max restart attempts exceeded',
                restart_attempts: restartCount
            });

            return;
        }

        logger.info('[SessionHealthMonitor] Attempting to restart stuck session', {
            sessionId,
            workspaceId: metadata.workspaceId,
            attempt: restartCount + 1,
            maxAttempts: this.config.maxRestartAttempts
        });

        try {
            // Increment restart counter
            this.restartAttempts.set(sessionId, restartCount + 1);

            // Notify Laravel about restart attempt
            await this.sessionManager.notifyLaravel('session_restarting', {
                workspace_id: metadata.workspaceId,
                session_id: sessionId,
                reason: 'Session stuck - no activity detected',
                restart_attempt: restartCount + 1
            });

            // Destroy existing client
            const client = this.sessionManager.clients.get(sessionId);
            if (client) {
                await client.destroy().catch(err => {
                    logger.warn('[SessionHealthMonitor] Error destroying client', {
                        sessionId,
                        error: err.message
                    });
                });
            }

            // Remove from active sessions
            this.sessionManager.clients.delete(sessionId);
            this.sessionManager.metadata.delete(sessionId);

            // Wait a bit before recreating
            await this.sleep(5000);

            // Recreate session
            logger.info('[SessionHealthMonitor] Recreating session after restart', {
                sessionId,
                workspaceId: metadata.workspaceId
            });

            // Trigger session recreation through session manager
            await this.sessionManager.restoreSession({
                session_id: sessionId,
                workspace_id: metadata.workspaceId,
                phone_number: metadata.phoneNumber,
                session_data: metadata.sessionData
            });

            logger.info('[SessionHealthMonitor] Session restart successful', {
                sessionId,
                workspaceId: metadata.workspaceId
            });

            // Reset restart counter on success
            setTimeout(() => {
                this.restartAttempts.delete(sessionId);
            }, 3600000); // Reset after 1 hour

        } catch (error) {
            logger.error('[SessionHealthMonitor] Error restarting session', {
                sessionId,
                workspaceId: metadata.workspaceId,
                error: error.message,
                stack: error.stack
            });

            // Notify Laravel about restart failure
            await this.sessionManager.notifyLaravel('session_restart_failed', {
                workspace_id: metadata.workspaceId,
                session_id: sessionId,
                error: error.message,
                restart_attempt: restartCount + 1
            });
        }
    }

    /**
     * Record activity for a session (called when messages are received)
     */
    recordActivity(sessionId) {
        const metadata = this.sessionManager.metadata.get(sessionId);
        if (metadata) {
            metadata.lastActivity = new Date();
            this.sessionManager.metadata.set(sessionId, metadata);
            
            logger.debug('[SessionHealthMonitor] Activity recorded', {
                sessionId,
                timestamp: metadata.lastActivity.toISOString()
            });
        }
    }

    /**
     * Get health status for all sessions
     */
    getHealthStatus() {
        const status = {
            totalSessions: this.sessionManager.clients.size,
            healthySessions: 0,
            stuckSessions: 0,
            unknownSessions: 0,
            sessions: []
        };

        const now = Date.now();

        for (const [sessionId, client] of this.sessionManager.clients.entries()) {
            const metadata = this.sessionManager.metadata.get(sessionId);
            if (!metadata) continue;

            const lastActivity = metadata.lastActivity ? metadata.lastActivity.getTime() : metadata.connectedAt.getTime();
            const inactiveDuration = now - lastActivity;
            const isHealthy = inactiveDuration < this.config.inactivityThreshold;

            if (isHealthy) {
                status.healthySessions++;
            } else {
                status.stuckSessions++;
            }

            status.sessions.push({
                sessionId,
                workspaceId: metadata.workspaceId,
                phoneNumber: metadata.phoneNumber,
                lastActivity: metadata.lastActivity?.toISOString(),
                inactiveDuration: Math.floor(inactiveDuration / 1000),
                isHealthy,
                restartAttempts: this.restartAttempts.get(sessionId) || 0
            });
        }

        return status;
    }

    /**
     * Helper function to sleep
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

module.exports = SessionHealthMonitor;
