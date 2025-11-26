/**
 * Session Migration Utility
 * 
 * Tools for migrating sessions from LocalAuth to RemoteAuth.
 * Provides export, import, validation, and rollback capabilities.
 * 
 * Usage:
 * ```bash
 * node src/utils/SessionMigration.js export <sessionId>
 * node src/utils/SessionMigration.js import <sessionId>
 * node src/utils/SessionMigration.js validate <sessionId>
 * node src/utils/SessionMigration.js migrate-all
 * ```
 * 
 * @author Blazz Platform
 * @date November 20, 2025
 * @follows docs/architecture/17-database-compatibility-audit-and-implementation-guide.md
 */

const fs = require('fs').promises;
const path = require('path');
const RedisStore = require('../auth/RedisStore');
const CustomRemoteAuth = require('../auth/CustomRemoteAuth');

class SessionMigration {
    constructor() {
        this.localSessionsPath = './sessions';
        this.backupPath = './migration-backups';
        this.redisStore = null;
    }

    /**
     * Initialize Redis store
     */
    async initializeRedis() {
        if (this.redisStore) {
            return;
        }

        this.redisStore = new RedisStore({
            host: process.env.REDIS_HOST || '127.0.0.1',
            port: process.env.REDIS_PORT || 6379,
            password: process.env.REDIS_PASSWORD || null,
            db: process.env.REDIS_DB || 0,
            keyPrefix: 'whatsapp:session:',
            sessionTTL: 7 * 24 * 60 * 60,
            enableBackup: true,
            backupPath: './session-backups'
        });

        console.log('✅ Redis store initialized');
    }

    /**
     * Export LocalAuth session to backup file
     * 
     * @param {string} sessionId - Session identifier
     * @returns {Promise<Object>}
     */
    async exportSession(sessionId) {
        try {
            console.log(`📤 Exporting session: ${sessionId}`);

            // Find session directory (search in all workspace folders)
            const sessionPath = await this.findSessionPath(sessionId);
            
            if (!sessionPath) {
                throw new Error(`Session not found: ${sessionId}`);
            }

            console.log(`Found session at: ${sessionPath}`);

            // Read session data recursively
            const sessionData = await this.readDirectory(sessionPath);

            // Create backup directory
            await fs.mkdir(this.backupPath, { recursive: true });

            // Save to backup file
            const backupFilePath = path.join(this.backupPath, `${sessionId}-${Date.now()}.json`);
            await fs.writeFile(backupFilePath, JSON.stringify(sessionData, null, 2), 'utf8');

            console.log(`✅ Session exported to: ${backupFilePath}`);

            return {
                success: true,
                sessionId,
                backupFilePath,
                size: JSON.stringify(sessionData).length
            };

        } catch (error) {
            console.error(`❌ Failed to export session ${sessionId}:`, error.message);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Import session to Redis (RemoteAuth)
     * 
     * @param {string} sessionId - Session identifier
     * @param {string} backupFilePath - Optional backup file path
     * @returns {Promise<Object>}
     */
    async importSession(sessionId, backupFilePath = null) {
        try {
            await this.initializeRedis();

            console.log(`📥 Importing session to Redis: ${sessionId}`);

            let sessionData;

            if (backupFilePath) {
                // Import from backup file
                const fileContent = await fs.readFile(backupFilePath, 'utf8');
                sessionData = JSON.parse(fileContent);
                console.log(`Loaded session from backup: ${backupFilePath}`);
            } else {
                // Import from LocalAuth directory
                const sessionPath = await this.findSessionPath(sessionId);
                
                if (!sessionPath) {
                    throw new Error(`Session not found: ${sessionId}`);
                }

                sessionData = await this.readDirectory(sessionPath);
                console.log(`Loaded session from LocalAuth: ${sessionPath}`);
            }

            // Save to Redis
            const serialized = JSON.stringify(sessionData);
            await this.redisStore.save(sessionId, serialized);

            console.log(`✅ Session imported to Redis: ${sessionId} (${serialized.length} bytes)`);

            return {
                success: true,
                sessionId,
                size: serialized.length,
                existsInRedis: await this.redisStore.sessionExists(sessionId)
            };

        } catch (error) {
            console.error(`❌ Failed to import session ${sessionId}:`, error.message);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Validate session in both LocalAuth and Redis
     * 
     * @param {string} sessionId - Session identifier
     * @returns {Promise<Object>}
     */
    async validateSession(sessionId) {
        try {
            await this.initializeRedis();

            console.log(`🔍 Validating session: ${sessionId}`);

            const validation = {
                sessionId,
                localAuth: {
                    exists: false,
                    path: null,
                    size: 0
                },
                remoteAuth: {
                    exists: false,
                    size: 0
                },
                match: false
            };

            // Check LocalAuth
            const sessionPath = await this.findSessionPath(sessionId);
            if (sessionPath) {
                validation.localAuth.exists = true;
                validation.localAuth.path = sessionPath;
                
                const sessionData = await this.readDirectory(sessionPath);
                validation.localAuth.size = JSON.stringify(sessionData).length;
            }

            // Check RemoteAuth
            const existsInRedis = await this.redisStore.sessionExists(sessionId);
            if (existsInRedis) {
                validation.remoteAuth.exists = true;
                
                const redisData = await this.redisStore.extract(sessionId);
                validation.remoteAuth.size = redisData ? redisData.length : 0;
            }

            // Compare
            validation.match = validation.localAuth.exists && 
                               validation.remoteAuth.exists &&
                               validation.localAuth.size === validation.remoteAuth.size;

            console.log(`✅ Validation complete:`, validation);

            return {
                success: true,
                validation
            };

        } catch (error) {
            console.error(`❌ Failed to validate session ${sessionId}:`, error.message);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Migrate all sessions from LocalAuth to RemoteAuth
     * 
     * @returns {Promise<Object>}
     */
    async migrateAll() {
        try {
            await this.initializeRedis();

            console.log(`🔄 Starting batch migration...`);

            const sessions = await this.findAllSessions();
            console.log(`Found ${sessions.length} sessions to migrate`);

            const results = {
                total: sessions.length,
                success: 0,
                failed: 0,
                errors: []
            };

            for (const sessionId of sessions) {
                console.log(`\n📦 Migrating session ${results.success + results.failed + 1}/${results.total}: ${sessionId}`);

                // Export first (backup)
                const exportResult = await this.exportSession(sessionId);
                
                if (!exportResult.success) {
                    results.failed++;
                    results.errors.push({
                        sessionId,
                        error: exportResult.error,
                        step: 'export'
                    });
                    continue;
                }

                // Import to Redis
                const importResult = await this.importSession(sessionId);
                
                if (importResult.success) {
                    results.success++;
                    console.log(`✅ ${sessionId} migrated successfully`);
                } else {
                    results.failed++;
                    results.errors.push({
                        sessionId,
                        error: importResult.error,
                        step: 'import'
                    });
                }

                // Small delay to avoid overwhelming Redis
                await this.sleep(100);
            }

            console.log(`\n✅ Migration complete: ${results.success}/${results.total} successful`);

            if (results.failed > 0) {
                console.log(`\n❌ Failed migrations: ${results.failed}`);
                results.errors.forEach(err => {
                    console.log(`   - ${err.sessionId}: ${err.error} (${err.step})`);
                });
            }

            return {
                success: results.failed === 0,
                results
            };

        } catch (error) {
            console.error(`❌ Batch migration failed:`, error.message);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Find session path in LocalAuth structure
     */
    async findSessionPath(sessionId) {
        try {
            // Check if sessions directory exists
            await fs.access(this.localSessionsPath);

            // Search in all workspace directories
            const workspaces = await fs.readdir(this.localSessionsPath);

            for (const workspace of workspaces) {
                const workspacePath = path.join(this.localSessionsPath, workspace);
                const stat = await fs.stat(workspacePath);

                if (stat.isDirectory()) {
                    const sessionPath = path.join(workspacePath, sessionId, 'session');

                    try {
                        await fs.access(sessionPath);
                        return sessionPath;
                    } catch {
                        // Continue searching
                    }
                }
            }

            return null;

        } catch (error) {
            return null;
        }
    }

    /**
     * Find all session IDs in LocalAuth structure
     */
    async findAllSessions() {
        const sessionIds = [];

        try {
            await fs.access(this.localSessionsPath);

            const workspaces = await fs.readdir(this.localSessionsPath);

            for (const workspace of workspaces) {
                const workspacePath = path.join(this.localSessionsPath, workspace);
                const stat = await fs.stat(workspacePath);

                if (stat.isDirectory()) {
                    const sessions = await fs.readdir(workspacePath);

                    for (const session of sessions) {
                        const sessionPath = path.join(workspacePath, session, 'session');

                        try {
                            await fs.access(sessionPath);
                            sessionIds.push(session);
                        } catch {
                            // Not a valid session
                        }
                    }
                }
            }

        } catch (error) {
            console.error('Failed to find sessions:', error.message);
        }

        return sessionIds;
    }

    /**
     * Read directory recursively
     */
    async readDirectory(dirPath) {
        const result = {};

        const entries = await fs.readdir(dirPath, { withFileTypes: true });

        for (const entry of entries) {
            const fullPath = path.join(dirPath, entry.name);

            if (entry.isDirectory()) {
                result[entry.name] = await this.readDirectory(fullPath);
            } else {
                try {
                    const content = await fs.readFile(fullPath, 'utf8');
                    result[entry.name] = content;
                } catch {
                    // Skip binary files or unreadable files
                    result[entry.name] = '[BINARY]';
                }
            }
        }

        return result;
    }

    /**
     * Sleep utility
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// CLI interface
if (require.main === module) {
    const migration = new SessionMigration();
    const command = process.argv[2];
    const sessionId = process.argv[3];

    (async () => {
        switch (command) {
            case 'export':
                if (!sessionId) {
                    console.error('Usage: node SessionMigration.js export <sessionId>');
                    process.exit(1);
                }
                await migration.exportSession(sessionId);
                break;

            case 'import':
                if (!sessionId) {
                    console.error('Usage: node SessionMigration.js import <sessionId> [backupFilePath]');
                    process.exit(1);
                }
                const backupFilePath = process.argv[4];
                await migration.importSession(sessionId, backupFilePath);
                break;

            case 'validate':
                if (!sessionId) {
                    console.error('Usage: node SessionMigration.js validate <sessionId>');
                    process.exit(1);
                }
                await migration.validateSession(sessionId);
                break;

            case 'migrate-all':
                await migration.migrateAll();
                break;

            default:
                console.log('Session Migration Utility');
                console.log('');
                console.log('Commands:');
                console.log('  export <sessionId>           Export session to backup file');
                console.log('  import <sessionId> [backup]  Import session to Redis');
                console.log('  validate <sessionId>         Validate session in both stores');
                console.log('  migrate-all                  Migrate all sessions to Redis');
                console.log('');
                console.log('Examples:');
                console.log('  node SessionMigration.js export session_123');
                console.log('  node SessionMigration.js import session_123');
                console.log('  node SessionMigration.js validate session_123');
                console.log('  node SessionMigration.js migrate-all');
                process.exit(0);
        }

        process.exit(0);
    })();
}

module.exports = SessionMigration;
