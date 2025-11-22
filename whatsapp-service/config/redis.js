/**
 * Redis Configuration Module
 * 
 * Centralized Redis configuration for WhatsApp session storage.
 * Provides singleton Redis client and connection management.
 * 
 * @author Blazz Platform
 * @date November 20, 2025
 * @follows docs/architecture/07-development-patterns-guidelines.md
 */

const Redis = require('ioredis');
const RedisStore = require('../src/auth/RedisStore');

class RedisConfig {
    constructor() {
        this.client = null;
        this.store = null;
        this.isConnected = false;
    }

    /**
     * Initialize Redis connection
     * 
     * @param {Object} options - Redis configuration options
     * @returns {Promise<Redis>}
     */
    async initialize(options = {}) {
        if (this.client) {
            console.log('[RedisConfig] Redis already initialized');
            return this.client;
        }

        const config = {
            host: options.host || process.env.REDIS_HOST || '127.0.0.1',
            port: parseInt(options.port || process.env.REDIS_PORT || 6379),
            password: options.password || process.env.REDIS_PASSWORD || null,
            db: parseInt(options.db || process.env.REDIS_DB || 0),
            ...options
        };

        console.log('[RedisConfig] Initializing Redis connection...', {
            host: config.host,
            port: config.port,
            db: config.db
        });

        try {
            this.client = new Redis({
                host: config.host,
                port: config.port,
                password: config.password,
                db: config.db,
                retryStrategy: (times) => {
                    const delay = Math.min(times * 50, 2000);
                    console.log(`[RedisConfig] Retry attempt ${times}, delay: ${delay}ms`);
                    return delay;
                },
                maxRetriesPerRequest: 3,
                enableReadyCheck: true,
                connectTimeout: 10000,
                lazyConnect: false,
            });

            // Event handlers
            this.client.on('connect', () => {
                console.log('[RedisConfig] ✅ Connected to Redis');
                this.isConnected = true;
            });

            this.client.on('ready', () => {
                console.log('[RedisConfig] ✅ Redis client ready');
            });

            this.client.on('error', (err) => {
                console.error('[RedisConfig] ❌ Redis error:', err.message);
                this.isConnected = false;
            });

            this.client.on('close', () => {
                console.log('[RedisConfig] Redis connection closed');
                this.isConnected = false;
            });

            this.client.on('reconnecting', () => {
                console.log('[RedisConfig] Redis reconnecting...');
            });

            this.client.on('end', () => {
                console.log('[RedisConfig] Redis connection ended');
                this.isConnected = false;
            });

            // Wait for connection to be ready
            await this.client.ping();
            console.log('[RedisConfig] ✅ Redis ping successful');

            // Initialize RedisStore
            this.store = new RedisStore({
                host: config.host,
                port: config.port,
                password: config.password,
                db: config.db,
                keyPrefix: process.env.REDIS_SESSION_PREFIX || 'whatsapp:session:',
                sessionTTL: parseInt(process.env.REDIS_SESSION_TTL || 7 * 24 * 60 * 60), // 7 days
                enableBackup: process.env.REDIS_ENABLE_BACKUP !== 'false',
                backupPath: process.env.REDIS_BACKUP_PATH || './session-backups',
            });

            console.log('[RedisConfig] ✅ RedisStore initialized');

            return this.client;

        } catch (error) {
            console.error('[RedisConfig] ❌ Failed to initialize Redis:', error.message);
            throw error;
        }
    }

    /**
     * Get Redis client instance
     * 
     * @returns {Redis}
     */
    getClient() {
        if (!this.client) {
            throw new Error('Redis not initialized. Call initialize() first.');
        }
        return this.client;
    }

    /**
     * Get RedisStore instance
     * 
     * @returns {RedisStore}
     */
    getStore() {
        if (!this.store) {
            throw new Error('RedisStore not initialized. Call initialize() first.');
        }
        return this.store;
    }

    /**
     * Check if Redis is connected
     * 
     * @returns {boolean}
     */
    isReady() {
        return this.isConnected && this.client && this.client.status === 'ready';
    }

    /**
     * Ping Redis to check connection
     * 
     * @returns {Promise<boolean>}
     */
    async ping() {
        try {
            if (!this.client) {
                return false;
            }
            const result = await this.client.ping();
            return result === 'PONG';
        } catch (error) {
            console.error('[RedisConfig] Ping failed:', error.message);
            return false;
        }
    }

    /**
     * Get Redis health status
     * 
     * @returns {Promise<Object>}
     */
    async getHealthStatus() {
        if (!this.client) {
            return {
                connected: false,
                status: 'not_initialized',
                error: 'Redis not initialized'
            };
        }

        try {
            const info = await this.client.info();
            const dbSize = await this.client.dbsize();
            const memory = await this.client.info('memory');

            return {
                connected: this.isConnected,
                status: this.client.status,
                dbSize,
                uptime: this.extractInfoValue(info, 'uptime_in_seconds'),
                connectedClients: this.extractInfoValue(info, 'connected_clients'),
                usedMemory: this.extractInfoValue(memory, 'used_memory_human'),
                version: this.extractInfoValue(info, 'redis_version'),
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
     * Extract value from Redis INFO output
     */
    extractInfoValue(info, key) {
        const line = info.split('\n').find(l => l.startsWith(`${key}:`));
        return line ? line.split(':')[1].trim() : 'unknown';
    }

    /**
     * Close Redis connection gracefully
     */
    async close() {
        if (this.store) {
            await this.store.close();
        }

        if (this.client) {
            try {
                await this.client.quit();
                console.log('[RedisConfig] Redis connection closed gracefully');
            } catch (error) {
                console.error('[RedisConfig] Error closing Redis:', error.message);
            }
            this.client = null;
        }

        this.isConnected = false;
    }

    /**
     * Force disconnect (for emergency shutdown)
     */
    disconnect() {
        if (this.client) {
            this.client.disconnect();
            this.client = null;
        }
        this.isConnected = false;
    }
}

// Export singleton instance
const redisConfig = new RedisConfig();

module.exports = redisConfig;
