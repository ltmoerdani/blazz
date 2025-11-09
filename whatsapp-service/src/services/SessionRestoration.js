const axios = require('axios');

/**
 * Session Restoration Service
 *
 * Handles automatic session restoration on Node.js service startup.
 * Queries Laravel database for all "connected" sessions and restores them
 * from LocalAuth disk storage.
 *
 * Features:
 * - Query Laravel for active sessions on startup
 * - Restore sessions from disk via LocalAuth
 * - Handle restoration failures gracefully
 * - Update Laravel DB with restoration status
 *
 * @package WhatsApp Service
 */
class SessionRestoration {
    constructor(sessionManager, logger) {
        this.sessionManager = sessionManager;
        this.logger = logger;
        this.laravelUrl = process.env.LARAVEL_URL;
        this.apiKey = process.env.API_KEY || process.env.LARAVEL_API_TOKEN;
    }

    /**
     * Restore all active sessions from Laravel database
     * Called once on Node.js service startup
     */
    async restoreAllSessions() {
        this.logger.info('🔄 Starting session restoration from database...');

        try {
            // Get all active sessions from Laravel
            const sessions = await this.getActiveSessions();

            if (!sessions || sessions.length === 0) {
                this.logger.info('No active sessions found in database');
                return {
                    success: true,
                    restored: 0,
                    failed: 0
                };
            }

            this.logger.info(`Found ${sessions.length} active session(s) to restore`);

            // Restore each session
            const results = await Promise.allSettled(
                sessions.map(session => this.restoreSession(session))
            );

            // Count successes and failures
            const restored = results.filter(r => r.status === 'fulfilled' && r.value.success).length;
            const failed = results.filter(r => r.status === 'rejected' || !r.value.success).length;

            this.logger.info(`Session restoration completed: ${restored} restored, ${failed} failed`);

            return {
                success: true,
                restored,
                failed,
                total: sessions.length
            };

        } catch (error) {
            this.logger.error('Session restoration failed', {
                error: error.message,
                stack: error.stack
            });

            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Get all active sessions from Laravel database
     */
    async getActiveSessions() {
        try {
            const response = await axios.get(
                `${this.laravelUrl}/api/whatsapp/sessions/active`,
                {
                    headers: {
                        'X-API-Key': this.apiKey,
                        'Content-Type': 'application/json'
                    },
                    timeout: 10000
                }
            );

            if (response.data && response.data.sessions) {
                return response.data.sessions;
            }

            return [];

        } catch (error) {
            this.logger.error('Failed to fetch active sessions from Laravel', {
                error: error.message,
                response: error.response?.data
            });

            throw error;
        }
    }

    /**
     * Restore a single session
     */
    async restoreSession(sessionData) {
        const { session_id, workspace_id, phone_number } = sessionData;

        this.logger.info(`Restoring session: ${session_id}`, {
            workspace_id,
            phone_number
        });

        try {
            // Check if session already exists in memory
            if (this.sessionManager.sessions.has(session_id)) {
                this.logger.info(`Session ${session_id} already exists in memory, skipping`);
                return { success: true, skipped: true };
            }

            // Create session - LocalAuth will restore from disk
            const result = await this.sessionManager.createSession(session_id, workspace_id);

            if (result.success) {
                this.logger.info(`✅ Session restored successfully: ${session_id}`);
                return { success: true, session_id };
            } else {
                throw new Error(result.error || 'Unknown error');
            }

        } catch (error) {
            this.logger.error(`❌ Failed to restore session: ${session_id}`, {
                error: error.message,
                workspace_id
            });

            // Mark session as disconnected in Laravel
            await this.markSessionAsDisconnected(session_id, workspace_id, error.message);

            return {
                success: false,
                session_id,
                error: error.message
            };
        }
    }

    /**
     * Mark session as disconnected in Laravel database
     */
    async markSessionAsDisconnected(sessionId, workspaceId, reason) {
        try {
            await axios.post(
                `${this.laravelUrl}/api/whatsapp/sessions/${sessionId}/mark-disconnected`,
                {
                    workspace_id: workspaceId,
                    reason: reason || 'Failed to restore session on startup'
                },
                {
                    headers: {
                        'X-API-Key': this.apiKey,
                        'Content-Type': 'application/json'
                    },
                    timeout: 5000
                }
            );

            this.logger.info(`Marked session as disconnected in Laravel: ${sessionId}`);

        } catch (error) {
            this.logger.error(`Failed to mark session as disconnected: ${sessionId}`, {
                error: error.message
            });
        }
    }

    /**
     * Restore single session by ID (for manual reconnection)
     */
    async restoreSingleSession(sessionId, workspaceId) {
        this.logger.info(`Manual restoration requested for session: ${sessionId}`);

        try {
            const sessionData = {
                session_id: sessionId,
                workspace_id: workspaceId
            };

            return await this.restoreSession(sessionData);

        } catch (error) {
            this.logger.error(`Failed to restore session manually: ${sessionId}`, {
                error: error.message
            });

            return {
                success: false,
                error: error.message
            };
        }
    }
}

module.exports = SessionRestoration;
