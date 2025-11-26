/**
 * Session Cleanup Service
 * 
 * Handles automatic cleanup of stale/inactive WhatsApp sessions.
 * Prevents resource leaks and maintains system health.
 * 
 * Features:
 * - Detect and cleanup stale sessions (inactive > threshold)
 * - Health monitoring and scoring
 * - Audit logging for all cleanup operations
 * - Safe cleanup (never touches active/authenticated sessions)
 * 
 * @author Blazz Platform
 * @date November 20, 2025
 * @follows docs/architecture/07-development-patterns-guidelines.md
 */

const axios = require('axios');
const fs = require('fs').promises;
const path = require('path');

class SessionCleanupService {
    constructor(sessionManager, logger) {
        this.sessionManager = sessionManager;
        this.logger = logger;
        
        // Configuration
        this.config = {
            // Thresholds (in milliseconds)
            staleThreshold: 7 * 24 * 60 * 60 * 1000,      // 7 days
            disconnectedThreshold: 3 * 24 * 60 * 60 * 1000, // 3 days
            failedThreshold: 24 * 60 * 60 * 1000,          // 1 day
            
            // Laravel API
            laravelUrl: process.env.LARAVEL_URL || 'http://127.0.0.1:8000',
            apiToken: process.env.API_TOKEN,
            
            // Safety settings
            dryRun: process.env.CLEANUP_DRY_RUN === 'true',
            maxCleanupPerRun: 10,
        };
        
        this.logger.info('SessionCleanupService initialized', {
            staleThreshold: `${this.config.staleThreshold / (24 * 60 * 60 * 1000)} days`,
            disconnectedThreshold: `${this.config.disconnectedThreshold / (24 * 60 * 60 * 1000)} days`,
            dryRun: this.config.dryRun
        });
    }

    /**
     * Run full cleanup cycle
     * 
     * @returns {Promise<Object>} Cleanup results
     */
    async runCleanup() {
        const startTime = Date.now();
        
        this.logger.info('🧹 Starting session cleanup cycle');
        
        const results = {
            checked: 0,
            cleaned: 0,
            failed: 0,
            skipped: 0,
            errors: []
        };

        try {
            // 1. Get all sessions from Laravel
            const accounts = await this.fetchAccountsForCleanup();
            results.checked = accounts.length;
            
            this.logger.info(`Found ${accounts.length} accounts to check`);

            // 2. Process each account
            for (const account of accounts) {
                try {
                    const action = await this.processAccount(account);
                    
                    if (action === 'cleaned') {
                        results.cleaned++;
                    } else if (action === 'skipped') {
                        results.skipped++;
                    }
                    
                    // Safety: Don't cleanup too many at once
                    if (results.cleaned >= this.config.maxCleanupPerRun) {
                        this.logger.warn(`Reached max cleanup limit (${this.config.maxCleanupPerRun}), stopping`);
                        break;
                    }
                } catch (error) {
                    results.failed++;
                    results.errors.push({
                        sessionId: account.session_id,
                        error: error.message
                    });
                    
                    this.logger.error(`Failed to process account ${account.session_id}`, {
                        error: error.message
                    });
                }
            }

            // 3. Update health scores for all active sessions
            await this.updateHealthScores();

            const duration = Date.now() - startTime;
            
            this.logger.info('✅ Cleanup cycle completed', {
                duration: `${duration}ms`,
                results
            });

            return results;

        } catch (error) {
            this.logger.error('❌ Cleanup cycle failed', {
                error: error.message,
                stack: error.stack
            });
            throw error;
        }
    }

    /**
     * Fetch accounts that need cleanup check
     * 
     * @returns {Promise<Array>}
     */
    async fetchAccountsForCleanup() {
        try {
            const response = await axios.get(
                `${this.config.laravelUrl}/api/whatsapp/accounts-for-cleanup`,
                {
                    headers: {
                        'Authorization': `Bearer ${this.config.apiToken}`,
                        'Accept': 'application/json'
                    },
                    timeout: 10000
                }
            );

            return response.data.data || [];

        } catch (error) {
            this.logger.error('Failed to fetch accounts from Laravel', {
                error: error.message
            });
            return [];
        }
    }

    /**
     * Process single account for cleanup
     * 
     * @param {Object} account - Account data from Laravel
     * @returns {Promise<string>} Action taken: 'cleaned', 'skipped'
     */
    async processAccount(account) {
        const now = Date.now();
        const lastActivity = account.last_activity_at ? new Date(account.last_activity_at).getTime() : 0;
        const timeSinceActivity = now - lastActivity;

        // Safety checks - NEVER cleanup these
        const safeStatuses = ['authenticated', 'connected'];
        if (safeStatuses.includes(account.status)) {
            // Check if truly active
            const session = this.sessionManager.sessions.get(account.session_id);
            if (session && session.client && session.client.info) {
                await this.logCleanup(account.id, 'health_check', 'skipped', 'Session is active and connected');
                return 'skipped';
            }
        }

        // Determine if should cleanup
        let shouldCleanup = false;
        let reason = '';

        if (account.status === 'failed' && timeSinceActivity > this.config.failedThreshold) {
            shouldCleanup = true;
            reason = `Failed session inactive for ${Math.round(timeSinceActivity / (24 * 60 * 60 * 1000))} days`;
        } else if (account.status === 'disconnected' && timeSinceActivity > this.config.disconnectedThreshold) {
            shouldCleanup = true;
            reason = `Disconnected session inactive for ${Math.round(timeSinceActivity / (24 * 60 * 60 * 1000))} days`;
        } else if (timeSinceActivity > this.config.staleThreshold) {
            shouldCleanup = true;
            reason = `Stale session inactive for ${Math.round(timeSinceActivity / (24 * 60 * 60 * 1000))} days`;
        }

        if (shouldCleanup) {
            if (this.config.dryRun) {
                this.logger.info(`[DRY RUN] Would cleanup session: ${account.session_id}`, { reason });
                await this.logCleanup(account.id, 'cleanup', 'skipped', `[DRY RUN] ${reason}`);
                return 'skipped';
            }

            await this.cleanupSession(account, reason);
            return 'cleaned';
        }

        await this.logCleanup(account.id, 'health_check', 'skipped', 'Session still valid');
        return 'skipped';
    }

    /**
     * Cleanup single session
     * 
     * @param {Object} account
     * @param {string} reason
     */
    async cleanupSession(account, reason) {
        this.logger.info(`🧹 Cleaning up session: ${account.session_id}`, { reason });

        try {
            // 1. Destroy session in SessionManager
            const session = this.sessionManager.sessions.get(account.session_id);
            if (session && session.client) {
                try {
                    await session.client.destroy();
                    this.logger.info(`Destroyed WhatsApp client for ${account.session_id}`);
                } catch (error) {
                    this.logger.warn(`Failed to destroy client: ${error.message}`);
                }
            }

            // 2. Remove from SessionManager
            this.sessionManager.sessions.delete(account.session_id);

            // 3. Delete session files
            await this.deleteSessionFiles(account);

            // 4. Update Laravel database
            await this.updateAccountStatus(account.id, 'failed', 'Cleaned up by automatic cleanup');

            // 5. Log cleanup
            await this.logCleanup(account.id, 'cleanup', 'success', reason);

            this.logger.info(`✅ Successfully cleaned up session: ${account.session_id}`);

        } catch (error) {
            this.logger.error(`Failed to cleanup session ${account.session_id}`, {
                error: error.message,
                stack: error.stack
            });

            await this.logCleanup(account.id, 'cleanup', 'failed', `Error: ${error.message}`);
            throw error;
        }
    }

    /**
     * Delete session files from filesystem
     * 
     * @param {Object} account
     */
    async deleteSessionFiles(account) {
        try {
            const sessionPath = path.join(
                process.cwd(),
                'sessions',
                `workspace_${account.workspace_id}`,
                account.session_id
            );

            // Check if path exists
            try {
                await fs.access(sessionPath);
            } catch {
                this.logger.info(`Session files not found: ${sessionPath}`);
                return;
            }

            // Delete recursively
            await fs.rm(sessionPath, { recursive: true, force: true });
            
            this.logger.info(`Deleted session files: ${sessionPath}`);

        } catch (error) {
            this.logger.error(`Failed to delete session files for ${account.session_id}`, {
                error: error.message
            });
            // Don't throw - continue cleanup even if file deletion fails
        }
    }

    /**
     * Update account status in Laravel
     * 
     * @param {number} accountId
     * @param {string} status
     * @param {string} reason
     */
    async updateAccountStatus(accountId, status, reason) {
        try {
            await axios.patch(
                `${this.config.laravelUrl}/api/whatsapp/accounts/${accountId}/status`,
                {
                    status,
                    reason,
                    last_cleanup_at: new Date().toISOString()
                },
                {
                    headers: {
                        'Authorization': `Bearer ${this.config.apiToken}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    timeout: 10000
                }
            );

            this.logger.info(`Updated account status in Laravel: ${accountId} -> ${status}`);

        } catch (error) {
            this.logger.error(`Failed to update account status in Laravel`, {
                accountId,
                error: error.message
            });
            // Don't throw - cleanup already done
        }
    }

    /**
     * Log cleanup operation to Laravel
     * 
     * @param {number} accountId
     * @param {string} action
     * @param {string} status
     * @param {string} reason
     * @param {Object} metadata
     */
    async logCleanup(accountId, action, status, reason, metadata = {}) {
        try {
            await axios.post(
                `${this.config.laravelUrl}/api/whatsapp/cleanup-logs`,
                {
                    whatsapp_account_id: accountId,
                    action,
                    status,
                    reason,
                    metadata
                },
                {
                    headers: {
                        'Authorization': `Bearer ${this.config.apiToken}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    timeout: 5000
                }
            );

        } catch (error) {
            // Silent fail - logging shouldn't break cleanup
            this.logger.warn(`Failed to log cleanup to Laravel`, {
                accountId,
                action,
                error: error.message
            });
        }
    }

    /**
     * Update health scores for all active sessions
     */
    async updateHealthScores() {
        this.logger.info('📊 Updating health scores for active sessions');

        let updated = 0;

        for (const [sessionId, session] of this.sessionManager.sessions) {
            try {
                const healthScore = this.calculateHealthScore(session);
                
                await axios.patch(
                    `${this.config.laravelUrl}/api/whatsapp/accounts/by-session/${sessionId}`,
                    {
                        health_score: healthScore,
                        last_health_check_at: new Date().toISOString()
                    },
                    {
                        headers: {
                            'Authorization': `Bearer ${this.config.apiToken}`,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        timeout: 5000
                    }
                );

                updated++;

            } catch (error) {
                this.logger.warn(`Failed to update health score for ${sessionId}`, {
                    error: error.message
                });
            }
        }

        this.logger.info(`✅ Updated health scores for ${updated} sessions`);
    }

    /**
     * Calculate health score for session
     * 
     * @param {Object} session
     * @returns {number} Score 0-100
     */
    calculateHealthScore(session) {
        let score = 100;

        // Check client state
        if (!session.client) {
            return 0;
        }

        const state = session.client.state;

        if (state === 'CONNECTED') {
            score = 100;
        } else if (state === 'OPENING') {
            score = 70;
        } else if (state === 'PAIRING') {
            score = 50;
        } else if (state === 'UNPAIRED') {
            score = 30;
        } else {
            score = 10;
        }

        // Deduct for retries
        const retries = session.retries || 0;
        score -= Math.min(retries * 10, 40); // Max 40 point deduction

        // Ensure 0-100 range
        return Math.max(0, Math.min(100, score));
    }

    /**
     * Get cleanup statistics
     * 
     * @returns {Promise<Object>}
     */
    async getStats() {
        try {
            const response = await axios.get(
                `${this.config.laravelUrl}/api/whatsapp/cleanup-stats`,
                {
                    headers: {
                        'Authorization': `Bearer ${this.config.apiToken}`,
                        'Accept': 'application/json'
                    },
                    timeout: 10000
                }
            );

            return response.data.data || {};

        } catch (error) {
            this.logger.error('Failed to fetch cleanup stats', {
                error: error.message
            });
            return {};
        }
    }

    /**
     * Manual cleanup trigger for specific session
     * 
     * @param {string} sessionId
     * @param {string} reason
     * @returns {Promise<boolean>}
     */
    async manualCleanup(sessionId, reason = 'Manual cleanup triggered') {
        this.logger.info(`Manual cleanup requested for: ${sessionId}`, { reason });

        try {
            // Fetch account info
            const response = await axios.get(
                `${this.config.laravelUrl}/api/whatsapp/accounts/by-session/${sessionId}`,
                {
                    headers: {
                        'Authorization': `Bearer ${this.config.apiToken}`,
                        'Accept': 'application/json'
                    },
                    timeout: 10000
                }
            );

            const account = response.data.data;
            
            if (!account) {
                throw new Error('Account not found');
            }

            // Cleanup
            await this.cleanupSession(account, reason);
            
            return true;

        } catch (error) {
            this.logger.error(`Manual cleanup failed for ${sessionId}`, {
                error: error.message
            });
            return false;
        }
    }
}

module.exports = SessionCleanupService;
