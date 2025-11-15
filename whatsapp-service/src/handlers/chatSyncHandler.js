const config = require('../../config/logger');
const WebhookNotifier = require('../../utils/webhookNotifier');
const pLimit = require('p-limit');

/**
 * Chat Sync Handler
 *
 * Handles synchronization of WhatsApp chats to Laravel backend.
 * Supports both private and group chats with batch processing and rate limiting.
 *
 * TASK-NODE-1: Chat Sync Handler implementation
 */
class ChatSyncHandler {
    /**
     * @param {Object} logger - Winston logger instance
     * @param {WebhookNotifier} webhookNotifier - Webhook notifier instance
     */
    constructor(logger, webhookNotifier) {
        this.logger = logger;
        this.webhookNotifier = webhookNotifier;

        // Rate limiting configuration
        this.config = {
            batchSize: parseInt(process.env.WHATSAPP_SYNC_BATCH_SIZE) || 50,
            maxConcurrentRequests: parseInt(process.env.WHATSAPP_SYNC_MAX_CONCURRENT) || 3,
            syncWindowDays: parseInt(process.env.WHATSAPP_SYNC_WINDOW_DAYS) || 30,
            maxChatsPerSync: parseInt(process.env.WHATSAPP_SYNC_MAX_CHATS) || 500,
            retryAttempts: parseInt(process.env.WHATSAPP_SYNC_RETRY_ATTEMPTS) || 3,
            retryDelayMs: parseInt(process.env.WHATSAPP_SYNC_RETRY_DELAY_MS) || 1000,
        };

        // p-limit instance for concurrent control
        this.limit = pLimit(this.config.maxConcurrentRequests);

        this.logger.info('ChatSyncHandler initialized', {
            config: this.config
        });
    }

    /**
     * Synchronize all chats for a session
     *
     * @param {Object} client - WhatsApp Web.js client instance
     * @param {number} accountId - WhatsApp Account ID (INTEGER from database)
     * @param {number} workspaceId - Workspace ID
     * @param {Object} options - Sync options
     * @returns {Promise<Object>} Sync results
     */
    async syncAllChats(client, accountId, workspaceId, options = {}) {
        const startTime = Date.now();
        const syncType = options.syncType || 'initial'; // 'initial' or 'incremental'

        this.logger.info('Starting chat sync', {
            account_id: accountId,
            workspace_id: workspaceId,
            sync_type: syncType,
            config: this.config
        });

        try {
            // Fetch all chats from WhatsApp Web.js
            const chats = await this.fetchChats(client, options);

            this.logger.info('Fetched chats from WhatsApp', {
                account_id: accountId,
                total_chats: chats.length,
                sync_type: syncType
            });

            // Apply limits
            const chatsToSync = this.applyLimits(chats);

            this.logger.info('Applying sync limits', {
                account_id: accountId,
                original_count: chats.length,
                limited_count: chatsToSync.length,
                max_chats: this.config.maxChatsPerSync
            });

            // Transform and batch chats
            const chatBatches = await this.prepareChatBatches(chatsToSync, client, accountId, workspaceId);

            this.logger.info('Prepared chat batches', {
                account_id: accountId,
                total_batches: chatBatches.length,
                batch_size: this.config.batchSize
            });

            // Send batches with rate limiting
            const results = await this.sendBatches(chatBatches, accountId, workspaceId);

            const duration = Date.now() - startTime;

            const summary = {
                success: true,
                account_id: accountId,
                workspace_id: workspaceId,
                sync_type: syncType,
                total_chats: chats.length,
                synced_chats: chatsToSync.length,
                total_batches: chatBatches.length,
                successful_batches: results.filter(r => r.success).length,
                failed_batches: results.filter(r => !r.success).length,
                duration_ms: duration,
                timestamp: new Date().toISOString()
            };

            this.logger.info('Chat sync completed', summary);

            return summary;

        } catch (error) {
            const duration = Date.now() - startTime;

            this.logger.error('Chat sync failed', {
                account_id: accountId,
                workspace_id: workspaceId,
                sync_type: syncType,
                error: error.message,
                stack: error.stack,
                duration_ms: duration
            });

            return {
                success: false,
                account_id: accountId,
                workspace_id: workspaceId,
                error: error.message,
                duration_ms: duration
            };
        }
    }

    /**
     * Fetch chats from WhatsApp Web.js client
     *
     * @param {Object} client - WhatsApp Web.js client
     * @param {Object} options - Fetch options
     * @returns {Promise<Array>} Array of chat objects
     */
    async fetchChats(client, options = {}) {
        try {
            const allChats = await client.getChats();

            // Filter chats based on sync window if specified
            if (options.syncWindowDays || this.config.syncWindowDays) {
                const windowDays = options.syncWindowDays || this.config.syncWindowDays;
                const cutoffDate = new Date();
                cutoffDate.setDate(cutoffDate.getDate() - windowDays);
                const cutoffTimestamp = Math.floor(cutoffDate.getTime() / 1000);

                return allChats.filter(chat => {
                    // Include chats with recent activity within the sync window
                    return chat.timestamp >= cutoffTimestamp;
                });
            }

            return allChats;
        } catch (error) {
            this.logger.error('Failed to fetch chats from WhatsApp', {
                error: error.message,
                stack: error.stack
            });
            throw error;
        }
    }

    /**
     * Apply limits to chat list
     *
     * @param {Array} chats - Array of chat objects
     * @returns {Array} Limited array of chats
     */
    applyLimits(chats) {
        // Sort by timestamp descending (most recent first)
        const sortedChats = [...chats].sort((a, b) => b.timestamp - a.timestamp);

        // Limit to max chats per sync
        return sortedChats.slice(0, this.config.maxChatsPerSync);
    }

    /**
     * Prepare chat batches for syncing
     *
     * @param {Array} chats - Array of WhatsApp chat objects
     * @param {Object} client - WhatsApp client instance
     * @param {number} accountId - WhatsApp Account ID (INTEGER from database)
     * @param {number} workspaceId - Workspace ID
     * @returns {Promise<Array>} Array of chat batches
     */
    async prepareChatBatches(chats, client, accountId, workspaceId) {
        // Transform chats to sync format
        const transformedChats = await Promise.all(
            chats.map(chat => this.transformChat(chat, client, accountId, workspaceId))
        );

        // Remove any failed transformations
        const validChats = transformedChats.filter(chat => chat !== null);

        // Split into batches
        const batches = [];
        for (let i = 0; i < validChats.length; i += this.config.batchSize) {
            batches.push(validChats.slice(i, i + this.config.batchSize));
        }

        return batches;
    }

    /**
     * Transform WhatsApp chat to sync format
     *
     * @param {Object} chat - WhatsApp chat object
     * @param {Object} client - WhatsApp client instance
     * @param {number} accountId - WhatsApp Account ID (INTEGER from database)
     * @param {number} workspaceId - Workspace ID
     * @returns {Promise<Object|null>} Transformed chat object or null if failed
     */
    async transformChat(chat, client, accountId, workspaceId) {
        try {
            const isGroup = chat.isGroup;
            const chatId = chat.id._serialized;
            const now = new Date().toISOString(); // Use ISO string for Laravel compatibility

            // Base chat data - using fields that already exist in database
            const chatData = {
                workspace_id: workspaceId,
                whatsapp_account_id: accountId,        // INTEGER (already exists in DB)
                chat_type: isGroup ? 'group' : 'private', // ENUM (already exists in DB)
                provider_type: 'webjs',               // VARCHAR (already exists in DB)
                chat_id: chatId,
                timestamp: chat.timestamp,
                unread_count: chat.unreadCount || 0,
                message_status: 'delivered',          // ENUM (already exists in DB)
                sent_at: now,                         // ISO string for Laravel TIMESTAMP
                delivered_at: now,                    // ISO string for Laravel TIMESTAMP
                read_at: null,                         // NULL for Laravel TIMESTAMP
                is_group: isGroup                     // Keep for backward compatibility
            };

            if (isGroup) {
                // Group chat data
                const groupData = await this.extractGroupData(chat);
                return {
                    ...chatData,
                    group_jid: chatId,                 // Required by Laravel validator (line 78)
                    group_name: groupData.name,
                    group_description: groupData.description,
                    group_participants: groupData.participants,
                    last_message: await this.getLastMessage(chat)
                };
            } else {
                // Private chat data
                const contact = await chat.getContact();
                return {
                    ...chatData,
                    contact_phone: this.normalizePhone(contact.id.user),
                    contact_name: contact.pushname || contact.name || contact.id.user,
                    last_message: await this.getLastMessage(chat)
                };
            }
        } catch (error) {
            this.logger.error('Failed to transform chat', {
                chat_id: chat.id._serialized,
                account_id: accountId,
                workspace_id: workspaceId,
                error: error.message,
                stack: error.stack
            });
            return null;
        }
    }

    /**
     * Extract group data from chat
     *
     * @param {Object} chat - WhatsApp chat object
     * @returns {Promise<Object>} Group data
     */
    async extractGroupData(chat) {
        try {
            const participants = chat.participants.map(participant => ({
                phone: this.normalizePhone(participant.id.user),
                is_admin: participant.isAdmin || false,
                is_superadmin: participant.isSuperAdmin || false
            }));

            return {
                name: chat.name || 'Unnamed Group',
                description: chat.description || '',
                participants: participants
            };
        } catch (error) {
            this.logger.error('Failed to extract group data', {
                chat_id: chat.id._serialized,
                error: error.message
            });
            return {
                name: 'Unnamed Group',
                description: '',
                participants: []
            };
        }
    }

    /**
     * Get last message from chat
     *
     * @param {Object} chat - WhatsApp chat object
     * @returns {Promise<Object|null>} Last message or null
     */
    async getLastMessage(chat) {
        try {
            const messages = await chat.fetchMessages({ limit: 1 });
            if (messages.length === 0) {
                return null;
            }

            const message = messages[0];
            return {
                body: message.body || '',
                timestamp: message.timestamp,
                from_me: message.fromMe
            };
        } catch (error) {
            this.logger.error('Failed to fetch last message', {
                chat_id: chat.id._serialized,
                error: error.message
            });
            return null;
        }
    }

    /**
     * Send chat batches to Laravel with rate limiting
     *
     * @param {Array} batches - Array of chat batches
     * @param {number} accountId - WhatsApp Account ID (INTEGER from database)
     * @param {number} workspaceId - Workspace ID
     * @returns {Promise<Array>} Array of results
     */
    async sendBatches(batches, accountId, workspaceId) {
        const results = [];

        // Use p-limit to control concurrency
        const promises = batches.map((batch, index) =>
            this.limit(async () => {
                this.logger.debug('Sending batch', {
                    account_id: accountId,
                    batch_index: index + 1,
                    total_batches: batches.length,
                    batch_size: batch.length
                });

                try {
                    const result = await this.sendBatchWithRetry(batch, accountId, workspaceId);
                    results.push({
                        success: true,
                        batch_index: index,
                        batch_size: batch.length,
                        result: result
                    });
                    return result;
                } catch (error) {
                    this.logger.error('Batch sync failed', {
                        account_id: accountId,
                        batch_index: index + 1,
                        error: error.message
                    });
                    results.push({
                        success: false,
                        batch_index: index,
                        batch_size: batch.length,
                        error: error.message
                    });
                    throw error;
                }
            })
        );

        // Wait for all batches with allSettled to continue even if some fail
        await Promise.allSettled(promises);

        return results;
    }

    /**
     * Send batch with retry logic
     *
     * @param {Array} batch - Chat batch
     * @param {number} accountId - WhatsApp Account ID (INTEGER from database)
     * @param {number} workspaceId - Workspace ID
     * @returns {Promise<Object>} Response from Laravel
     */
    async sendBatchWithRetry(batch, accountId, workspaceId) {
        let lastError;

        for (let attempt = 1; attempt <= this.config.retryAttempts; attempt++) {
            try {
                const response = await this.webhookNotifier.syncChatBatch(accountId, workspaceId, batch);

                this.logger.debug('Batch sent successfully', {
                    account_id: accountId,
                    attempt: attempt,
                    batch_size: batch.length,
                    response_status: response.status
                });

                return response;
            } catch (error) {
                lastError = error;

                this.logger.warn('Batch send attempt failed', {
                    account_id: accountId,
                    attempt: attempt,
                    max_attempts: this.config.retryAttempts,
                    error: error.message
                });

                // Wait before retry (exponential backoff)
                if (attempt < this.config.retryAttempts) {
                    const delay = this.config.retryDelayMs * Math.pow(2, attempt - 1);
                    await this.sleep(delay);
                }
            }
        }

        throw lastError;
    }

    /**
     * Normalize phone number to E164 format
     *
     * @param {string} phone - Phone number
     * @returns {string} Normalized phone number
     */
    normalizePhone(phone) {
        // Remove all non-numeric characters
        let normalized = phone.replace(/\D/g, '');

        // Ensure it starts with country code
        if (!normalized.startsWith('+')) {
            normalized = '+' + normalized;
        }

        return normalized;
    }

    /**
     * Sleep for specified milliseconds
     *
     * @param {number} ms - Milliseconds to sleep
     * @returns {Promise<void>}
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

module.exports = ChatSyncHandler;
