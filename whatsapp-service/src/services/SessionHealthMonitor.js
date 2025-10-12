const axios = require('axios');
const winston = require('winston');

class SessionHealthMonitor {
    constructor(sessionManager) {
        this.sessionManager = sessionManager;
        this.healthChecks = new Map();
        this.checkInterval = 30000; // 30 seconds
        this.testMessageInterval = 300000; // 5 minutes
        this.isRunning = false;
    }

    start() {
        if (this.isRunning) return;

        this.isRunning = true;
        winston.info('SessionHealthMonitor started');

        // Start health check interval
        this.healthCheckTimer = setInterval(() => {
            this.performHealthChecks();
        }, this.checkInterval);

        // Start test message interval
        this.testMessageTimer = setInterval(() => {
            this.sendTestMessages();
        }, this.testMessageInterval);
    }

    stop() {
        if (!this.isRunning) return;

        this.isRunning = false;
        if (this.healthCheckTimer) {
            clearInterval(this.healthCheckTimer);
        }
        if (this.testMessageTimer) {
            clearInterval(this.testMessageTimer);
        }

        winston.info('SessionHealthMonitor stopped');
    }

    async performHealthChecks() {
        const sessions = this.sessionManager.getAllSessions();

        for (const session of sessions) {
            if (session.status !== 'connected') continue;

            const sessionId = session.session_id;
            const lastCheck = this.healthChecks.get(sessionId);

            try {
                // Check if session is responsive
                const isResponsive = await this.checkSessionResponsiveness(sessionId);

                // Check last activity
                const now = new Date();
                const lastActivity = session.last_activity || session.connected_at;
                const inactiveMinutes = lastActivity ?
                    (now - new Date(lastActivity)) / (1000 * 60) : 0;

                // Update health score
                const healthScore = this.calculateHealthScore({
                    isResponsive,
                    inactiveMinutes,
                    lastCheck
                });

                // Store health check result
                this.healthChecks.set(sessionId, {
                    timestamp: now,
                    isResponsive,
                    healthScore,
                    inactiveMinutes
                });

                // Alert if health score is low
                if (healthScore < 50) {
                    await this.alertUnhealthySession(sessionId, healthScore, {
                        isResponsive,
                        inactiveMinutes
                    });
                }

                // Auto-recover if needed
                if (healthScore < 30) {
                    await this.attemptRecovery(sessionId);
                }

            } catch (error) {
                winston.error('Health check failed for session', {
                    sessionId,
                    error: error.message
                });

                this.healthChecks.set(sessionId, {
                    timestamp: new Date(),
                    isResponsive: false,
                    healthScore: 0,
                    error: error.message
                });
            }
        }
    }

    async checkSessionResponsiveness(sessionId) {
        try {
            // Try to get session info as a responsiveness test
            const response = await axios.get(
                `${process.env.LARAVEL_URL}/api/whatsapp/sessions/${sessionId}/status`,
                {
                    params: {
                        workspace_id: this.sessionManager.getSessionMetadata(sessionId)?.workspaceId,
                        api_key: process.env.API_KEY
                    },
                    timeout: 5000
                }
            );

            return response.status === 200;
        } catch (error) {
            winston.debug('Session responsiveness check failed', {
                sessionId,
                error: error.message
            });
            return false;
        }
    }

    calculateHealthScore({ isResponsive, inactiveMinutes, lastCheck }) {
        let score = 100;

        // Responsiveness factor (40 points)
        if (!isResponsive) {
            score -= 40;
        }

        // Activity factor (30 points)
        if (inactiveMinutes > 60) {
            score -= 30;
        } else if (inactiveMinutes > 30) {
            score -= 15;
        } else if (inactiveMinutes > 10) {
            score -= 5;
        }

        // Consistency factor (30 points)
        if (lastCheck && !lastCheck.isResponsive && isResponsive) {
            score += 10; // Recovery bonus
        } else if (lastCheck && lastCheck.isResponsive && !isResponsive) {
            score -= 20; // Degradation penalty
        }

        return Math.max(0, Math.min(100, score));
    }

    async sendTestMessages() {
        const sessions = this.sessionManager.getAllSessions();

        for (const session of sessions) {
            if (session.status !== 'connected') continue;

            try {
                // Send a test message to a dummy number (will fail but tests connectivity)
                await this.sessionManager.sendMessage(
                    session.session_id,
                    '1234567890', // Test number that should fail
                    'HEALTH_CHECK_TEST_MESSAGE',
                    'text'
                );
            } catch (error) {
                // Expected to fail for test number, but tests WhatsApp connectivity
                if (error.message.includes('connected')) {
                    winston.warning('Session may be disconnected', {
                        sessionId: session.session_id,
                        error: error.message
                    });
                }
            }
        }
    }

    async alertUnhealthySession(sessionId, healthScore, metrics) {
        winston.warning('Unhealthy session detected', {
            sessionId,
            healthScore,
            metrics
        });

        // Send alert to Laravel for admin notification
        try {
            await axios.post(`${process.env.LARAVEL_URL}/api/admin/alerts/session-health`, {
                session_id: sessionId,
                health_score: healthScore,
                metrics,
                api_key: process.env.API_KEY
            });
        } catch (error) {
            winston.error('Failed to send health alert', { error: error.message });
        }
    }

    async attemptRecovery(sessionId) {
        winston.info('Attempting session recovery', { sessionId });

        try {
            // Disconnect and reconnect session
            await this.sessionManager.disconnectSession(sessionId);

            const metadata = this.sessionManager.getSessionMetadata(sessionId);
            if (metadata) {
                await this.sessionManager.createSession(sessionId, metadata.workspaceId);
            }

            winston.info('Session recovery completed', { sessionId });
        } catch (error) {
            winston.error('Session recovery failed', {
                sessionId,
                error: error.message
            });
        }
    }

    getHealthReport() {
        const report = {};

        for (const [sessionId, check] of this.healthChecks) {
            report[sessionId] = {
                health_score: check.healthScore,
                last_check: check.timestamp,
                is_responsive: check.isResponsive,
                inactive_minutes: check.inactiveMinutes
            };
        }

        return report;
    }
}

module.exports = SessionHealthMonitor;
