#!/usr/bin/env node

/**
 * PM2 Health Check Script
 *
 * Custom health check script for WhatsApp Node.js Service
 * Checks application health and reports to PM2 monitoring
 *
 * TASK-ARCH-4: Custom health check for PM2 monitoring
 */

const http = require('http');

// Configuration
const PORT = process.env.PORT || 3001;
const HEALTH_CHECK_URL = process.env.HEALTH_CHECK_URL || `http://localhost:${PORT}/health`;
const CHECK_INTERVAL = parseInt(process.env.HEALTH_CHECK_INTERVAL) || 30000; // 30 seconds
const TIMEOUT = parseInt(process.env.HEALTH_CHECK_TIMEOUT) || 10000; // 10 seconds

let failureCount = 0;
const MAX_FAILURES = 3;

/**
 * Perform health check
 */
async function performHealthCheck() {
    return new Promise((resolve, reject) => {
        const url = new URL(HEALTH_CHECK_URL);

        const options = {
            hostname: url.hostname,
            port: url.port || (url.protocol === 'https:' ? 443 : 80),
            path: url.pathname + url.search,
            method: 'GET',
            timeout: TIMEOUT,
            headers: {
                'User-Agent': 'PM2-Health-Check/1.0'
            }
        };

        const req = http.request(options, (res) => {
            let data = '';

            res.on('data', (chunk) => {
                data += chunk;
            });

            res.on('end', () => {
                try {
                    const healthData = JSON.parse(data);

                    // Check if response is successful and healthy
                    if (res.statusCode === 200 && healthData.status === 'healthy') {
                        resolve({
                            healthy: true,
                            status: res.statusCode,
                            data: healthData
                        });
                    } else {
                        resolve({
                            healthy: false,
                            status: res.statusCode,
                            error: `Unhealthy response: ${healthData.status || 'unknown'}`
                        });
                    }
                } catch (parseError) {
                    resolve({
                        healthy: false,
                        status: res.statusCode,
                        error: `Failed to parse health response: ${parseError.message}`
                    });
                }
            });
        });

        req.on('error', (error) => {
            resolve({
                healthy: false,
                error: `Health check failed: ${error.message}`
            });
        });

        req.on('timeout', () => {
            req.destroy();
            resolve({
                healthy: false,
                error: `Health check timeout after ${TIMEOUT}ms`
            });
        });

        req.end();
    });
}

/**
 * Main health check loop
 */
async function healthCheckLoop() {
    try {
        const result = await performHealthCheck();

        if (result.healthy) {
            // Health check passed
            failureCount = 0;

            if (process.env.PM2_APP_NAME && process.send) {
                process.send({
                    type: 'process:msg',
                    data: {
                        success: true,
                        message: 'Health check passed',
                        details: result.data
                    }
                });
            }

            console.log(`[HEALTH] ✓ Health check passed - Uptime: ${result.data?.uptime || 'unknown'}s, Sessions: ${result.data?.sessions?.total || 0}`);
        } else {
            // Health check failed
            failureCount++;

            console.error(`[HEALTH] ✗ Health check failed (${failureCount}/${MAX_FAILURES}): ${result.error}`);

            if (process.env.PM2_APP_NAME && process.send) {
                process.send({
                    type: 'process:msg',
                    data: {
                        success: false,
                        message: result.error,
                        failureCount: failureCount
                    }
                });
            }

            // If too many consecutive failures, exit to trigger PM2 restart
            if (failureCount >= MAX_FAILURES) {
                console.error(`[HEALTH] ✗ ${MAX_FAILURES} consecutive health check failures. Triggering restart.`);
                process.exit(1);
            }
        }
    } catch (error) {
        failureCount++;
        console.error(`[HEALTH] ✗ Unexpected health check error: ${error.message}`);

        if (failureCount >= MAX_FAILURES) {
            console.error(`[HEALTH] ✗ ${MAX_FAILURES} consecutive errors. Triggering restart.`);
            process.exit(1);
        }
    }
}

/**
 * Graceful shutdown
 */
function gracefulShutdown() {
    console.log('[HEALTH] Health check script shutting down gracefully');
    if (healthCheckInterval) {
        clearInterval(healthCheckInterval);
    }
    process.exit(0);
}

// Start health checks
console.log(`[HEALTH] Starting health checks every ${CHECK_INTERVAL}ms for ${HEALTH_CHECK_URL}`);
let healthCheckInterval = setInterval(healthCheckLoop, CHECK_INTERVAL);

// Run first health check immediately
healthCheckLoop();

// Handle graceful shutdown
process.on('SIGTERM', gracefulShutdown);
process.on('SIGINT', gracefulShutdown);
process.on('SIGUSR2', gracefulShutdown); // PM2 restart signal

// Handle uncaught exceptions
process.on('uncaughtException', (error) => {
    console.error('[HEALTH] Uncaught exception:', error);
    process.exit(1);
});

process.on('unhandledRejection', (reason, promise) => {
    console.error('[HEALTH] Unhandled rejection at:', promise, 'reason:', reason);
    process.exit(1);
});