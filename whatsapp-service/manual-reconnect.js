#!/usr/bin/env node

/**
 * Manual Session Reconnect Script
 * Reconnects existing WhatsApp session without QR code
 */

const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode');

const SESSION_ID = 'webjs_1_1763181942_W0hejiNg';
const WORKSPACE_ID = 1;

console.log('🔄 Starting manual session reconnect...');
console.log(`Session ID: ${SESSION_ID}`);
console.log(`Workspace ID: ${WORKSPACE_ID}`);

const client = new Client({
    authStrategy: new LocalAuth({
        clientId: SESSION_ID,
        dataPath: `./sessions/${WORKSPACE_ID}/${SESSION_ID}`
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
            '--disable-gpu'
        ]
    },
    webVersionCache: {
        type: 'remote',
        remotePath: 'https://raw.githubusercontent.com/wppconnect-team/wa-version/main/html/2.2412.54.html',
    }
});

// QR Code Event (shouldn't happen if session exists)
client.on('qr', async (qr) => {
    console.log('⚠️  QR Code generated (session might be expired)');
    const qrImage = await qrcode.toString(qr, { type: 'terminal', small: true });
    console.log(qrImage);
});

// Authenticated Event
client.on('authenticated', () => {
    console.log('✅ Session authenticated');
});

// Ready Event
client.on('ready', () => {
    console.log('✅ WhatsApp client is ready!');
    console.log(`📱 Connected as: ${client.info.pushname}`);
    console.log(`📞 Phone: ${client.info.wid.user}`);
    
    // Test: Get chats
    client.getChats().then(chats => {
        console.log(`💬 Total chats: ${chats.length}`);
    });
    
    console.log('\n🎉 Session reconnected successfully!');
    console.log('💡 You can now send messages from your application.');
    console.log('⚠️  Keep this process running to maintain connection.\n');
});

// Message Event
client.on('message', async (message) => {
    console.log(`📨 Message received from ${message.from}`);
    console.log(`   Body: ${message.body.substring(0, 50)}...`);
});

// Disconnected Event
client.on('disconnected', (reason) => {
    console.log('❌ Client was disconnected:', reason);
    process.exit(1);
});

// Error Handler
client.on('auth_failure', () => {
    console.error('❌ Authentication failed');
    process.exit(1);
});

// Initialize
console.log('🚀 Initializing WhatsApp client...');
client.initialize();

// Graceful shutdown
process.on('SIGINT', async () => {
    console.log('\n⏹️  Shutting down...');
    await client.destroy();
    process.exit(0);
});
