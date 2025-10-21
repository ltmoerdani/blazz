const winston = require('winston');

class SessionPool {
    constructor(sessionManager) {
        this.sessionManager = sessionManager;
        this.maxConcurrentSessions = parseInt(process.env.MAX_CONCURRENT_SESSIONS) || 50;
        this.sessionQueue = [];
        this.activeSessions = new Set();
        this.sessionLimits = new Map(); // Track per-workspace limits
        this.isProcessingQueue = false;
    }

    async requestSession(workspaceId, sessionId, priority = 'normal') {
        const sessionKey = `${workspaceId}:${sessionId}`;

        // Check if session is already active
        if (this.activeSessions.has(sessionKey)) {
            return { success: true, status: 'already_active' };
        }

        // Check if we've reached the global limit
        if (this.activeSessions.size >= this.maxConcurrentSessions) {
            if (priority === 'high') {
                // Try to free up a low-priority session
                const freed = await this.freeLowPrioritySession();

                if (!freed) {
                    // Queue the request if it's high priority
                    return this.queueSessionRequest(workspaceId, sessionId, priority);
                }
            } else {
                // Queue normal priority requests
                return this.queueSessionRequest(workspaceId, sessionId, priority);
            }
        }

        // Check workspace-specific limits
        const workspaceLimit = await this.getWorkspaceLimit(workspaceId);
        const workspaceActiveCount = await this.getWorkspaceActiveCount(workspaceId);

        if (workspaceActiveCount >= workspaceLimit) {
            return {
                success: false,
                error: `Workspace limit reached: ${workspaceActiveCount}/${workspaceLimit}`,
                queued: true
            };
        }

        // Activate the session
        this.activeSessions.add(sessionKey);

        winston.info('Session activated in pool', {
            sessionId,
            workspaceId,
            activeCount: this.activeSessions.size,
            maxLimit: this.maxConcurrentSessions
        });

        return {
            success: true,
            status: 'activated',
            activeCount: this.activeSessions.size
        };
    }

    async releaseSession(workspaceId, sessionId) {
        const sessionKey = `${workspaceId}:${sessionId}`;

        if (this.activeSessions.has(sessionKey)) {
            this.activeSessions.delete(sessionKey);

            winston.info('Session released from pool', {
                sessionId,
                workspaceId,
                activeCount: this.activeSessions.size
            });

            // Process queued requests
            if (this.sessionQueue.length > 0) {
                setImmediate(() => this.processQueue());
            }

            return { success: true };
        }

        return { success: false, error: 'Session not found in pool' };
    }

    async queueSessionRequest(workspaceId, sessionId, priority) {
        const request = {
            workspaceId,
            sessionId,
            priority,
            timestamp: Date.now(),
            sessionKey: `${workspaceId}:${sessionId}`
        };

        // Insert into queue based on priority
        if (priority === 'high') {
            this.sessionQueue.unshift(request); // Add to front
        } else {
            this.sessionQueue.push(request); // Add to back
        }

        winston.info('Session request queued', {
            sessionId,
            workspaceId,
            priority,
            queueLength: this.sessionQueue.length
        });

        // Try to process queue immediately
        if (!this.isProcessingQueue) {
            setImmediate(() => this.processQueue());
        }

        return {
            success: false,
            queued: true,
            position: this.sessionQueue.length,
            estimatedWait: this.estimateWaitTime()
        };
    }

    async processQueue() {
        if (this.isProcessingQueue || this.sessionQueue.length === 0) {
            return;
        }

        if (this.activeSessions.size >= this.maxConcurrentSessions) {
            return; // Still at capacity
        }

        this.isProcessingQueue = true;

        try {
            // Process requests in priority order
            const request = this.sessionQueue.shift();
            if (!request) return;

            // Check if session is still needed (not already active)
            const sessionKey = request.sessionKey;
            if (this.activeSessions.has(sessionKey)) {
                winston.debug('Session already active, skipping queue request', { sessionKey });
                return;
            }

            // Check workspace limits
            const workspaceLimit = await this.getWorkspaceLimit(request.workspaceId);
            const workspaceActiveCount = await this.getWorkspaceActiveCount(request.workspaceId);

            if (workspaceActiveCount >= workspaceLimit) {
                winston.debug('Workspace limit reached, re-queuing request', {
                    workspaceId: request.workspaceId,
                    activeCount: workspaceActiveCount,
                    limit: workspaceLimit
                });

                // Re-queue with lower priority
                request.priority = 'low';
                this.sessionQueue.push(request);
                return;
            }

            // Check if enough capacity is available
            if (this.activeSessions.size >= this.maxConcurrentSessions) {
                // Re-queue the request
                this.sessionQueue.unshift(request);
                return;
            }

            // Activate the session
            this.activeSessions.add(sessionKey);

            winston.info('Queued session activated', {
                sessionId: request.sessionId,
                workspaceId: request.workspaceId,
                queueLength: this.sessionQueue.length,
                activeCount: this.activeSessions.size
            });

            // Process next item in queue if capacity available
            if (this.sessionQueue.length > 0 && this.activeSessions.size < this.maxConcurrentSessions) {
                setImmediate(() => this.processQueue());
            }

        } catch (error) {
            winston.error('Error processing session queue', { error: error.message });
        } finally {
            this.isProcessingQueue = false;
        }
    }

    async freeLowPrioritySession() {
        // Find and disconnect a low-priority session to make room
        const sessions = this.sessionManager.getAllSessions();
        const lowPrioritySessions = sessions.filter(s =>
            s.status === 'connected' &&
            this.getSessionPriority(s.session_id) === 'low'
        );

        if (lowPrioritySessions.length > 0) {
            const sessionToFree = lowPrioritySessions[0];

            winston.info('Freeing low-priority session for high-priority request', {
                freedSessionId: sessionToFree.session_id,
                workspaceId: sessionToFree.workspace_id
            });

            await this.sessionManager.disconnectSession(sessionToFree.session_id);
            return true;
        }

        return false;
    }

    getSessionPriority(sessionId) {
        // This would be based on session metadata or configuration
        // For now, return 'normal' as default
        return 'normal';
    }

    async getWorkspaceLimit(workspaceId) {
        // Get workspace-specific limits from database or configuration
        // For now, use a default limit
        const defaultLimit = 10; // 10 sessions per workspace

        // TODO: Query database for actual workspace limits
        // const workspace = await this.queryWorkspaceLimits(workspaceId);

        return defaultLimit;
    }

    async getWorkspaceActiveCount(workspaceId) {
        let count = 0;

        for (const sessionKey of this.activeSessions) {
            const [wsId] = sessionKey.split(':');
            if (wsId === workspaceId) {
                count++;
            }
        }

        return count;
    }

    estimateWaitTime() {
        if (this.sessionQueue.length === 0) return 0;

        // Estimate based on average session duration and queue position
        const avgSessionDuration = 60 * 60 * 1000; // 1 hour
        const estimatedWait = (this.sessionQueue.length * avgSessionDuration) / this.maxConcurrentSessions;

        return Math.min(estimatedWait, 30 * 60 * 1000); // Max 30 minutes
    }

    getPoolStatus() {
        const workspaceStats = {};

        // Calculate per-workspace statistics
        for (const sessionKey of this.activeSessions) {
            const [workspaceId] = sessionKey.split(':');

            if (!workspaceStats[workspaceId]) {
                workspaceStats[workspaceId] = {
                    active: 0,
                    queued: 0
                };
            }

            workspaceStats[workspaceId].active++;
        }

        // Calculate queued per workspace
        for (const request of this.sessionQueue) {
            if (!workspaceStats[request.workspaceId]) {
                workspaceStats[request.workspaceId] = {
                    active: 0,
                    queued: 0
                };
            }

            workspaceStats[request.workspaceId].queued++;
        }

        return {
            totalActive: this.activeSessions.size,
            maxConcurrent: this.maxConcurrentSessions,
            queueLength: this.sessionQueue.length,
            utilizationPercent: Math.round((this.activeSessions.size / this.maxConcurrentSessions) * 100),
            workspaceStats,
            estimatedWaitTime: this.estimateWaitTime()
        };
    }

    setWorkspaceLimit(workspaceId, limit) {
        this.sessionLimits.set(workspaceId, limit);
        winston.info('Workspace session limit updated', { workspaceId, limit });
    }

    async cleanupStaleSessions() {
        const now = Date.now();
        const staleThreshold = 2 * 60 * 60 * 1000; // 2 hours

        for (const sessionKey of this.activeSessions) {
            const [workspaceId, sessionId] = sessionKey.split(':');
            const metadata = this.sessionManager.getSessionMetadata(sessionId);

            if (metadata && metadata.lastActivity) {
                const inactiveTime = now - metadata.lastActivity;

                if (inactiveTime > staleThreshold) {
                    winston.warning('Removing stale session from pool', {
                        sessionId,
                        workspaceId,
                        inactiveMinutes: Math.round(inactiveTime / (1000 * 60))
                    });

                    await this.releaseSession(workspaceId, sessionId);
                }
            }
        }
    }

    // Enhanced session manager methods for pool integration
    enhanceSessionManager() {
        const originalCreateSession = this.sessionManager.createSession.bind(this.sessionManager);
        const originalDisconnectSession = this.sessionManager.disconnectSession.bind(this.sessionManager);

        this.sessionManager.createSession = async (sessionId, workspaceId, options = {}) => {
            // Ensure options object exists with defaults
            const sessionOptions = {
                priority: 'normal',
                ...options
            };

            // Request session from pool
            const poolResult = await this.requestSession(workspaceId, sessionId, sessionOptions.priority);

            if (!poolResult.success) {
                throw new Error(poolResult.error || 'Session pool unavailable');
            }

            try {
                // Create the actual session
                const result = await originalCreateSession(sessionId, workspaceId);

                // Update metadata with pool information
                const metadata = this.sessionManager.getSessionMetadata(sessionId) || {};
                metadata.poolActivated = new Date();
                metadata.priority = sessionOptions.priority;
                this.sessionManager.updateSessionMetadata(sessionId, metadata);

                return result;
            } catch (error) {
                // Release pool slot on failure
                await this.releaseSession(workspaceId, sessionId);
                throw error;
            }
        };

        this.sessionManager.disconnectSession = async (sessionId) => {
            const metadata = this.sessionManager.getSessionMetadata(sessionId);

            // Release from pool
            if (metadata && metadata.workspaceId) {
                await this.releaseSession(metadata.workspaceId, sessionId);
            }

            // Disconnect the actual session
            return await originalDisconnectSession(sessionId);
        };

        this.sessionManager.getSessionMetadata = (sessionId) => {
            return this.sessionManager.metadata.get(sessionId) || {};
        };

        this.sessionManager.updateSessionMetadata = (sessionId, metadata) => {
            const existing = this.sessionManager.metadata.get(sessionId) || {};
            const updated = { ...existing, ...metadata };
            this.sessionManager.metadata.set(sessionId, updated);
            return updated;
        };
    }
}

module.exports = SessionPool;
