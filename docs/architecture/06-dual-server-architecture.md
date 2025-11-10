# 🔄 Dual-Server WhatsApp Architecture

## Overview

Document ini menjelaskan **Dual-Server Architecture** yang menjadi inovasi utama dari Blazz WhatsApp Business Platform. Arsitektur ini menggunakan dua server terpisah yang berkolaborasi untuk provide WhatsApp service yang robust dan scalable.

---

## 🎯 Konsep Dual-Server

### **Motivasi:**

1. **Process Isolation** - WhatsApp operations yang crash tidak akan down main application
2. **Technology Optimization** - Menggunakan teknologi terbaik untuk setiap tugas (PHP untuk web, Node.js untuk real-time)
3. **Independent Scaling** - Scale WhatsApp services secara terpisah dari main application
4. **Resource Management** - WhatsApp operations yang heavy (session management, real-time events) tidak block web server
5. **Development Flexibility** - Tim WhatsApp service bisa berkembang independently

---

## 🏗️ Arsitektur Detail

```
┌─────────────────────────────────────────────────────────────────────────┐
│                            PRIMARY SERVER                               │
│                        (Laravel - PHP 8.2+)                           │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                    WEB APPLICATION                               │   │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │   │
│  │  │  Vue.js SPA  │  │ Inertia.js   │  │ Tailwind CSS │         │   │
│  │  │ Components   │  │  SSR Bridge  │  │  Styling     │         │   │
│  │  └──────────────┘  └──────────────┘  └──────────────┘         │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                    BUSINESS LOGIC                                │   │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │   │
│  │  │ Controllers  │  │   Services   │  │    Models    │         │   │
│  │  │              │  │              │  │              │         │   │
│  │  │ CampaignCtrl │  │CampaignServ  │  │   Campaign   │         │   │
│  │  │ ContactCtrl  │  │ ContactServ  │  │   Contact    │         │   │
│  │  │ ChatCtrl     │  │  ChatServ    │  │     Chat     │         │   │
│  │  └──────────────┘  └──────────────┘  └──────────────┘         │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │              WHATSAPP HTTP API GATEWAY                         │   │
│  │  ┌─────────────────────────────────────────────────────┐       │   │
│  │  │          /api/whatsapp/* Endpoints                │       │   │
│  │  │                                                     │       │   │
│  │  │  POST /api/whatsapp/send-message                   │       │   │
│  │  │  POST /api/whatsapp/send-template                  │       │   │
│  │  │  GET  /api/whatsapp/session-status                 │       │   │
│  │  │  POST /api/whatsapp/generate-qr                    │       │   │
│  │  │  POST /api/whatsapp/reconnect                       │       │   │
│  │  │  POST /api/whatsapp/switch-provider                │       │   │
│  │  │                                                     │       │   │
│  │  │  Internal HTTP Client → Node.js WhatsApp Service   │       │   │
│  │  └─────────────────────────────────────────────────────┘       │   │
│  └─────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼ HTTP API Communication
                                    │ (Internal Network)
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                          SECONDARY SERVER                             │
│                     (Node.js + TypeScript)                            │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                  EXPRESS.js SERVER                               │   │
│  │  ┌─────────────────────────────────────────────────────┐       │   │
│  │  │             /internal/* Endpoints                  │       │   │
│  │  │                                                     │       │   │
│  │  │  POST /internal/send-message                       │       │   │
│  │  │  POST /internal/send-template                      │       │   │
│  │  │  GET  /internal/session/{id}/status                │       │   │
│  │  │  POST /internal/session/{id}/qr                    │       │   │
│  │  │  POST /internal/session/{id}/reconnect             │       │   │
│  │  │  POST /internal/provider/switch                    │       │   │
│  │  │                                                     │       │   │
│  │  │  WebSocket: /internal/ws                           │       │   │
│  │  └─────────────────────────────────────────────────────┘       │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                  WHATSAPP SERVICE CORE                          │   │
│  │  ┌─────────────────────────────────────────────────────┐       │   │
│  │  │               Provider Manager                     │       │   │
│  │  │  ┌──────────────┐  ┌──────────────┐  ┌──────────┐ │       │   │
│  │  │  │   Meta API   │  │ WhatsApp    │  │Fallback  │ │       │   │
│  │  │  │   Provider   │  │ Web.js Prov │  │ Provider │ │       │   │
│  │  │  └──────────────┘  └──────────────┘  └──────────┘ │       │   │
│  │  └─────────────────────────────────────────────────────┘       │   │
│  │                                                                 │   │
│  │  ┌─────────────────────────────────────────────────────┐       │   │
│  │  │              Session Management                     │       │   │
│  │  │  ┌──────────────┐  ┌──────────────┐  ┌──────────┐ │       │   │
│  │  │  │  QR Code    │  │   Session    │  │Auto-     │ │       │   │
│  │  │  │ Generation  │  │ Persistence  │  │Reconnect │ │       │   │
│  │  │  └──────────────┘  └──────────────┘  └──────────┘ │       │   │
│  │  └─────────────────────────────────────────────────────┘       │   │
│  │                                                                 │   │
│  │  ┌─────────────────────────────────────────────────────┐       │   │
│  │  │              Event System                           │       │   │
│  │  │  ┌──────────────┐  ┌──────────────┐  ┌──────────┐ │       │   │
│  │  │  │   Message    │  │   Status     │  │Session   │ │       │   │
│  │  │  │   Events     │  │   Events     │  │Events    │ │       │   │
│  │  │  └──────────────┘  └──────────────┘  └──────────┘ │       │   │
│  │  └─────────────────────────────────────────────────────┘       │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                EXTERNAL WHATSAPP APIS                            │   │
│  │  ┌─────────────────────────────────────────────────────┐       │   │
│  │  │            WhatsApp Cloud API (Meta)              │       │   │
│  │  │  https://graph.facebook.com/v18.0/                │       │   │
│  │  └─────────────────────────────────────────────────────┘       │   │
│  │                                                                 │   │
│  │  ┌─────────────────────────────────────────────────────┐       │   │
│  │  │              WhatsApp Web (puppeteer)              │       │   │
│  │  │         Browser automation instance               │       │   │
│  │  └─────────────────────────────────────────────────────┘       │   │
│  └─────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 🔄 Communication Flow

### **1. Outgoing Message Flow**

```
1. User Action (Vue.js Component)
   │
   ▼
2. HTTP Request → Laravel Controller
   │   POST /user/chats/send
   │
   ▼
3. Laravel Service Layer
   │   ChatService@sendMessage()
   │
   ▼
4. WhatsApp HTTP API Gateway
   │   POST /api/whatsapp/send-message
   │   Body: { workspace_id, contact_uuid, message }
   │
   ▼
5. HTTP Request to Node.js Service
   │   POST http://localhost:3001/internal/send-message
   │   Headers: { Authorization: "Bearer internal-token" }
   │
   ▼
6. Node.js WhatsApp Service
   │   ProviderManager.getProvider(workspace_id)
   │   Provider.sendMessage(contact, message)
   │
   ▼
7. External WhatsApp API
   │   Meta API OR WhatsApp Web automation
   │
   ▼
8. Response Chain
   │   Node.js → Laravel → Vue.js Component
   │   + Event broadcast untuk real-time update
```

### **2. Incoming Message Flow (Webhook)**

```
1. WhatsApp Webhook
   │   POST /webhook/whatsapp/{identifier}
   │
   ▼
2. Laravel WebhookController
   │   Verify signature
   │   Parse webhook payload
   │
   ▼
3. Process Incoming Message
   │   Create Chat record
   │   Broadcast NewChatEvent
   │
   ▼
4. Optional: Forward to Node.js
   │   POST /internal/incoming-message
   │   Untuk trigger auto-reply atau process khusus
   │
   ▼
5. Real-time Update
   │   Laravel Echo broadcast ke frontend
   │   Vue component update UI
```

---

## 🛠️ Implementation Details

### **Laravel Side: WhatsApp HTTP Client**

```php
// app/Services/WhatsApp/Http/WhatsAppServiceClient.php
namespace App\Services\WhatsApp\Http;

use Illuminate\Support\Facades\Http;

class WhatsAppServiceClient
{
    private string $baseUrl;
    private string $internalToken;

    public function __construct()
    {
        $this->baseUrl = config('whatsapp.node_service_url');
        $this->internalToken = config('whatsapp.internal_token');
    }

    public function sendMessage(string $workspaceId, string $contactUuid, string $message): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->internalToken,
            'Accept' => 'application/json',
        ])->post("{$this->baseUrl}/internal/send-message", [
            'workspace_id' => $workspaceId,
            'contact_uuid' => $contactUuid,
            'message' => $message,
            'type' => 'text',
        ]);

        return $response->json();
    }

    public function getSessionStatus(string $workspaceId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->internalToken,
        ])->get("{$this->baseUrl}/internal/session/{$workspaceId}/status");

        return $response->json();
    }

    public function generateQRCode(string $workspaceId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->internalToken,
        ])->post("{$this->baseUrl}/internal/session/{$workspaceId}/qr");

        return $response->json();
    }
}
```

### **Node.js Side: Express Server**

```typescript
// whatsapp-service/src/server.ts
import express from 'express';
import { WhatsAppServiceManager } from './services/WhatsAppServiceManager';
import { authenticateInternalRequest } from './middleware/auth';

const app = express();
const port = process.env.PORT || 3001;

// Middleware
app.use(express.json());
app.use(authenticateInternalRequest);

// WhatsApp Endpoints
app.post('/internal/send-message', async (req, res) => {
    try {
        const { workspace_id, contact_uuid, message, type } = req.body;

        const whatsappService = WhatsAppServiceManager.getInstance();
        const result = await whatsappService.sendMessage(
            workspace_id,
            contact_uuid,
            message,
            type
        );

        res.json({ success: true, data: result });
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

app.get('/internal/session/:workspaceId/status', async (req, res) => {
    try {
        const { workspaceId } = req.params;

        const whatsappService = WhatsAppServiceManager.getInstance();
        const status = await whatsappService.getSessionStatus(workspaceId);

        res.json({ success: true, data: status });
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

app.post('/internal/session/:workspaceId/qr', async (req, res) => {
    try {
        const { workspaceId } = req.params;

        const whatsappService = WhatsAppServiceManager.getInstance();
        const qrCode = await whatsappService.generateQRCode(workspaceId);

        res.json({ success: true, data: qrCode });
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

// Start server
app.listen(port, () => {
    console.log(`WhatsApp Service running on port ${port}`);
});
```

### **Provider Manager Pattern**

```typescript
// whatsapp-service/src/services/WhatsAppServiceManager.ts
import { MetaApiProvider } from '../providers/MetaApiProvider';
import { WebJsProvider } from '../providers/WebJsProvider';
import { WhatsAppProviderInterface } from '../interfaces/WhatsAppProviderInterface';

export class WhatsAppServiceManager {
    private static instance: WhatsAppServiceManager;
    private providers: Map<string, WhatsAppProviderInterface> = new Map();

    static getInstance(): WhatsAppServiceManager {
        if (!this.instance) {
            this.instance = new WhatsAppServiceManager();
        }
        return this.instance;
    }

    async getProvider(workspaceId: string): Promise<WhatsAppProviderInterface> {
        if (this.providers.has(workspaceId)) {
            return this.providers.get(workspaceId)!;
        }

        // Fetch workspace configuration from Laravel API
        const workspaceConfig = await this.fetchWorkspaceConfig(workspaceId);

        const provider = this.createProvider(workspaceConfig);
        await provider.initialize();

        this.providers.set(workspaceId, provider);
        return provider;
    }

    private createProvider(config: WorkspaceConfig): WhatsAppProviderInterface {
        switch (config.provider_type) {
            case 'meta_api':
                return new MetaApiProvider(config);
            case 'web_js':
                return new WebJsProvider(config);
            default:
                throw new Error(`Unknown provider: ${config.provider_type}`);
        }
    }

    async sendMessage(workspaceId: string, contactUuid: string, message: string, type: string): Promise<any> {
        const provider = await this.getProvider(workspaceId);
        return await provider.sendMessage(contactUuid, message, type);
    }

    async getSessionStatus(workspaceId: string): Promise<any> {
        const provider = await this.getProvider(workspaceId);
        return await provider.getSessionStatus();
    }

    async generateQRCode(workspaceId: string): Promise<string> {
        const provider = await this.getProvider(workspaceId);
        return await provider.generateQRCode();
    }

    private async fetchWorkspaceConfig(workspaceId: string): Promise<WorkspaceConfig> {
        // Call Laravel API to get workspace configuration
        const response = await fetch(`${process.env.LARAVEL_URL}/api/internal/workspace/${workspaceId}/config`, {
            headers: {
                'Authorization': `Bearer ${process.env.INTERNAL_API_TOKEN}`,
            },
        });

        return await response.json();
    }
}
```

---

## 🔧 Configuration & Deployment

### **Environment Variables**

**Laravel (.env):**
```env
# WhatsApp Node.js Service Configuration
WHATSAPP_NODE_SERVICE_URL=http://localhost:3001
WHATSAPP_INTERNAL_TOKEN=your-internal-secret-token
WHATSAPP_WEBHOOK_SECRET=your-webhook-secret

# WhatsApp Provider Configuration
DEFAULT_WHATSAPP_PROVIDER=meta_api
ENABLE_PROVIDER_SWITCHING=true
```

**Node.js (.env):**
```env
# Server Configuration
PORT=3001
NODE_ENV=production

# Laravel Integration
LARAVEL_URL=http://localhost:8000
INTERNAL_API_TOKEN=your-internal-secret-token

# WhatsApp Configuration
META_API_VERSION=v18.0
WEB_JS_HEADLESS=true
PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser

# Session Storage
REDIS_URL=redis://localhost:6379
SESSION_STORAGE_TYPE=redis
```

### **Docker Configuration**

```dockerfile
# whatsapp-service/Dockerfile
FROM node:18-alpine

WORKDIR /app

# Install dependencies
COPY package*.json ./
RUN npm ci --only=production

# Copy source code
COPY . .

# Build TypeScript
RUN npm run build

# Install Puppeteer dependencies
RUN apk add --no-cache \
    chromium \
    nss \
    freetype \
    freetype-dev \
    harfbuzz \
    ca-certificates \
    ttf-freefont

# Set Puppeteer executable path
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser

EXPOSE 3001

CMD ["npm", "start"]
```

```yaml
# docker-compose.yml
version: '3.8'

services:
  # Laravel Application
  app:
    build: .
    ports:
      - "8000:8000"
    environment:
      - WHATSAPP_NODE_SERVICE_URL=http://whatsapp-service:3001
    depends_on:
      - mysql
      - redis
      - whatsapp-service

  # WhatsApp Node.js Service
  whatsapp-service:
    build: ./whatsapp-service
    ports:
      - "3001:3001"
    environment:
      - LARAVEL_URL=http://app:8000
      - REDIS_URL=redis://redis:6379
    depends_on:
      - redis

  # Redis for session storage
  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

  # MySQL Database
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: blazz
    ports:
      - "3306:3306"
```

---

## 📊 Performance & Scalability

### **Scaling Strategy:**

1. **Horizontal Scaling**
   - Multiple Node.js instances behind load balancer
   - Redis session sharing across instances
   - Sticky sessions untuk WhatsApp connections

2. **Resource Optimization**
   - Node.js instance isolated untuk heavy operations
   - Laravel server tetap responsive untuk web requests
   - Separate monitoring dan alerting

3. **Fault Tolerance**
   - Node.js service crash tidak affect main application
   - Automatic restart dengan process manager (PM2)
   - Health checks dan circuit breakers

### **Monitoring & Observability:**

```typescript
// WhatsApp Service Health Check
app.get('/health', async (req, res) => {
    const health = {
        status: 'ok',
        timestamp: new Date().toISOString(),
        services: {
            whatsapp: await checkWhatsAppConnections(),
            redis: await checkRedisConnection(),
            database: await checkDatabaseConnection(),
        },
        metrics: {
            active_sessions: WhatsAppServiceManager.getInstance().getActiveSessionsCount(),
            messages_sent_today: await getMessagesSentToday(),
            uptime: process.uptime(),
        }
    };

    res.json(health);
});
```

---

## 🎯 Benefits & Trade-offs

### **Benefits:**

✅ **Process Isolation** - WhatsApp issues tidak crash main application
✅ **Technology Optimization** - Best tool untuk setiap job
✅ **Independent Scaling** - Scale services berdasarkan kebutuhan
✅ **Development Velocity** - Parallel development tim
✅ **Fault Tolerance** - Graceful degradation saat partial outage
✅ **Performance** - Non-blocking WhatsApp operations

### **Trade-offs:**

❌ **Increased Complexity** - Additional infrastructure components
❌ **Network Latency** - HTTP communication antar servers
❌ **Deployment Overhead** - Two deployments untuk manage
❌ **Debugging Complexity** - Cross-service troubleshooting
❌ **Resource Usage** - Additional server resources

---

## 🔮 Future Enhancements

1. **WebSocket Integration** - Real-time communication antar services
2. **Service Mesh** - Advanced inter-service communication dengan Istio/Linkerd
3. **Event Sourcing** - Persist semua WhatsApp events untuk audit trail
4. **Multi-Region Deployment** - WhatsApp services di multiple geographic locations
5. **AI Integration** - Node.js service untuk AI-powered message processing

---

**Last Updated:** November 10, 2025
**Architecture Version:** v2.0 (Dual-Server)
**Components:** Laravel 12.29.0 + Node.js 18 + TypeScript