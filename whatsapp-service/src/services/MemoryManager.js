const os = require('os');
const v8 = require('v8');
const winston = require('winston');

class MemoryManager {
    constructor(sessionManager) {
        this.sessionManager = sessionManager;
        this.checkInterval = 60000; // 1 minute
        this.memoryThreshold = 0.8; // 80% memory usage
        this.sessionMemoryLimit = 100 * 1024 * 1024; // 100MB per session
        this.isRunning = false;

        // Enable garbage collection if available
        if (typeof global.gc === 'function') {
            this.gcEnabled = true;
        } else {
            // Try to enable GC exposure
            try {
                if (v8.setFlagsFromString) {
                    v8.setFlagsFromString('--expose-gc');
                    global.gc = require('vm').runInNewContext('gc');
                    this.gcEnabled = true;
                }
            } catch (error) {
                winston.warn('Garbage collection not available');
                this.gcEnabled = false;
            }
        }
    }

    start() {
        if (this.isRunning) return;

        this.isRunning = true;
        winston.info('MemoryManager started');

        this.monitorTimer = setInterval(() => {
            this.checkMemoryUsage();
        }, this.checkInterval);

        // Perform initial check
        this.checkMemoryUsage();
    }

    stop() {
        if (!this.isRunning) return;

        this.isRunning = false;
        if (this.monitorTimer) {
            clearInterval(this.monitorTimer);
        }

        winston.info('MemoryManager stopped');
    }

    checkMemoryUsage() {
        const memUsage = process.memoryUsage();
        const totalMemory = os.totalmem();
        const freeMemory = os.freemem();
        const usedMemory = totalMemory - freeMemory;
        const memoryUsagePercent = usedMemory / totalMemory;

        // Get heap statistics
        const heapStats = v8.getHeapStatistics();

        winston.debug('Memory usage check', {
            rss: this.formatBytes(memUsage.rss),
            heapTotal: this.formatBytes(memUsage.heapTotal),
            heapUsed: this.formatBytes(memUsage.heapUsed),
            external: this.formatBytes(memUsage.external),
            systemMemoryPercent: Math.round(memoryUsagePercent * 100) + '%',
            heapSizeLimit: this.formatBytes(heapStats.total_heap_size),
            usedHeapSize: this.formatBytes(heapStats.used_heap_size)
        });

        // Check if memory usage is high
        if (memoryUsagePercent > this.memoryThreshold) {
            winston.warning('High memory usage detected', {
                usage: Math.round(memoryUsagePercent * 100) + '%',
                threshold: Math.round(this.memoryThreshold * 100) + '%',
                rss: this.formatBytes(memUsage.rss)
            });

            this.handleHighMemoryUsage(memUsage);
        }

        // Check per-session memory usage
        this.checkSessionMemoryUsage();

        // Force garbage collection if enabled
        if (this.gcEnabled && memoryUsagePercent > 0.7) {
            this.performGarbageCollection();
        }
    }

    checkSessionMemoryUsage() {
        const sessions = this.sessionManager.getAllSessions();

        for (const session of sessions) {
            const sessionId = session.session_id;
            const metadata = this.sessionManager.getSessionMetadata(sessionId);

            if (metadata && metadata.memoryUsage) {
                const sessionMemoryMB = metadata.memoryUsage / (1024 * 1024);

                if (sessionMemoryMB > 100) { // 100MB threshold
                    winston.warning('Session using excessive memory', {
                        sessionId,
                        memoryUsage: `${Math.round(sessionMemoryMB)}MB`,
                        threshold: '100MB'
                    });

                    // Mark session for potential cleanup
                    metadata.needsMemoryCleanup = true;
                    this.sessionManager.updateSessionMetadata(sessionId, metadata);
                }
            }
        }
    }

    handleHighMemoryUsage(memUsage) {
        const memoryUsagePercent = (memUsage.heapUsed / memUsage.heapTotal) * 100;

        // If heap usage is critical (>90%), take immediate action
        if (memoryUsagePercent > 90) {
            winston.error('Critical memory usage - taking emergency actions', {
                heapUsage: `${Math.round(memoryUsagePercent)}%`,
                rss: this.formatBytes(memUsage.rss)
            });

            this.performEmergencyCleanup();
        } else if (memoryUsagePercent > 80) {
            winston.warning('High memory usage - performing cleanup', {
                heapUsage: `${Math.round(memoryUsagePercent)}%`
            });

            this.performMemoryCleanup();
        }
    }

    performEmergencyCleanup() {
        try {
            // Force garbage collection
            if (this.gcEnabled && global.gc) {
                global.gc();
                winston.info('Emergency garbage collection performed');
            }

            // Get sessions sorted by memory usage
            const sessions = this.sessionManager.getAllSessions();
            const highMemorySessions = sessions
                .filter(s => s.status === 'connected')
                .sort((a, b) => (b.memoryUsage || 0) - (a.memoryUsage || 0))
                .slice(0, 2); // Top 2 memory consumers

            // Disconnect sessions with highest memory usage
            for (const session of highMemorySessions) {
                winston.warning('Disconnecting high-memory session for emergency cleanup', {
                    sessionId: session.session_id,
                    memoryUsage: this.formatBytes(session.memoryUsage || 0)
                });

                this.sessionManager.disconnectSession(session.session_id);
            }

        } catch (error) {
            winston.error('Emergency cleanup failed', { error: error.message });
        }
    }

    performMemoryCleanup() {
        try {
            // Force garbage collection
            if (this.gcEnabled && global.gc) {
                global.gc();
                winston.info('Garbage collection performed');
            }

            // Clean up inactive sessions
            this.cleanupInactiveSessions();

            // Clear internal caches
            this.clearInternalCaches();

        } catch (error) {
            winston.error('Memory cleanup failed', { error: error.message });
        }
    }

    cleanupInactiveSessions() {
        const sessions = this.sessionManager.getAllSessions();
        const now = Date.now();
        const inactiveThreshold = 60 * 60 * 1000; // 1 hour

        for (const session of sessions) {
            const metadata = this.sessionManager.getSessionMetadata(session.session_id);

            if (metadata && metadata.lastActivity) {
                const inactiveTime = now - metadata.lastActivity;

                if (inactiveTime > inactiveThreshold && session.status === 'connected') {
                    winston.info('Disconnecting inactive session', {
                        sessionId: session.session_id,
                        inactiveMinutes: Math.round(inactiveTime / (1000 * 60))
                    });

                    this.sessionManager.disconnectSession(session.session_id);
                }
            }
        }
    }

    clearInternalCaches() {
        // Clear any internal caches that might be consuming memory
        // This is a placeholder for any caching mechanisms

        if (this.sessionManager.clearCache) {
            this.sessionManager.clearCache();
        }

        // Force Node.js to clean up internal structures
        if (global.gc) {
            global.gc();
        }
    }

    performGarbageCollection() {
        if (!this.gcEnabled || !global.gc) {
            return;
        }

        try {
            const beforeGC = process.memoryUsage().heapUsed;
            global.gc();
            const afterGC = process.memoryUsage().heapUsed;
            const freed = beforeGC - afterGC;

            winston.info('Manual garbage collection performed', {
                before: this.formatBytes(beforeGC),
                after: this.formatBytes(afterGC),
                freed: this.formatBytes(freed)
            });
        } catch (error) {
            winston.debug('Garbage collection failed', { error: error.message });
        }
    }

    getMemoryReport() {
        const memUsage = process.memoryUsage();
        const totalMemory = os.totalmem();
        const freeMemory = os.freemem();
        const usedMemory = totalMemory - freeMemory;
        const memoryUsagePercent = usedMemory / totalMemory;

        return {
            system: {
                total: this.formatBytes(totalMemory),
                used: this.formatBytes(usedMemory),
                free: this.formatBytes(freeMemory),
                usagePercent: Math.round(memoryUsagePercent * 100)
            },
            process: {
                rss: this.formatBytes(memUsage.rss),
                heapTotal: this.formatBytes(memUsage.heapTotal),
                heapUsed: this.formatBytes(memUsage.heapUsed),
                external: this.formatBytes(memUsage.external),
                heapUsagePercent: Math.round((memUsage.heapUsed / memUsage.heapTotal) * 100)
            },
            sessions: this.getSessionMemoryReport(),
            thresholds: {
                memoryThreshold: Math.round(this.memoryThreshold * 100) + '%',
                sessionMemoryLimit: this.formatBytes(this.sessionMemoryLimit)
            },
            garbageCollection: {
                enabled: this.gcEnabled,
                lastRun: new Date().toISOString()
            }
        };
    }

    getSessionMemoryReport() {
        const sessions = this.sessionManager.getAllSessions();
        const report = {};

        for (const session of sessions) {
            const metadata = this.sessionManager.getSessionMetadata(session.session_id);

            report[session.session_id] = {
                status: session.status,
                memoryUsage: metadata?.memoryUsage ? this.formatBytes(metadata.memoryUsage) : 'Unknown',
                needsCleanup: metadata?.needsMemoryCleanup || false,
                lastActivity: metadata?.lastActivity || null
            };
        }

        return report;
    }

    formatBytes(bytes) {
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let size = bytes;
        let unitIndex = 0;

        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }

        return `${Math.round(size * 100) / 100} ${units[unitIndex]}`;
    }

    cleanup() {
        this.stop();
    }
}

module.exports = MemoryManager;
