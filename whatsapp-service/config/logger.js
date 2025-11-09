/**
 * Logger Configuration for WhatsApp Web.js Service
 *
 * Provides structured logging with timestamps, session context, and severity levels.
 * Logs are written to console (for PM2/Docker) and optionally to files.
 *
 * Log Levels:
 * - error: Critical errors that require immediate attention
 * - warn: Warning conditions (e.g., rate limits, retries)
 * - info: Informational messages (e.g., sync started/completed)
 * - debug: Detailed debug information (e.g., message payloads)
 *
 * @module logger
 */

const fs = require('fs');
const path = require('path');

class Logger {
    constructor() {
        this.logLevel = process.env.LOG_LEVEL || 'info';
        this.logDir = path.join(__dirname, '../logs');
        this.enableFileLogging = process.env.ENABLE_FILE_LOGGING === 'true';

        // Create logs directory if it doesn't exist
        if (this.enableFileLogging && !fs.existsSync(this.logDir)) {
            fs.mkdirSync(this.logDir, { recursive: true });
        }

        this.levels = {
            error: 0,
            warn: 1,
            info: 2,
            debug: 3,
        };

        this.currentLevel = this.levels[this.logLevel] || this.levels.info;
    }

    /**
     * Format log message with timestamp and context
     *
     * @param {string} level - Log level
     * @param {string} message - Log message
     * @param {Object} context - Additional context data
     * @returns {string} Formatted log message
     */
    formatMessage(level, message, context = {}) {
        const timestamp = new Date().toISOString();
        const levelUpper = level.toUpperCase().padEnd(5);

        // Structured log format for easy parsing
        const logEntry = {
            timestamp,
            level: levelUpper.trim(),
            message,
            ...context,
        };

        return JSON.stringify(logEntry);
    }

    /**
     * Write log to file
     *
     * @param {string} level - Log level
     * @param {string} formattedMessage - Formatted log message
     */
    writeToFile(level, formattedMessage) {
        if (!this.enableFileLogging) return;

        const filename = level === 'error'
            ? 'error.log'
            : 'whatsapp-service.log';

        const logPath = path.join(this.logDir, filename);

        fs.appendFile(logPath, formattedMessage + '\n', (err) => {
            if (err) {
                console.error('Failed to write log to file:', err);
            }
        });
    }

    /**
     * Log error message
     *
     * @param {string} message - Error message
     * @param {Object} context - Additional context
     */
    error(message, context = {}) {
        if (this.currentLevel < this.levels.error) return;

        const formatted = this.formatMessage('error', message, context);
        console.error(formatted);
        this.writeToFile('error', formatted);
    }

    /**
     * Log warning message
     *
     * @param {string} message - Warning message
     * @param {Object} context - Additional context
     */
    warn(message, context = {}) {
        if (this.currentLevel < this.levels.warn) return;

        const formatted = this.formatMessage('warn', message, context);
        console.warn(formatted);
        this.writeToFile('warn', formatted);
    }

    /**
     * Log info message
     *
     * @param {string} message - Info message
     * @param {Object} context - Additional context
     */
    info(message, context = {}) {
        if (this.currentLevel < this.levels.info) return;

        const formatted = this.formatMessage('info', message, context);
        console.log(formatted);
        this.writeToFile('info', formatted);
    }

    /**
     * Log debug message
     *
     * @param {string} message - Debug message
     * @param {Object} context - Additional context
     */
    debug(message, context = {}) {
        if (this.currentLevel < this.levels.debug) return;

        const formatted = this.formatMessage('debug', message, context);
        console.debug(formatted);
        this.writeToFile('debug', formatted);
    }

    /**
     * Log chat sync started event
     *
     * @param {string} sessionId - WhatsApp session ID
     * @param {Object} config - Sync configuration
     */
    logSyncStarted(sessionId, config = {}) {
        this.info('Chat sync started', {
            sessionId,
            totalChats: config.totalChats || 0,
            maxChats: config.maxChats || 'unlimited',
            syncWindow: config.syncWindow || 'unlimited',
            timestamp: new Date().toISOString(),
        });
    }

    /**
     * Log chat sync completed event
     *
     * @param {string} sessionId - WhatsApp session ID
     * @param {Object} metrics - Sync metrics
     */
    logSyncCompleted(sessionId, metrics = {}) {
        this.info('Chat sync completed', {
            sessionId,
            totalSynced: metrics.totalSynced || 0,
            privatechats: metrics.privatechats || 0,
            groupChats: metrics.groupChats || 0,
            durationSeconds: metrics.durationSeconds || 0,
            errors: metrics.errors || 0,
            timestamp: new Date().toISOString(),
        });
    }

    /**
     * Log chat sync error
     *
     * @param {string} sessionId - WhatsApp session ID
     * @param {Error} error - Error object
     * @param {Object} context - Additional context
     */
    logSyncError(sessionId, error, context = {}) {
        this.error('Chat sync failed', {
            sessionId,
            error: error.message,
            stack: error.stack,
            ...context,
            timestamp: new Date().toISOString(),
        });
    }

    /**
     * Log incoming message received
     *
     * @param {string} sessionId - WhatsApp session ID
     * @param {Object} message - Message data
     */
    logMessageReceived(sessionId, message) {
        this.info('Message received', {
            sessionId,
            messageId: message.id,
            from: message.from,
            type: message.type,
            chatType: message.chatType || 'private',
            timestamp: new Date().toISOString(),
        });
    }

    /**
     * Log webhook notification sent
     *
     * @param {string} endpoint - Webhook endpoint
     * @param {number} statusCode - HTTP status code
     * @param {Object} context - Additional context
     */
    logWebhookSent(endpoint, statusCode, context = {}) {
        const level = statusCode >= 200 && statusCode < 300 ? 'info' : 'warn';

        this[level]('Webhook notification sent', {
            endpoint,
            statusCode,
            ...context,
            timestamp: new Date().toISOString(),
        });
    }

    /**
     * Log session status change
     *
     * @param {string} sessionId - WhatsApp session ID
     * @param {string} oldStatus - Previous status
     * @param {string} newStatus - New status
     */
    logSessionStatusChange(sessionId, oldStatus, newStatus) {
        this.info('Session status changed', {
            sessionId,
            oldStatus,
            newStatus,
            timestamp: new Date().toISOString(),
        });
    }
}

// Export singleton instance
module.exports = new Logger();
