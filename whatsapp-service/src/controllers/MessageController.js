/**
 * Message Controller
 *
 * Handles all message-related API endpoints.
 * Extracted from server.js for better separation of concerns.
 *
 * TASK-ARCH-2: Extract API endpoints to dedicated controller classes
 */
class MessageController {
    constructor(sessionManager, logger) {
        this.sessionManager = sessionManager;
        this.logger = logger;
    }

    /**
     * Send message via WhatsApp
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {Promise<void>}
     */
    async sendMessage(req, res) {
        try {
            const { session_id, workspace_id, recipient_phone, message, type } = req.body;

            // API key validation now handled by middleware

            // Validate required fields
            if (!session_id || !recipient_phone || !message) {
                return res.status(400).json({
                    error: 'Missing required fields',
                    required: ['session_id', 'recipient_phone', 'message']
                });
            }

            // Validate phone number format
            const phoneRegex = /^\+?[\d\s\-\(\)]+$/;
            if (!phoneRegex.test(recipient_phone.replace(/\s/g, ''))) {
                return res.status(400).json({
                    error: 'Invalid phone number format'
                });
            }

            // Prepare message object
            let messageObj;
            if (typeof message === 'string') {
                messageObj = message;
            } else if (typeof message === 'object') {
                messageObj = message;
            } else {
                return res.status(400).json({
                    error: 'Message must be string or object'
                });
            }

            const messageType = type || 'text';

            const result = await this.sessionManager.sendMessage(
                session_id,
                recipient_phone,
                messageObj,
                messageType
            );

            this.logger.info('Message sent via API', {
                session_id,
                recipient_phone,
                message_type: messageType,
                message_id: result.message_id,
                success: result.success
            });

            res.json(result);
        } catch (error) {
            this.logger.error('API send message failed', {
                error: error.message,
                stack: error.stack,
                session_id: req.body.session_id,
                recipient_phone: req.body.recipient_phone
            });

            // Provide more specific error messages
            if (error.message === 'Session not found') {
                return res.status(404).json({ error: 'Session not found' });
            } else if (error.message === 'Session not connected') {
                return res.status(400).json({ error: 'Session not connected. Please check session status.' });
            }

            res.status(500).json({ error: error.message });
        }
    }

    /**
     * Send message with media attachment
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {Promise<void>}
     */
    async sendMediaMessage(req, res) {
        try {
            const { session_id, workspace_id, recipient_phone, media_url, caption, filename } = req.body;

            // API key validation now handled by middleware

            // Validate required fields
            if (!session_id || !recipient_phone || !media_url) {
                return res.status(400).json({
                    error: 'Missing required fields',
                    required: ['session_id', 'recipient_phone', 'media_url']
                });
            }

            const messageObj = {
                mediaUrl: media_url,
                caption: caption || '',
                filename: filename || 'media'
            };

            const result = await this.sessionManager.sendMessage(
                session_id,
                recipient_phone,
                messageObj,
                'media'
            );

            this.logger.info('Media message sent via API', {
                session_id,
                recipient_phone,
                media_url,
                message_id: result.message_id,
                success: result.success
            });

            res.json(result);
        } catch (error) {
            this.logger.error('API send media message failed', {
                error: error.message,
                stack: error.stack,
                session_id: req.body.session_id,
                recipient_phone: req.body.recipient_phone,
                media_url: req.body.media_url
            });

            if (error.message === 'Session not found') {
                return res.status(404).json({ error: 'Session not found' });
            } else if (error.message === 'Session not connected') {
                return res.status(400).json({ error: 'Session not connected. Please check session status.' });
            }

            res.status(500).json({ error: error.message });
        }
    }

    /**
     * Bulk send messages to multiple recipients
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {Promise<void>}
     */
    async bulkSendMessages(req, res) {
        try {
            const { session_id, workspace_id, recipients, message, type } = req.body;

            // API key validation now handled by middleware

            // Validate required fields
            if (!session_id || !recipients || !Array.isArray(recipients) || recipients.length === 0) {
                return res.status(400).json({
                    error: 'Missing required fields',
                    required: ['session_id', 'recipients (array)']
                });
            }

            if (!message) {
                return res.status(400).json({
                    error: 'Message content is required'
                });
            }

            // Validate recipients
            const phoneRegex = /^\+?[\d\s\-\(\)]+$/;
            const invalidRecipients = recipients.filter(phone =>
                !phoneRegex.test(phone.replace(/\s/g, ''))
            );

            if (invalidRecipients.length > 0) {
                return res.status(400).json({
                    error: 'Invalid phone number format for some recipients',
                    invalid_recipients: invalidRecipients
                });
            }

            const messageType = type || 'text';
            const results = [];
            const errors = [];

            // Send messages sequentially to avoid rate limiting
            for (const recipient of recipients) {
                try {
                    const result = await this.sessionManager.sendMessage(
                        session_id,
                        recipient,
                        message,
                        messageType
                    );

                    results.push({
                        recipient: recipient,
                        success: true,
                        message_id: result.message_id,
                        timestamp: result.timestamp
                    });

                    // Small delay between messages to avoid rate limiting
                    await new Promise(resolve => setTimeout(resolve, 1000));

                } catch (error) {
                    errors.push({
                        recipient: recipient,
                        success: false,
                        error: error.message
                    });

                    results.push({
                        recipient: recipient,
                        success: false,
                        error: error.message
                    });
                }
            }

            const successfulCount = results.filter(r => r.success).length;
            const failedCount = results.length - successfulCount;

            this.logger.info('Bulk messages sent via API', {
                session_id,
                total_recipients: recipients.length,
                successful: successfulCount,
                failed: failedCount
            });

            res.json({
                success: true,
                total: results.length,
                successful: successfulCount,
                failed: failedCount,
                results: results,
                errors: errors
            });
        } catch (error) {
            this.logger.error('API bulk send messages failed', {
                error: error.message,
                stack: error.stack,
                session_id: req.body.session_id
            });

            res.status(500).json({ error: error.message });
        }
    }

    /**
     * Get message status (check if message exists in session)
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {Promise<void>}
     */
    async getMessageStatus(req, res) {
        try {
            const { sessionId } = req.params;
            const { message_id } = req.query;

            // API key validation now handled by middleware

            if (!message_id) {
                return res.status(400).json({
                    error: 'Missing required field: message_id'
                });
            }

            const client = this.sessionManager.getClient(sessionId);
            if (!client) {
                return res.status(404).json({ error: 'Session not found' });
            }

            // Get session metadata
            const metadata = this.sessionManager.getSessionMetadata(sessionId);
            if (!metadata || metadata.status !== 'connected') {
                return res.status(400).json({ error: 'Session not connected' });
            }

            // Note: WhatsApp Web.js doesn't have a direct way to check message status
            // This is a placeholder implementation that returns basic session info
            res.json({
                success: true,
                session_id: sessionId,
                message_id: message_id,
                session_status: metadata.status,
                connected_at: metadata.connectedAt,
                last_activity: metadata.lastActivity,
                note: 'Exact message status tracking depends on message_ack events'
            });
        } catch (error) {
            this.logger.error('API get message status failed', {
                error: error.message,
                session_id: req.params.sessionId,
                message_id: req.query.message_id
            });

            res.status(500).json({ error: error.message });
        }
    }

    /**
     * Validate session before sending message
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {Promise<void>}
     */
    async validateSessionForSending(req, res) {
        try {
            const { sessionId } = req.params;
            // API key validation now handled by middleware

            const status = await this.sessionManager.getSessionStatus(sessionId);

            if (!status) {
                return res.status(404).json({
                    success: false,
                    error: 'Session not found',
                    can_send: false
                });
            }

            const canSend = status.status === 'connected';
            const isHealthy = status.last_activity &&
                (Date.now() - new Date(status.last_activity).getTime()) < 300000; // 5 minutes

            res.json({
                success: true,
                session_id: sessionId,
                status: status.status,
                can_send: canSend,
                is_healthy: isHealthy,
                last_activity: status.last_activity,
                phone_number: status.phone_number,
                recommendations: this.getSendingRecommendations(status, canSend, isHealthy)
            });
        } catch (error) {
            this.logger.error('API validate session failed', {
                error: error.message,
                session_id: req.params.sessionId
            });

            res.status(500).json({ error: error.message });
        }
    }

    /**
     * Get sending recommendations based on session status
     *
     * @param {Object} status - Session status
     * @param {boolean} canSend - Whether session can send messages
     * @param {boolean} isHealthy - Whether session is healthy
     * @returns {Array} Array of recommendations
     */
    getSendingRecommendations(status, canSend, isHealthy) {
        const recommendations = [];

        if (!canSend) {
            if (status.status === 'qr_scanning') {
                recommendations.push('Session is waiting for QR code scan');
            } else if (status.status === 'disconnected') {
                recommendations.push('Session is disconnected, please reconnect');
            } else {
                recommendations.push(`Session is ${status.status}, cannot send messages`);
            }
        }

        if (!isHealthy && canSend) {
            recommendations.push('Session has been inactive for a while, consider reconnecting');
        }

        if (canSend && isHealthy) {
            recommendations.push('Session is ready to send messages');
        }

        return recommendations;
    }
}

module.exports = MessageController;