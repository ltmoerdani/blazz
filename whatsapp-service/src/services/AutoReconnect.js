/**
 * Auto-Reconnect Service
 *
 * Handles automatic reconnection for WhatsApp sessions that disconnect
 * due to technical issues (not user-initiated disconnects).
 *
 * Features:
 * - Exponential backoff retry strategy
 * - Distinguish user vs technical disconnects
 * - Maximum retry attempts
 * - Notify Laravel of reconnection status
 *
 * @package WhatsApp Service
 */
class AutoReconnect {
    constructor(sessionManager, logger) {
        this.sessionManager = sessionManager;
        this.logger = logger;
        this.reconnectAttempts = new Map(); // Track attempts per session
        this.maxRetries = 5;
        this.baseDelay = 5000; // 5 seconds
        this.maxDelay = 300000; // 5 minutes
    }

    /**
     * Handle disconnection event
     * Decides whether to auto-reconnect based on disconnect reason
     */
    async handleDisconnection(sessionId, workspaceId, reason) {
        this.logger.info('Session disconnected', {
            sessionId,
            workspaceId,
            reason
        });

        // Check if this is a user-initiated disconnect
        if (this.isUserInitiated(reason)) {
            this.logger.info('User-initiated disconnect, not reconnecting', {
                sessionId
            });
            this.reconnectAttempts.delete(sessionId);
            return;
        }

        // Technical disconnect - attempt auto-reconnect
        this.logger.info('Technical disconnect detected, initiating auto-reconnect', {
            sessionId,
            reason
        });

        await this.scheduleReconnect(sessionId, workspaceId);
    }

    /**
     * Check if disconnect was user-initiated
     */
    isUserInitiated(reason) {
        if (!reason) return false;

        const userInitiatedReasons = [
            'LOGOUT',
            'USER_LOGOUT',
            'LOGGED_OUT',
            'USER_REQUESTED',
            'DELETED_SESSION'
        ];

        const reasonUpper = String(reason).toUpperCase();
        return userInitiatedReasons.some(r => reasonUpper.includes(r));
    }

    /**
     * Schedule reconnection attempt with exponential backoff
     */
    async scheduleReconnect(sessionId, workspaceId) {
        // Get current attempt count
        const attempts = this.reconnectAttempts.get(sessionId) || 0;

        if (attempts >= this.maxRetries) {
            this.logger.error('Max reconnection attempts reached', {
                sessionId,
                attempts
            });

            // Notify Laravel that auto-reconnect failed
            await this.notifyReconnectFailed(sessionId, workspaceId);
            this.reconnectAttempts.delete(sessionId);
            return;
        }

        // Calculate delay with exponential backoff
        const delay = Math.min(
            this.baseDelay * Math.pow(2, attempts),
            this.maxDelay
        );

        this.logger.info('Scheduling reconnection attempt', {
            sessionId,
            attempt: attempts + 1,
            maxRetries: this.maxRetries,
            delayMs: delay
        });

        // Increment attempt counter
        this.reconnectAttempts.set(sessionId, attempts + 1);

        // Schedule reconnection
        setTimeout(async () => {
            await this.attemptReconnect(sessionId, workspaceId);
        }, delay);
    }

    /**
     * Attempt to reconnect session
     */
    async attemptReconnect(sessionId, workspaceId) {
        const attempts = this.reconnectAttempts.get(sessionId) || 0;

        this.logger.info('Attempting reconnection', {
            sessionId,
            workspaceId,
            attempt: attempts
        });

        try {
            // Try to create/restore session
            const result = await this.sessionManager.createSession(sessionId, workspaceId);

            if (result.success) {
                this.logger.info('✅ Auto-reconnection successful', {
                    sessionId,
                    attempts
                });

                // Clear retry counter
                this.reconnectAttempts.delete(sessionId);

                // Notify Laravel of successful reconnection
                await this.notifyReconnectSuccess(sessionId, workspaceId);

            } else {
                throw new Error(result.error || 'Unknown error');
            }

        } catch (error) {
            this.logger.error('Reconnection attempt failed', {
                sessionId,
                attempt: attempts,
                error: error.message
            });

            // Schedule next retry
            await this.scheduleReconnect(sessionId, workspaceId);
        }
    }

    /**
     * Notify Laravel of successful reconnection
     */
    async notifyReconnectSuccess(sessionId, workspaceId) {
        try {
            await this.sessionManager.sendToLaravel('session_reconnected', {
                workspace_id: workspaceId,
                session_id: sessionId,
                status: 'reconnected',
                timestamp: new Date().toISOString()
            });

            this.logger.info('Notified Laravel of successful reconnection', {
                sessionId
            });

        } catch (error) {
            this.logger.error('Failed to notify Laravel of reconnection', {
                sessionId,
                error: error.message
            });
        }
    }

    /**
     * Notify Laravel that auto-reconnect failed
     */
    async notifyReconnectFailed(sessionId, workspaceId) {
        try {
            await this.sessionManager.sendToLaravel('session_reconnect_failed', {
                workspace_id: workspaceId,
                session_id: sessionId,
                status: 'reconnect_failed',
                max_retries: this.maxRetries,
                timestamp: new Date().toISOString()
            });

            this.logger.info('Notified Laravel of reconnection failure', {
                sessionId
            });

        } catch (error) {
            this.logger.error('Failed to notify Laravel of reconnection failure', {
                sessionId,
                error: error.message
            });
        }
    }

    /**
     * Cancel pending reconnection attempts for a session
     */
    cancelReconnect(sessionId) {
        if (this.reconnectAttempts.has(sessionId)) {
            this.logger.info('Cancelling reconnection attempts', { sessionId });
            this.reconnectAttempts.delete(sessionId);
        }
    }

    /**
     * Get reconnection status for a session
     */
    getReconnectStatus(sessionId) {
        return {
            session_id: sessionId,
            reconnecting: this.reconnectAttempts.has(sessionId),
            attempts: this.reconnectAttempts.get(sessionId) || 0,
            max_retries: this.maxRetries
        };
    }
}

module.exports = AutoReconnect;
