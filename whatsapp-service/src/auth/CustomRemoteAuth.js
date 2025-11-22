/**
 * Custom RemoteAuth Strategy for WhatsApp Web.js
 * 
 * Custom implementation of RemoteAuth strategy using Redis as backend.
 * Compatible with whatsapp-web.js Client authStrategy interface.
 * 
 * This strategy enables:
 * - Centralized session storage across PM2 cluster workers
 * - Automatic session backup and restoration
 * - Session persistence across server restarts
 * - Multi-worker session sharing (critical for scale)
 * 
 * @author Blazz Platform
 * @date November 20, 2025
 * @follows docs/architecture/07-development-patterns-guidelines.md
 */

const fs = require('fs').promises;
const path = require('path');

class CustomRemoteAuth {
    constructor(options = {}) {
        this.clientId = options.clientId;
        this.dataPath = options.dataPath || './.wwebjs_auth';
        this.store = options.store; // RedisStore instance
        this.backupSyncIntervalMs = options.backupSyncIntervalMs || 60000; // 1 minute default
        
        if (!this.store) {
            throw new Error('CustomRemoteAuth requires a store instance');
        }

        if (!this.clientId) {
            throw new Error('CustomRemoteAuth requires a clientId');
        }

        this.sessionBackupInterval = null;
        this.authenticated = false;
        
        console.log(`[CustomRemoteAuth] Initialized for client: ${this.clientId}`);
    }

    /**
     * Get authentication data path (local temporary storage)
     */
    async getAuthDataPath() {
        const dirPath = path.join(this.dataPath, `RemoteAuth-${this.clientId}`);
        
        try {
            await fs.mkdir(dirPath, { recursive: true });
        } catch (error) {
            console.error('[CustomRemoteAuth] Failed to create auth directory:', error.message);
        }

        return dirPath;
    }

    /**
     * Setup method (required by whatsapp-web.js Client interface)
     * Called during Client initialization
     */
    async setup() {
        console.log(`[CustomRemoteAuth] Setup called for client: ${this.clientId}`);
        // Setup is handled in beforeBrowserInitialized
        // This method just needs to exist to satisfy the interface
    }

    /**
     * Called when authentication is needed (QR code scan required)
     * Required by whatsapp-web.js Client interface
     */
    async onAuthenticationNeeded() {
        console.log(`[CustomRemoteAuth] Authentication needed for client: ${this.clientId}`);
        // QR code will be emitted by whatsapp-web.js 'qr' event
        // We don't need to do anything special here
    }

    /**
     * Setup authentication (called by whatsapp-web.js)
     * Load existing session from Redis or create new
     */
    async beforeBrowserInitialized() {
        console.log(`[CustomRemoteAuth] Before browser initialized: ${this.clientId}`);

        // Check if session exists in Redis
        const sessionExists = await this.store.sessionExists(this.clientId);

        if (sessionExists) {
            console.log(`[CustomRemoteAuth] Existing session found in Redis: ${this.clientId}`);
            
            // Extract session from Redis and save to local temp folder
            const sessionData = await this.store.extract(this.clientId);
            
            if (sessionData) {
                await this.restoreSessionLocally(sessionData);
                console.log(`[CustomRemoteAuth] Session restored from Redis: ${this.clientId}`);
            }
        } else {
            console.log(`[CustomRemoteAuth] No existing session found: ${this.clientId}`);
        }
    }

    /**
     * Post-authentication hook (called after successful auth)
     */
    async afterBrowserInitialized() {
        console.log(`[CustomRemoteAuth] After browser initialized: ${this.clientId}`);
        
        // Start periodic backup sync
        this.startBackupSync();
    }

    /**
     * Save session to Redis after authentication
     */
    async afterAuthReady() {
        console.log(`[CustomRemoteAuth] After auth ready: ${this.clientId}`);
        
        this.authenticated = true;

        // Backup session to Redis immediately after authentication
        await this.backupSession();
    }

    /**
     * Cleanup on logout
     */
    async logout() {
        console.log(`[CustomRemoteAuth] Logout: ${this.clientId}`);
        
        this.authenticated = false;
        this.stopBackupSync();

        // Delete session from Redis and local storage
        await this.store.delete(this.clientId);
        await this.deleteLocalSession();
    }

    /**
     * Cleanup on disconnect
     */
    async disconnect() {
        console.log(`[CustomRemoteAuth] Disconnect: ${this.clientId}`);
        
        this.stopBackupSync();

        // Keep session in Redis for reconnection, just stop syncing
    }

    /**
     * Cleanup on destroy
     */
    async destroy() {
        console.log(`[CustomRemoteAuth] Destroy: ${this.clientId}`);
        
        this.authenticated = false;
        this.stopBackupSync();
        
        // Optionally delete session (keep for now to allow reconnection)
        // await this.store.delete(this.clientId);
    }

    /**
     * Backup session from local storage to Redis
     */
    async backupSession() {
        try {
            const authPath = await this.getAuthDataPath();
            const sessionPath = path.join(authPath, 'session');

            // Check if local session exists
            try {
                await fs.access(sessionPath);
            } catch {
                console.log(`[CustomRemoteAuth] No local session to backup: ${this.clientId}`);
                return;
            }

            // Read all session files recursively
            const sessionData = await this.readSessionDirectory(sessionPath);

            if (sessionData && Object.keys(sessionData).length > 0) {
                // Serialize to JSON
                const serialized = JSON.stringify(sessionData);

                // Save to Redis
                await this.store.save(this.clientId, serialized);

                console.log(`[CustomRemoteAuth] Session backed up to Redis: ${this.clientId} (${serialized.length} bytes)`);
            }

        } catch (error) {
            console.error(`[CustomRemoteAuth] Failed to backup session ${this.clientId}:`, error.message);
        }
    }

    /**
     * Restore session from Redis to local storage
     */
    async restoreSessionLocally(sessionData) {
        try {
            const authPath = await this.getAuthDataPath();
            const sessionPath = path.join(authPath, 'session');

            // Parse JSON session data
            const data = JSON.parse(sessionData);

            // Write session files
            await this.writeSessionDirectory(sessionPath, data);

            console.log(`[CustomRemoteAuth] Session restored locally: ${this.clientId}`);

        } catch (error) {
            console.error(`[CustomRemoteAuth] Failed to restore session locally ${this.clientId}:`, error.message);
            throw error;
        }
    }

    /**
     * Read session directory recursively
     */
    async readSessionDirectory(dirPath) {
        const result = {};

        try {
            const entries = await fs.readdir(dirPath, { withFileTypes: true });

            for (const entry of entries) {
                const fullPath = path.join(dirPath, entry.name);

                if (entry.isDirectory()) {
                    result[entry.name] = await this.readSessionDirectory(fullPath);
                } else {
                    const content = await fs.readFile(fullPath, 'utf8');
                    result[entry.name] = content;
                }
            }

        } catch (error) {
            console.error(`[CustomRemoteAuth] Failed to read session directory:`, error.message);
        }

        return result;
    }

    /**
     * Write session directory recursively
     */
    async writeSessionDirectory(dirPath, data) {
        await fs.mkdir(dirPath, { recursive: true });

        for (const [name, content] of Object.entries(data)) {
            const fullPath = path.join(dirPath, name);

            if (typeof content === 'object' && content !== null) {
                // It's a directory
                await this.writeSessionDirectory(fullPath, content);
            } else {
                // It's a file
                await fs.writeFile(fullPath, content, 'utf8');
            }
        }
    }

    /**
     * Delete local session files
     */
    async deleteLocalSession() {
        try {
            const authPath = await this.getAuthDataPath();
            await fs.rm(authPath, { recursive: true, force: true });
            console.log(`[CustomRemoteAuth] Local session deleted: ${this.clientId}`);
        } catch (error) {
            console.error(`[CustomRemoteAuth] Failed to delete local session:`, error.message);
        }
    }

    /**
     * Start periodic backup sync
     */
    startBackupSync() {
        if (this.sessionBackupInterval) {
            return; // Already running
        }

        console.log(`[CustomRemoteAuth] Starting backup sync (every ${this.backupSyncIntervalMs}ms): ${this.clientId}`);

        this.sessionBackupInterval = setInterval(async () => {
            if (this.authenticated) {
                await this.backupSession();
            }
        }, this.backupSyncIntervalMs);
    }

    /**
     * Stop periodic backup sync
     */
    stopBackupSync() {
        if (this.sessionBackupInterval) {
            clearInterval(this.sessionBackupInterval);
            this.sessionBackupInterval = null;
            console.log(`[CustomRemoteAuth] Backup sync stopped: ${this.clientId}`);
        }
    }

    /**
     * Get session info (for debugging)
     */
    async getSessionInfo() {
        const exists = await this.store.sessionExists(this.clientId);
        
        return {
            clientId: this.clientId,
            authenticated: this.authenticated,
            existsInRedis: exists,
            backupSyncActive: this.sessionBackupInterval !== null
        };
    }
}

module.exports = CustomRemoteAuth;
