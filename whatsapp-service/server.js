const express = require('express');
const axios = require('axios');
const crypto = require('crypto');
const winston = require('winston');
const { Client, LocalAuth, MessageMedia } = require('whatsapp-web.js');
const qrcode = require('qrcode');

// Import mitigation services
const SessionHealthMonitor = require('./src/services/SessionHealthMonitor');
const SessionStorageOptimizer = require('./src/services/SessionStorageOptimizer');
const WhatsAppRateLimiter = require('./src/services/WhatsAppRateLimiter');
const MemoryManager = require('./src/services/MemoryManager');
const ProfileLockCleaner = require('./src/services/ProfileLockCleaner');
const SessionPool = require('./src/services/SessionPool');
const QRRateLimiter = require('./src/services/QRRateLimiter');
const TimeoutHandler = require('./src/middleware/TimeoutHandler');

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
                status: 'initializing',
                createdAt: new Date(),
                phoneNumber: null,
                lastActivity: new Date()
            });

            // QR Code Event
            client.on('qr', async (qr) => {
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

                // Send QR code to Laravel
                await this.sendToLaravel('/api/whatsapp/events/qr-generated', {
                    workspace_id: workspaceId,
                    session_id: sessionId,
                    qr_code: qrCodeData,
                    expires_in: 300
                });
            });

            // Authenticated Event
            client.on('authenticated', async () => {
                logger.info('WhatsApp session authenticated', { sessionId, workspaceId });

                this.metadata.set(sessionId, {
                    ...this.metadata.get(sessionId),
                    status: 'authenticated',
                    authenticatedAt: new Date()
                });

                await this.sendToLaravel('/api/whatsapp/events/authenticated', {
                    workspace_id: workspaceId,
                    session_id: sessionId,
                    status: 'authenticated'
                });
            });

            // Ready Event
            client.on('ready', async () => {
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

                await this.sendToLaravel('/api/whatsapp/events/session-ready', {
                    workspace_id: workspaceId,
                    session_id: sessionId,
                    phone_number: info.wid.user,
                    status: 'connected'
                });
            });

            // Disconnected Event
            client.on('disconnected', async (reason) => {
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

                await this.sendToLaravel('/api/whatsapp/events/disconnected', {
                    workspace_id: workspaceId,
                    session_id: sessionId,
                    reason: reason
                });
            });

            // Message Event
            client.on('message', async (message) => {
                logger.debug('Message received', {
                    sessionId,
                    workspaceId,
                    from: message.from,
                    to: message.to,
                    type: message.type
                });

                await this.sendToLaravel('/api/whatsapp/webhooks/message-received', {
                    workspace_id: workspaceId,
                    session_id: sessionId,
                    message: {
                        id: message.id._serialized,
                        from: message.from,
                        to: message.to,
                        body: message.body,
                        timestamp: message.timestamp,
                        from_me: message.fromMe,
                        type: message.type,
                        has_media: message.hasMedia
                    }
                });
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
                status: 'initializing'
            };

        } catch (error) {
            logger.error('Failed to create WhatsApp session', {
                sessionId,
                workspaceId,
                error: error.message
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

    async sendToLaravel(endpoint, data) {
        try {
            const timestamp = Date.now().toString();
            const payload = JSON.stringify(data);
            const signature = crypto
                .createHmac('sha256', process.env.HMAC_SECRET || process.env.API_SECRET)
                .update(timestamp + payload)
                .digest('hex');

            await axios.post(`${process.env.LARAVEL_URL}${endpoint}`, data, {
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-Key': process.env.API_KEY || process.env.LARAVEL_API_TOKEN,
                    'X-Timestamp': timestamp,
                    'X-Signature': signature,
                },
                timeout: 10000
            });

            logger.debug('Data sent to Laravel successfully', { endpoint });
        } catch (error) {
            logger.error('Failed to send data to Laravel', {
                endpoint,
                error: error.message
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
        const { workspace_id, session_id, api_key } = req.body;

        // Validate API key
        if (api_key !== (process.env.API_KEY || process.env.LARAVEL_API_TOKEN)) {
            return res.status(401).json({ error: 'Invalid API key' });
        }

        const result = await sessionManager.createSession(session_id, workspace_id);
        res.json(result);
    } catch (error) {
        logger.error('API session creation failed', { error: error.message });
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
app.listen(PORT, () => {
    logger.info(`WhatsApp Service started on port ${PORT}`);
    logger.info(`Laravel backend: ${process.env.LARAVEL_URL}`);
    logger.info(`Environment: ${process.env.NODE_ENV || 'development'}`);
});

module.exports = { app, sessionManager };
