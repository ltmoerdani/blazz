const { Client, LocalAuth, MessageMedia } = require('whatsapp-web.js');
const qrcode = require('qrcode');
const crypto = require('crypto');
const axios = require('axios');

// Import handlers and utilities (TASK-NODE-2)
const ChatSyncHandler = require('../handlers/chatSyncHandler');
const WebhookNotifier = require('../../utils/webhookNotifier');

// Import session restoration and auto-reconnect services
const AccountRestoration = require('../services/AccountRestoration');
const AutoReconnect = require('../services/AutoReconnect');

/**
 * Session Manager
 *
 * Manages WhatsApp sessions with full lifecycle handling.
 * Extracted from server.js for better separation of concerns.
 *
 * TASK-ARCH-1: Extract WhatsAppAccountManager to dedicated manager class
 */
class SessionManager {
    constructor(logger) {
        this.logger = logger;
        this.sessions = new Map();
        this.metadata = new Map();
        this.qrCodes = new Map();

        // Initialize webhook notifier and chat sync handler (TASK-NODE-2)
        this.webhookNotifier = new WebhookNotifier(logger);
        this.chatSyncHandler = new ChatSyncHandler(logger, this.webhookNotifier);

        // Initialize session restoration and auto-reconnect services
        this.accountRestoration = new AccountRestoration(this, logger);
        this.autoReconnect = new AutoReconnect(this, logger);
    }

    /**
     * Create a new WhatsApp session
     *
     * @param {string} sessionId - Session identifier
     * @param {number} workspaceId - Workspace ID
     * @param {Object} options - Session options
     * @returns {Promise<Object>} Session creation result
     */
    async createSession(sessionId, workspaceId, options = {}) {
        const { account_id, priority } = options;

        this.logger.info('Creating WhatsApp session', {
            sessionId,
            workspaceId,
            accountId: account_id,
            priority
        });

        try {
            const client = new Client({
                authStrategy: new LocalAuth({
                    clientId: sessionId,
                    dataPath: `./sessions/${workspaceId}/${sessionId}`
                }),
                puppeteer: {
                    headless: true,
                    timeout: 90000, // 90 seconds timeout for browser launch (first launch can be slow)
                    protocolTimeout: 90000, // 90 seconds for DevTools protocol operations
                    args: [
                        '--no-sandbox',
                        '--disable-setuid-sandbox',
                        '--disable-dev-shm-usage',
                        '--disable-accelerated-2d-canvas',
                        '--no-first-run',
                        '--no-zygote',
                        '--disable-gpu',
                        '--disable-web-security',
                        '--disable-features=VizDisplayCompositor'
                    ],
                    executablePath: undefined, // Let puppeteer find chromium automatically
                },
                webVersionCache: {
                    type: 'remote',
                    remotePath: 'https://raw.githubusercontent.com/wppconnect-team/wa-version/main/html/2.2412.54.html',
                }
            });

            // Store session info with account_id
            this.sessions.set(sessionId, client);
            this.metadata.set(sessionId, {
                workspaceId,
                accountId: account_id, // INTEGER ID from database
                status: 'qr_scanning',
                createdAt: new Date(),
                phoneNumber: null,
                lastActivity: new Date()
            });

            // Set up event handlers
            this.setupClientEventHandlers(client, sessionId, workspaceId);

            // Initialize client
            await client.initialize();

            return {
                success: true,
                session_id: sessionId,
                status: 'qr_scanning'
            };

        } catch (error) {
            this.logger.error('Failed to create WhatsApp session', {
                sessionId,
                workspaceId,
                error: error.message,
                stack: error.stack,
                errorDetails: JSON.stringify(error, Object.getOwnPropertyNames(error))
            });

            // Clean up on failure
            this.sessions.delete(sessionId);
            this.metadata.delete(sessionId);

            throw error;
        }
    }

    /**
     * Set up event handlers for WhatsApp client
     *
     * @param {Object} client - WhatsApp Web.js client
     * @param {string} sessionId - Session identifier
     * @param {number} workspaceId - Workspace ID
     */
    setupClientEventHandlers(client, sessionId, workspaceId) {
        // QR Code Event
        client.on('qr', async (qr) => {
            try {
                const sessionMetadata = this.metadata.get(sessionId);
                const now = new Date();

                // Check if QR code already exists and is still valid (5 minutes)
                if (sessionMetadata.qrGeneratedAt) {
                    const timeDiff = (now - sessionMetadata.qrGeneratedAt) / 1000; // in seconds
                    if (timeDiff < 300) { // 5 minutes = 300 seconds
                        this.logger.info('QR code already exists and is still valid, skipping regeneration', {
                            sessionId,
                            workspaceId,
                            timeSinceLastQR: timeDiff
                        });
                        return;
                    }
                }

                this.logger.info('QR code generated', { sessionId, workspaceId });

                const qrCodeData = await qrcode.toDataURL(qr, {
                    width: 256,
                    margin: 2,
                    color: {
                        dark: '#000000',
                        light: '#FFFFFF'
                    }
                });

                this.metadata.set(sessionId, {
                    ...sessionMetadata,
                    status: 'qr_scanning',
                    qrCode: qrCodeData,
                    qrGeneratedAt: new Date()
                });

                // Send QR code to Laravel using single webhook endpoint
                await this.sendToLaravel('qr_code_generated', {
                    workspace_id: workspaceId,
                    session_id: sessionId,
                    qr_code: qrCodeData,
                    expires_in: 300
                });
            } catch (error) {
                this.logger.error('Error in QR event handler', {
                    sessionId,
                    workspaceId,
                    error: error.message,
                    stack: error.stack
                });
            }
        });

        // Authenticated Event
        client.on('authenticated', async () => {
            try {
                this.logger.info('WhatsApp session authenticated', { sessionId, workspaceId });

                this.metadata.set(sessionId, {
                    ...this.metadata.get(sessionId),
                    status: 'authenticated',
                    authenticatedAt: new Date()
                });

                await this.sendToLaravel('session_authenticated', {
                    workspace_id: workspaceId,
                    session_id: sessionId,
                    status: 'authenticated'
                });
            } catch (error) {
                this.logger.error('Error in authenticated event handler', {
                    sessionId,
                    workspaceId,
                    error: error.message
                });
            }
        });

        // Ready Event
        client.on('ready', async () => {
            try {
                const info = client.info;
                this.logger.info('WhatsApp session ready', {
                    sessionId,
                    workspaceId,
                    phoneNumber: info.wid.user
                });

                this.metadata.set(sessionId, {
                    ...this.metadata.get(sessionId),
                    status: 'connected',
                    phoneNumber: info.wid.user,
                    platform: info.platform,
                    connectedAt: new Date()
                });

                await this.sendToLaravel('session_ready', {
                    workspace_id: workspaceId,
                    session_id: sessionId,
                    phone_number: info.wid.user,
                    status: 'connected'
                });

                // TASK-NODE-2: Trigger initial chat sync after session is ready
                const sessionMetadata = this.metadata.get(sessionId);

                this.logger.info('Triggering initial chat sync', {
                    sessionId,
                    workspaceId,
                    accountId: sessionMetadata?.accountId
                });

                // Run sync in background (non-blocking)
                this.chatSyncHandler.syncAllChats(client, sessionMetadata?.accountId, workspaceId, {
                    syncType: 'initial'
                }).then(result => {
                    this.logger.info('Initial chat sync completed', {
                        sessionId,
                        workspaceId,
                        accountId: sessionMetadata?.accountId,
                        result
                    });
                }).catch(error => {
                    this.logger.error('Initial chat sync failed', {
                        sessionId,
                        workspaceId,
                        accountId: sessionMetadata?.accountId,
                        error: error.message,
                        stack: error.stack
                    });
                });

            } catch (error) {
                this.logger.error('Error in ready event handler', {
                    sessionId,
                    workspaceId,
                    error: error.message
                });
            }
        });

        // Disconnected Event
        client.on('disconnected', async (reason) => {
            try {
                this.logger.warning('WhatsApp session disconnected', {
                    sessionId,
                    workspaceId,
                    reason
                });

                const metadata = this.metadata.get(sessionId);
                this.metadata.set(sessionId, {
                    ...metadata,
                    status: 'disconnected',
                    lastDisconnectReason: reason,
                    disconnectedAt: new Date()
                });

                await this.sendToLaravel('session_disconnected', {
                    workspace_id: workspaceId,
                    session_id: sessionId,
                    reason: reason
                });

                // Trigger auto-reconnect for technical disconnects
                await this.autoReconnect.handleDisconnection(sessionId, workspaceId, reason);
            } catch (error) {
                this.logger.error('Error in disconnected event handler', {
                    sessionId,
                    workspaceId,
                    error: error.message
                });
            }
        });

        // Message Event (TASK-NODE-3: Enhanced with group support)
        client.on('message', async (message) => {
            try {
                // Get chat to detect if it's a group
                const chat = await message.getChat();
                const isGroup = chat.isGroup;

                this.logger.debug('Message received', {
                    sessionId,
                    workspaceId,
                    from: message.from,
                    to: message.to,
                    type: message.type,
                    is_group: isGroup
                });

                // Base message data
                const messageData = {
                    id: message.id._serialized,
                    from: message.from, // Will be overridden for groups below
                    to: message.to,
                    body: message.body,
                    timestamp: message.timestamp,
                    from_me: message.fromMe,
                    type: message.type,
                    has_media: message.hasMedia,
                    chat_type: isGroup ? 'group' : 'private'
                };

                // Add group-specific data if it's a group message
                if (isGroup) {
                    // CRITICAL: For group messages, 'from' should be the GROUP ID, not the sender's number
                    console.log('🔍 BEFORE - messageData.from:', messageData.from);
                    console.log('🔍 chat.id._serialized:', chat.id._serialized);
                    messageData.from = chat.id._serialized;
                    console.log('🔍 AFTER - messageData.from:', messageData.from);

                    messageData.group_id = chat.id._serialized;
                    messageData.group_name = chat.name || 'Unnamed Group';

                    // Include participants
                    messageData.participants = chat.participants.map(p => p.id._serialized);

                    // Get sender contact info for group messages
                    if (!message.fromMe) {
                        const contact = await message.getContact();
                        messageData.sender_phone = contact.id.user;
                        messageData.sender_name = contact.pushname || contact.name || contact.id.user;
                    }
                }

                // Download media if message has media
                if (message.hasMedia) {
                    try {
                        this.logger.debug('Downloading media', {
                            sessionId,
                            workspaceId,
                            messageId: message.id._serialized,
                            type: message.type
                        });

                        const media = await message.downloadMedia();

                        if (media) {
                            messageData.media = {
                                data: media.data, // base64 string
                                mimetype: media.mimetype,
                                filename: media.filename || `${message.type}_${Date.now()}`
                            };

                            this.logger.info('Media downloaded successfully', {
                                sessionId,
                                workspaceId,
                                messageId: message.id._serialized,
                                mimetype: media.mimetype,
                                size: media.data.length
                            });
                        }
                    } catch (error) {
                        this.logger.error('Failed to download media', {
                            sessionId,
                            workspaceId,
                            messageId: message.id._serialized,
                            error: error.message
                        });
                        // Continue without media
                    }
                }

                await this.sendToLaravel('message_received', {
                    workspace_id: workspaceId,
                    session_id: sessionId,
                    message: messageData
                });
            } catch (error) {
                this.logger.error('Error in message event handler', {
                    sessionId,
                    workspaceId,
                    error: error.message,
                    stack: error.stack
                });
            }
        });

        // Message Create Event (for sent messages)
        client.on('message_create', async (message) => {
            if (message.fromMe) {
                this.logger.debug('Message sent', {
                    sessionId,
                    workspaceId,
                    to: message.to,
                    id: message.id._serialized
                });

                // Update activity timestamp
                const metadata = this.metadata.get(sessionId);
                if (metadata) {
                    metadata.lastActivity = new Date();
                    this.metadata.set(sessionId, metadata);
                }

                // Broadcast message sent event for real-time UI updates
                await this.sendToLaravel('message_sent', {
                    workspace_id: workspaceId,
                    session_id: sessionId,
                    message: {
                        id: message.id._serialized,
                        to: message.to,
                        body: message.body,
                        timestamp: message.timestamp,
                        type: message.type,
                        has_media: message.hasMedia,
                        status: 'pending'
                    }
                });
            }
        });

        // Message ACK Event (for delivery and read status)
        client.on('message_ack', async (message, ack) => {
            try {
                this.logger.debug('Message ACK received', {
                    sessionId,
                    workspaceId,
                    messageId: message.id._serialized,
                    ack: ack
                });

                // Convert WhatsApp ACK to our status format
                let status;
                switch (ack) {
                    case 1:
                        status = 'pending'; // Message sent to WhatsApp server
                        break;
                    case 2:
                        status = 'sent'; // Message delivered to recipient's phone
                        break;
                    case 3:
                        status = 'delivered'; // Message delivered to recipient
                        break;
                    case 4:
                        status = 'read'; // Message read by recipient
                        break;
                    default:
                        status = 'pending';
                }

                // Broadcast status update to Laravel
                await this.sendToLaravel('message_status_updated', {
                    workspace_id: workspaceId,
                    session_id: sessionId,
                    message_id: message.id._serialized,
                    status: status,
                    ack_level: ack,
                    timestamp: message.timestamp
                });

                // Send specific events for delivery and read
                if (ack === 3) {
                    await this.sendToLaravel('message_delivered', {
                        workspace_id: workspaceId,
                        session_id: sessionId,
                        message_id: message.id._serialized,
                        recipient: message.to,
                        timestamp: message.timestamp
                    });
                } else if (ack === 4) {
                    await this.sendToLaravel('message_read', {
                        workspace_id: workspaceId,
                        session_id: sessionId,
                        message_id: message.id._serialized,
                        recipient: message.to,
                        timestamp: message.timestamp
                    });
                }

            } catch (error) {
                this.logger.error('Error in message_ack event handler', {
                    sessionId,
                    workspaceId,
                    messageId: message.id._serialized,
                    ack: ack,
                    error: error.message
                });
            }
        });

        // Typing Event (when contact starts/stop typing)
        client.on('typing', async (contact, isTyping, chatId) => {
            try {
                this.logger.debug('Typing indicator', {
                    sessionId,
                    workspaceId,
                    contact: contact.id._serialized,
                    isTyping: isTyping,
                    chatId: chatId
                });

                await this.sendToLaravel('typing_indicator', {
                    workspace_id: workspaceId,
                    session_id: sessionId,
                    contact_id: contact.id._serialized,
                    contact_name: contact.pushname || contact.name || contact.id.user,
                    is_typing: isTyping,
                    chat_id: chatId,
                    timestamp: Date.now()
                });

            } catch (error) {
                this.logger.error('Error in typing event handler', {
                    sessionId,
                    workspaceId,
                    contactId: contact.id._serialized,
                    error: error.message
                });
            }
        });

        // Chat State Event (presence changes)
        client.on('chat_state', async (chatState, chat) => {
            try {
                this.logger.debug('Chat state changed', {
                    sessionId,
                    workspaceId,
                    chatId: chat.id._serialized,
                    chatState: chatState.chatState,
                    lastSeen: chatState.lastPresence?.timestamp
                });

                await this.sendToLaravel('chat_state_updated', {
                    workspace_id: workspaceId,
                    session_id: sessionId,
                    chat_id: chat.id._serialized,
                    chat_state: chatState.chatState,
                    last_seen: chatState.lastPresence?.timestamp,
                    timestamp: Date.now()
                });

            } catch (error) {
                this.logger.error('Error in chat_state event handler', {
                    sessionId,
                    workspaceId,
                    chatId: chat.id._serialized,
                    error: error.message
                });
            }
        });
    }

    /**
     * Send message using WhatsApp session
     *
     * @param {string} sessionId - Session identifier
     * @param {string} recipientPhone - Recipient phone number
     * @param {Object|string} message - Message content
     * @param {string} type - Message type ('text' or 'media')
     * @returns {Promise<Object>} Send result
     */
    async sendMessage(sessionId, recipientPhone, message, type = 'text') {
        const client = this.sessions.get(sessionId);
        if (!client) {
            throw new Error('Session not found');
        }

        const metadata = this.metadata.get(sessionId);
        if (!metadata || metadata.status !== 'connected') {
            throw new Error('Session not connected');
        }

        try {
            let result;

            if (type === 'text') {
                result = await client.sendMessage(`${recipientPhone}@c.us`, message);
            } else if (type === 'media' && message.mediaUrl) {
                const media = await MessageMedia.fromUrl(message.mediaUrl, {
                    filename: message.filename || 'media'
                });
                result = await client.sendMessage(`${recipientPhone}@c.us`, media, {
                    caption: message.caption || ''
                });
            }

            this.logger.info('Message sent successfully', {
                sessionId,
                recipientPhone,
                messageId: result?.id?._serialized
            });

            return {
                success: true,
                message_id: result?.id?._serialized,
                timestamp: result?.timestamp
            };

        } catch (error) {
            this.logger.error('Failed to send message', {
                sessionId,
                recipientPhone,
                error: error.message
            });
            throw error;
        }
    }

    /**
     * Get session status
     *
     * @param {string} sessionId - Session identifier
     * @returns {Object|null} Session status or null if not found
     */
    async getSessionStatus(sessionId) {
        const metadata = this.metadata.get(sessionId);
        if (!metadata) {
            return null;
        }

        return {
            session_id: sessionId,
            status: metadata.status,
            phone_number: metadata.phoneNumber,
            workspace_id: metadata.workspaceId,
            connected_at: metadata.connectedAt,
            last_activity: metadata.lastActivity
        };
    }

    /**
     * Disconnect and clean up session
     *
     * @param {string} sessionId - Session identifier
     * @returns {Promise<Object>} Disconnection result
     */
    async disconnectSession(sessionId) {
        const client = this.sessions.get(sessionId);
        if (!client) {
            throw new Error('Session not found');
        }

        try {
            await client.destroy();
            this.sessions.delete(sessionId);
            this.metadata.delete(sessionId);

            this.logger.info('Session disconnected successfully', { sessionId });

            return { success: true };
        } catch (error) {
            this.logger.error('Failed to disconnect session', {
                sessionId,
                error: error.message
            });
            throw error;
        }
    }

    /**
     * Restore existing session without creating new one
     * Attempts to reinitialize existing session from LocalAuth
     *
     * @param {string} sessionId - Session identifier
     * @param {number} workspaceId - Workspace ID
     * @returns {Promise<Object>} Restoration result
     */
    async restoreSession(sessionId, workspaceId) {
        this.logger.info('Attempting to restore existing WhatsApp session', { sessionId, workspaceId });

        try {
            // Check if session already exists in memory
            if (this.sessions.has(sessionId)) {
                const existingClient = this.sessions.get(sessionId);

                // Check if client is still connected and ready
                if (existingClient && existingClient.info && existingClient.info.wid) {
                    this.logger.info('Session already exists and is connected', { sessionId });
                    return {
                        success: true,
                        restored: true,
                        message: 'Session already connected'
                    };
                }
            }

            // Check if LocalAuth data exists for this session
            const fs = require('fs').promises;
            const path = require('path');
            const sessionPath = path.join(process.cwd(), 'sessions', workspaceId.toString(), sessionId);

            try {
                // Check if session folder exists
                await fs.access(sessionPath);

                // Look for session files
                const files = await fs.readdir(sessionPath);
                const hasSessionData = files.some(file =>
                    file.includes('session') || file.includes('LocalAuth') || file.includes('storage')
                );

                if (!hasSessionData) {
                    this.logger.info('No existing session data found, will create new session', { sessionId, workspaceId });
                    return {
                        success: false,
                        restored: false,
                        error: 'No existing session data'
                    };
                }

            } catch (accessError) {
                this.logger.info('Session directory does not exist, will create new session', {
                    sessionId,
                    workspaceId,
                    error: accessError.message
                });
                return {
                    success: false,
                    restored: false,
                    error: 'Session directory not found'
                };
            }

            // Attempt to create new client with existing LocalAuth data
            const client = new Client({
                authStrategy: new LocalAuth({
                    clientId: sessionId,
                    dataPath: `./sessions/${workspaceId}/${sessionId}`
                }),
                puppeteer: {
                    headless: true,
                    timeout: 90000,
                    protocolTimeout: 90000,
                    args: [
                        '--no-sandbox',
                        '--disable-setuid-sandbox',
                        '--disable-dev-shm-usage',
                        '--disable-accelerated-2d-canvas',
                        '--no-first-run',
                        '--no-zygote',
                        '--disable-gpu',
                        '--disable-web-security',
                        '--disable-features=VizDisplayCompositor'
                    ],
                    executablePath: undefined,
                },
                webVersionCache: {
                    type: 'remote',
                    remotePath: 'https://raw.githubusercontent.com/wppconnect-team/wa-version/main/html/2.2412.54.html',
                }
            });

            // Set up event handlers for restoration
            this.setupClientEventHandlers(client, sessionId, workspaceId);

            // Initialize client (this will try to restore from existing auth)
            await client.initialize();

            // Store session info
            this.sessions.set(sessionId, client);
            this.metadata.set(sessionId, {
                workspaceId,
                createdAt: new Date(),
                restored: true // Mark as restored session
            });

            this.logger.info('✅ Session restoration initiated successfully', { sessionId, workspaceId });

            return {
                success: true,
                restored: true,
                message: 'Session restoration initiated'
            };

        } catch (error) {
            this.logger.error('Failed to restore session', {
                sessionId,
                workspaceId,
                error: error.message,
                stack: error.stack
            });

            return {
                success: false,
                restored: false,
                error: error.message
            };
        }
    }

    /**
     * Send data to Laravel webhook endpoint
     *
     * @param {string} eventName - Event name
     * @param {Object} data - Event data
     * @returns {Promise<void>}
     */
    async sendToLaravel(eventName, data) {
        try {
            // Use single webhook endpoint with event-wrapped format
            const endpoint = '/api/whatsapp/webhooks/webjs';

            // Wrap data dengan event type
            const payload = {
                event: eventName,
                data: data
            };

            // Use Unix timestamp in seconds (not milliseconds) to match PHP's time()
            const timestamp = Math.floor(Date.now() / 1000).toString();
            const payloadString = JSON.stringify(payload);
            const signature = crypto
                .createHmac('sha256', process.env.HMAC_SECRET || process.env.API_SECRET)
                .update(timestamp + payloadString)
                .digest('hex');

            await axios.post(`${process.env.LARAVEL_URL}${endpoint}`, payload, {
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-Key': process.env.API_KEY || process.env.LARAVEL_API_TOKEN,
                    'X-Timestamp': timestamp,
                    'X-HMAC-Signature': signature,  // Fixed: Use X-HMAC-Signature instead of X-Signature
                },
                timeout: 10000
            });

            this.logger.debug('Data sent to Laravel successfully', { event: eventName, endpoint });
        } catch (error) {
            this.logger.error('Failed to send data to Laravel', {
                event: eventName,
                error: error.message,
                response: error.response?.data
            });
        }
    }

    /**
     * Get all active sessions
     *
     * @returns {Array} Array of session objects
     */
    getAllSessions() {
        return Array.from(this.sessions.entries()).map(([sessionId, client]) => {
            const metadata = this.metadata.get(sessionId);
            return {
                session_id: sessionId,
                workspace_id: metadata?.workspaceId,
                status: metadata?.status,
                phone_number: metadata?.phoneNumber,
                connected_at: metadata?.connectedAt
            };
        });
    }

    /**
     * Get session metadata
     *
     * @param {string} sessionId - Session identifier
     * @returns {Object|null} Session metadata
     */
    getSessionMetadata(sessionId) {
        return this.metadata.get(sessionId) || null;
    }

    /**
     * Update session metadata
     *
     * @param {string} sessionId - Session identifier
     * @param {Object} updates - Metadata updates
     * @returns {void}
     */
    updateSessionMetadata(sessionId, updates) {
        const existing = this.metadata.get(sessionId);
        if (existing) {
            this.metadata.set(sessionId, { ...existing, ...updates });
        }
    }

    /**
     * Get WhatsApp client by session ID
     *
     * @param {string} sessionId - Session identifier
     * @returns {Object|null} WhatsApp Web.js client or null
     */
    getClient(sessionId) {
        return this.sessions.get(sessionId) || null;
    }

    /**
     * Gracefully shutdown all sessions
     *
     * @returns {Promise<void>}
     */
    async shutdownAllSessions() {
        this.logger.info('Shutting down all sessions...');

        for (const [sessionId, client] of this.sessions) {
            try {
                await client.destroy();
                this.logger.info('Session disconnected during shutdown', { sessionId });
            } catch (error) {
                this.logger.error('Error disconnecting session during shutdown', {
                    sessionId,
                    error: error.message
                });
            }
        }

        // Clear all sessions and metadata
        this.sessions.clear();
        this.metadata.clear();
        this.qrCodes.clear();
    }
    /**
     * Create a new group
     * 
     * @param {string} sessionId - Session identifier
     * @param {string} name - Group name
     * @param {Array<string>} participants - Array of participant phone numbers
     * @returns {Promise<Object>} Result
     */
    async createGroup(sessionId, name, participants) {
        const client = this.sessions.get(sessionId);
        if (!client) throw new Error('Session not found');

        try {
            // Format participants to ID format
            const participantIds = participants.map(p => {
                const phone = p.replace(/\D/g, '');
                return `${phone}@c.us`;
            });

            const result = await client.createGroup(name, participantIds);

            this.logger.info('Group created successfully', {
                sessionId,
                name,
                groupId: result.gid._serialized
            });

            return {
                success: true,
                group_id: result.gid._serialized,
                name: name,
                participants: participantIds
            };
        } catch (error) {
            this.logger.error('Failed to create group', {
                sessionId,
                name,
                error: error.message
            });
            throw error;
        }
    }

    /**
     * Add participants to a group
     */
    async addParticipants(sessionId, groupId, participants) {
        const client = this.sessions.get(sessionId);
        if (!client) throw new Error('Session not found');

        try {
            const chat = await client.getChatById(groupId);
            if (!chat.isGroup) throw new Error('Chat is not a group');

            const participantIds = participants.map(p => {
                const phone = p.replace(/\D/g, '');
                return `${phone}@c.us`;
            });

            await chat.addParticipants(participantIds);

            return { success: true };
        } catch (error) {
            this.logger.error('Failed to add participants', { sessionId, groupId, error: error.message });
            throw error;
        }
    }

    /**
     * Remove participants from a group
     */
    async removeParticipants(sessionId, groupId, participants) {
        const client = this.sessions.get(sessionId);
        if (!client) throw new Error('Session not found');

        try {
            const chat = await client.getChatById(groupId);
            if (!chat.isGroup) throw new Error('Chat is not a group');

            const participantIds = participants.map(p => {
                const phone = p.replace(/\D/g, '');
                return `${phone}@c.us`;
            });

            await chat.removeParticipants(participantIds);

            return { success: true };
        } catch (error) {
            this.logger.error('Failed to remove participants', { sessionId, groupId, error: error.message });
            throw error;
        }
    }

    /**
     * Promote participants to admin
     */
    async promoteParticipants(sessionId, groupId, participants) {
        const client = this.sessions.get(sessionId);
        if (!client) throw new Error('Session not found');

        try {
            const chat = await client.getChatById(groupId);
            if (!chat.isGroup) throw new Error('Chat is not a group');

            const participantIds = participants.map(p => {
                const phone = p.replace(/\D/g, '');
                return `${phone}@c.us`;
            });

            await chat.promoteParticipants(participantIds);

            return { success: true };
        } catch (error) {
            this.logger.error('Failed to promote participants', { sessionId, groupId, error: error.message });
            throw error;
        }
    }

    /**
     * Demote participants to regular users
     */
    async demoteParticipants(sessionId, groupId, participants) {
        const client = this.sessions.get(sessionId);
        if (!client) throw new Error('Session not found');

        try {
            const chat = await client.getChatById(groupId);
            if (!chat.isGroup) throw new Error('Chat is not a group');

            const participantIds = participants.map(p => {
                const phone = p.replace(/\D/g, '');
                return `${phone}@c.us`;
            });

            await chat.demoteParticipants(participantIds);

            return { success: true };
        } catch (error) {
            this.logger.error('Failed to demote participants', { sessionId, groupId, error: error.message });
            throw error;
        }
    }

    /**
     * Update group subject (name)
     */
    async updateGroupSubject(sessionId, groupId, subject) {
        const client = this.sessions.get(sessionId);
        if (!client) throw new Error('Session not found');

        try {
            const chat = await client.getChatById(groupId);
            if (!chat.isGroup) throw new Error('Chat is not a group');

            await chat.setSubject(subject);

            return { success: true };
        } catch (error) {
            this.logger.error('Failed to update group subject', { sessionId, groupId, error: error.message });
            throw error;
        }
    }

    /**
     * Update group description
     */
    async updateGroupDescription(sessionId, groupId, description) {
        const client = this.sessions.get(sessionId);
        if (!client) throw new Error('Session not found');

        try {
            const chat = await client.getChatById(groupId);
            if (!chat.isGroup) throw new Error('Chat is not a group');

            await chat.setDescription(description);

            return { success: true };
        } catch (error) {
            this.logger.error('Failed to update group description', { sessionId, groupId, error: error.message });
            throw error;
        }
    }
}

module.exports = SessionManager;