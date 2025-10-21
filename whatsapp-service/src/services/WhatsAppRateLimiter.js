const winston = require('winston');

class WhatsAppRateLimiter {
    constructor() {
        this.messageCounts = new Map();
        this.banRiskScores = new Map();
        this.contactCounts = new Map();

        // Rate limits based on WhatsApp guidelines
        this.limits = {
            messagesPerMinute: 30,
            messagesPerHour: 1000,
            uniqueContactsPerDay: 500,
            broadcastSize: 256,
            mediaPerHour: 100
        };

        // Progressive delays (in milliseconds)
        this.delays = {
            low: 1000,      // 1 second for 5-10 msg/min
            medium: 2000,   // 2 seconds for 10-15 msg/min
            high: 3000,     // 3 seconds for 15-20 msg/min
            critical: 5000  // 5 seconds for 20+ msg/min
        };

        this.resetInterval = setInterval(() => {
            this.resetDailyCounts();
        }, 24 * 60 * 60 * 1000); // Reset daily at midnight
    }

    async checkAndThrottle(sessionId, recipientCount = 1) {
        const now = Date.now();
        const sessionData = this.getOrCreateSessionData(sessionId);

        // Check broadcast size limit
        if (recipientCount > this.limits.broadcastSize) {
            throw new Error(`Broadcast size ${recipientCount} exceeds WhatsApp limit of ${this.limits.broadcastSize}`);
        }

        // Check messages per minute
        const minuteCount = this.getMessageCount(sessionId, 'minute', now);
        if (minuteCount >= this.limits.messagesPerMinute) {
            throw new Error(`Rate limit exceeded: ${minuteCount}/${this.limits.messagesPerMinute} messages per minute`);
        }

        // Check messages per hour
        const hourCount = this.getMessageCount(sessionId, 'hour', now);
        if (hourCount >= this.limits.messagesPerHour) {
            // Mark session for pause if hourly limit reached
            sessionData.status = 'paused';
            await this.pauseSession(sessionId, 'Hourly limit reached');
            throw new Error(`Hourly limit reached: ${hourCount}/${this.limits.messagesPerHour} messages`);
        }

        // Check unique contacts per day
        const dayCount = this.getContactCount(sessionId, now);
        if (dayCount >= this.limits.uniqueContactsPerDay) {
            throw new Error(`Daily contact limit reached: ${dayCount}/${this.limits.uniqueContactsPerDay}`);
        }

        // Update counters
        this.updateMessageCount(sessionId, 'minute', now);
        this.updateMessageCount(sessionId, 'hour', now);
        this.updateContactCount(sessionId, now);

        // Calculate dynamic delay
        const delay = this.calculateDelay(minuteCount);
        if (delay > 0) {
            winston.debug('Applying rate limit delay', {
                sessionId,
                delay: `${delay}ms`,
                messageCount: minuteCount
            });
            await this.sleep(delay);
        }

        // Update ban risk score
        const banRiskScore = this.calculateBanRisk(sessionId);
        this.banRiskScores.set(sessionId, banRiskScore);

        // Auto-pause if ban risk is high
        if (banRiskScore > 80) {
            sessionData.status = 'paused';
            await this.pauseSession(sessionId, 'High ban risk detected');
        }

        return true;
    }

    getOrCreateSessionData(sessionId) {
        if (!this.messageCounts.has(sessionId)) {
            this.messageCounts.set(sessionId, {
                minute: [],
                hour: [],
                status: 'active',
                lastActivity: Date.now()
            });
        }
        return this.messageCounts.get(sessionId);
    }

    getMessageCount(sessionId, period, now) {
        const sessionData = this.messageCounts.get(sessionId);
        if (!sessionData) return 0;

        const counts = sessionData[period];
        const periodMs = period === 'minute' ? 60 * 1000 : 60 * 60 * 1000;

        // Remove old entries
        const validCounts = counts.filter(timestamp => (now - timestamp) < periodMs);

        // Update with cleaned counts
        sessionData[period] = validCounts;

        return validCounts.length;
    }

    updateMessageCount(sessionId, period, now) {
        const sessionData = this.messageCounts.get(sessionId);
        if (sessionData) {
            sessionData[period].push(now);
        }
    }

    getContactCount(sessionId, now) {
        const contacts = this.contactCounts.get(sessionId);
        if (!contacts) return 0;

        // Remove contacts older than 24 hours
        const validContacts = contacts.filter(timestamp => (now - timestamp) < 24 * 60 * 60 * 1000);

        // Update with cleaned contacts
        this.contactCounts.set(sessionId, validContacts);

        return validContacts.length;
    }

    updateContactCount(sessionId, now) {
        if (!this.contactCounts.has(sessionId)) {
            this.contactCounts.set(sessionId, []);
        }
        this.contactCounts.get(sessionId).push(now);
    }

    calculateDelay(messageCount) {
        if (messageCount >= 20) return this.delays.critical;
        if (messageCount >= 15) return this.delays.high;
        if (messageCount >= 10) return this.delays.medium;
        if (messageCount >= 5) return this.delays.low;
        return 0;
    }

    calculateBanRisk(sessionId) {
        const sessionData = this.messageCounts.get(sessionId);
        if (!sessionData) return 0;

        let score = 0;

        // Volume factor (40 points max)
        const hourCount = this.getMessageCount(sessionId, 'hour', Date.now());
        const hourlyPercentage = (hourCount / this.limits.messagesPerHour) * 100;
        score += (hourlyPercentage / 100) * 40;

        // Burst factor (30 points max)
        const minuteCount = this.getMessageCount(sessionId, 'minute', Date.now());
        const minutePercentage = (minuteCount / this.limits.messagesPerMinute) * 100;
        score += (minutePercentage / 100) * 30;

        // Contact diversity factor (20 points max)
        const contactCount = this.getContactCount(sessionId, Date.now());
        const contactPercentage = (contactCount / this.limits.uniqueContactsPerDay) * 100;
        score += (contactPercentage / 100) * 20;

        // Broadcast frequency factor (10 points max)
        const recentBroadcasts = this.getRecentBroadcasts(sessionId);
        if (recentBroadcasts > 5) {
            score += 10;
        } else if (recentBroadcasts > 3) {
            score += 5;
        }

        return Math.min(Math.round(score), 100);
    }

    getRecentBroadcasts(sessionId) {
        // This would track recent broadcast operations
        // For now, return a placeholder
        return 0;
    }

    async pauseSession(sessionId, reason) {
        winston.warning('Pausing session due to rate limit', {
            sessionId,
            reason
        });

        // TODO: Send pause notification to Laravel
        // TODO: Set session status to paused in database
    }

    async sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    resetDailyCounts() {
        winston.info('Resetting daily rate limit counts');

        // Reset contact counts for all sessions
        this.contactCounts.clear();

        // Reset ban risk scores
        this.banRiskScores.clear();

        winston.info('Daily rate limit counts reset');
    }

    getRateLimitStatus(sessionId) {
        const now = Date.now();
        const sessionData = this.messageCounts.get(sessionId);

        if (!sessionData) {
            return {
                messagesPerMinute: { current: 0, limit: this.limits.messagesPerMinute },
                messagesPerHour: { current: 0, limit: this.limits.messagesPerHour },
                uniqueContactsPerDay: { current: 0, limit: this.limits.uniqueContactsPerDay },
                banRiskScore: 0,
                status: 'active'
            };
        }

        return {
            messagesPerMinute: {
                current: this.getMessageCount(sessionId, 'minute', now),
                limit: this.limits.messagesPerMinute
            },
            messagesPerHour: {
                current: this.getMessageCount(sessionId, 'hour', now),
                limit: this.limits.messagesPerHour
            },
            uniqueContactsPerDay: {
                current: this.getContactCount(sessionId, now),
                limit: this.limits.uniqueContactsPerDay
            },
            banRiskScore: this.banRiskScores.get(sessionId) || 0,
            status: sessionData.status
        };
    }

    cleanup() {
        if (this.resetInterval) {
            clearInterval(this.resetInterval);
        }
    }
}

module.exports = WhatsAppRateLimiter;
