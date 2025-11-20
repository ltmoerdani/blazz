const fs = require('fs').promises;
const path = require('path');

/**
 * Session Lock Manager
 * 
 * Prevents multiple PM2 workers from accessing the same WhatsApp session simultaneously.
 * Uses filesystem-based locking for cluster coordination.
 */
class SessionLock {
    constructor(logger) {
        this.logger = logger;
        this.lockDir = path.join(__dirname, '../../.locks');
        this.locks = new Map(); // Track locks held by this worker
        this.initLockDir();
    }

    async initLockDir() {
        try {
            await fs.mkdir(this.lockDir, { recursive: true });
        } catch (error) {
            this.logger.error('Failed to create lock directory', { error: error.message });
        }
    }

    /**
     * Acquire lock for a session
     * @param {string} sessionId - Session identifier
     * @param {number} timeout - Maximum wait time in ms (default: 30000)
     * @returns {Promise<boolean>} - True if lock acquired, false if timeout
     */
    async acquireLock(sessionId, timeout = 30000) {
        const lockFile = path.join(this.lockDir, `${sessionId}.lock`);
        const workerId = process.env.pm_id || process.pid;
        const startTime = Date.now();

        while (Date.now() - startTime < timeout) {
            try {
                // Try to create lock file exclusively
                const handle = await fs.open(lockFile, 'wx');
                await handle.write(JSON.stringify({
                    workerId,
                    pid: process.pid,
                    acquiredAt: new Date().toISOString()
                }));
                await handle.close();

                this.locks.set(sessionId, lockFile);
                this.logger.info(`🔒 Lock acquired for session ${sessionId} by worker ${workerId}`);
                return true;

            } catch (error) {
                if (error.code === 'EEXIST') {
                    // Lock exists, check if it's stale (older than 5 minutes)
                    try {
                        const stats = await fs.stat(lockFile);
                        const age = Date.now() - stats.mtimeMs;
                        
                        if (age > 300000) { // 5 minutes
                            this.logger.warning(`Removing stale lock for session ${sessionId} (age: ${Math.round(age/1000)}s)`);
                            await this.releaseLock(sessionId);
                            continue;
                        }
                    } catch (statError) {
                        // Lock file disappeared, try again
                        continue;
                    }

                    // Wait and retry
                    await new Promise(resolve => setTimeout(resolve, 1000));
                } else {
                    this.logger.error(`Failed to acquire lock for ${sessionId}:`, error.message);
                    return false;
                }
            }
        }

        this.logger.warn(`Lock acquisition timeout for session ${sessionId} after ${timeout}ms`);
        return false;
    }

    /**
     * Release lock for a session
     * @param {string} sessionId - Session identifier
     */
    async releaseLock(sessionId) {
        const lockFile = this.locks.get(sessionId) || path.join(this.lockDir, `${sessionId}.lock`);
        
        try {
            await fs.unlink(lockFile);
            this.locks.delete(sessionId);
            this.logger.info(`🔓 Lock released for session ${sessionId}`);
        } catch (error) {
            if (error.code !== 'ENOENT') {
                this.logger.error(`Failed to release lock for ${sessionId}:`, error.message);
            }
        }
    }

    /**
     * Check if session is locked by another worker
     * @param {string} sessionId - Session identifier
     * @returns {Promise<boolean>}
     */
    async isLocked(sessionId) {
        const lockFile = path.join(this.lockDir, `${sessionId}.lock`);
        
        try {
            await fs.access(lockFile);
            return true;
        } catch {
            return false;
        }
    }

    /**
     * Release all locks held by this worker
     */
    async releaseAll() {
        const sessionIds = Array.from(this.locks.keys());
        
        for (const sessionId of sessionIds) {
            await this.releaseLock(sessionId);
        }
        
        this.logger.info(`Released ${sessionIds.length} locks`);
    }
}

module.exports = SessionLock;
