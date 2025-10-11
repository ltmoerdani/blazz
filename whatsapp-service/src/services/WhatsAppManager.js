const { Client, LocalAuth } = require('whatsapp-web.js');
const fs = require('fs').promises;
const path = require('path');
const axios = require('axios');
const logger = require('../utils/logger');

class WhatsAppManager {
  constructor() {
    this.sessionsByWorkspace = new Map(); // workspaceId -> sessionMeta
    this.sessionsById = new Map(); // sessionId -> sessionMeta
    this.maxSessions = parseInt(process.env.MAX_CONCURRENT_SESSIONS || '50', 10);
    this.sessionBasePath = process.env.SESSION_STORAGE_PATH || path.join(__dirname, '../../sessions');
    this.webhookUrl = `${process.env.LARAVEL_URL}${process.env.WEBHOOK_ENDPOINT || '/api/webhooks/whatsapp-webjs'}`;
    this.hmacSecret = process.env.HMAC_SECRET;
  }

  async createSession(workspaceId) {
    if (this.sessionsByWorkspace.size >= this.maxSessions) {
      throw new Error(`Maximum session limit reached (${this.maxSessions})`);
    }

    const existing = this.sessionsByWorkspace.get(workspaceId);
    if (existing && existing.status !== 'disconnected') {
      throw new Error(`Session already exists for workspace ${workspaceId}`);
    }

    const sessionId = `session_${workspaceId}_${Math.random().toString(16).slice(2, 10)}`;
    const dataPath = path.join(this.sessionBasePath, String(workspaceId));

    logger.info('Creating WhatsApp session', { workspace_id: workspaceId, session_id: sessionId });

    const client = new Client({
      authStrategy: new LocalAuth({ clientId: sessionId, dataPath }),
      puppeteer: {
        headless: true,
        args: [
          '--no-sandbox',
          '--disable-setuid-sandbox',
          '--disable-dev-shm-usage',
          '--disable-accelerated-2d-canvas',
          '--no-first-run',
          '--no-zygote',
          '--disable-gpu'
        ]
      }
    });

    const meta = {
      client,
      workspaceId,
      sessionId,
      status: 'initializing',
      createdAt: new Date(),
      lastSeen: new Date()
    };

    this.sessionsByWorkspace.set(workspaceId, meta);
    this.sessionsById.set(sessionId, meta);

    this.setupClientEvents(meta);

    await client.initialize();
    return { success: true, session_id: sessionId, status: 'qr_required' };
  }

  setupClientEvents(meta) {
    const { client, workspaceId, sessionId } = meta;

    client.on('qr', async (qr) => {
      logger.info('QR generated', { workspace_id: workspaceId });
      meta.status = 'qr_required';
      meta.qr = qr;
      await this.sendWebhook(workspaceId, 'session.qr', { session_id: sessionId, qr_code: qr });
    });

    client.on('ready', async () => {
      logger.info('Session ready', { workspace_id: workspaceId });
      meta.status = 'ready';
      meta.qr = null;
      meta.connectedAt = new Date();
      const phone = client.info?.wid?.user || null;
      await this.sendWebhook(workspaceId, 'session.ready', { session_id: sessionId, phone_number: phone });
    });

    client.on('auth_failure', async (msg) => {
      logger.error('Auth failure', { workspace_id: workspaceId, msg });
      meta.status = 'auth_failed';
      await this.sendWebhook(workspaceId, 'session.auth_failed', { session_id: sessionId, reason: msg });
    });

    client.on('disconnected', async (reason) => {
      logger.warn('Session disconnected', { workspace_id: workspaceId, reason });
      meta.status = 'disconnected';
      meta.disconnectedAt = new Date();
      await this.sendWebhook(workspaceId, 'session.disconnected', { session_id: sessionId, reason });
      // Optional cleanup after delay
      setTimeout(() => this.destroySession(workspaceId), 60000);
    });

    client.on('message', async (msg) => {
      meta.lastSeen = new Date();
      await this.sendWebhook(workspaceId, 'message.received', {
        message_id: msg.id?._serialized,
        from: msg.from,
        body: msg.body,
        timestamp: msg.timestamp,
        contact_name: msg._data?.notifyName || msg.from
      });
    });
  }

  async destroySession(workspaceId, cleanupFiles = false) {
    const meta = this.sessionsByWorkspace.get(workspaceId);
    if (!meta) return { success: false, message: 'Session not found' };

    try {
      if (meta.client) {
        try { await meta.client.logout(); } catch (_) {}
        try { await meta.client.destroy(); } catch (_) {}
      }
      if (cleanupFiles) {
        const dir = path.join(this.sessionBasePath, String(workspaceId));
        try { await fs.rm(dir, { recursive: true, force: true }); } catch (_) {}
      }
      this.sessionsByWorkspace.delete(workspaceId);
      this.sessionsById.delete(meta.sessionId);
      await this.sendWebhook(workspaceId, 'session.destroyed', { session_id: meta.sessionId, cleanup_files: cleanupFiles });
      return { success: true };
    } catch (err) {
      logger.error('Destroy session failed', { workspace_id: workspaceId, error: err.message });
      this.sessionsByWorkspace.delete(workspaceId);
      this.sessionsById.delete(meta.sessionId);
      return { success: false, error: err.message };
    }
  }

  getSessionStatusById(sessionId) {
    const meta = this.sessionsById.get(sessionId);
    if (!meta) return { exists: false, status: 'not_found' };
    return {
      exists: true,
      status: meta.status === 'ready' ? 'connected' : meta.status,
      session_id: meta.sessionId,
      workspace_id: meta.workspaceId,
      last_seen: meta.lastSeen || null
    };
  }

  getSessionStatus(workspaceId) {
    const meta = this.sessionsByWorkspace.get(workspaceId);
    if (!meta) return { exists: false, status: 'not_found' };
    return {
      exists: true,
      status: meta.status,
      session_id: meta.sessionId,
      workspace_id: meta.workspaceId,
      last_seen: meta.lastSeen || null
    };
  }

  async sendMessage(workspaceId, phoneNumber, message, options = {}) {
    const meta = this.sessionsByWorkspace.get(workspaceId);
    if (!meta || meta.status !== 'ready') {
      throw new Error('Session not ready');
    }
    const result = await meta.client.sendMessage(phoneNumber, message);
    await this.sendWebhook(workspaceId, 'message.sent', { message_id: result.id?._serialized || null });
    return { success: true, message_id: result.id?._serialized || null };
  }

  async sendWebhook(workspaceId, event, data) {
    const timestamp = Math.floor(Date.now() / 1000);
    const payload = { event, data };
    const body = JSON.stringify(payload);
    const signature = require('../utils/crypto').generateSignature(body, timestamp, this.hmacSecret);

    try {
      await axios.post(this.webhookUrl, payload, {
        headers: {
          'Content-Type': 'application/json',
          'X-Workspace-ID': String(workspaceId),
          'X-Timestamp': String(timestamp),
          'X-HMAC-Signature': signature
        },
        timeout: parseInt(process.env.HEALTH_CHECK_TIMEOUT || '5000', 10)
      });
      logger.info('Webhook sent', { workspace_id: workspaceId, event });
    } catch (err) {
      logger.error('Webhook failed', { workspace_id: workspaceId, error: err.message });
    }
  }
}

module.exports = new WhatsAppManager();
