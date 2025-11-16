/**
 * Session Controller
 *
 * Handles all session-related API endpoints.
 * Extracted from server.js for better separation of concerns.
 *
 * TASK-ARCH-2: Extract API endpoints to dedicated controller classes
 */
class SessionController {
    constructor(sessionManager, logger) {
        this.sessionManager = sessionManager;
        this.logger = logger;
    }

    /**
     * Create new WhatsApp session
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {Promise<void>}
     */
    async createSession(req, res) {
        try {
            const { workspace_id, account_id, session_id, priority } = req.body;

            // Validate API key

            // Pass options object with priority and account_id
            const options = {
                priority: priority || 'normal',
                account_id: account_id // INTEGER ID from database
            };

            const result = await this.sessionManager.createSession(session_id, workspace_id, options);
            res.json(result);
        } catch (error) {
            this.logger.error('API session creation failed', {
                error: error.message,
                stack: error.stack,
                errorDetails: JSON.stringify(error, Object.getOwnPropertyNames(error))
            });
            res.status(500).json({ error: error.message });
        }
    }

    /**
     * Get session status
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {Promise<void>}
     */
    async getSessionStatus(req, res) {
        try {
            const { sessionId } = req.params;
            const { workspace_id } = req.query;


            const status = await this.sessionManager.getSessionStatus(sessionId);
            if (status) {
                res.json(status);
            } else {
                res.status(404).json({ error: 'Session not found' });
            }
        } catch (error) {
            this.logger.error('API get session status failed', { error: error.message });
            res.status(500).json({ error: error.message });
        }
    }

    /**
     * Disconnect session
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {Promise<void>}
     */
    async disconnectSession(req, res) {
        try {
            const { sessionId } = req.params;
            const { workspace_id } = req.body;


            const result = await this.sessionManager.disconnectSession(sessionId);
            res.json(result);
        } catch (error) {
            this.logger.error('API disconnect session failed', { error: error.message });
            res.status(500).json({ error: error.message });
        }
    }

    /**
     * Reconnect session (disconnect then create new)
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {Promise<void>}
     */
    async reconnectSession(req, res) {
        try {
            const { sessionId } = req.params;
            const { workspace_id, account_id, priority } = req.body;


            // Disconnect existing session first
            await this.sessionManager.disconnectSession(sessionId);

            // Create new session with options
            const options = {
                priority: priority || 'normal',
                account_id: account_id
            };

            const result = await this.sessionManager.createSession(sessionId, workspace_id, options);
            res.json(result);
        } catch (error) {
            this.logger.error('API reconnect session failed', { error: error.message });
            res.status(500).json({ error: error.message });
        }
    }

    /**
     * Regenerate QR code for session
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {Promise<void>}
     */
    async regenerateQR(req, res) {
        try {
            const { sessionId } = req.params;
            const { workspace_id, account_id, priority } = req.body;


            // Disconnect and reconnect to generate new QR
            await this.sessionManager.disconnectSession(sessionId);

            const options = {
                priority: priority || 'normal',
                account_id: account_id
            };

            const result = await this.sessionManager.createSession(sessionId, workspace_id, options);

            res.json({
                qr_code: result.qr_code,
                expires_in: 300
            });
        } catch (error) {
            this.logger.error('API regenerate QR failed', { error: error.message });
            res.status(500).json({ error: error.message });
        }
    }

    /**
     * Get all sessions
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {Promise<void>}
     */
    async getAllSessions(req, res) {
        try {
            const { api_key } = req.query;


            const sessions = this.sessionManager.getAllSessions();
            res.json({
                success: true,
                sessions: sessions,
                total: sessions.length
            });
        } catch (error) {
            this.logger.error('API get all sessions failed', { error: error.message });
            res.status(500).json({ error: error.message });
        }
    }

    /**
     * Restore existing session
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {Promise<void>}
     */
    async restoreSession(req, res) {
        try {
            const { sessionId } = req.params;
            const { workspace_id } = req.body;


            const result = await this.sessionManager.restoreSession(sessionId, workspaceId);
            res.json(result);
        } catch (error) {
            this.logger.error('API restore session failed', { error: error.message });
            res.status(500).json({ error: error.message });
        }
    }

    /**
     * Get session metadata
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {Promise<void>}
     */
    async getSessionMetadata(req, res) {
        try {
            const { sessionId } = req.params;
            const { api_key } = req.query;


            const metadata = this.sessionManager.getSessionMetadata(sessionId);
            if (metadata) {
                res.json({
                    success: true,
                    session_id: sessionId,
                    metadata: metadata
                });
            } else {
                res.status(404).json({ error: 'Session not found' });
            }
        } catch (error) {
            this.logger.error('API get session metadata failed', { error: error.message });
            res.status(500).json({ error: error.message });
        }
    }

    /**
     * Update session metadata
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {Promise<void>}
     */
    async updateSessionMetadata(req, res) {
        try {
            const { sessionId } = req.params;
            const { api_key, updates } = req.body;


            if (!updates || typeof updates !== 'object') {
                return res.status(400).json({ error: 'Invalid updates object' });
            }

            this.sessionManager.updateSessionMetadata(sessionId, updates);

            const updatedMetadata = this.sessionManager.getSessionMetadata(sessionId);
            res.json({
                success: true,
                session_id: sessionId,
                metadata: updatedMetadata
            });
        } catch (error) {
            this.logger.error('API update session metadata failed', { error: error.message });
            res.status(500).json({ error: error.message });
        }
    }
}

module.exports = SessionController;