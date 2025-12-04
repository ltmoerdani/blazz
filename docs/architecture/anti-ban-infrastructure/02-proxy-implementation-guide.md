# 🔧 Proxy Implementation Guide

**Version:** 1.0  
**Tanggal:** 3 Desember 2025  
**Status:** Technical Implementation Guide  
**Prerequisites:** Baca [05-ip-proxy-anti-detection-analysis.md](./05-ip-proxy-anti-detection-analysis.md) terlebih dahulu

---

## 📋 Overview

Dokumen ini berisi panduan teknis lengkap untuk mengimplementasikan **residential proxy** dan **fingerprint randomization** di WhatsApp Service Node.js.

---

## 🛠️ Phase 1: Puppeteer Stealth (FREE - Do First)

### Step 1.1: Install Dependencies

```bash
cd /Applications/MAMP/htdocs/blazz/whatsapp-service

# Install stealth plugins
npm install puppeteer-extra puppeteer-extra-plugin-stealth puppeteer-extra-plugin-anonymize-ua

# Install additional utilities
npm install user-agents random-useragent
```

### Step 1.2: Create Stealth Configuration

**File:** `whatsapp-service/src/config/stealth.js`

```javascript
/**
 * Puppeteer Stealth Configuration
 * Anti-detection settings for WhatsApp Web automation
 */

const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const AnonymizeUAPlugin = require('puppeteer-extra-plugin-anonymize-ua');

// Apply stealth plugins
puppeteer.use(StealthPlugin());
puppeteer.use(AnonymizeUAPlugin());

/**
 * Viewport options (mobile-like dimensions)
 */
const VIEWPORTS = [
    { width: 375, height: 812, deviceScaleFactor: 3 },  // iPhone X
    { width: 414, height: 896, deviceScaleFactor: 3 },  // iPhone 11 Pro Max
    { width: 360, height: 740, deviceScaleFactor: 3 },  // Samsung Galaxy S10
    { width: 412, height: 869, deviceScaleFactor: 2.625 },  // Pixel 4
    { width: 384, height: 854, deviceScaleFactor: 3 },  // Nexus 5
    { width: 393, height: 873, deviceScaleFactor: 2.75 },  // Pixel 5
    { width: 428, height: 926, deviceScaleFactor: 3 },  // iPhone 12 Pro Max
];

/**
 * User-Agent strings (Android & iOS)
 */
const USER_AGENTS = [
    // Android
    'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36',
    'Mozilla/5.0 (Linux; Android 12; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Mobile Safari/537.36',
    'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36',
    'Mozilla/5.0 (Linux; Android 11; SM-A515F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Mobile Safari/537.36',
    'Mozilla/5.0 (Linux; Android 12; moto g stylus 5G) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Mobile Safari/537.36',
    // iOS (for variety)
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1',
];

/**
 * WebGL Vendor/Renderer combinations (Android devices)
 */
const WEBGL_CONFIGS = [
    { vendor: 'Qualcomm', renderer: 'Adreno (TM) 650' },
    { vendor: 'Qualcomm', renderer: 'Adreno (TM) 640' },
    { vendor: 'ARM', renderer: 'Mali-G78 MP24' },
    { vendor: 'ARM', renderer: 'Mali-G77 MC9' },
    { vendor: 'Qualcomm', renderer: 'Adreno (TM) 730' },
    { vendor: 'Google Inc. (Qualcomm)', renderer: 'ANGLE (Qualcomm, Adreno (TM) 660, OpenGL ES 3.2)' },
];

/**
 * Timezone configurations by country
 */
const TIMEZONES = {
    'ID': ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'],
    'SG': ['Asia/Singapore'],
    'MY': ['Asia/Kuala_Lumpur'],
    'TH': ['Asia/Bangkok'],
    'VN': ['Asia/Ho_Chi_Minh'],
    'PH': ['Asia/Manila'],
    'US': ['America/New_York', 'America/Los_Angeles', 'America/Chicago'],
    'GB': ['Europe/London'],
    'AU': ['Australia/Sydney', 'Australia/Melbourne'],
};

/**
 * Language configurations by country
 */
const LANGUAGES = {
    'ID': ['id-ID', 'id'],
    'SG': ['en-SG', 'en'],
    'MY': ['ms-MY', 'en-MY'],
    'TH': ['th-TH', 'th'],
    'VN': ['vi-VN', 'vi'],
    'PH': ['en-PH', 'fil-PH'],
    'US': ['en-US', 'en'],
    'GB': ['en-GB', 'en'],
    'AU': ['en-AU', 'en'],
};

/**
 * Generate consistent hash from string
 */
function hashCode(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        const char = str.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash = hash & hash;
    }
    return Math.abs(hash);
}

/**
 * Get random item from array using seed
 */
function seededRandom(array, seed) {
    return array[seed % array.length];
}

/**
 * Extract country code from phone number
 */
function getCountryFromPhone(phoneNumber) {
    if (!phoneNumber) return 'ID';
    
    const phone = phoneNumber.replace(/\D/g, '');
    
    if (phone.startsWith('62')) return 'ID';
    if (phone.startsWith('65')) return 'SG';
    if (phone.startsWith('60')) return 'MY';
    if (phone.startsWith('66')) return 'TH';
    if (phone.startsWith('84')) return 'VN';
    if (phone.startsWith('63')) return 'PH';
    if (phone.startsWith('1')) return 'US';
    if (phone.startsWith('44')) return 'GB';
    if (phone.startsWith('61')) return 'AU';
    
    return 'ID';  // Default
}

/**
 * Generate fingerprint for a session
 * Fingerprint is consistent per session (same sessionId = same fingerprint)
 */
function generateFingerprint(sessionId, phoneNumber = null) {
    const seed = hashCode(sessionId);
    const country = getCountryFromPhone(phoneNumber);
    
    return {
        viewport: seededRandom(VIEWPORTS, seed),
        userAgent: seededRandom(USER_AGENTS, seed),
        webgl: seededRandom(WEBGL_CONFIGS, seed),
        timezone: seededRandom(TIMEZONES[country] || TIMEZONES['ID'], seed),
        language: seededRandom(LANGUAGES[country] || LANGUAGES['ID'], seed),
        hardwareConcurrency: [2, 4, 6, 8][seed % 4],
        deviceMemory: [2, 4, 8][seed % 3],
        platform: seed % 3 === 0 ? 'iPhone' : 'Linux armv81',
    };
}

/**
 * Get Puppeteer launch options with stealth
 */
function getLaunchOptions(sessionId, phoneNumber = null, proxyConfig = null) {
    const fingerprint = generateFingerprint(sessionId, phoneNumber);
    
    const args = [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-accelerated-2d-canvas',
        '--no-first-run',
        '--no-zygote',
        '--disable-gpu',
        
        // Anti-detection args
        '--disable-blink-features=AutomationControlled',
        '--disable-features=IsolateOrigins,site-per-process',
        `--window-size=${fingerprint.viewport.width},${fingerprint.viewport.height}`,
        `--lang=${fingerprint.language}`,
        
        // Disable web security for better compatibility
        '--disable-web-security',
        '--disable-features=IsolateOrigins',
        '--disable-site-isolation-trials',
    ];
    
    // Add proxy if configured
    if (proxyConfig && proxyConfig.host) {
        args.push(`--proxy-server=${proxyConfig.host}:${proxyConfig.port || 80}`);
    }
    
    return {
        headless: 'new',
        args,
        defaultViewport: {
            width: fingerprint.viewport.width,
            height: fingerprint.viewport.height,
            deviceScaleFactor: fingerprint.viewport.deviceScaleFactor || 2,
            isMobile: true,
            hasTouch: true,
        },
        ignoreDefaultArgs: ['--enable-automation'],
        executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || undefined,
    };
}

/**
 * Apply fingerprint to page after creation
 */
async function applyFingerprint(page, sessionId, phoneNumber = null) {
    const fingerprint = generateFingerprint(sessionId, phoneNumber);
    
    // Override navigator properties
    await page.evaluateOnNewDocument((fp) => {
        // Hardware concurrency
        Object.defineProperty(navigator, 'hardwareConcurrency', {
            get: () => fp.hardwareConcurrency,
        });
        
        // Device memory
        Object.defineProperty(navigator, 'deviceMemory', {
            get: () => fp.deviceMemory,
        });
        
        // Platform
        Object.defineProperty(navigator, 'platform', {
            get: () => fp.platform,
        });
        
        // Languages
        Object.defineProperty(navigator, 'language', {
            get: () => fp.language,
        });
        Object.defineProperty(navigator, 'languages', {
            get: () => [fp.language, fp.language.split('-')[0]],
        });
        
        // WebGL
        const getParameter = WebGLRenderingContext.prototype.getParameter;
        WebGLRenderingContext.prototype.getParameter = function(parameter) {
            if (parameter === 37445) return fp.webgl.vendor;
            if (parameter === 37446) return fp.webgl.renderer;
            return getParameter.call(this, parameter);
        };
        
        // Timezone
        const originalDateTimeFormat = Intl.DateTimeFormat;
        Intl.DateTimeFormat = function(locales, options) {
            options = options || {};
            options.timeZone = fp.timezone;
            return new originalDateTimeFormat(locales, options);
        };
        
        // Remove automation indicators
        delete navigator.__proto__.webdriver;
        
    }, fingerprint);
    
    // Set user agent
    await page.setUserAgent(fingerprint.userAgent);
    
    // Set timezone
    await page.emulateTimezone(fingerprint.timezone);
    
    return fingerprint;
}

module.exports = {
    puppeteer,  // Export the enhanced puppeteer
    generateFingerprint,
    getLaunchOptions,
    applyFingerprint,
    getCountryFromPhone,
    hashCode,
};
```

### Step 1.3: Update SessionManager

**Update:** `whatsapp-service/src/managers/SessionManager.js`

```javascript
// Replace puppeteer import at top of file
// FROM:
// const { Client, LocalAuth, MessageMedia } = require('whatsapp-web.js');

// TO:
const { Client, LocalAuth, MessageMedia } = require('whatsapp-web.js');
const { 
    puppeteer, 
    getLaunchOptions, 
    applyFingerprint,
    generateFingerprint 
} = require('../config/stealth');

// Then update createClient method:
async createClient(sessionId, workspaceId, phoneNumber = null) {
    // Get stealth launch options
    const launchOptions = getLaunchOptions(sessionId, phoneNumber, null);
    
    const client = new Client({
        authStrategy: new LocalAuth({
            clientId: sessionId,
            dataPath: './sessions'
        }),
        puppeteer: launchOptions,
        // Additional WhatsApp Web.js options
        qrMaxRetries: 3,
        restartOnAuthFail: true,
    });
    
    // Store fingerprint info for logging
    const fingerprint = generateFingerprint(sessionId, phoneNumber);
    this.sessionFingerprints.set(sessionId, fingerprint);
    
    console.log(`[Session ${sessionId}] Created with fingerprint:`, {
        viewport: `${fingerprint.viewport.width}x${fingerprint.viewport.height}`,
        timezone: fingerprint.timezone,
        userAgent: fingerprint.userAgent.substring(0, 50) + '...',
    });
    
    return client;
}
```

---

## 🌐 Phase 2: Proxy Integration

### Step 2.1: Environment Configuration

**Update:** `whatsapp-service/.env`

```env
# Proxy Configuration
PROXY_ENABLED=false
PROXY_PROVIDER=brightdata
# Options: brightdata, oxylabs, smartproxy, iproyal, custom

# Bright Data (Luminati)
BRIGHTDATA_CUSTOMER=your_customer_id
BRIGHTDATA_ZONE=residential
BRIGHTDATA_PASSWORD=your_password

# Oxylabs
OXYLABS_USERNAME=your_username
OXYLABS_PASSWORD=your_password

# Smartproxy
SMARTPROXY_USERNAME=your_username
SMARTPROXY_PASSWORD=your_password

# IPRoyal
IPROYAL_API_KEY=your_api_key

# Custom Proxy (for testing)
CUSTOM_PROXY_HOST=
CUSTOM_PROXY_PORT=
CUSTOM_PROXY_USERNAME=
CUSTOM_PROXY_PASSWORD=
```

### Step 2.2: Create ProxyManager

**File:** `whatsapp-service/src/managers/ProxyManager.js`

```javascript
/**
 * Proxy Manager
 * Manages residential proxy allocation for WhatsApp sessions
 */

const axios = require('axios');
const https = require('https');

class ProxyManager {
    constructor() {
        this.enabled = process.env.PROXY_ENABLED === 'true';
        this.provider = process.env.PROXY_PROVIDER || 'brightdata';
        this.sessionProxyMap = new Map();  // sessionId -> proxyConfig
        
        console.log(`[ProxyManager] Initialized - Enabled: ${this.enabled}, Provider: ${this.provider}`);
    }
    
    /**
     * Check if proxy is enabled
     */
    isEnabled() {
        return this.enabled;
    }
    
    /**
     * Get or assign proxy for a session (sticky)
     */
    async getProxyForSession(sessionId, countryCode = 'ID') {
        if (!this.enabled) {
            return null;
        }
        
        // Check if session already has a proxy
        if (this.sessionProxyMap.has(sessionId)) {
            return this.sessionProxyMap.get(sessionId);
        }
        
        // Acquire new proxy
        const proxy = await this.acquireProxy(countryCode);
        
        if (proxy) {
            this.sessionProxyMap.set(sessionId, proxy);
            console.log(`[ProxyManager] Assigned proxy to session ${sessionId}:`, {
                host: proxy.host,
                country: proxy.country,
            });
        }
        
        return proxy;
    }
    
    /**
     * Acquire proxy from configured provider
     */
    async acquireProxy(countryCode) {
        try {
            switch (this.provider) {
                case 'brightdata':
                    return this.getBrightDataProxy(countryCode);
                case 'oxylabs':
                    return this.getOxylabsProxy(countryCode);
                case 'smartproxy':
                    return this.getSmartproxyProxy(countryCode);
                case 'iproyal':
                    return this.getIPRoyalProxy(countryCode);
                case 'custom':
                    return this.getCustomProxy();
                default:
                    console.warn(`[ProxyManager] Unknown provider: ${this.provider}`);
                    return null;
            }
        } catch (error) {
            console.error(`[ProxyManager] Failed to acquire proxy:`, error.message);
            return null;
        }
    }
    
    /**
     * Bright Data (Luminati) residential proxy
     * Docs: https://brightdata.com/integration/proxy-manager
     */
    getBrightDataProxy(countryCode) {
        const customer = process.env.BRIGHTDATA_CUSTOMER;
        const zone = process.env.BRIGHTDATA_ZONE || 'residential';
        const password = process.env.BRIGHTDATA_PASSWORD;
        
        if (!customer || !password) {
            console.error('[ProxyManager] Bright Data credentials not configured');
            return null;
        }
        
        // Session ID for sticky IP
        const sessionNum = Math.floor(Math.random() * 1000000);
        
        return {
            host: 'brd.superproxy.io',
            port: 22225,
            auth: {
                username: `${customer}-zone-${zone}-country-${countryCode.toLowerCase()}-session-${sessionNum}`,
                password: password,
            },
            type: 'residential',
            country: countryCode,
            provider: 'brightdata',
        };
    }
    
    /**
     * Oxylabs residential proxy
     * Docs: https://developers.oxylabs.io/
     */
    getOxylabsProxy(countryCode) {
        const username = process.env.OXYLABS_USERNAME;
        const password = process.env.OXYLABS_PASSWORD;
        
        if (!username || !password) {
            console.error('[ProxyManager] Oxylabs credentials not configured');
            return null;
        }
        
        // Session ID for sticky IP
        const sessionNum = Math.floor(Math.random() * 1000000);
        
        return {
            host: 'pr.oxylabs.io',
            port: 7777,
            auth: {
                username: `customer-${username}-cc-${countryCode.toLowerCase()}-sessid-${sessionNum}`,
                password: password,
            },
            type: 'residential',
            country: countryCode,
            provider: 'oxylabs',
        };
    }
    
    /**
     * Smartproxy residential proxy
     * Docs: https://dashboard.smartproxy.com/
     */
    getSmartproxyProxy(countryCode) {
        const username = process.env.SMARTPROXY_USERNAME;
        const password = process.env.SMARTPROXY_PASSWORD;
        
        if (!username || !password) {
            console.error('[ProxyManager] Smartproxy credentials not configured');
            return null;
        }
        
        // Session ID for sticky IP
        const sessionNum = Math.floor(Math.random() * 1000000);
        
        return {
            host: 'gate.smartproxy.com',
            port: 7000,
            auth: {
                username: `${username}-country-${countryCode.toLowerCase()}-session-${sessionNum}`,
                password: password,
            },
            type: 'residential',
            country: countryCode,
            provider: 'smartproxy',
        };
    }
    
    /**
     * IPRoyal residential proxy
     * Docs: https://iproyal.com/
     */
    getIPRoyalProxy(countryCode) {
        const apiKey = process.env.IPROYAL_API_KEY;
        
        if (!apiKey) {
            console.error('[ProxyManager] IPRoyal API key not configured');
            return null;
        }
        
        // IPRoyal uses different format
        return {
            host: 'geo.iproyal.com',
            port: 12321,
            auth: {
                username: apiKey,
                password: `country-${countryCode.toLowerCase()}`,
            },
            type: 'residential',
            country: countryCode,
            provider: 'iproyal',
        };
    }
    
    /**
     * Custom proxy (for testing or self-hosted)
     */
    getCustomProxy() {
        const host = process.env.CUSTOM_PROXY_HOST;
        const port = process.env.CUSTOM_PROXY_PORT;
        const username = process.env.CUSTOM_PROXY_USERNAME;
        const password = process.env.CUSTOM_PROXY_PASSWORD;
        
        if (!host || !port) {
            console.error('[ProxyManager] Custom proxy not configured');
            return null;
        }
        
        const config = {
            host,
            port: parseInt(port),
            type: 'custom',
            country: 'CUSTOM',
            provider: 'custom',
        };
        
        if (username && password) {
            config.auth = { username, password };
        }
        
        return config;
    }
    
    /**
     * Release proxy when session ends
     */
    releaseProxy(sessionId) {
        if (this.sessionProxyMap.has(sessionId)) {
            console.log(`[ProxyManager] Released proxy for session ${sessionId}`);
            this.sessionProxyMap.delete(sessionId);
        }
    }
    
    /**
     * Test proxy connectivity
     */
    async testProxy(proxyConfig) {
        if (!proxyConfig) return { success: false, error: 'No proxy config' };
        
        try {
            const proxyUrl = proxyConfig.auth
                ? `http://${proxyConfig.auth.username}:${proxyConfig.auth.password}@${proxyConfig.host}:${proxyConfig.port}`
                : `http://${proxyConfig.host}:${proxyConfig.port}`;
            
            const response = await axios.get('https://api.ipify.org?format=json', {
                proxy: {
                    host: proxyConfig.host,
                    port: proxyConfig.port,
                    auth: proxyConfig.auth,
                },
                timeout: 10000,
            });
            
            return {
                success: true,
                ip: response.data.ip,
                country: proxyConfig.country,
            };
        } catch (error) {
            return {
                success: false,
                error: error.message,
            };
        }
    }
    
    /**
     * Get proxy statistics
     */
    getStats() {
        return {
            enabled: this.enabled,
            provider: this.provider,
            activeSessions: this.sessionProxyMap.size,
            sessions: Array.from(this.sessionProxyMap.entries()).map(([id, config]) => ({
                sessionId: id,
                country: config.country,
                provider: config.provider,
            })),
        };
    }
}

module.exports = ProxyManager;
```

### Step 2.3: Integrate Proxy with SessionManager

**Update:** `whatsapp-service/src/managers/SessionManager.js`

```javascript
// Add at top of file
const ProxyManager = require('./ProxyManager');

// In constructor
constructor() {
    // ... existing code ...
    this.proxyManager = new ProxyManager();
    this.sessionFingerprints = new Map();
}

// Update createClient method
async createClient(sessionId, workspaceId, phoneNumber = null) {
    // Get country code from phone number
    const countryCode = this.getCountryFromPhone(phoneNumber);
    
    // Get proxy if enabled
    const proxyConfig = await this.proxyManager.getProxyForSession(sessionId, countryCode);
    
    // Get stealth launch options with proxy
    const launchOptions = getLaunchOptions(sessionId, phoneNumber, proxyConfig);
    
    // Add proxy authentication if needed
    if (proxyConfig && proxyConfig.auth) {
        launchOptions.args.push(
            `--proxy-server=${proxyConfig.host}:${proxyConfig.port}`
        );
    }
    
    const client = new Client({
        authStrategy: new LocalAuth({
            clientId: sessionId,
            dataPath: './sessions'
        }),
        puppeteer: launchOptions,
        qrMaxRetries: 3,
        restartOnAuthFail: true,
    });
    
    // Handle proxy authentication
    if (proxyConfig && proxyConfig.auth) {
        client.on('puppeteer:browser', async (browser) => {
            const page = await browser.newPage();
            await page.authenticate({
                username: proxyConfig.auth.username,
                password: proxyConfig.auth.password,
            });
        });
    }
    
    // Store metadata
    const fingerprint = generateFingerprint(sessionId, phoneNumber);
    this.sessionFingerprints.set(sessionId, {
        fingerprint,
        proxy: proxyConfig,
        country: countryCode,
    });
    
    console.log(`[Session ${sessionId}] Created with:`, {
        viewport: `${fingerprint.viewport.width}x${fingerprint.viewport.height}`,
        timezone: fingerprint.timezone,
        proxy: proxyConfig ? `${proxyConfig.provider}/${proxyConfig.country}` : 'none',
    });
    
    return client;
}

// Helper method
getCountryFromPhone(phoneNumber) {
    if (!phoneNumber) return 'ID';
    
    const phone = phoneNumber.replace(/\D/g, '');
    
    if (phone.startsWith('62')) return 'ID';
    if (phone.startsWith('65')) return 'SG';
    if (phone.startsWith('60')) return 'MY';
    if (phone.startsWith('66')) return 'TH';
    if (phone.startsWith('84')) return 'VN';
    if (phone.startsWith('63')) return 'PH';
    if (phone.startsWith('1')) return 'US';
    if (phone.startsWith('44')) return 'GB';
    if (phone.startsWith('61')) return 'AU';
    
    return 'ID';
}

// Cleanup on session destroy
async destroySession(sessionId) {
    // ... existing cleanup code ...
    
    // Release proxy
    this.proxyManager.releaseProxy(sessionId);
    this.sessionFingerprints.delete(sessionId);
}
```

### Step 2.4: Add Proxy Status API

**Update:** `whatsapp-service/src/routes/internal.js`

```javascript
// Add proxy status endpoint
router.get('/proxy/status', async (req, res) => {
    try {
        const stats = sessionManager.proxyManager.getStats();
        res.json({
            success: true,
            data: stats,
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message,
        });
    }
});

// Add proxy test endpoint
router.post('/proxy/test', async (req, res) => {
    try {
        const { country = 'ID' } = req.body;
        
        const proxyConfig = await sessionManager.proxyManager.acquireProxy(country);
        if (!proxyConfig) {
            return res.status(400).json({
                success: false,
                error: 'Failed to acquire proxy',
            });
        }
        
        const testResult = await sessionManager.proxyManager.testProxy(proxyConfig);
        
        res.json({
            success: testResult.success,
            data: testResult,
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message,
        });
    }
});
```

---

## 📊 Phase 3: Monitoring & Logging

### Step 3.1: Add Proxy Usage Logging

**File:** `whatsapp-service/src/utils/ProxyLogger.js`

```javascript
const fs = require('fs');
const path = require('path');

class ProxyLogger {
    constructor() {
        this.logDir = path.join(__dirname, '../../logs/proxy');
        this.ensureLogDir();
    }
    
    ensureLogDir() {
        if (!fs.existsSync(this.logDir)) {
            fs.mkdirSync(this.logDir, { recursive: true });
        }
    }
    
    /**
     * Log proxy usage
     */
    logUsage(sessionId, proxyConfig, action, details = {}) {
        const entry = {
            timestamp: new Date().toISOString(),
            sessionId,
            action,  // 'assigned', 'request', 'released', 'error'
            proxy: proxyConfig ? {
                provider: proxyConfig.provider,
                country: proxyConfig.country,
                host: proxyConfig.host,
            } : null,
            ...details,
        };
        
        const filename = `proxy-${new Date().toISOString().split('T')[0]}.log`;
        const filepath = path.join(this.logDir, filename);
        
        fs.appendFileSync(filepath, JSON.stringify(entry) + '\n');
    }
    
    /**
     * Get usage statistics
     */
    async getStats(date = null) {
        const targetDate = date || new Date().toISOString().split('T')[0];
        const filename = `proxy-${targetDate}.log`;
        const filepath = path.join(this.logDir, filename);
        
        if (!fs.existsSync(filepath)) {
            return { date: targetDate, entries: 0, stats: {} };
        }
        
        const content = fs.readFileSync(filepath, 'utf8');
        const lines = content.trim().split('\n').filter(l => l);
        const entries = lines.map(l => JSON.parse(l));
        
        // Calculate stats
        const stats = {
            totalRequests: entries.filter(e => e.action === 'request').length,
            uniqueSessions: new Set(entries.map(e => e.sessionId)).size,
            byProvider: {},
            byCountry: {},
            errors: entries.filter(e => e.action === 'error').length,
        };
        
        entries.forEach(e => {
            if (e.proxy) {
                stats.byProvider[e.proxy.provider] = (stats.byProvider[e.proxy.provider] || 0) + 1;
                stats.byCountry[e.proxy.country] = (stats.byCountry[e.proxy.country] || 0) + 1;
            }
        });
        
        return { date: targetDate, entries: entries.length, stats };
    }
}

module.exports = new ProxyLogger();
```

---

## ✅ Testing Checklist

### Phase 1 Testing (Stealth)

```bash
# 1. Start WhatsApp service
cd whatsapp-service
npm start

# 2. Create a test session
curl -X POST http://localhost:3001/internal/sessions \
  -H "Content-Type: application/json" \
  -d '{"workspace_id": 1, "session_id": "test_stealth_1"}'

# 3. Check fingerprint in logs
# Should show randomized viewport, timezone, user-agent

# 4. Verify anti-detection
# Open browser console in WhatsApp Web page:
# - navigator.webdriver should be undefined
# - navigator.languages should show localized values
```

### Phase 2 Testing (Proxy)

```bash
# 1. Configure proxy in .env
PROXY_ENABLED=true
PROXY_PROVIDER=brightdata
BRIGHTDATA_CUSTOMER=xxx
BRIGHTDATA_PASSWORD=xxx

# 2. Test proxy connectivity
curl -X POST http://localhost:3001/internal/proxy/test \
  -H "Content-Type: application/json" \
  -d '{"country": "ID"}'

# Expected response:
# {"success": true, "data": {"ip": "xxx.xxx.xxx.xxx", "country": "ID"}}

# 3. Create session with proxy
curl -X POST http://localhost:3001/internal/sessions \
  -H "Content-Type: application/json" \
  -d '{"workspace_id": 1, "session_id": "test_proxy_1", "phone_number": "6281234567890"}'

# 4. Verify IP in WhatsApp Web
# The session should show Indonesian residential IP, not server IP
```

---

## ⚠️ Important Notes

### Cost Considerations

| Provider | Cost per GB | Minimum Purchase | Notes |
|----------|-------------|------------------|-------|
| Bright Data | $15 | $500 | Enterprise, best quality |
| Oxylabs | $15 | $300 | Good documentation |
| Smartproxy | $12.5 | $75 | Good starter option |
| IPRoyal | $7 | $5 | Most affordable |

**Estimated Usage per WhatsApp Session:**
- ~50MB/day active usage
- ~1.5GB/month per session
- 30 sessions ≈ 45GB/month ≈ $315-675/month (depending on provider)

### Security Best Practices

1. **Never commit proxy credentials to git**
2. **Use environment variables for all secrets**
3. **Implement IP rotation on ban detection**
4. **Monitor proxy health regularly**
5. **Log all proxy usage for cost tracking**

### Fallback Strategy

```javascript
// If proxy fails, fallback to direct connection
async getProxyOrFallback(sessionId, countryCode) {
    const proxy = await this.getProxyForSession(sessionId, countryCode);
    
    if (!proxy) {
        console.warn(`[ProxyManager] No proxy available, using direct connection`);
        return null;  // Will use server IP
    }
    
    // Test proxy
    const test = await this.testProxy(proxy);
    if (!test.success) {
        console.warn(`[ProxyManager] Proxy test failed, using direct connection`);
        return null;
    }
    
    return proxy;
}
```

---

## 📚 References

- [Puppeteer Extra Stealth](https://github.com/berstend/puppeteer-extra/tree/master/packages/puppeteer-extra-plugin-stealth)
- [Bright Data Documentation](https://brightdata.com/integration/proxy-manager)
- [Oxylabs Documentation](https://developers.oxylabs.io/)
- [WhatsApp Web.js Documentation](https://wwebjs.dev/)
- [05-ip-proxy-anti-detection-analysis.md](./05-ip-proxy-anti-detection-analysis.md)

---

**Author:** AI Assistant  
**Version:** 1.0  
**Status:** Ready for Implementation  
**Estimated Implementation Time:** 2-3 days

