const fs = require('fs').promises;
const path = require('path');
const winston = require('winston');

class ProfileLockCleaner {
    constructor(sessionManager) {
        this.sessionManager = sessionManager;
        this.cleanupInterval = 5 * 60 * 1000; // 5 minutes
        this.isRunning = false;
    }

    start() {
        if (this.isRunning) return;

        this.isRunning = true;
        winston.info('ProfileLockCleaner started');

        this.cleanupTimer = setInterval(() => {
            this.performCleanup();
        }, this.cleanupInterval);

        // Perform initial cleanup
        this.performCleanup();
    }

    stop() {
        if (!this.isRunning) return;

        this.isRunning = false;
        if (this.cleanupTimer) {
            clearInterval(this.cleanupTimer);
        }

        winston.info('ProfileLockCleaner stopped');
    }

    async performCleanup() {
        try {
            winston.debug('Starting profile lock cleanup');

            // Get all session directories
            const sessionDirs = await this.getAllSessionDirectories();

            for (const sessionDir of sessionDirs) {
                await this.cleanupSessionLocks(sessionDir);
            }

            winston.debug('Profile lock cleanup completed');
        } catch (error) {
            winston.error('Profile lock cleanup failed', { error: error.message });
        }
    }

    async getAllSessionDirectories() {
        const sessionsPath = process.env.SESSION_STORAGE_PATH || './sessions';
        const directories = [];

        try {
            const workspaceDirs = await fs.readdir(sessionsPath);

            for (const workspaceDir of workspaceDirs) {
                const workspacePath = path.join(sessionsPath, workspaceDir);

                try {
                    const stats = await fs.stat(workspacePath);
                    if (stats.isDirectory()) {
                        const sessionDirs = await fs.readdir(workspacePath);

                        for (const sessionDir of sessionDirs) {
                            directories.push(path.join(workspacePath, sessionDir));
                        }
                    }
                } catch (error) {
                    winston.debug('Error reading workspace directory', {
                        workspaceDir,
                        error: error.message
                    });
                }
            }
        } catch (error) {
            winston.debug('Error reading sessions directory', { error: error.message });
        }

        return directories;
    }

    async cleanupSessionLocks(sessionDir) {
        try {
            // List of Chrome lock files that can prevent startup
            const lockFiles = [
                'SingletonLock',
                'SingletonCookie',
                'SingletonSocket',
                'lockfile',
                '.com.google.Chrome.lock',
                'ChromeLock'
            ];

            for (const lockFile of lockFiles) {
                const lockPath = path.join(sessionDir, lockFile);

                try {
                    if (await this.pathExists(lockPath)) {
                        const stats = await fs.stat(lockPath);
                        const age = Date.now() - stats.mtime.getTime();

                        // Remove lock files older than 10 minutes (orphaned locks)
                        if (age > 10 * 60 * 1000) {
                            await fs.unlink(lockPath);
                            winston.debug('Removed orphaned lock file', {
                                lockPath,
                                age: Math.round(age / (1000 * 60)) + ' minutes'
                            });
                        }
                    }
                } catch (error) {
                    // File might not exist or already removed
                    winston.debug('Error checking lock file', {
                        lockPath,
                        error: error.message
                    });
                }
            }

            // Clean up Chrome preference files that might be corrupted
            await this.cleanupCorruptedPreferences(sessionDir);

        } catch (error) {
            winston.error('Error cleaning session locks', {
                sessionDir,
                error: error.message
            });
        }
    }

    async cleanupCorruptedPreferences(sessionDir) {
        // Clean up potentially corrupted Chrome preference files
        const prefFiles = [
            'Default/Preferences',
            'Default/Secure Preferences',
            'Local State'
        ];

        for (const prefFile of prefFiles) {
            const prefPath = path.join(sessionDir, prefFile);

            try {
                if (await this.pathExists(prefPath)) {
                    const stats = await fs.stat(prefPath);
                    const age = Date.now() - stats.mtime.getTime();

                    // If preferences file is very old (>7 days), it might be corrupted
                    if (age > 7 * 24 * 60 * 60 * 1000) {
                        // Backup the file before removing
                        const backupPath = `${prefPath}.backup.${Date.now()}`;
                        await fs.copyFile(prefPath, backupPath);

                        // Remove the potentially corrupted file
                        await fs.unlink(prefPath);

                        winston.info('Removed potentially corrupted preferences file', {
                            prefPath,
                            backupPath,
                            age: Math.round(age / (1000 * 60 * 60 * 24)) + ' days'
                        });
                    }
                }
            } catch (error) {
                winston.debug('Error handling preferences file', {
                    prefPath,
                    error: error.message
                });
            }
        }
    }

    async cleanupBeforeSessionStart(sessionDir) {
        winston.info('Cleaning locks before session start', { sessionDir });

        try {
            // Remove all lock files before starting a new session
            const lockFiles = [
                'SingletonLock',
                'SingletonCookie',
                'SingletonSocket',
                'lockfile',
                '.com.google.Chrome.lock'
            ];

            for (const lockFile of lockFiles) {
                const lockPath = path.join(sessionDir, lockFile);

                try {
                    if (await this.pathExists(lockPath)) {
                        await fs.unlink(lockPath);
                        winston.debug('Removed lock file before session start', { lockPath });
                    }
                } catch (error) {
                    // Ignore errors for non-existent files
                }
            }

            // Ensure session directory exists and has proper permissions
            await fs.mkdir(sessionDir, { recursive: true });

            winston.info('Pre-session cleanup completed', { sessionDir });
        } catch (error) {
            winston.error('Pre-session cleanup failed', {
                sessionDir,
                error: error.message
            });
            throw error;
        }
    }

    async cleanupAfterSessionEnd(sessionDir) {
        winston.info('Cleaning locks after session end', { sessionDir });

        try {
            // Clean up any lock files that might be left after session ends
            await this.cleanupSessionLocks(sessionDir);

            // Remove temporary files that might be left behind
            await this.cleanupTemporaryFiles(sessionDir);

        } catch (error) {
            winston.error('Post-session cleanup failed', {
                sessionDir,
                error: error.message
            });
        }
    }

    async cleanupTemporaryFiles(sessionDir) {
        // Remove temporary files that Chrome might leave behind
        const tempPatterns = [
            'Default/Cache/*',
            'Default/Media Cache/*',
            'Default/GPUCache/*',
            '*.tmp',
            '*.temp'
        ];

        for (const pattern of tempPatterns) {
            try {
                const files = await this.getFilesByPattern(sessionDir, pattern);

                for (const file of files) {
                    try {
                        const stats = await fs.stat(file);
                        const age = Date.now() - stats.mtime.getTime();

                        // Remove temp files older than 1 hour
                        if (age > 60 * 60 * 1000) {
                            await fs.unlink(file);
                            winston.debug('Removed temporary file', { file });
                        }
                    } catch (error) {
                        // File might already be removed
                    }
                }
            } catch (error) {
                // Pattern might not match any files
            }
        }
    }

    async getFilesByPattern(directory, pattern) {
        // Simple pattern matching for file cleanup
        const files = [];

        try {
            const items = await fs.readdir(directory);

            for (const item of items) {
                const itemPath = path.join(directory, item);

                try {
                    const stats = await fs.stat(itemPath);

                    if (stats.isFile() && this.matchPattern(item, pattern)) {
                        files.push(itemPath);
                    } else if (stats.isDirectory() && pattern.includes('*')) {
                        // Recursively search subdirectories
                        const subFiles = await this.getFilesByPattern(itemPath, pattern);
                        files.push(...subFiles);
                    }
                } catch (error) {
                    // Skip inaccessible files
                }
            }
        } catch (error) {
            // Directory might not exist
        }

        return files;
    }

    matchPattern(filename, pattern) {
        if (pattern === '*.tmp') {
            return filename.endsWith('.tmp');
        }
        if (pattern === '*.temp') {
            return filename.endsWith('.temp');
        }
        if (pattern === '*.log') {
            return filename.endsWith('.log');
        }
        return false;
    }

    async pathExists(filePath) {
        try {
            await fs.access(filePath);
            return true;
        } catch (error) {
            return false;
        }
    }

    getCleanupReport() {
        return {
            cleanupInterval: '5 minutes',
            lockFilesCleaned: [
                'SingletonLock',
                'SingletonCookie',
                'SingletonSocket',
                'lockfile',
                '.com.google.Chrome.lock'
            ],
            corruptedFilesHandled: [
                'Default/Preferences',
                'Default/Secure Preferences',
                'Local State'
            ],
            lastCleanup: new Date().toISOString()
        };
    }

    cleanup() {
        this.stop();
    }
}

module.exports = ProfileLockCleaner;
