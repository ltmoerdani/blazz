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

// Import RemoteAuth components (NEW - Week 3 RemoteAuth Migration)
const CustomRemoteAuth = require('../auth/CustomRemoteAuth');
const redisConfig = require('../../config/redis');

// Import MobileActivityMonitor for conflict detection
const MobileActivityMonitor = require('../monitors/MobileActivityMonitor');

/**
 * Session Manager
 *
 * Manages WhatsApp sessions with full lifecycle handling.
 * Extracted from server.js for better separation of concerns.
 *
 * TASK-ARCH-1: Extract WhatsAppAccountManager to dedicated manager class
 * WEEK-3 UPDATE: Support both LocalAuth and RemoteAuth strategies
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

        // Initialize MobileActivityMonitor for conflict detection
        this.mobileActivityMonitor = new MobileActivityMonitor({
            logger: logger,
            webhookUrl: process.env.LARAVEL_WEBHOOK_URL + '/api/v1/whatsapp/webhook',
            activityTimeoutMs: 60000
        });

        // NEW: Auth strategy configuration (Week 3 RemoteAuth Migration)
        this.authStrategy = process.env.AUTH_STRATEGY || 'localauth';
        this.redisStore = null;

        this.logger.info('SessionManager initialized', {
            authStrategy: this.authStrategy,
            redisEnabled: this.authStrategy === 'remoteauth'
        });
    }

    /**
     * Initialize RemoteAuth (if enabled)
     * Must be called before creating sessions with RemoteAuth
     */
    async initializeRemoteAuth() {
        if (this.authStrategy !== 'remoteauth') {
            this.logger.info('RemoteAuth not enabled, skipping Redis initialization');
            return;
        }

        try {
            this.logger.info('Initializing RemoteAuth with Redis...');

            await redisConfig.initialize();
            this.redisStore = redisConfig.getStore();

            const health = await redisConfig.getHealthStatus();
            this.logger.info('RemoteAuth initialized successfully', health);

        } catch (error) {
            this.logger.error('Failed to initialize RemoteAuth:', error.message);
            this.logger.warn('Falling back to LocalAuth');
            this.authStrategy = 'localauth';
        }
    }

    /**
     * Get auth strategy instance for session
     * 
     * @param {string} sessionId - Session identifier
     * @param {number} workspaceId - Workspace ID
     * @returns {Object} Auth strategy instance
     */
    getAuthStrategy(sessionId, workspaceId) {
        if (this.authStrategy === 'remoteauth' && this.redisStore) {
            this.logger.info('Using RemoteAuth strategy', { sessionId });

            return new CustomRemoteAuth({
                clientId: sessionId,
                dataPath: './.wwebjs_auth',
                store: this.redisStore,
                backupSyncIntervalMs: 60000 // Backup every 1 minute
            });
        }

        this.logger.info('Using LocalAuth strategy', { sessionId });

        return new LocalAuth({
            clientId: sessionId,
            dataPath: `./sessions/${workspaceId}/${sessionId}`
        });
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
        
        // PERFORMANCE MONITORING: Track session creation time
        const performanceStart = Date.now();

        this.logger.info('Creating WhatsApp session', {
            sessionId,
            workspaceId,
            accountId: account_id,
            priority,
            authStrategy: this.authStrategy
        });

        try {
            // Get auth strategy (LocalAuth or RemoteAuth)
            const authStrategy = this.getAuthStrategy(sessionId, workspaceId);

            // Determine storage path
            const baseStoragePath = process.env.SESSION_STORAGE_PATH || './sessions';
            const sessionDataPath = `${baseStoragePath}/workspace_${workspaceId}`;

            const client = new Client({
                authStrategy: new LocalAuth({
                    clientId: sessionId,
                    dataPath: sessionDataPath
                }),
                puppeteer: {
                    headless: true,
                    timeout: 15000,  // OPTIMIZED: 15s for fast failure detection
                    protocolTimeout: 15000,  // OPTIMIZED: Faster failure detection
                    args: [
                        '--no-sandbox',
                        '--disable-setuid-sandbox',
                        '--disable-dev-shm-usage',
                        '--disable-gpu',
                        '--single-process',  // OPTIMIZED: Critical for performance
                        '--disable-extensions',  // OPTIMIZED: Reduce overhead
                        '--disable-background-timer-throttling',  // OPTIMIZED: Prevent throttling
                        '--disable-renderer-backgrounding',
                        '--disable-backgrounding-occluded-windows',
                        '--no-zygote',
                        '--no-first-run',
                        '--disable-web-security'
                    ],
                    executablePath: undefined,
                },
                webVersionCache: {
                    type: 'local',  // OPTIMIZED: Cache locally (no download)
                    path: './cache/whatsapp-web'
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
                lastActivity: new Date(),
                performanceStart: performanceStart  // Track for QR perf monitoring
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
        // QR Code Event - OPTIMIZED for fast generation
        client.on('qr', async (qr) => {
            try {
                // OPTIMIZED: Fast QR generation with medium error correction
                const qrCodeData = await qrcode.toDataURL(qr, {
                    width: 256,
                    margin: 2,
                    errorCorrectionLevel: 'M'  // Medium level (faster than default)
                });

                const sessionMetadata = this.metadata.get(sessionId);
                const qrGenTime = Date.now() - (sessionMetadata.performanceStart || Date.now());
                
                // Track QR regeneration count
                const qrCount = (sessionMetadata.qrCount || 0) + 1;
                
                this.metadata.set(sessionId, {
                    ...sessionMetadata,
                    status: 'qr_scanning',
                    qrCode: qrCodeData,
                    qrGeneratedAt: Date.now(),
                    qrCount: qrCount  // Track how many times QR regenerated
                });

                // PERFORMANCE MONITORING: Log QR generation time
                this.logger.info('QR code generated', { 
                    sessionId, 
                    workspaceId,
                    timeMs: qrGenTime,
                    target: 10000,
                    status: qrGenTime < 10000 ? '✅ PASS' : '❌ FAIL',
                    qrCount: qrCount,  // Show regeneration count
                    isRegeneration: qrCount > 1  // Flag if this is a regeneration
                });
                
                // Alert if QR generation is slow
                if (qrGenTime > 15000) {
                    this.logger.error('⚠️ QR generation too slow!', {
                        sessionId,
                        workspaceId,
                        timeMs: qrGenTime,
                        threshold: 15000,
                        action: 'Please investigate performance bottleneck'
                    });
                }

                // OPTIMIZED: Non-blocking webhook (fire-and-forget)
                setImmediate(async () => {
                    const webhookStart = Date.now();
                    try {
                        await this.sendToLaravel('qr_code_generated', {
                            workspace_id: workspaceId,
                            session_id: sessionId,
                            qr_code: qrCodeData,
                            expires_in: 60,  // QR expires in 60s (WhatsApp regenerates it)
                            qr_count: qrCount,  // Track regeneration count
                            is_regeneration: qrCount > 1  // Flag for frontend
                        });
                        
                        const webhookTime = Date.now() - webhookStart;
                        const totalTime = Date.now() - (sessionMetadata.performanceStart || Date.now());
                        
                        this.logger.info('QR webhook delivered', {
                            sessionId,
                            workspaceId,
                            webhookTimeMs: webhookTime,
                            totalTimeMs: totalTime,
                            status: totalTime < 15000 ? '✅ PASS' : '⚠️ SLOW'
                        });
                    } catch (error) {
                        const webhookTime = Date.now() - webhookStart;
                        this.logger.error('Webhook notification failed (non-fatal)', {
                            sessionId,
                            workspaceId,
                            webhookTimeMs: webhookTime,
                            error: error.message
                        });
                    }
                });

            } catch (error) {
                this.logger.error('QR generation failed', {
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

                // OPTIMIZED: Non-blocking webhook
                this.sendToLaravel('session_authenticated', {
                    workspace_id: workspaceId,
                    session_id: sessionId,
                    status: 'authenticated'
                }).catch(error => {
                    this.logger.error('Webhook failed (non-fatal)', {
                        sessionId,
                        error: error.message
                    });
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
                // CRITICAL FIX: Use optimized phone extraction with retry strategy
                const phoneNumber = await this.extractPhoneNumberSafely(client, sessionId);
                
                if (!phoneNumber) {
                    this.logger.error('❌ CRITICAL: Failed to extract phone number after all retries', {
                        sessionId,
                        workspaceId,
                        clientInfoExists: !!client.info,
                        widExists: !!client.info?.wid,
                        timestamp: Date.now()
                    });
                    
                    // Update metadata with error state
                    this.metadata.set(sessionId, {
                        ...this.metadata.get(sessionId),
                        status: 'error',
                        error: 'phone_extraction_failed',
                        lastError: 'Unable to extract phone number'
                    });
                    
                    // Notify Laravel about extraction failure
                    this.sendToLaravel('session_error', {
                        workspace_id: workspaceId,
                        session_id: sessionId,
                        error: 'phone_extraction_failed',
                        message: 'Failed to extract phone number after retries'
                    }).catch(err => {
                        this.logger.error('Failed to send error webhook', { error: err.message });
                    });
                    
                    return;
                }
                
                const info = client.info;
                this.logger.info('✅ WhatsApp session ready with phone number', {
                    sessionId,
                    workspaceId,
                    phoneNumber: phoneNumber,
                    platform: info?.platform,
                    extractionMethod: this.lastExtractionMethod || 'client.info.wid'
                });

                this.metadata.set(sessionId, {
                    ...this.metadata.get(sessionId),
                    status: 'connected',
                    phoneNumber: phoneNumber,
                    platform: info?.platform,
                    connectedAt: new Date(),
                    phoneExtractionMethod: this.lastExtractionMethod
                });

                // CRITICAL FIX: Synchronous webhook with retry and detailed logging
                this.logger.info('📤 Sending session_ready webhook to Laravel', {
                    sessionId,
                    workspaceId,
                    phoneNumber,
                    platform: info?.platform,
                    extractionMethod: this.lastExtractionMethod
                });
                
                try {
                    await this.sendToLaravel('session_ready', {
                        workspace_id: workspaceId,
                        session_id: sessionId,
                        phone_number: phoneNumber,
                        status: 'connected',
                        platform: info?.platform,
                        extraction_method: this.lastExtractionMethod
                    });
                    
                    this.logger.info('✅ session_ready webhook sent successfully', {
                        sessionId,
                        phoneNumber
                    });
                } catch (error) {
                    this.logger.error('❌ CRITICAL: session_ready webhook failed', {
                        sessionId,
                        phoneNumber,
                        error: error.message,
                        stack: error.stack,
                        laravelUrl: process.env.LARAVEL_URL,
                        webhookEndpoint: '/api/whatsapp/webhooks/webjs'
                    });
                    
                    // Send error notification as fallback
                    try {
                        await this.sendToLaravel('session_error', {
                            workspace_id: workspaceId,
                            session_id: sessionId,
                            error: 'webhook_send_failed',
                            message: `Failed to send session_ready: ${error.message}`,
                            phone_number: phoneNumber
                        });
                    } catch (fallbackError) {
                        this.logger.error('❌ Fallback error webhook also failed', {
                            error: fallbackError.message
                        });
                    }
                }

                // OPTIMIZED: Chat sync disabled for faster connection
                // User can trigger manually via API: POST /api/sessions/{id}/sync-chats
                this.logger.info('Session ready. Chat sync available via manual trigger', {
                    sessionId,
                    workspaceId,
                    phoneNumber: phoneNumber,
                    note: 'Auto-sync disabled for performance optimization'
                });

            } catch (error) {
                this.logger.error('Error in ready event handler', {
                    sessionId,
                    workspaceId,
                    error: error.message,
                    stack: error.stack
                });
            }
        });

        // Disconnected Event
        client.on('disconnected', async (reason) => {
            try {
                this.logger.warn('WhatsApp session disconnected', {
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

                // OPTIMIZED: Non-blocking webhook
                this.sendToLaravel('session_disconnected', {
                    workspace_id: workspaceId,
                    session_id: sessionId,
                    reason: reason
                }).catch(error => {
                    this.logger.error('Webhook failed (non-fatal)', {
                        sessionId,
                        error: error.message
                    });
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
                // CRITICAL FIX: Skip non-user messages (notification_template, e2e_notification, etc.)
                // These are WhatsApp system messages that appear when:
                // 1. End-to-end encryption is established with a new contact
                // 2. Chat encryption key changes
                // 3. Other internal WhatsApp notifications
                // See: https://github.com/pedroslopez/whatsapp-web.js/issues/3723
                const systemMessageTypes = [
                    'notification_template',  // E2E encryption setup notification
                    'e2e_notification',        // End-to-end notification
                    'gp2',                     // Group participant add/remove
                    'call_log',                // Call history entry
                    'ciphertext',              // Encrypted message placeholder (not decrypted)
                    'protocol',                // WhatsApp protocol message
                    'revoked'                  // Deleted/revoked message
                ];

                if (systemMessageTypes.includes(message.type)) {
                    this.logger.debug('Skipping system message', {
                        sessionId,
                        workspaceId,
                        from: message.from,
                        type: message.type,
                        reason: 'system_message_filtered'
                    });
                    return; // Skip processing - don't send to Laravel
                }

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
        // CRITICAL FIX: Handle self-sent messages from other devices (mobile)
        client.on('message_create', async (message) => {
            if (message.fromMe) {
                try {
                    // Get device type to determine if message is from mobile
                    const deviceType = message.deviceType || 'unknown';

                    // Track mobile activity for conflict detection
                    if (deviceType !== 'web') {
                        await this.mobileActivityMonitor.trackActivity(
                            sessionId,
                            deviceType,
                            message.id._serialized,
                            workspaceId
                        );
                    }

                    // Get chat to determine if group and get proper chat ID
                    const chat = await message.getChat();
                    const isGroup = chat.isGroup;

                    // CRITICAL: Use chat.id for "from" to ensure proper contact matching
                    // For groups, this is the group ID, not sender ID
                    const chatId = chat.id._serialized;

                    this.logger.info('Self-sent message detected (from mobile or web)', {
                        sessionId,
                        workspaceId,
                        messageId: message.id._serialized,
                        chatId: chatId,
                        isGroup: isGroup,
                        to: message.to,
                        hasBody: !!message.body,
                        deviceType: deviceType,
                        source: deviceType === 'web' ? 'web' : 'mobile'
                    });

                    // Update activity timestamp
                    const metadata = this.metadata.get(sessionId);
                    if (metadata) {
                        metadata.lastActivity = new Date();
                        this.metadata.set(sessionId, metadata);
                    }

                    // Build complete message data with chat context
                    const messageData = {
                        id: message.id._serialized,
                        from: chatId,  // CRITICAL: Use chat ID instead of sender ID
                        to: message.to,
                        body: message.body,
                        timestamp: message.timestamp,
                        type: message.type,
                        has_media: message.hasMedia,
                        status: 'pending',
                        from_me: true,
                        chat_type: isGroup ? 'group' : 'private'
                    };

                    // Add group-specific data if applicable
                    if (isGroup) {
                        messageData.group_id = chatId;
                        messageData.group_name = chat.name || 'Unnamed Group';
                        messageData.participants = chat.participants?.map(p => p.id._serialized) || [];
                    }

                    // Broadcast message sent event for real-time UI updates
                    // This will trigger handleMessageSent in Laravel
                    await this.sendToLaravel('message_sent', {
                        workspace_id: workspaceId,
                        session_id: sessionId,
                        message: messageData,
                        source: 'message_create_event'  // Flag to identify source
                    });

                    this.logger.info('Self-sent message broadcasted to Laravel', {
                        messageId: message.id._serialized,
                        chatId: chatId,
                        isGroup: isGroup
                    });

                } catch (error) {
                    this.logger.error('Error processing self-sent message', {
                        sessionId,
                        workspaceId,
                        messageId: message.id._serialized,
                        error: error.message,
                        stack: error.stack
                    });
                }
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

        // If session not in memory, it's already disconnected or never existed
        // This is not an error - just return success
        if (!client) {
            this.logger.info('Session not in memory (already disconnected or not initialized)', { sessionId });

            // Clean up metadata just in case
            this.metadata.delete(sessionId);

            // Try to cleanup session files on filesystem
            try {
                const fs = require('fs').promises;
                const path = require('path');

                // Try to extract workspace_id from sessionId (format: webjs_{workspaceId}_{timestamp}_{random})
                const parts = sessionId.split('_');
                if (parts.length >= 2 && parts[0] === 'webjs') {
                    const workspaceId = parts[1];
                    const sessionPath = path.join(process.cwd(), 'sessions', workspaceId, sessionId);

                    // Check if path exists and delete
                    try {
                        await fs.access(sessionPath);
                        await fs.rm(sessionPath, { recursive: true, force: true });
                        this.logger.info('Cleaned up session files from filesystem', { sessionId, sessionPath });
                    } catch (err) {
                        // Path doesn't exist or already deleted - this is fine
                        this.logger.debug('Session path not found or already deleted', { sessionId, sessionPath });
                    }
                }
            } catch (cleanupError) {
                // Filesystem cleanup failed but it's not critical
                this.logger.warn('Failed to cleanup session files', {
                    sessionId,
                    error: cleanupError.message
                });
            }

            return {
                success: true,
                message: 'Session already disconnected or not found in memory',
                alreadyDisconnected: true
            };
        }

        try {
            // Destroy the WhatsApp client connection
            await client.destroy();

            // Remove from active sessions
            this.sessions.delete(sessionId);
            this.metadata.delete(sessionId);

            this.logger.info('Session disconnected successfully', { sessionId });

            return {
                success: true,
                message: 'Session disconnected successfully'
            };
        } catch (error) {
            this.logger.error('Failed to disconnect session', {
                sessionId,
                error: error.message
            });

            // Even if destroy fails, remove from sessions map
            this.sessions.delete(sessionId);
            this.metadata.delete(sessionId);

            // Still return success because we've cleaned up our side
            return {
                success: true,
                message: 'Session removed from memory (destroy may have failed)',
                warning: error.message
            };
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
     * Extract phone number safely with optimized retry strategy
     * Based on WhatsApp Web.js production best practices (v1.33.2+)
     * 
     * Strategy:
     * - Initial 2.5s delay (aligns with library's internal 2s initialization)
     * - 15 retries × 500ms = 7.5s total retry window
     * - Fast fallback to window.Store.Conn.me if primary fails
     * - No contact scanning (performance optimization)
     * 
     * @param {Object} client - WhatsApp Web.js client
     * @param {string} sessionId - Session identifier for logging
     * @returns {Promise<string|null>} Phone number or null if extraction fails
     */
    async extractPhoneNumberSafely(client, sessionId) {
        const extractionStart = Date.now();
        const attempts = [];
        
        this.logger.info('🔍 Starting phone number extraction', {
            sessionId,
            clientInfoExists: !!client.info,
            widExists: !!client.info?.wid,
            timestamp: extractionStart
        });
        
        // METHOD 1: Primary - client.info.wid.user with optimized retry
        // Initial delay: 2.5s (aligns with library's hardcoded 2s internal delay)
        this.logger.debug('⏱️ Initial delay: 2.5s for library initialization');
        await new Promise(resolve => setTimeout(resolve, 2500));
        
        // Retry loop: 15 attempts × 500ms intervals = 7.5s
        for (let i = 0; i < 15; i++) {
            const checkTime = Date.now();
            const isAvailable = !!(client.info?.wid?.user);
            
            attempts.push({
                attempt: i + 1,
                timestamp: checkTime,
                elapsed: checkTime - extractionStart,
                available: isAvailable,
                value: client.info?.wid?.user || null
            });
            
            if (isAvailable) {
                const phoneNumber = client.info.wid.user;
                const totalTime = Date.now() - extractionStart;
                
                this.logger.info('✅ Phone number extracted successfully', {
                    sessionId,
                    phoneNumber,
                    method: 'client.info.wid',
                    attempt: i + 1,
                    totalTimeMs: totalTime,
                    attempts: attempts
                });
                
                this.lastExtractionMethod = 'client.info.wid';
                return phoneNumber;
            }
            
            // Wait 500ms before next attempt
            await new Promise(resolve => setTimeout(resolve, 500));
        }
        
        this.logger.warn('⚠️ Primary method failed, trying fallback', {
            sessionId,
            attempts: attempts.length,
            elapsedMs: Date.now() - extractionStart
        });
        
        // METHOD 2: Fallback - Direct Store.Conn.me lookup
        try {
            this.logger.debug('🔄 Attempting Store.Conn.me fallback', { sessionId });
            
            const phoneNumber = await client.pupPage.evaluate(() => {
                try {
                    const me = window.Store?.Conn?.me;
                    if (me && me.user) {
                        return me.user;
                    }
                    
                    // Alternative: Try User store
                    const user = window.Store?.User?.getMaybeMeUser?.();
                    if (user && user.user) {
                        return user.user;
                    }
                    
                    return null;
                } catch (error) {
                    return null;
                }
            });
            
            if (phoneNumber) {
                const totalTime = Date.now() - extractionStart;
                
                this.logger.info('✅ Phone number extracted via fallback', {
                    sessionId,
                    phoneNumber,
                    method: 'Store.Conn.me',
                    totalTimeMs: totalTime
                });
                
                this.lastExtractionMethod = 'Store.Conn.me';
                return phoneNumber;
            }
        } catch (error) {
            this.logger.error('❌ Fallback method failed', {
                sessionId,
                error: error.message,
                stack: error.stack
            });
        }
        
        // All methods failed
        const totalTime = Date.now() - extractionStart;
        this.logger.error('❌ All phone extraction methods failed', {
            sessionId,
            totalTimeMs: totalTime,
            attempts: attempts,
            recommendation: 'Session initialization may have failed - consider restarting'
        });
        
        this.lastExtractionMethod = 'failed';
        return null;
    }

    /**
     * Send data to Laravel webhook endpoint
     * OPTIMIZED: Use WebhookNotifier for consistent HTTP handling
     *
     * @param {string} eventName - Event name
     * @param {Object} data - Event data
     * @returns {Promise<void>}
     */
    async sendToLaravel(eventName, data) {
        try {
            // Use WebhookNotifier for optimized HTTP with keepAlive: false
            const payload = {
                event: eventName,
                data: data
            };

            this.logger.info('🌐 sendToLaravel called', {
                event: eventName,
                dataKeys: Object.keys(data),
                phoneNumber: data.phone_number,
                sessionId: data.session_id,
                laravelUrl: process.env.LARAVEL_URL,
                endpoint: '/api/whatsapp/webhooks/webjs'
            });

            await this.webhookNotifier.notify('/api/whatsapp/webhooks/webjs', payload);

            this.logger.info('✅ sendToLaravel completed', { 
                event: eventName,
                success: true 
            });
        } catch (error) {
            this.logger.error('❌ sendToLaravel FAILED', {
                event: eventName,
                error: error.message,
                stack: error.stack,
                errorCode: error.code,
                errorResponse: error.response?.data
            });
            throw error; // Re-throw to let caller handle it
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