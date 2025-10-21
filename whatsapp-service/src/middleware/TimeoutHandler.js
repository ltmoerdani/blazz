const winston = require('winston');

class TimeoutHandler {
    constructor() {
        this.defaultTimeout = 30000; // 30 seconds
        this.timeouts = new Map();
    }

    // Middleware function for Express
    middleware() {
        return (req, res, next) => {
            const timeout = this.getTimeoutForRoute(req.path) || this.defaultTimeout;

            // Set up timeout for this request
            const timeoutId = setTimeout(() => {
                this.handleTimeout(req, res);
            }, timeout);

            // Store timeout ID for cleanup
            this.timeouts.set(`${req.method}:${req.path}:${Date.now()}`, timeoutId);

            // Clean up timeout on response
            const originalEnd = res.end;
            res.end = function(...args) {
                clearTimeout(timeoutId);
                return originalEnd.apply(this, args);
            };

            next();
        };
    }

    getTimeoutForRoute(path) {
        // Define timeouts for different routes
        const routeTimeouts = {
            '/api/sessions': 60000,      // 60s for session creation
            '/api/messages/send': 45000,  // 45s for message sending
            '/health': 5000,              // 5s for health checks
            '/api/sessions/.*/status': 10000, // 10s for status checks
            '/api/sessions/.*/reconnect': 60000, // 60s for reconnection
            '/api/sessions/.*/regenerate-qr': 30000 // 30s for QR regeneration
        };

        for (const [route, timeout] of Object.entries(routeTimeouts)) {
            if (new RegExp(route).test(path)) {
                return timeout;
            }
        }

        return this.defaultTimeout;
    }

    handleTimeout(req, res) {
        const error = {
            error: 'Request timeout',
            path: req.path,
            method: req.method,
            timeout: this.getTimeoutForRoute(req.path),
            timestamp: new Date().toISOString()
        };

        winston.warning('Request timeout', error);

        if (!res.headersSent) {
            res.status(408).json({
                error: 'Request timeout',
                message: 'The request took too long to process',
                timeout: this.getTimeoutForRoute(req.path)
            });
        }
    }

    // Promise-based timeout wrapper
    async withTimeout(promise, timeoutMs = this.defaultTimeout, errorMessage = 'Operation timeout') {
        return new Promise((resolve, reject) => {
            const timeoutId = setTimeout(() => {
                reject(new Error(`${errorMessage} (timeout: ${timeoutMs}ms)`));
            }, timeoutMs);

            promise
                .then((result) => {
                    clearTimeout(timeoutId);
                    resolve(result);
                })
                .catch((error) => {
                    clearTimeout(timeoutId);
                    reject(error);
                });
        });
    }

    // Timeout wrapper for async functions
    timeoutWrapper(fn, timeoutMs = this.defaultTimeout) {
        return async (...args) => {
            return this.withTimeout(fn.apply(this, args), timeoutMs);
        };
    }

    getTimeoutStats() {
        return {
            defaultTimeout: this.defaultTimeout,
            activeTimeouts: this.timeouts.size,
            routeTimeouts: {
                '/api/sessions': 60000,
                '/api/messages/send': 45000,
                '/health': 5000,
                '/api/sessions/.*/status': 10000,
                '/api/sessions/.*/reconnect': 60000,
                '/api/sessions/.*/regenerate-qr': 30000
            }
        };
    }

    cleanup() {
        // Clear all active timeouts
        for (const timeoutId of this.timeouts.values()) {
            clearTimeout(timeoutId);
        }
        this.timeouts.clear();

        winston.info('TimeoutHandler cleaned up');
    }
}

module.exports = TimeoutHandler;
