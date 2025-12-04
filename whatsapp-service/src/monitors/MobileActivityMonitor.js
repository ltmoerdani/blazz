/**
 * MobileActivityMonitor
 *
 * Monitors mobile WhatsApp activity per SESSION and emits webhooks.
 * Tracks when user sends messages from mobile device.
 *
 * @module monitors/MobileActivityMonitor
 */

const axios = require('axios');

class MobileActivityMonitor {
    /**
     * @param {Object} options
     * @param {Object} options.logger - Logger instance
     * @param {string} options.webhookUrl - Laravel webhook URL
     * @param {number} options.activityTimeoutMs - Activity timeout (default: 60000)
     */
    constructor(options = {}) {
        this.logger = options.logger || console;
        this.webhookUrl = options.webhookUrl || process.env.LARAVEL_WEBHOOK_URL;
        this.activityTimeoutMs = options.activityTimeoutMs || 60000;

        // Map<sessionId, ActivityData>
        // Track per SESSION, not per chat
        this.activityMap = new Map();

        // Cleanup interval
        this.cleanupInterval = setInterval(() => {
            this.clearExpired();
        }, 60000);
    }

    /**
     * Track mobile activity for a session
     *
     * @param {string} sessionId - WhatsApp session ID
     * @param {string} deviceType - Device type: android, ios, web, unknown
     * @param {string} messageId - Message ID
     * @param {number} workspaceId - Workspace ID
     * @returns {Promise<Object>}
     */
    async trackActivity(sessionId, deviceType, messageId, workspaceId) {
        try {
            // Skip if device type is 'web' (our own client)
            if (deviceType === 'web') {
                this.logger.debug('Skipping web device type', { sessionId });
                return {
                    success: true,
                    data: { skipped: true, reason: 'web_device' },
                    message: 'Web device type skipped'
                };
            }

            const now = new Date();
            const existing = this.activityMap.get(sessionId);

            const activityData = {
                lastActivity: now,
                deviceType,
                messageCount: existing ? existing.messageCount + 1 : 1,
                firstActivity: existing ? existing.firstActivity : now,
                lastMessageId: messageId
            };

            this.activityMap.set(sessionId, activityData);

            this.logger.info('Mobile activity tracked', {
                sessionId,
                deviceType,
                messageCount: activityData.messageCount
            });

            // Emit webhook to Laravel
            const webhookResult = await this._emitWebhook(
                sessionId,
                deviceType,
                messageId,
                workspaceId
            );

            return {
                success: true,
                data: {
                    tracked: true,
                    activityData,
                    webhookSent: webhookResult.success
                },
                message: 'Activity tracked successfully'
            };

        } catch (error) {
            this.logger.error('Failed to track mobile activity', {
                sessionId,
                error: error.message
            });

            return {
                success: false,
                data: null,
                message: error.message
            };
        }
    }

    /**
     * Check if session has recent mobile activity
     *
     * @param {string} sessionId
     * @param {number} withinSeconds - Check within this many seconds
     * @returns {boolean}
     */
    isSessionActive(sessionId, withinSeconds = 30) {
        const activity = this.activityMap.get(sessionId);
        if (!activity) return false;

        const elapsed = (Date.now() - activity.lastActivity.getTime()) / 1000;
        return elapsed < withinSeconds;
    }

    /**
     * Get last activity timestamp for session
     *
     * @param {string} sessionId
     * @returns {Date|null}
     */
    getLastActivity(sessionId) {
        const activity = this.activityMap.get(sessionId);
        return activity ? activity.lastActivity : null;
    }

    /**
     * Get seconds since last activity
     *
     * @param {string} sessionId
     * @returns {number|null}
     */
    getSecondsSinceLastActivity(sessionId) {
        const activity = this.activityMap.get(sessionId);
        if (!activity) return null;

        return Math.floor((Date.now() - activity.lastActivity.getTime()) / 1000);
    }

    /**
     * Get activity data for a session (for internal API)
     *
     * @param {string} sessionId
     * @returns {Object|null}
     */
    getActivityData(sessionId) {
        const activity = this.activityMap.get(sessionId);
        if (!activity) return null;

        return {
            session_id: sessionId,
            last_activity: activity.lastActivity.toISOString(),
            device_type: activity.deviceType,
            seconds_since_activity: this.getSecondsSinceLastActivity(sessionId),
            message_count: activity.messageCount
        };
    }

    /**
     * Clear expired activity entries
     *
     * @returns {number} Count of cleared entries
     */
    clearExpired() {
        let clearedCount = 0;
        const now = Date.now();

        for (const [sessionId, activity] of this.activityMap.entries()) {
            const elapsed = now - activity.lastActivity.getTime();
            if (elapsed >= this.activityTimeoutMs) {
                this.activityMap.delete(sessionId);
                clearedCount++;
            }
        }

        if (clearedCount > 0) {
            this.logger.debug('Cleared expired activity entries', { count: clearedCount });
        }

        return clearedCount;
    }

    /**
     * Emit webhook to Laravel backend
     *
     * @private
     */
    async _emitWebhook(sessionId, deviceType, messageId, workspaceId) {
        try {
            const payload = {
                event: 'mobile_activity_detected',
                session_id: sessionId,
                timestamp: new Date().toISOString(),
                data: {
                    device_type: deviceType,
                    message_id: messageId,
                    workspace_id: workspaceId
                }
            };

            const response = await axios.post(this.webhookUrl, payload, {
                headers: {
                    'Content-Type': 'application/json',
                    'X-Webhook-Source': 'whatsapp-service'
                },
                timeout: 5000
            });

            this.logger.info('Webhook sent successfully', {
                sessionId,
                status: response.status
            });

            return { success: true, data: response.data };

        } catch (error) {
            this.logger.error('Failed to send webhook', {
                sessionId,
                error: error.message
            });

            return { success: false, message: error.message };
        }
    }

    /**
     * Get statistics
     */
    getStats() {
        let activeSessions = 0;
        const now = Date.now();

        for (const [sessionId, activity] of this.activityMap.entries()) {
            const elapsed = (now - activity.lastActivity.getTime()) / 1000;
            if (elapsed < 60) {
                activeSessions++;
            }
        }

        return {
            totalSessions: this.activityMap.size,
            activeSessions,
            inactiveSessions: this.activityMap.size - activeSessions
        };
    }

    /**
     * Cleanup resources
     */
    destroy() {
        if (this.cleanupInterval) {
            clearInterval(this.cleanupInterval);
        }
        this.activityMap.clear();
    }
}

module.exports = MobileActivityMonitor;