/**
 * Health Controller
 *
 * Handles health check and monitoring endpoints.
 * Extracted from server.js for better separation of concerns.
 *
 * TASK-ARCH-2: Extract API endpoints to dedicated controller classes
 */
class HealthController {
    constructor(sessionManager, logger) {
        this.sessionManager = sessionManager;
        this.logger = logger;
        this.startTime = new Date();
    }

    /**
     * Basic health check endpoint
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {void}
     */
    async basicHealth(req, res) {
        try {
            const sessions = this.sessionManager.getAllSessions();
            const healthySessions = sessions.filter(s => s.status === 'connected').length;

            res.json({
                status: 'healthy',
                uptime: process.uptime(),
                sessions: {
                    total: sessions.length,
                    connected: healthySessions,
                    disconnected: sessions.length - healthySessions
                },
                memory: {
                    used: Math.round(process.memoryUsage().heapUsed / 1024 / 1024),
                    total: Math.round(process.memoryUsage().heapTotal / 1024 / 1024),
                    unit: 'MB'
                },
                timestamp: new Date().toISOString()
            });
        } catch (error) {
            this.logger.error('Health check failed', { error: error.message });
            res.status(500).json({
                status: 'unhealthy',
                error: error.message,
                timestamp: new Date().toISOString()
            });
        }
    }

    /**
     * Detailed health check with system metrics
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {void}
     */
    async detailedHealth(req, res) {
        try {
            const sessions = this.sessionManager.getAllSessions();
            const connectedSessions = sessions.filter(s => s.status === 'connected');
            const disconnectedSessions = sessions.filter(s => s.status !== 'connected');

            // Calculate session statistics
            const sessionStats = this.calculateSessionStats(sessions);

            // System metrics
            const memoryUsage = process.memoryUsage();
            const cpuUsage = process.cpuUsage();

            res.json({
                status: 'healthy',
                uptime: process.uptime(),
                start_time: this.startTime.toISOString(),
                timestamp: new Date().toISOString(),

                // Session information
                sessions: {
                    total: sessions.length,
                    connected: connectedSessions.length,
                    disconnected: disconnectedSessions.length,
                    connection_rate: sessions.length > 0 ? (connectedSessions.length / sessions.length) * 100 : 0,
                    details: sessionStats
                },

                // Memory information
                memory: {
                    heap_used: Math.round(memoryUsage.heapUsed / 1024 / 1024),
                    heap_total: Math.round(memoryUsage.heapTotal / 1024 / 1024),
                    external: Math.round(memoryUsage.external / 1024 / 1024),
                    rss: Math.round(memoryUsage.rss / 1024 / 1024),
                    unit: 'MB'
                },

                // CPU information
                cpu: {
                    user: cpuUsage.user,
                    system: cpuUsage.system
                },

                // Node.js information
                node: {
                    version: process.version,
                    platform: process.platform,
                    arch: process.arch,
                    pid: process.pid
                },

                // Environment information
                environment: {
                    node_env: process.env.NODE_ENV || 'development',
                    port: process.env.PORT || 3000,
                    laravel_url: process.env.LARAVEL_URL ? 'configured' : 'not_configured'
                }
            });
        } catch (error) {
            this.logger.error('Detailed health check failed', { error: error.message });
            res.status(500).json({
                status: 'unhealthy',
                error: error.message,
                timestamp: new Date().toISOString()
            });
        }
    }

    /**
     * Readiness probe for container orchestration
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {void}
     */
    async readiness(req, res) {
        try {
            // Check if essential services are ready
            const sessions = this.sessionManager.getAllSessions();
            const hasActiveSessions = sessions.length > 0;

            // Check if Laravel URL is configured
            const laravelConfigured = !!process.env.LARAVEL_URL;

            // Check if API key is configured
            const apiKeyConfigured = !!(process.env.API_KEY || process.env.LARAVEL_API_TOKEN);

            const isReady = laravelConfigured && apiKeyConfigured;

            const status = {
                ready: isReady,
                timestamp: new Date().toISOString(),
                checks: {
                    laravel_configured: laravelConfigured,
                    api_key_configured: apiKeyConfigured,
                    active_sessions: hasActiveSessions,
                    session_count: sessions.length
                }
            };

            if (isReady) {
                res.status(200).json(status);
            } else {
                res.status(503).json(status);
            }
        } catch (error) {
            this.logger.error('Readiness check failed', { error: error.message });
            res.status(503).json({
                ready: false,
                error: error.message,
                timestamp: new Date().toISOString()
            });
        }
    }

    /**
     * Liveness probe for container orchestration
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {void}
     */
    async liveness(req, res) {
        try {
            const uptime = process.uptime();
            const isAlive = uptime < 3600; // Consider unhealthy if running more than 1 hour without restart

            // Check if event loop is responsive
            const eventLoopDelay = this.measureEventLoopDelay();
            const isEventLoopResponsive = eventLoopDelay < 1000; // 1 second threshold

            const status = {
                alive: isAlive && isEventLoopResponsive,
                uptime: uptime,
                event_loop_delay_ms: eventLoopDelay,
                timestamp: new Date().toISOString()
            };

            if (isAlive && isEventLoopResponsive) {
                res.status(200).json(status);
            } else {
                res.status(503).json(status);
            }
        } catch (error) {
            this.logger.error('Liveness check failed', { error: error.message });
            res.status(503).json({
                alive: false,
                error: error.message,
                timestamp: new Date().toISOString()
            });
        }
    }

    /**
     * Get performance metrics
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {void}
     */
    async performanceMetrics(req, res) {
        try {
            const memoryUsage = process.memoryUsage();
            const cpuUsage = process.cpuUsage();
            const uptime = process.uptime();

            const metrics = {
                timestamp: new Date().toISOString(),
                uptime_seconds: uptime,

                // Memory metrics
                memory: {
                    heap_used_mb: Math.round(memoryUsage.heapUsed / 1024 / 1024),
                    heap_total_mb: Math.round(memoryUsage.heapTotal / 1024 / 1024),
                    heap_usage_percent: Math.round((memoryUsage.heapUsed / memoryUsage.heapTotal) * 100),
                    external_mb: Math.round(memoryUsage.external / 1024 / 1024),
                    rss_mb: Math.round(memoryUsage.rss / 1024 / 1024)
                },

                // Performance metrics
                performance: {
                    event_loop_delay_ms: this.measureEventLoopDelay(),
                    cpu_user: cpuUsage.user,
                    cpu_system: cpuUsage.system
                },

                // Session metrics
                sessions: this.calculateSessionStats(this.sessionManager.getAllSessions())
            };

            res.json(metrics);
        } catch (error) {
            this.logger.error('Performance metrics failed', { error: error.message });
            res.status(500).json({
                error: error.message,
                timestamp: new Date().toISOString()
            });
        }
    }

    /**
     * Calculate session statistics
     *
     * @param {Array} sessions - Array of session objects
     * @returns {Object} Session statistics
     */
    calculateSessionStats(sessions) {
        const statusCounts = sessions.reduce((acc, session) => {
            acc[session.status] = (acc[session.status] || 0) + 1;
            return acc;
        }, {});

        const connectedSessions = sessions.filter(s => s.status === 'connected');
        const oldestSession = connectedSessions.length > 0 ?
            connectedSessions.reduce((oldest, session) => {
                const sessionTime = new Date(session.connected_at).getTime();
                const oldestTime = new Date(oldest.connected_at).getTime();
                return sessionTime < oldestTime ? session : oldest;
            }) : null;

        const newestSession = connectedSessions.length > 0 ?
            connectedSessions.reduce((newest, session) => {
                const sessionTime = new Date(session.connected_at).getTime();
                const newestTime = new Date(newest.connected_at).getTime();
                return sessionTime > newestTime ? session : newest;
            }) : null;

        return {
            by_status: statusCounts,
            longest_uptime: oldestSession ? {
                session_id: oldestSession.session_id,
                connected_at: oldestSession.connected_at,
                uptime_hours: (Date.now() - new Date(oldestSession.connected_at).getTime()) / (1000 * 60 * 60)
            } : null,
            newest_session: newestSession ? {
                session_id: newestSession.session_id,
                connected_at: newestSession.connected_at
            } : null
        };
    }

    /**
     * Measure event loop delay
     *
     * @returns {number} Event loop delay in milliseconds
     */
    measureEventLoopDelay() {
        const start = process.hrtime.bigint();
        return new Promise(resolve => {
            setImmediate(() => {
                const delay = Number(process.hrtime.bigint() - start) / 1000000; // Convert to milliseconds
                resolve(Math.round(delay));
            });
        });
    }

    /**
     * Get application information
     *
     * @param {Object} req - Express request object
     * @param {Object} res - Express response object
     * @returns {void}
     */
    async appInfo(req, res) {
        try {
            const packageJson = require('../../../package.json');

            res.json({
                name: packageJson.name || 'whatsapp-service',
                version: packageJson.version || '1.0.0',
                description: packageJson.description || 'WhatsApp Web.js Service',
                environment: {
                    node_env: process.env.NODE_ENV || 'development',
                    node_version: process.version,
                    platform: process.platform,
                    arch: process.arch
                },
                service: {
                    start_time: this.startTime.toISOString(),
                    uptime_seconds: process.uptime(),
                    pid: process.pid
                },
                endpoints: {
                    health: '/health',
                    detailed_health: '/health/detailed',
                    readiness: '/health/ready',
                    liveness: '/health/live',
                    metrics: '/health/metrics'
                },
                timestamp: new Date().toISOString()
            });
        } catch (error) {
            this.logger.error('App info failed', { error: error.message });
            res.status(500).json({
                error: error.message,
                timestamp: new Date().toISOString()
            });
        }
    }
}

module.exports = HealthController;