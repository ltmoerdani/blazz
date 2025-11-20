/**
 * Webhook Notifier Utility
 *
 * Handles secure communication from Node.js WhatsApp service to Laravel backend
 * using HMAC SHA256 signature authentication.
 *
 * Security Features:
 * - HMAC SHA256 signature (X-HMAC-Signature header)
 * - Timestamp-based anti-replay protection (X-Timestamp header)
 * - Retry mechanism with exponential backoff
 *
 * @module webhookNotifier
 */

const crypto = require('crypto');
const axios = require('axios');

class WebhookNotifier {
    constructor(logger = console) {
        this.logger = logger;
        this.secret = process.env.HMAC_SECRET || process.env.API_SECRET;
        this.laravelUrl = process.env.LARAVEL_URL || 'http://localhost:8000';
        this.maxRetries = parseInt(process.env.WEBHOOK_MAX_RETRIES) || 3;
        this.timeout = parseInt(process.env.WEBHOOK_TIMEOUT) || 30000; // 30 seconds (increased from 10s due to queue processing)

        if (!this.secret) {
            this.logger.error('[WebhookNotifier] FATAL: HMAC_SECRET not configured in environment');
            throw new Error('HMAC_SECRET environment variable is required');
        }

        this.logger.info('[WebhookNotifier] Initialized', {
            laravelUrl: this.laravelUrl,
            maxRetries: this.maxRetries,
            timeout: this.timeout
        });
    }

    /**
     * Calculate HMAC signature for payload
     *
     * @param {string} timestamp - Unix timestamp as string
     * @param {string} payload - JSON payload as string
     * @returns {string} HMAC SHA256 hex signature
     */
    calculateSignature(timestamp, payload) {
        // Match Laravel's VerifyWhatsAppHmac middleware: hash_hmac(timestamp + payload, secret)
        const data = timestamp + payload;
        return crypto.createHmac('sha256', this.secret).update(data).digest('hex');
    }

    /**
     * Send notification to Laravel webhook endpoint
     *
     * @param {string} endpoint - Webhook endpoint path (e.g., '/api/whatsapp/webhooks/webjs')
     * @param {Object} payload - Data to send
     * @param {Object} options - Additional options
     * @param {number} options.retryCount - Current retry attempt (internal use)
     * @returns {Promise<Object>} Laravel response data
     * @throws {Error} If all retry attempts fail
     */
    async notify(endpoint, payload, options = {}) {
        const retryCount = options.retryCount || 0;
        const timestamp = Math.floor(Date.now() / 1000).toString();
        const body = JSON.stringify(payload);
        const signature = this.calculateSignature(timestamp, body);

        const url = `${this.laravelUrl}${endpoint}`;

        this.logger.info('[WebhookNotifier] Sending webhook notification', {
            endpoint,
            url,
            timestamp,
            payload_size: body.length,
            retry_count: retryCount,
            has_signature: !!signature,
        });

        try {
            const response = await axios.post(url, body, {
                headers: {
                    'Content-Type': 'application/json',
                    'X-HMAC-Signature': signature,
                    'X-Timestamp': timestamp,
                    'User-Agent': 'WhatsApp-WebJS-Service/1.0',
                },
                timeout: this.timeout,
                validateStatus: (status) => status < 500, // Don't throw on 4xx errors
            });

            if (response.status >= 200 && response.status < 300) {
                this.logger.info('[WebhookNotifier] Webhook notification successful', {
                    endpoint,
                    status: response.status,
                    response_data: response.data,
                });

                return response.data;
            } else if (response.status === 401) {
                this.logger.error('[WebhookNotifier] Webhook authentication failed', {
                    endpoint,
                    status: response.status,
                    error: response.data?.message,
                    timestamp,
                });

                throw new Error(`Webhook authentication failed: ${response.data?.message || 'Invalid signature'}`);
            } else if (response.status === 429) {
                this.logger.warn('[WebhookNotifier] Rate limit exceeded', {
                    endpoint,
                    status: response.status,
                });

                // Retry with exponential backoff for rate limits
                if (retryCount < this.maxRetries) {
                    const delay = Math.pow(2, retryCount) * 1000; // 1s, 2s, 4s
                    this.logger.info(`[WebhookNotifier] Retrying after ${delay}ms (attempt ${retryCount + 1}/${this.maxRetries})`);
                    await this.sleep(delay);
                    return this.notify(endpoint, payload, { retryCount: retryCount + 1 });
                }

                throw new Error('Webhook rate limit exceeded after retries');
            } else {
                this.logger.error('[WebhookNotifier] Webhook request failed', {
                    endpoint,
                    status: response.status,
                    error: response.data,
                });

                throw new Error(`Webhook failed with status ${response.status}: ${JSON.stringify(response.data)}`);
            }

        } catch (error) {
            // Network errors, timeout errors
            if (error.code === 'ECONNABORTED' || error.code === 'ECONNREFUSED' || error.code === 'ETIMEDOUT') {
                this.logger.error('[WebhookNotifier] Network error', {
                    endpoint,
                    error_code: error.code,
                    message: error.message,
                    retry_count: retryCount,
                });

                // Retry with exponential backoff
                if (retryCount < this.maxRetries) {
                    const delay = Math.pow(2, retryCount) * 2000; // 2s, 4s, 8s
                    this.logger.info(`[WebhookNotifier] Retrying after ${delay}ms (attempt ${retryCount + 1}/${this.maxRetries})`);
                    await this.sleep(delay);
                    return this.notify(endpoint, payload, { retryCount: retryCount + 1 });
                }
            }

            // Re-throw if not retryable or max retries exceeded
            this.logger.error('[WebhookNotifier] Webhook notification failed permanently', {
                endpoint,
                error: error.message,
                retries: retryCount,
            });

            throw error;
        }
    }

    /**
     * Send chat sync batch to Laravel
     *
     * @param {number} accountId - WhatsApp Account ID (INTEGER from database)
     * @param {number} workspaceId - Workspace ID
     * @param {Array} chats - Array of chat objects
     * @returns {Promise<Object>} Laravel response
     */
    async syncChatBatch(accountId, workspaceId, chats) {
        return this.notify('/api/whatsapp/chats/sync', {
            whatsapp_account_id: accountId,  // INTEGER ID from database
            workspace_id: workspaceId,
            chats: chats,
        });
    }

    /**
     * Send incoming message notification to Laravel
     *
     * @param {Object} messageData - Message data object
     * @returns {Promise<Object>} Laravel response
     */
    async notifyIncomingMessage(messageData) {
        return this.notify('/api/whatsapp/webhooks/webjs', messageData);
    }

    /**
     * Send session status change to Laravel
     *
     * @param {number} sessionId - WhatsApp session ID
     * @param {string} status - New status (connected, disconnected, etc.)
     * @param {Object} additionalData - Additional status data
     * @returns {Promise<Object>} Laravel response
     */
    async notifySessionStatus(sessionId, status, additionalData = {}) {
        return this.notify('/api/whatsapp/webhooks/webjs', {
            event: 'session_status_changed',
            session_id: sessionId,
            status: status,
            ...additionalData,
        });
    }

    /**
     * Sleep utility for retry delays
     *
     * @param {number} ms - Milliseconds to sleep
     * @returns {Promise<void>}
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Test webhook connectivity and authentication
     *
     * @returns {Promise<boolean>} True if test successful
     */
    async testConnection() {
        try {
            this.logger.info('[WebhookNotifier] Testing webhook connection...');

            const response = await this.notify('/api/whatsapp/webhooks/webjs', {
                event: 'connection_test',
                timestamp: Date.now(),
            });

            this.logger.info('[WebhookNotifier] Connection test successful');
            return true;

        } catch (error) {
            this.logger.error('[WebhookNotifier] Connection test failed:', error.message);
            return false;
        }
    }
}

// Export the class, not an instance (allow instantiation with logger)
module.exports = WebhookNotifier;
