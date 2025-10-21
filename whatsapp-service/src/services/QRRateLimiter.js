const winston = require('winston');

class QRRateLimiter {
    constructor() {
        this.qrGenerations = new Map();
        this.workspaceLimits = new Map();

        // Rate limits for QR generation
        this.limits = {
            maxPerHour: 5,        // Max 5 QR generations per workspace per hour
            maxPerDay: 20,        // Max 20 QR generations per workspace per day
            globalMaxPerHour: 100 // Global max 100 QR generations per hour
        };

        this.globalCounts = {
            hour: [],
            day: []
        };

        // Reset counters periodically
        this.resetInterval = setInterval(() => {
            this.resetCounters();
        }, 60 * 60 * 1000); // Reset every hour
    }

    async canGenerateQR(workspaceId) {
        const now = Date.now();

        // Check global limits
        if (!this.checkGlobalLimits(now)) {
            return {
                allowed: false,
                reason: 'Global QR generation limit reached',
                retryAfter: this.getGlobalRetryAfter(now)
            };
        }

        // Check workspace limits
        const workspaceResult = this.checkWorkspaceLimits(workspaceId, now);
        if (!workspaceResult.allowed) {
            return workspaceResult;
        }

        // Update counters
        this.updateWorkspaceCounter(workspaceId, now);
        this.updateGlobalCounter(now);

        return {
            allowed: true,
            remainingHourly: this.getRemainingHourly(workspaceId, now),
            remainingDaily: this.getRemainingDaily(workspaceId, now)
        };
    }

    checkGlobalLimits(now) {
        // Clean old entries
        this.globalCounts.hour = this.globalCounts.hour.filter(
            timestamp => (now - timestamp) < 60 * 60 * 1000
        );

        return this.globalCounts.hour.length < this.limits.globalMaxPerHour;
    }

    checkWorkspaceLimits(workspaceId, now) {
        const workspaceData = this.getOrCreateWorkspaceData(workspaceId);

        // Check hourly limit
        const hourlyCount = this.getCountInPeriod(workspaceData.hourly, now, 60 * 60 * 1000);
        if (hourlyCount >= this.limits.maxPerHour) {
            return {
                allowed: false,
                reason: `Hourly limit reached: ${hourlyCount}/${this.limits.maxPerHour}`,
                retryAfter: this.getRetryAfter(workspaceData.hourly, 60 * 60 * 1000)
            };
        }

        // Check daily limit
        const dailyCount = this.getCountInPeriod(workspaceData.daily, now, 24 * 60 * 60 * 1000);
        if (dailyCount >= this.limits.maxPerDay) {
            return {
                allowed: false,
                reason: `Daily limit reached: ${dailyCount}/${this.limits.maxPerDay}`,
                retryAfter: this.getRetryAfter(workspaceData.daily, 24 * 60 * 60 * 1000)
            };
        }

        return { allowed: true };
    }

    getOrCreateWorkspaceData(workspaceId) {
        if (!this.qrGenerations.has(workspaceId)) {
            this.qrGenerations.set(workspaceId, {
                hourly: [],
                daily: [],
                lastGeneration: null
            });
        }
        return this.qrGenerations.get(workspaceId);
    }

    getCountInPeriod(timestamps, now, periodMs) {
        return timestamps.filter(timestamp => (now - timestamp) < periodMs).length;
    }

    updateWorkspaceCounter(workspaceId, now) {
        const workspaceData = this.qrGenerations.get(workspaceId);
        if (workspaceData) {
            workspaceData.hourly.push(now);
            workspaceData.daily.push(now);
            workspaceData.lastGeneration = now;
        }
    }

    updateGlobalCounter(now) {
        this.globalCounts.hour.push(now);
    }

    getRetryAfter(timestamps, periodMs) {
        if (timestamps.length === 0) return 0;

        // Find the oldest timestamp that would allow a new request
        const sortedTimestamps = timestamps.sort((a, b) => a - b);
        const now = Date.now();

        for (let i = 0; i < sortedTimestamps.length; i++) {
            const timestamp = sortedTimestamps[i];
            if ((now - timestamp) >= periodMs) {
                // This timestamp is old enough to be ignored
                continue;
            }

            // Calculate when this timestamp will be old enough
            const retryAfter = periodMs - (now - timestamp);
            return Math.max(0, retryAfter);
        }

        return 0;
    }

    getGlobalRetryAfter(now) {
        if (this.globalCounts.hour.length === 0) return 0;

        const sortedTimestamps = this.globalCounts.hour.sort((a, b) => a - b);
        const oldestRecent = sortedTimestamps[0];

        return Math.max(0, 60 * 60 * 1000 - (now - oldestRecent));
    }

    getRemainingHourly(workspaceId, now) {
        const workspaceData = this.qrGenerations.get(workspaceId);
        if (!workspaceData) return this.limits.maxPerHour;

        const count = this.getCountInPeriod(workspaceData.hourly, now, 60 * 60 * 1000);
        return Math.max(0, this.limits.maxPerHour - count);
    }

    getRemainingDaily(workspaceId, now) {
        const workspaceData = this.qrGenerations.get(workspaceId);
        if (!workspaceData) return this.limits.maxPerDay;

        const count = this.getCountInPeriod(workspaceData.daily, now, 24 * 60 * 60 * 1000);
        return Math.max(0, this.limits.maxPerDay - count);
    }

    resetCounters() {
        winston.info('Resetting QR rate limiter counters');

        // Reset workspace daily counters
        for (const [workspaceId, data] of this.qrGenerations) {
            // Keep only last 24 hours for daily counter
            data.daily = data.daily.filter(
                timestamp => (Date.now() - timestamp) < 24 * 60 * 60 * 1000
            );
        }

        // Reset global hourly counter
        this.globalCounts.hour = this.globalCounts.hour.filter(
            timestamp => (Date.now() - timestamp) < 60 * 60 * 1000
        );

        winston.info('QR rate limiter counters reset');
    }

    getRateLimitStatus(workspaceId) {
        const now = Date.now();
        const workspaceData = this.qrGenerations.get(workspaceId);

        return {
            workspaceId,
            hourly: {
                used: workspaceData ? this.getCountInPeriod(workspaceData.hourly, now, 60 * 60 * 1000) : 0,
                limit: this.limits.maxPerHour,
                remaining: workspaceData ? this.getRemainingHourly(workspaceId, now) : this.limits.maxPerHour
            },
            daily: {
                used: workspaceData ? this.getCountInPeriod(workspaceData.daily, now, 24 * 60 * 60 * 1000) : 0,
                limit: this.limits.maxPerDay,
                remaining: workspaceData ? this.getRemainingDaily(workspaceId, now) : this.limits.maxPerDay
            },
            global: {
                used: this.getCountInPeriod(this.globalCounts.hour, now, 60 * 60 * 1000),
                limit: this.limits.globalMaxPerHour,
                remaining: this.limits.globalMaxPerHour - this.getCountInPeriod(this.globalCounts.hour, now, 60 * 60 * 1000)
            },
            lastGeneration: workspaceData?.lastGeneration || null
        };
    }

    setWorkspaceLimit(workspaceId, hourlyLimit, dailyLimit) {
        this.workspaceLimits.set(workspaceId, {
            hourly: hourlyLimit,
            daily: dailyLimit
        });

        winston.info('Workspace QR limits updated', {
            workspaceId,
            hourlyLimit,
            dailyLimit
        });
    }

    cleanup() {
        if (this.resetInterval) {
            clearInterval(this.resetInterval);
        }
    }
}

module.exports = QRRateLimiter;
