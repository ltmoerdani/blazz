/**
 * Redis Store for WhatsApp RemoteAuth
 * 
 * Custom implementation of Redis-backed session storage for whatsapp-web.js RemoteAuth.
 * This replaces LocalAuth's file-based storage with centralized Redis storage,
 * enabling PM2 cluster mode compatibility.
 * 
 * Features:
 * - Thread-safe session storage across PM2 workers
 * - Automatic session persistence and restoration
 * - Backup/restore capabilities
 * - TTL support for session cleanup
 * 
 * @author Blazz Platform
 * @date November 20, 2025
 * @follows docs/architecture/07-development-patterns-guidelines.md
 */

const Redis = require('ioredis');
const fs = require('fs').promises;
const path = require('path');

class RedisStore {
    constructor(config = {}) {
        this.config = {
            host: config.host || process.env.REDIS_HOST || '127.0.0.1',
            port: config.port || process.env.REDIS_PORT || 6379,
            password: config.password || process.env.REDIS_PASSWORD || null,
            db: config.db || process.env.REDIS_DB || 0,
            keyPrefix: config.keyPrefix || 'whatsapp:session:',
            sessionTTL: config.sessionTTL || 7 * 24 * 60 * 60, // 7 days default
            enableBackup: config.enableBackup !== false,
            backupPath: config.backupPath || './session-backups',
            ...config
        };

        // Initialize Redis client
        this.redis = new Redis({
            host: this.config.host,
            port: this.config.port,
            password: this.config.password,
            db: this.config.db,
            retryStrategy: (times) => {
                const delay = Math.min(times * 50, 2000);
                return delay;
            },
            maxRetriesPerRequest: 3,
            enableReadyCheck: true,
            connectTimeout: 10000,
        });

        this.redis.on('connect', () => {
            console.log('[RedisStore] Connected to Redis');
        });

        this.redis.on('error', (err) => {
            console.error('[RedisStore] Redis connection error:', err.message);
        });

        this.redis.on('close', () => {
            console.log('[RedisStore] Redis connection closed');
        });

        // Create backup directory if enabled
        if (this.config.enableBackup) {
            this.ensureBackupDirectory();
        }
    }

    /**
     * Get session key with prefix
     */
    getKey(sessionId) {
        return `${this.config.keyPrefix}${sessionId}`;
    }

    /**
     * Save session data to Redis
     * 
     * @param {string} sessionId - Session identifier
     * @param {string} sessionData - Serialized session data
     * @returns {Promise<boolean>}
     */
    async save(sessionId, sessionData) {
        try {
            const key = this.getKey(sessionId);
            
            // Save to Redis with TTL
            await this.redis.setex(
                key,
                this.config.sessionTTL,
                sessionData
            );

            // Backup to filesystem if enabled
            if (this.config.enableBackup) {
                await this.backupToFile(sessionId, sessionData);
            }

            console.log(`[RedisStore] Session saved: ${sessionId}`);
            return true;

        } catch (error) {
            console.error(`[RedisStore] Failed to save session ${sessionId}:`, error.message);
            
            // Fallback to file backup
            if (this.config.enableBackup) {
                try {
                    await this.backupToFile(sessionId, sessionData);
                    console.log(`[RedisStore] Session saved to backup file: ${sessionId}`);
                    return true;
                } catch (backupError) {
                    console.error(`[RedisStore] Backup also failed:`, backupError.message);
                }
            }
            
            throw error;
        }
    }

    /**
     * Extract session data from Redis
     * 
     * @param {string} sessionId - Session identifier
     * @returns {Promise<string|null>}
     */
    async extract(sessionId) {
        try {
            const key = this.getKey(sessionId);
            const sessionData = await this.redis.get(key);

            if (sessionData) {
                console.log(`[RedisStore] Session extracted from Redis: ${sessionId}`);
                
                // Refresh TTL on access
                await this.redis.expire(key, this.config.sessionTTL);
                
                return sessionData;
            }

            // Try to restore from backup if Redis doesn't have it
            if (this.config.enableBackup) {
                console.log(`[RedisStore] Session not in Redis, trying backup: ${sessionId}`);
                const backupData = await this.restoreFromFile(sessionId);
                
                if (backupData) {
                    // Restore to Redis
                    await this.save(sessionId, backupData);
                    return backupData;
                }
            }

            console.log(`[RedisStore] Session not found: ${sessionId}`);
            return null;

        } catch (error) {
            console.error(`[RedisStore] Failed to extract session ${sessionId}:`, error.message);
            
            // Try backup as fallback
            if (this.config.enableBackup) {
                try {
                    const backupData = await this.restoreFromFile(sessionId);
                    if (backupData) {
                        console.log(`[RedisStore] Session restored from backup: ${sessionId}`);
                        return backupData;
                    }
                } catch (backupError) {
                    console.error(`[RedisStore] Backup restore failed:`, backupError.message);
                }
            }
            
            return null;
        }
    }

    /**
     * Delete session from Redis
     * 
     * @param {string} sessionId - Session identifier
     * @returns {Promise<boolean>}
     */
    async delete(sessionId) {
        try {
            const key = this.getKey(sessionId);
            await this.redis.del(key);

            // Also delete backup file
            if (this.config.enableBackup) {
                await this.deleteBackupFile(sessionId);
            }

            console.log(`[RedisStore] Session deleted: ${sessionId}`);
            return true;

        } catch (error) {
            console.error(`[RedisStore] Failed to delete session ${sessionId}:`, error.message);
            return false;
        }
    }

    /**
     * Check if session exists
     * 
     * @param {string} sessionId - Session identifier
     * @returns {Promise<boolean>}
     */
    async sessionExists(sessionId) {
        try {
            const key = this.getKey(sessionId);
            const exists = await this.redis.exists(key);
            return exists === 1;

        } catch (error) {
            console.error(`[RedisStore] Failed to check session existence ${sessionId}:`, error.message);
            
            // Check backup as fallback
            if (this.config.enableBackup) {
                const backupPath = this.getBackupPath(sessionId);
                try {
                    await fs.access(backupPath);
                    return true;
                } catch {
                    return false;
                }
            }
            
            return false;
        }
    }

    /**
     * Get all session IDs
     * 
     * @returns {Promise<string[]>}
     */
    async getAllSessions() {
        try {
            const pattern = `${this.config.keyPrefix}*`;
            const keys = await this.redis.keys(pattern);
            
            // Remove prefix from keys
            const sessionIds = keys.map(key => 
                key.substring(this.config.keyPrefix.length)
            );

            return sessionIds;

        } catch (error) {
            console.error('[RedisStore] Failed to get all sessions:', error.message);
            return [];
        }
    }

    /**
     * Backup session to file
     * 
     * @param {string} sessionId - Session identifier
     * @param {string} sessionData - Session data
     * @returns {Promise<void>}
     */
    async backupToFile(sessionId, sessionData) {
        try {
            const backupPath = this.getBackupPath(sessionId);
            await fs.writeFile(backupPath, sessionData, 'utf8');
            console.log(`[RedisStore] Backup created: ${backupPath}`);
        } catch (error) {
            console.error(`[RedisStore] Failed to backup to file ${sessionId}:`, error.message);
            throw error;
        }
    }

    /**
     * Restore session from file
     * 
     * @param {string} sessionId - Session identifier
     * @returns {Promise<string|null>}
     */
    async restoreFromFile(sessionId) {
        try {
            const backupPath = this.getBackupPath(sessionId);
            const sessionData = await fs.readFile(backupPath, 'utf8');
            return sessionData;
        } catch (error) {
            // File doesn't exist or can't be read
            return null;
        }
    }

    /**
     * Delete backup file
     * 
     * @param {string} sessionId - Session identifier
     * @returns {Promise<void>}
     */
    async deleteBackupFile(sessionId) {
        try {
            const backupPath = this.getBackupPath(sessionId);
            await fs.unlink(backupPath);
            console.log(`[RedisStore] Backup deleted: ${backupPath}`);
        } catch (error) {
            // Ignore if file doesn't exist
            if (error.code !== 'ENOENT') {
                console.error(`[RedisStore] Failed to delete backup file:`, error.message);
            }
        }
    }

    /**
     * Get backup file path
     */
    getBackupPath(sessionId) {
        return path.join(this.config.backupPath, `${sessionId}.json`);
    }

    /**
     * Ensure backup directory exists
     */
    async ensureBackupDirectory() {
        try {
            await fs.mkdir(this.config.backupPath, { recursive: true });
        } catch (error) {
            console.error('[RedisStore] Failed to create backup directory:', error.message);
        }
    }

    /**
     * Get Redis client health status
     * 
     * @returns {Promise<Object>}
     */
    async getHealthStatus() {
        try {
            const info = await this.redis.info();
            const sessionCount = await this.redis.dbsize();

            return {
                connected: this.redis.status === 'ready',
                status: this.redis.status,
                sessionCount,
                info: info.split('\n').filter(line => 
                    line.includes('connected_clients') || 
                    line.includes('used_memory_human') ||
                    line.includes('uptime_in_seconds')
                ).join('\n')
            };

        } catch (error) {
            return {
                connected: false,
                status: 'error',
                error: error.message
            };
        }
    }

    /**
     * Close Redis connection
     */
    async close() {
        try {
            await this.redis.quit();
            console.log('[RedisStore] Redis connection closed gracefully');
        } catch (error) {
            console.error('[RedisStore] Error closing Redis connection:', error.message);
        }
    }

    /**
     * Ping Redis to check connection
     * 
     * @returns {Promise<boolean>}
     */
    async ping() {
        try {
            const result = await this.redis.ping();
            return result === 'PONG';
        } catch (error) {
            console.error('[RedisStore] Ping failed:', error.message);
            return false;
        }
    }
}

module.exports = RedisStore;
