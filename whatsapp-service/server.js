const express = require('express');
const axios = require('axios');
const crypto = require('crypto');
const winston = require('winston');
const dotenv = require('dotenv');
const { Client, LocalAuth, MessageMedia } = require('whatsapp-web.js');
const qrcode = require('qrcode');

// Load environment variables
dotenv.config();

// Import mitigation services
const SessionHealthMonitor = require('./src/services/SessionHealthMonitor');
const SessionStorageOptimizer = require('./src/services/SessionStorageOptimizer');
const WhatsAppRateLimiter = require('./src/services/WhatsAppRateLimiter');
const MemoryManager = require('./src/services/MemoryManager');
const ProfileLockCleaner = require('./src/services/ProfileLockCleaner');
const SessionPool = require('./src/services/SessionPool');
const QRRateLimiter = require('./src/services/QRRateLimiter');
const TimeoutHandler = require('./src/middleware/TimeoutHandler');

// Import handlers and utilities (TASK-NODE-2)
const ChatSyncHandler = require('./src/handlers/chatSyncHandler');
const WebhookNotifier = require('./utils/webhookNotifier');

// Import session restoration and auto-reconnect services
const SessionRestoration = require('./src/services/SessionRestoration');
const AutoReconnect = require('./src/services/AutoReconnect');

const app = express();
const PORT = process.env.PORT || 3000;

// Configure Winston logger
const logger = winston.createLogger({
    level: process.env.LOG_LEVEL || 'info',
    format: winston.format.combine(
        winston.format.timestamp(),
        winston.format.errors({ stack: true }),
        winston.format.json()
    ),
    defaultMeta: { service: 'whatsapp-service' },
    transports: [
        new winston.transports.File({
            filename: process.env.LOG_FILE || './logs/whatsapp-service.log',
            maxsize: process.env.LOG_MAX_SIZE || 10485760, // 10MB
            maxFiles: process.env.LOG_MAX_FILES || 7
        }),
        new winston.transports.Console({
            format: winston.format.simple()
        })
    ],
});

// Middleware
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));

// Session Manager
class WhatsAppSessionManager {
    constructor() {
        this.sessions = new Map();
        this.metadata = new Map();
        this.qrCodes = new Map();

        // Initialize webhook notifier and chat sync handler (TASK-NODE-2)
        this.webhookNotifier = new WebhookNotifier(logger);
        this.chatSyncHandler = new ChatSyncHandler(logger, this.webhookNotifier);

        // Initialize session restoration and auto-reconnect services
        this.sessionRestoration = new SessionRestoration(this, logger);
        this.autoReconnect = new AutoReconnect(this, logger);
    }

    async createSession(sessionId, workspaceId) {
        logger.info('Creating WhatsApp session', { sessionId, workspaceId });

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

            // Store session info
            this.sessions.set(sessionId, client);
            this.metadata.set(sessionId, {
                workspaceId,
                status: 'qr_scanning',
                createdAt: new Date(),
                phoneNumber: null,
                lastActivity: new Date()
            });

            // QR Code Event
            client.on('qr', async (qr) => {
                try {
                    logger.info('QR code generated', { sessionId, workspaceId });

                    const qrCodeData = await qrcode.toDataURL(qr, {
                        width: 256,
                        margin: 2,
                        color: {
                            dark: '#000000',
                            light: '#FFFFFF'
                        }
                    });

                    this.metadata.set(sessionId, {
                        ...this.metadata.get(sessionId),
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
                    logger.error('Error in QR event handler', {
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
                    logger.info('WhatsApp session authenticated', { sessionId, workspaceId });

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
                    logger.error('Error in authenticated event handler', {
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
                    logger.info('WhatsApp session ready', {
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
                    logger.info('Triggering initial chat sync', {
                        sessionId,
                        workspaceId
                    });

                    // Run sync in background (non-blocking)
                    this.chatSyncHandler.syncAllChats(client, sessionId, workspaceId, {
                        syncType: 'initial'
                    }).then(result => {
                        logger.info('Initial chat sync completed', {
                            sessionId,
                            workspaceId,
                            result
                        });
                    }).catch(error => {
                        logger.error('Initial chat sync failed', {
                            sessionId,
                            workspaceId,
                            error: error.message,
                            stack: error.stack
                        });
                    });

                } catch (error) {
                    logger.error('Error in ready event handler', {
                        sessionId,
                        workspaceId,
                        error: error.message
                    });
                }
            });

            // Disconnected Event
            client.on('disconnected', async (reason) => {
                try {
                    logger.warning('WhatsApp session disconnected', {
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
                    logger.error('Error in disconnected event handler', {
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

                    logger.debug('Message received', {
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
                        from: message.from,
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
                        messageData.group_id = chat.id._serialized;
                        messageData.group_name = chat.name || 'Unnamed Group';

                        // Get sender contact info for group messages
                        if (!message.fromMe) {
                            const contact = await message.getContact();
                            messageData.sender_phone = contact.id.user;
                            messageData.sender_name = contact.pushname || contact.name || contact.id.user;
                        }
                    }

                    await this.sendToLaravel('message_received', {
                        workspace_id: workspaceId,
                        session_id: sessionId,
                        message: messageData
                    });
                } catch (error) {
                    logger.error('Error in message event handler', {
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
                    logger.debug('Message sent', {
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
                }
            });

            // Initialize client
            await client.initialize();

            return {
                success: true,
                session_id: sessionId,
                status: 'qr_scanning'
            };

        } catch (error) {
            logger.error('Failed to create WhatsApp session', {
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

            logger.info('Message sent successfully', {
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
            logger.error('Failed to send message', {
                sessionId,
                recipientPhone,
                error: error.message
            });
            throw error;
        }
    }

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

    async disconnectSession(sessionId) {
        const client = this.sessions.get(sessionId);
        if (!client) {
            throw new Error('Session not found');
        }

        try {
            await client.destroy();
            this.sessions.delete(sessionId);
            this.metadata.delete(sessionId);

            logger.info('Session disconnected successfully', { sessionId });

            return { success: true };
        } catch (error) {
            logger.error('Failed to disconnect session', {
                sessionId,
                error: error.message
            });
            throw error;
        }
    }

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

            logger.debug('Data sent to Laravel successfully', { event: eventName, endpoint });
        } catch (error) {
            logger.error('Failed to send data to Laravel', {
                event: eventName,
                error: error.message,
                response: error.response?.data
            });
        }
    }

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
}

// Initialize session manager
const sessionManager = new WhatsAppSessionManager();

// Initialize mitigation services
const sessionHealthMonitor = new SessionHealthMonitor(sessionManager);
const sessionStorageOptimizer = new SessionStorageOptimizer(sessionManager);
const whatsAppRateLimiter = new WhatsAppRateLimiter();
const memoryManager = new MemoryManager(sessionManager);
const profileLockCleaner = new ProfileLockCleaner(sessionManager);
const sessionPool = new SessionPool(sessionManager);
const qrRateLimiter = new QRRateLimiter();
const timeoutHandler = new TimeoutHandler();

// Enhance session manager with pool functionality
sessionPool.enhanceSessionManager();

// Apply timeout middleware
app.use(timeoutHandler.middleware());

// API Routes

// Health check endpoint
app.get('/health', (req, res) => {
    const sessions = sessionManager.getAllSessions();
    const healthySessions = sessions.filter(s => s.status === 'connected').length;

    res.json({
        status: 'healthy',
        uptime: process.uptime(),
        sessions: {
            total: sessions.length,
            connected: healthySessions,
            disconnected: sessions.length - healthySessions
        },
        memory: {
            used: Math.round(process.memoryUsage().heapUsed / 1024 / 1024),
            total: Math.round(process.memoryUsage().heapTotal / 1024 / 1024),
            unit: 'MB'
        },
        timestamp: new Date().toISOString()
    });
});

// Create session
app.post('/api/sessions', async (req, res) => {
    try {
        const { workspace_id, session_id, api_key, priority } = req.body;

        // Validate API key
        if (api_key !== (process.env.API_KEY || process.env.LARAVEL_API_TOKEN)) {
            return res.status(401).json({ error: 'Invalid API key' });
        }

        // Pass options object with priority
        const options = {
            priority: priority || 'normal'
        };
        
        const result = await sessionManager.createSession(session_id, workspace_id, options);
        res.json(result);
    } catch (error) {
        logger.error('API session creation failed', { 
            error: error.message,
            stack: error.stack,
            errorDetails: JSON.stringify(error, Object.getOwnPropertyNames(error))
        });
        res.status(500).json({ error: error.message });
    }
});

// Get session status
app.get('/api/sessions/:sessionId/status', async (req, res) => {
    try {
        const { sessionId } = req.params;
        const { workspace_id, api_key } = req.query;

        if (api_key !== (process.env.API_KEY || process.env.LARAVEL_API_TOKEN)) {
            return res.status(401).json({ error: 'Invalid API key' });
        }

        const status = await sessionManager.getSessionStatus(sessionId);
        if (status) {
            res.json(status);
        } else {
            res.status(404).json({ error: 'Session not found' });
        }
    } catch (error) {
        logger.error('API get session status failed', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

// Send message
app.post('/api/messages/send', async (req, res) => {
    try {
        const { session_id, workspace_id, recipient_phone, message, type, api_key } = req.body;

        if (api_key !== (process.env.API_KEY || process.env.LARAVEL_API_TOKEN)) {
            return res.status(401).json({ error: 'Invalid API key' });
        }

        const result = await sessionManager.sendMessage(session_id, recipient_phone, {
            text: message,
            type: type || 'text'
        });

        res.json(result);
    } catch (error) {
        logger.error('API send message failed', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

// Disconnect session
app.delete('/api/sessions/:sessionId', async (req, res) => {
    try {
        const { sessionId } = req.params;
        const { workspace_id, api_key } = req.body;

        if (api_key !== (process.env.API_KEY || process.env.LARAVEL_API_TOKEN)) {
            return res.status(401).json({ error: 'Invalid API key' });
        }

        const result = await sessionManager.disconnectSession(sessionId);
        res.json(result);
    } catch (error) {
        logger.error('API disconnect session failed', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

// Reconnect session
app.post('/api/sessions/:sessionId/reconnect', async (req, res) => {
    try {
        const { sessionId } = req.params;
        const { workspace_id, api_key } = req.body;

        if (api_key !== (process.env.API_KEY || process.env.LARAVEL_API_TOKEN)) {
            return res.status(401).json({ error: 'Invalid API key' });
        }

        // Disconnect existing session first
        await sessionManager.disconnectSession(sessionId);

        // Create new session
        const result = await sessionManager.createSession(sessionId, workspace_id);
        res.json(result);
    } catch (error) {
        logger.error('API reconnect session failed', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

// Regenerate QR code
app.post('/api/sessions/:sessionId/regenerate-qr', async (req, res) => {
    try {
        const { sessionId } = req.params;
        const { workspace_id, api_key } = req.body;

        if (api_key !== (process.env.API_KEY || process.env.LARAVEL_API_TOKEN)) {
            return res.status(401).json({ error: 'Invalid API key' });
        }

        // Disconnect and reconnect to generate new QR
        await sessionManager.disconnectSession(sessionId);
        const result = await sessionManager.createSession(sessionId, workspace_id);

        res.json({
            qr_code: result.qr_code,
            expires_in: 300
        });
    } catch (error) {
        logger.error('API regenerate QR failed', { error: error.message });
        res.status(500).json({ error: error.message });
    }
});

// Graceful shutdown
process.on('SIGTERM', async () => {
    logger.info('SIGTERM received, shutting down gracefully');

    // Disconnect all sessions
    for (const [sessionId, client] of sessionManager.sessions) {
        try {
            await client.destroy();
            logger.info('Session disconnected during shutdown', { sessionId });
        } catch (error) {
            logger.error('Error disconnecting session during shutdown', {
                sessionId,
                error: error.message
            });
        }
    }

    process.exit(0);
});

process.on('SIGINT', async () => {
    logger.info('SIGINT received, shutting down gracefully');

    // Disconnect all sessions
    for (const [sessionId, client] of sessionManager.sessions) {
        try {
            await client.destroy();
            logger.info('Session disconnected during shutdown', { sessionId });
        } catch (error) {
            logger.error('Error disconnecting session during shutdown', {
                sessionId,
                error: error.message
            });
        }
    }

    process.exit(0);
});

// Start server
app.listen(PORT, async () => {
    logger.info(`WhatsApp Service started on port ${PORT}`);
    logger.info(`Laravel backend: ${process.env.LARAVEL_URL}`);
    logger.info(`Environment: ${process.env.NODE_ENV || 'development'}`);

    // Restore all active sessions from database on startup
    logger.info('🔄 Initiating session restoration...');
    try {
        const result = await sessionManager.sessionRestoration.restoreAllSessions();

        if (result.success) {
            logger.info(`✅ Session restoration completed: ${result.restored} restored, ${result.failed} failed, ${result.total || 0} total`);
        } else {
            logger.error('❌ Session restoration failed:', result.error);
        }
    } catch (error) {
        logger.error('❌ Session restoration error:', {
            error: error.message,
            stack: error.stack
        });
    }
});

module.exports = { app, sessionManager };
