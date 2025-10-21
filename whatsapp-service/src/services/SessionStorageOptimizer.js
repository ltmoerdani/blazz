const fs = require('fs').promises;
const path = require('path');
const winston = require('winston');

class SessionStorageOptimizer {
    constructor(sessionManager) {
        this.sessionManager = sessionManager;
        this.cleanupInterval = 24 * 60 * 60 * 1000; // 24 hours
        this.maxSessionSize = 100 * 1024 * 1024; // 100MB per session
        this.totalStorageLimit = 5 * 1024 * 1024 * 1024; // 5GB total
        this.isRunning = false;
    }

    start() {
        if (this.isRunning) return;

        this.isRunning = true;
        winston.info('SessionStorageOptimizer started');

        // Start cleanup interval
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

        winston.info('SessionStorageOptimizer stopped');
    }

    async performCleanup() {
        try {
            winston.info('Starting session storage cleanup');

            // Get all session directories
            const sessionDirs = await this.getAllSessionDirectories();

            // Clean up each session directory
            for (const dir of sessionDirs) {
                await this.cleanupSessionDirectory(dir);
            }

            // Check total storage usage
            await this.checkTotalStorageUsage();

            winston.info('Session storage cleanup completed');
        } catch (error) {
            winston.error('Session storage cleanup failed', { error: error.message });
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

    async cleanupSessionDirectory(sessionDir) {
        try {
            const dirStats = await fs.stat(sessionDir);
            const dirSize = await this.getDirectorySize(sessionDir);

            // Check if session directory exceeds size limit
            if (dirSize > this.maxSessionSize) {
                winston.info('Session directory exceeds size limit, cleaning up', {
                    sessionDir,
                    size: this.formatBytes(dirSize),
                    limit: this.formatBytes(this.maxSessionSize)
                });

                await this.cleanupLargeSessionDirectory(sessionDir);
            }

            // Clean up old cache files
            await this.cleanupCacheFiles(sessionDir);

            // Remove orphaned lock files
            await this.cleanupLockFiles(sessionDir);

        } catch (error) {
            winston.error('Error cleaning session directory', {
                sessionDir,
                error: error.message
            });
        }
    }

    async cleanupLargeSessionDirectory(sessionDir) {
        // Remove cache directories that can be safely deleted
        const cacheDirs = [
            'Default/Cache',
            'Default/Media Cache',
            'Default/GPUCache',
            'Default/Service Worker'
        ];

        for (const cacheDir of cacheDirs) {
            const cachePath = path.join(sessionDir, cacheDir);

            try {
                if (await this.pathExists(cachePath)) {
                    const cacheSize = await this.getDirectorySize(cachePath);
                    await fs.rm(cachePath, { recursive: true, force: true });

                    winston.debug('Removed cache directory', {
                        cachePath,
                        size: this.formatBytes(cacheSize)
                    });
                }
            } catch (error) {
                winston.debug('Error removing cache directory', {
                    cachePath,
                    error: error.message
                });
            }
        }

        // Clean up old log files (keep last 7 days)
        await this.cleanupOldFiles(sessionDir, '*.log', 7 * 24 * 60 * 60 * 1000);
    }

    async cleanupCacheFiles(sessionDir) {
        // Remove temporary cache files older than 1 hour
        const cacheFiles = [
            'Default/Cookies',
            'Default/History',
            'Default/Top Sites',
            'Default/VisitedLinks'
        ];

        for (const file of cacheFiles) {
            const filePath = path.join(sessionDir, file);

            try {
                if (await this.pathExists(filePath)) {
                    const stats = await fs.stat(filePath);
                    const age = Date.now() - stats.mtime.getTime();

                    // Remove if older than 1 hour
                    if (age > 60 * 60 * 1000) {
                        await fs.unlink(filePath);
                        winston.debug('Removed old cache file', { filePath });
                    }
                }
            } catch (error) {
                // File might not exist, ignore
            }
        }
    }

    async cleanupLockFiles(sessionDir) {
        // Remove Chrome lock files that might prevent startup
        const lockFiles = [
            'SingletonLock',
            'SingletonCookie',
            'SingletonSocket',
            'lockfile'
        ];

        for (const lockFile of lockFiles) {
            const lockPath = path.join(sessionDir, lockFile);

            try {
                if (await this.pathExists(lockPath)) {
                    await fs.unlink(lockPath);
                    winston.debug('Removed lock file', { lockPath });
                }
            } catch (error) {
                // File might not exist, ignore
            }
        }
    }

    async cleanupOldFiles(directory, pattern, maxAge) {
        try {
            const files = await fs.readdir(directory);

            for (const file of files) {
                if (this.matchPattern(file, pattern)) {
                    const filePath = path.join(directory, file);

                    try {
                        const stats = await fs.stat(filePath);
                        const age = Date.now() - stats.mtime.getTime();

                        if (age > maxAge) {
                            await fs.unlink(filePath);
                            winston.debug('Removed old file', { filePath, age: Math.round(age / (1000 * 60 * 60 * 24)) + ' days' });
                        }
                    } catch (error) {
                        winston.debug('Error checking file age', { filePath, error: error.message });
                    }
                }
            }
        } catch (error) {
            winston.debug('Error reading directory for cleanup', { directory, error: error.message });
        }
    }

    async checkTotalStorageUsage() {
        const sessionsPath = process.env.SESSION_STORAGE_PATH || './sessions';
        const totalSize = await this.getDirectorySize(sessionsPath);

        winston.info('Total session storage usage', {
            path: sessionsPath,
            size: this.formatBytes(totalSize),
            limit: this.formatBytes(this.totalStorageLimit)
        });

        // Alert if approaching limit
        if (totalSize > this.totalStorageLimit * 0.8) {
            winston.warning('Session storage approaching limit', {
                usage: this.formatBytes(totalSize),
                limit: this.formatBytes(this.totalStorageLimit),
                percentage: Math.round((totalSize / this.totalStorageLimit) * 100)
            });

            // TODO: Send alert to Laravel admin
        }

        return totalSize;
    }

    async getDirectorySize(directory) {
        let totalSize = 0;

        try {
            const files = await fs.readdir(directory);

            for (const file of files) {
                const filePath = path.join(directory, file);

                try {
                    const stats = await fs.stat(filePath);

                    if (stats.isDirectory()) {
                        totalSize += await this.getDirectorySize(filePath);
                    } else {
                        totalSize += stats.size;
                    }
                } catch (error) {
                    // Skip files that can't be accessed
                }
            }
        } catch (error) {
            winston.debug('Error calculating directory size', { directory, error: error.message });
        }

        return totalSize;
    }

    async pathExists(filePath) {
        try {
            await fs.access(filePath);
            return true;
        } catch (error) {
            return false;
        }
    }

    matchPattern(filename, pattern) {
        // Simple pattern matching for cleanup
        if (pattern === '*.log') {
            return filename.endsWith('.log');
        }
        return false;
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

    getStorageReport() {
        // This would return detailed storage information
        return {
            maxSessionSize: this.formatBytes(this.maxSessionSize),
            totalStorageLimit: this.formatBytes(this.totalStorageLimit),
            cleanupInterval: '24 hours',
            lastCleanup: new Date().toISOString()
        };
    }
}

module.exports = SessionStorageOptimizer;
