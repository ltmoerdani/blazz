# 📢 WhatsApp Broadcast System Documentation

**Version:** 1.0.0
**Last Updated:** November 14, 2025
**Architecture Version:** v2.0 (Dual-Server Hybrid)
**Target Audience:** Developers, System Architects, DevOps Engineers

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Campaign Types](#campaign-types)
4. [System Components](#system-components)
5. [Database Schema](#database-schema)
6. [API Endpoints](#api-endpoints)
7. [WhatsApp Providers](#whatsapp-providers)
8. [Implementation Guides](#implementation-guides)
9. [Performance & Scaling](#performance--scaling)
10. [Monitoring & Debugging](#monitoring--debugging)
11. [Troubleshooting](#troubleshooting)

---

## 🎯 Overview

### **What is WhatsApp Broadcast System?**

The WhatsApp Broadcast System is a **hybrid messaging platform** that enables businesses to send bulk WhatsApp messages to their contacts through two primary approaches:

1. **Template-Based Campaigns** - Using pre-approved WhatsApp templates
2. **Direct Message Campaigns** - Creating messages on-the-fly without template approval

### **Key Features**

- ✅ **Dual WhatsApp Provider Support** - Meta API + WhatsApp Web JS
- ✅ **Hybrid Campaign Creation** - Template or direct mode
- ✅ **Automatic Provider Selection** - WebJS priority, Meta API fallback
- ✅ **Advanced Scheduling** - Immediate or scheduled sending
- ✅ **Contact Group Targeting** - Specific groups or all contacts
- ✅ **Real-time Analytics** - Delivery status, read receipts, failure tracking
- ✅ **Retry Logic** - Configurable retry intervals and max attempts
- ✅ **Session Management** - Multiple WhatsApp sessions with health monitoring

### **Business Value**

- 🚀 **Faster Campaign Creation** - Direct mode eliminates template approval delays
- 💰 **Cost Optimization** - WebJS provider reduces Meta API usage fees
- 📈 **Higher Deliverability** - WebJS has better spam detection avoidance
- 🔄 **Provider Flexibility** - Switch between providers based on availability
- 📊 **Performance Monitoring** - Real-time campaign health metrics
- 🛡️ **Reliability** - Automatic failover between providers

---

## 🏗️ Architecture

### **High-Level Architecture**

```
┌─────────────────────────────────────────────────────────────────┐
│                    Frontend Layer                               │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │  Campaign UI    │  │  Template UI    │  │  Analytics UI   │ │
│  │ (Vue.js +       │  │ (Vue.js +       │  │ (Vue.js +       │ │
│  │  Inertia.js)    │  │  Inertia.js)    │  │  Inertia.js)    │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Laravel Backend                              │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │   Controllers   │  │    Services     │  │      Jobs       │ │
│  │                 │  │                 │  │                 │ │
│  │ CampaignController│  │CampaignService │  │SendCampaignJob │ │
│  │TemplateController│  │TemplateService │  │RetryCampaignJob│ │
│  │                 │  │WhatsAppService │  │                 │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                   Node.js WhatsApp Service                      │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │   WebJS Provider│  │   Meta API      │  │  Session Mgmt   │ │
│  │                 │  │   Provider      │  │                 │ │
│  │  Multiple       │  │  Business       │  │  Health Checks  │ │
│  │  Sessions       │  │  API            │  │  Failover       │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                    WhatsApp Platform                             │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │  WhatsApp Web   │  │  WhatsApp       │  │  WhatsApp       │ │
│  │     JS          │  │  Business API   │  │  Sessions       │ │
│  │                 │  │                 │  │                 │ │
│  │  Direct         │  │  Template       │  │  Multiple       │ │
│  │  Connection     │  │  Messaging      │  │  Numbers        │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

### **Data Flow**

1. **Campaign Creation** → Laravel Frontend → Backend Validation → Database Storage
2. **Campaign Processing** → Scheduler → Queue System → Job Processing
3. **Provider Selection** → Session Health Check → Optimal Provider Selection
4. **Message Sending** → Node.js Service → WhatsApp API → Message Delivery
5. **Status Tracking** → Webhook Processing → Database Updates → Analytics

### **Service Boundaries**

```
┌─────────────────────┐    ┌─────────────────────┐    ┌─────────────────────┐
│   Laravel (PHP)      │    │  Node.js Service     │    │   WhatsApp APIs      │
│                     │    │                     │    │                     │
│ • UI Rendering      │◄──►│ • Provider Logic     │◄──►│ • Message Delivery   │
│ • Form Validation   │    │ • Session Management │    │ • Status Updates     │
│ • Business Rules    │    │ • Health Monitoring  │    │ • Webhook Handling   │
│ • Database Ops      │    │ • Load Balancing     │    │                     │
│ • Queue Management  │    │ • Failover Logic     │    │                     │
└─────────────────────┘    └─────────────────────┘    └─────────────────────┘
```

---

## 📱 Campaign Types

### **1. Template-Based Campaigns**

**Use Case:** Standard marketing, utility, or authentication messages
**Provider:** Meta API (requires approved templates)
**Approval:** Required by WhatsApp

**Flow:**
1. Create template in WhatsApp Business Manager
2. Wait for template approval
3. Create campaign using approved template
4. Assign variables for personalization
5. Send campaign

**Example Use Cases:**
- Marketing promotions
- Order confirmations
- Appointment reminders
- Authentication codes

### **2. Direct Message Campaigns** ⭐

**Use Case:** Immediate messaging without template delays
**Provider:** WhatsApp Web JS (primary) or Meta API (fallback)
**Approval:** Not required for WebJS

**Flow:**
1. Create message content directly in campaign form
2. Build WhatsApp-compliant template structure
3. Send immediately via optimal provider
4. No approval waiting period

**Example Use Cases:**
- Time-sensitive announcements
- Emergency notifications
- Personal follow-ups
- Testing campaigns

### **3. Provider Selection Strategy**

```php
// Priority Order for Provider Selection
1. WhatsApp Web JS (Primary) - No approval needed
   ├── Health Score ≥ 70
   ├── Connected & Active
   └── Primary Session if available

2. WhatsApp Web JS (Secondary) - Backup sessions
   ├── Health Score ≥ 60
   ├── Connected & Active
   └── Load balancing across sessions

3. Meta API (Fallback) - For approved templates only
   ├── Template is approved
   ├── WebJS sessions unavailable
   └── Meta API configured

4. Queue for Retry - No provider available
   └── Automatic retry with exponential backoff
```

---

## 🔧 System Components

### **Frontend Components**

#### **Campaign Management**
- **CampaignList** - Browse and filter campaigns
- **CampaignForm** - Create/edit campaigns (hybrid mode)
- **CampaignAnalytics** - Real-time performance metrics
- **CampaignDetails** - Individual campaign status

#### **Template Builder**
- **TemplateSelector** - Choose existing templates
- **DirectTemplateBuilder** - Create messages on-the-fly
- **TemplatePreview** - WhatsApp-like live preview
- **VariableAssigner** - Map contact data to template variables

#### **Contact Management**
- **ContactGroupSelector** - Target specific contact groups
- **ContactImport** - Bulk contact upload
- **ContactSegmentation** - Advanced filtering

### **Backend Services**

#### **Core Services**
```php
// Campaign Management
├── CampaignService          // Campaign CRUD operations
├── TemplateService          // Template management
├── ContactService           // Contact group operations
└── WhatsAppProviderService  // Provider selection logic

// Message Processing
├── MessageSendingService     // Template message sending
├── VariableProcessor        // {{1}}, {{2}} variable replacement
├── CampaignQueueService     // Queue management
└── RetryService             // Failed message retry logic

// Analytics & Monitoring
├── CampaignAnalyticsService // Performance metrics
├── SessionHealthService     // WhatsApp session monitoring
└── WebhookService          // Status update processing
```

#### **Queue Jobs**
```php
// Campaign Processing
├── ProcessCampaignJob           // Main campaign processor
├── SendCampaignJob             // Message sender
├── RetryCampaignJob            // Failed message retry
└── CleanupCampaignJob          // Archive old campaigns

// Session Management
├── HealthCheckJob              // Session health monitoring
├── SessionReconnectJob        // Reconnect failed sessions
└── LoadBalanceJob            // Distribute messages across sessions
```

### **Database Models**

#### **Core Models**
```php
// Campaign Management
├── Campaign                  // Main campaign entity
├── CampaignLog             // Individual message tracking
├── CampaignLogRetry        // Retry attempt tracking
├── Template               // WhatsApp templates
└── ContactGroup          // Contact segmentation

// WhatsApp Integration
├── WhatsAppSession        // WhatsApp connection sessions
├── Chat                  // Individual messages
├── ChatMedia             // Message attachments
└── ChatLog               // Message history

// Analytics
├── CampaignAnalytics     // Performance metrics
├── SessionAnalytics      // Session health data
└── ProviderAnalytics     // Provider performance
```

---

## 🗄️ Database Schema

### **Campaign Tables**

#### **campaigns**
```sql
CREATE TABLE campaigns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,

    -- Campaign Settings
    name VARCHAR(255) NOT NULL,
    template_mode ENUM('template', 'direct') DEFAULT 'template',
    template_id BIGINT UNSIGNED NULL,
    contact_group_id BIGINT UNSIGNED NULL,

    -- Hybrid Campaign Support
    direct_template_data JSON NULL,
    whatsapp_session_id BIGINT UNSIGNED NULL,
    provider_preference ENUM('webjs', 'meta', 'auto') DEFAULT 'auto',

    -- Status & Scheduling
    status ENUM('scheduled', 'ongoing', 'completed', 'failed') DEFAULT 'scheduled',
    scheduled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Metadata
    metadata JSON NULL,
    created_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL,

    -- Foreign Keys
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE SET NULL,
    FOREIGN KEY (contact_group_id) REFERENCES contact_groups(id) ON DELETE SET NULL,
    FOREIGN KEY (whatsapp_session_id) REFERENCES whatsapp_sessions(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_campaign_workspace_status (workspace_id, status),
    INDEX idx_campaign_template_mode (template_mode),
    INDEX idx_campaign_scheduled (scheduled_at, status),
    INDEX idx_campaign_session (whatsapp_session_id, status)
);
```

#### **campaign_logs**
```sql
CREATE TABLE campaign_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Core Relations
    campaign_id BIGINT UNSIGNED NOT NULL,
    contact_id BIGINT UNSIGNED NOT NULL,
    chat_id BIGINT UNSIGNED NULL,
    whatsapp_session_id BIGINT UNSIGNED NULL,

    -- Status & Processing
    status ENUM('pending', 'ongoing', 'success', 'failed') DEFAULT 'pending',
    retry_count INT DEFAULT 0,

    -- Response Data
    metadata JSON NULL,
    error_message TEXT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE SET NULL,
    FOREIGN KEY (whatsapp_session_id) REFERENCES whatsapp_sessions(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_log_campaign_status (campaign_id, status),
    INDEX idx_log_contact_status (contact_id, status),
    INDEX idx_log_session_status (whatsapp_session_id, status),
    INDEX idx_log_retry (retry_count, status)
);
```

#### **campaign_log_retries**
```sql
CREATE TABLE campaign_log_retries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_log_id BIGINT UNSIGNED NOT NULL,
    chat_id BIGINT UNSIGNED NULL,
    status ENUM('ongoing', 'success', 'failed') DEFAULT 'ongoing',
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (campaign_log_id) REFERENCES campaign_logs(id) ON DELETE CASCADE,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE SET NULL,

    INDEX idx_retry_log (campaign_log_id, status),
    INDEX idx_retry_created (created_at)
);
```

### **WhatsApp Integration Tables**

#### **whatsapp_sessions**
```sql
CREATE TABLE whatsapp_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,

    -- Session Identification
    session_id VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    provider_type ENUM('meta', 'webjs') NOT NULL,

    -- Status & Health
    status ENUM('connecting', 'connected', 'disconnected', 'error') DEFAULT 'connecting',
    is_primary BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    health_score INT DEFAULT 100,

    -- Session Data
    qr_code TEXT NULL,
    session_data JSON NULL,
    metadata JSON NULL,

    -- Timestamps
    last_activity_at TIMESTAMP NULL,
    last_connected_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL,

    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_session_workspace_provider (workspace_id, provider_type),
    INDEX idx_session_status (status, is_active),
    INDEX idx_session_health (health_score, last_activity_at)
);
```

### **Analytics Tables**

#### **campaign_analytics**
```sql
CREATE TABLE campaign_analytics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,

    -- Performance Metrics
    total_messages INT DEFAULT 0,
    sent_messages INT DEFAULT 0,
    delivered_messages INT DEFAULT 0,
    read_messages INT DEFAULT 0,
    failed_messages INT DEFAULT 0,

    -- Provider Breakdown
    webjs_messages INT DEFAULT 0,
    meta_api_messages INT DEFAULT 0,

    -- Timing Metrics
    avg_delivery_time DECIMAL(8,3) NULL, -- in seconds
    first_delivery_at TIMESTAMP NULL,
    last_delivery_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,

    UNIQUE KEY unique_campaign_date (campaign_id, date),
    INDEX idx_analytics_campaign_date (campaign_id, date),
    INDEX idx_analytics_workspace_date (workspace_id, date)
);
```

---

## 🔌 API Endpoints

### **Campaign Management**

#### **List Campaigns**
```http
GET /campaigns
Content-Type: application/json
Authorization: Bearer {token}

Query Parameters:
- search: string (optional) - Search campaign names
- template_mode: enum[template,direct] (optional) - Filter by mode
- status: enum[scheduled,ongoing,completed,failed] (optional) - Filter by status
- per_page: integer (default: 15) - Pagination
- page: integer (default: 1) - Page number

Response:
{
  "data": [
    {
      "id": 1,
      "uuid": "campaign-uuid",
      "name": "Marketing Campaign",
      "template_mode": "direct",
      "status": "completed",
      "created_at": "2025-01-14T10:30:00Z",
      "scheduled_at": "2025-01-14T11:00:00Z",
      "template": {
        "name": "Promo Template",
        "language": "id"
      },
      "contact_group": {
        "name": "VIP Customers"
      },
      "counts": {
        "total": 1500,
        "sent": 1450,
        "delivered": 1420,
        "read": 1200,
        "failed": 50
      }
    }
  ],
  "links": {...},
  "meta": {...}
}
```

#### **Create Campaign**
```http
POST /campaigns
Content-Type: application/json
Authorization: Bearer {token}

Request Body:
{
  "name": "Flash Sale Campaign",
  "template_mode": "direct",
  "contact_group_id": "contact-group-uuid",
  "whatsapp_session_id": "session-uuid", // optional
  "provider_preference": "webjs", // optional
  "skip_schedule": false,
  "scheduled_at": "2025-01-14T15:30:00Z",

  // Direct Template Data (only if template_mode = 'direct')
  "template_category": "MARKETING",
  "template_language": "id",
  "components": {
    "header": {
      "format": "TEXT",
      "text": "🔥 FLASH SALE!",
      "example": []
    },
    "body": {
      "text": "Hi {{1}}, special offer for you! Use code {{2}} for 50% off. Valid until {{3}}.",
      "example": [
        ["Customer Name", "SAVE50", "Tomorrow"]
      ]
    },
    "footer": {
      "text": "Terms and conditions apply"
    },
    "buttons": [
      {
        "type": "QUICK_REPLY",
        "text": "Shop Now"
      },
      {
        "type": "URL",
        "text": "View Deals",
        "url": "https://example.com/deals"
      }
    ]
  }
}

Response:
{
  "success": true,
  "data": {
    "id": 123,
    "uuid": "campaign-uuid",
    "name": "Flash Sale Campaign",
    "template_mode": "direct",
    "status": "scheduled",
    "scheduled_at": "2025-01-14T15:30:00Z"
  },
  "message": "Campaign created successfully!"
}
```

#### **Campaign Details**
```http
GET /campaigns/{uuid}
Content-Type: application/json
Authorization: Bearer {token}

Response:
{
  "campaign": {
    "id": 123,
    "uuid": "campaign-uuid",
    "name": "Flash Sale Campaign",
    "template_mode": "direct",
    "status": "ongoing",
    "created_at": "2025-01-14T10:30:00Z",
    "scheduled_at": "2025-01-14T15:30:00Z",

    // Template Data
    "template_data": {
      "category": "MARKETING",
      "language": "id",
      "components": {...}
    },

    // Performance Metrics
    "total_message_count": 1500,
    "total_sent_count": 1200,
    "total_delivered_count": 1100,
    "total_read_count": 800,
    "total_failed_count": 50,

    // Provider Usage
    "provider_breakdown": {
      "webjs": 1000,
      "meta_api": 200
    },

    // Targeting
    "contact_group": {
      "name": "VIP Customers",
      "total_contacts": 1500
    },

    "whatsapp_session": {
      "phone_number": "+62812345678",
      "provider_type": "webjs",
      "health_score": 95
    }
  },

  // Individual Message Logs (paginated)
  "rows": {
    "data": [
      {
        "id": 456,
        "contact": {
          "name": "John Doe",
          "phone": "+628123456789"
        },
        "status": "delivered",
        "created_at": "2025-01-14T15:31:00Z",
        "chat": {
          "id": 789,
          "status": "delivered",
          "provider_type": "webjs"
        },
        "retry_count": 0
      }
    ]
  }
}
```

#### **Export Campaign**
```http
GET /campaigns/export/{uuid}
Content-Type: application/vnd.ms-excel
Authorization: Bearer {token}

Response: CSV file with campaign details and message logs
```

#### **Delete Campaign**
```http
DELETE /campaigns/{uuid}
Content-Type: application/json
Authorization: Bearer {token}

Response:
{
  "success": true,
  "message": "Campaign deleted successfully!"
}
```

### **Campaign Analytics**

#### **Get Campaign Analytics**
```http
GET /campaigns/{uuid}/analytics
Content-Type: application/json
Authorization: Bearer {token}

Response:
{
  "campaign": {
    "id": 123,
    "name": "Flash Sale Campaign"
  },
  "performance": {
    "total_messages": 1500,
    "sent_messages": 1450,
    "delivered_messages": 1420,
    "read_messages": 1200,
    "failed_messages": 30,
    "delivery_rate": 94.67,
    "read_rate": 80.0,
    "failure_rate": 2.0
  },
  "timeline": [
    {
      "date": "2025-01-14",
      "sent": 500,
      "delivered": 480,
      "read": 400,
      "failed": 20
    }
  ],
  "provider_performance": {
    "webjs": {
      "messages": 1200,
      "delivered": 1180,
      "delivery_rate": 98.33,
      "avg_delivery_time": 2.5
    },
    "meta_api": {
      "messages": 300,
      "delivered": 240,
      "delivery_rate": 80.0,
      "avg_delivery_time": 8.2
    }
  },
  "hourly_distribution": [
    {"hour": "15:00", "messages": 150},
    {"hour": "16:00", "messages": 200}
  ]
}
```

---

## 📱 WhatsApp Providers

### **WhatsApp Web JS Provider**

#### **Overview**
- **Connection Type**: Direct WhatsApp Web connection
- **Approval Required**: No
- **Message Types**: Text, Media, Buttons, Lists
- **Rate Limits**: Similar to WhatsApp Web app
- **Session Management**: Multiple sessions supported

#### **Advantages**
✅ **No Template Approval** - Send messages instantly
✅ **Better Deliverability** - Less likely to be flagged as spam
✅ **Cost Effective** - No Meta API usage fees
✅ **Full Control** - Direct WhatsApp connection

#### **Limitations**
❌ **Session Stability** - Requires active session management
❌ **Scalability** - Limited by session capacity
❌ **Rate Limiting** - WhatsApp Web app limits apply
❌ **Media Size** - Smaller file size limits

#### **Configuration**
```php
// config/whatsapp.php
return [
    'webjs' => [
        'max_sessions_per_workspace' => 5,
        'health_check_interval' => 300, // 5 minutes
        'session_timeout' => 3600, // 1 hour
        'message_rate_limit' => 30, // per minute
        'auto_reconnect' => true,
        'qr_expiry' => 600, // 10 minutes
    ],
];
```

#### **Session Management**
```php
// Health Check
$healthScore = $session->calculateHealthScore([
    'connection_stability' => 0.4,  // 40%
    'message_success_rate' => 0.3, // 30%
    'recent_activity' => 0.2,       // 20%
    'error_rate' => -0.1,           // -10%
]);

// Session Selection
$optimalSession = WhatsAppSession::forWorkspace($workspaceId)
    ->connected()
    ->where('health_score', '>=', 70)
    ->orderByDesc('is_primary')
    ->orderByDesc('health_score')
    ->first();
```

### **Meta API Provider**

#### **Overview**
- **Connection Type**: Official WhatsApp Business API
- **Approval Required**: Yes (template approval)
- **Message Types**: Template messages only
- **Rate Limits**: Configurable by WhatsApp
- **Scalability**: Enterprise-grade

#### **Advantages**
✅ **High Scalability** - Enterprise message volume
✅ **Official Support** - WhatsApp Business support
✅ **Advanced Features** - Custom buttons, catalogs
✅ **Analytics** - Detailed message insights

#### **Limitations**
❌ **Template Approval** - Must wait for WhatsApp approval
❌ **Cost** - Per-message pricing applies
❌ **Restrictions** - Only template-based messaging
❌ **Complex Setup** - Business verification required

#### **Configuration**
```php
// config/whatsapp.php
return [
    'meta_api' => [
        'base_url' => 'https://graph.facebook.com/v18.0',
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'webhook_url' => env('APP_URL') . '/webhook/whatsapp',
    ],
];
```

### **Provider Selection Algorithm**

```php
class WhatsAppProviderSelector
{
    public function selectProvider(Campaign $campaign): array
    {
        // 1. Check for explicit preference
        if ($campaign->provider_preference !== 'auto') {
            return $this->validatePreferredProvider($campaign);
        }

        // 2. Template mode requires Meta API (if approved)
        if ($campaign->template_mode === 'template' && $campaign->template->is_approved) {
            return $this->selectMetaAPIProvider($campaign);
        }

        // 3. Direct mode prefers WebJS
        return $this->selectOptimalWebJSSession($campaign);
    }

    private function selectOptimalWebJSSession(Campaign $campaign): array
    {
        $session = WhatsAppSession::forWorkspace($campaign->workspace_id)
            ->connected()
            ->where('is_active', true)
            ->where('health_score', '>=', 70)
            ->where('provider_type', 'webjs')
            ->orderByDesc('is_primary')
            ->orderByDesc('health_score')
            ->first();

        if ($session) {
            return [
                'type' => 'webjs',
                'session_id' => $session->id,
                'phone_number' => $session->phone_number,
                'health_score' => $session->health_score,
                'confidence' => 'high'
            ];
        }

        // Fallback to Meta API if available
        return $this->selectMetaAPIProvider($campaign);
    }
}
```

---

## 📚 Implementation Guides

### **Quick Start Guide**

#### **1. Database Setup**
```bash
# Run migrations
php artisan migrate

# Seed WhatsApp session configuration
php artisan db:seed --class=WhatsAppSessionSeeder
```

#### **2. Configure WhatsApp Providers**
```bash
# WebJS Configuration - No additional setup needed
# Just create sessions via UI

# Meta API Configuration
# 1. Create Meta App: https://developers.facebook.com/
# 2. Get App ID and Secret
# 3. Configure Webhook URL
# 4. Add environment variables:
META_APP_ID=your_app_id
META_APP_SECRET=your_app_secret
META_WEBHOOK_VERIFY_TOKEN=your_verify_token
```

#### **3. Start Queue Workers**
```bash
# Start campaign processing queue
php artisan queue:work --queue=campaigns --sleep=3 --tries=3

# Start WhatsApp communication queue
php artisan queue:work --queue=whatsapp --sleep=1 --tries=5
```

#### **4. Schedule Campaign Processor**
```bash
# Add to crontab
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### **Creating Your First Campaign**

#### **Template-Based Campaign**
```php
use App\Services\CampaignService;

$campaignService = new CampaignService($workspaceId);

$result = $campaignService->createFromTemplate([
    'name' => 'Welcome Campaign',
    'template_id' => 'template-uuid',
    'contact_group_id' => 'contacts-uuid',
    'scheduled_at' => '2025-01-15T10:00:00Z',
    'variables' => [
        'header' => ['Welcome'],
        'body' => ['{{contact_name}}', '{{company_name}}']
    ]
]);
```

#### **Direct Message Campaign**
```php
use App\Services\HybridCampaignService;

$campaignService = new HybridCampaignService($workspaceId);

$result = $campaignService->createFromDirectTemplate([
    'name' => 'Flash Sale Announcement',
    'template_mode' => 'direct',
    'contact_group_id' => 'vip-customers-uuid',
    'skip_schedule' => true,

    'template_category' => 'MARKETING',
    'template_language' => 'id',
    'components' => [
        'header' => [
            'format' => 'TEXT',
            'text' => '🔥 LIMITED TIME OFFER!'
        ],
        'body' => [
            'text' => 'Hi {{1}}, exclusive 50% discount just for you! Use code {{2}}. Offer expires {{3}}.',
            'example' => ['Customer Name', 'SAVE50', 'Today at midnight']
        ],
        'buttons' => [
            [
                'type' => 'QUICK_REPLY',
                'text' => 'Claim Discount'
            ]
        ]
    ]
]);
```

### **Advanced Configuration**

#### **Custom Provider Selection**
```php
// In campaign service
protected function selectProviderForCampaign(Campaign $campaign)
{
    // Business logic for provider selection
    if ($campaign->contact_count > 10000) {
        // Large campaigns prefer Meta API
        return $this->selectMetaAPIProvider($campaign);
    }

    if ($campaign->urgency === 'high') {
        // Urgent campaigns prefer WebJS
        return $this->selectWebJSProvider($campaign);
    }

    // Default automatic selection
    return $this->selectOptimalProvider($campaign);
}
```

#### **Custom Retry Logic**
```php
// In campaign configuration
$campaignMetadata = [
    'retry_settings' => [
        'enabled' => true,
        'intervals' => [5, 15, 30, 60], // minutes
        'max_attempts' => 4,
        'move_to_failed_group' => true,
        'failed_group_uuid' => 'failed-contacts-uuid'
    ]
];
```

#### **Session Health Monitoring**
```php
// Custom health check
class CustomHealthChecker
{
    public function calculateSessionHealth(WhatsAppSession $session): int
    {
        $score = 100;

        // Connection stability (40%)
        $score += $this->getConnectionStabilityScore($session) * 0.4;

        // Message success rate (30%)
        $score += $this->getSuccessRateScore($session) * 0.3;

        // Recent activity (20%)
        $score += $this->getRecentActivityScore($session) * 0.2;

        // Error rate penalty (10%)
        $score -= $this->getErrorRatePenalty($session) * 0.1;

        return max(0, min(100, $score));
    }
}
```

### **Node.js Service Setup**

#### **Installation**
```bash
cd whatsapp-service
npm install

# Configure environment
cp .env.example .env
# Edit .env with your settings

# Start service
npm start
# or for development
npm run dev
```

#### **Service Configuration**
```javascript
// whatsapp-service/config/whatsapp.js
module.exports = {
    providers: {
        webjs: {
            maxSessions: 10,
            healthCheckInterval: 300000, // 5 minutes
            autoReconnect: true,
            qrCodeExpiry: 600000, // 10 minutes
        },
        metaApi: {
            version: 'v18.0',
            timeout: 30000,
            retryAttempts: 3,
        }
    },

    loadBalancing: {
        strategy: 'health_score', // 'round_robin', 'health_score'
        preferredProvider: 'webjs',
        failoverEnabled: true,
    }
};
```

---

## ⚡ Performance & Scaling

### **Performance Metrics**

#### **Campaign Processing Performance**
```php
// Campaign processing benchmark
class CampaignPerformanceMonitor
{
    public function getCampaignMetrics(Campaign $campaign): array
    {
        return [
            'processing_speed' => $this->calculateProcessingSpeed($campaign), // messages/minute
            'queue_depth' => Queue::size('campaigns'),
            'worker_utilization' => $this->getWorkerUtilization(),
            'error_rate' => $this->getErrorRate($campaign),
            'avg_delivery_time' => $this->getAverageDeliveryTime($campaign),
        ];
    }

    private function calculateProcessingSpeed(Campaign $campaign): float
    {
        $totalMessages = $campaign->campaignLogs()->count();
        $processingTime = $campaign->updated_at->diffInSeconds($campaign->created_at);

        return $processingTime > 0 ? ($totalMessages / $processingTime) * 60 : 0;
    }
}
```

#### **Database Performance**
```sql
-- Optimized queries for campaign listing
EXPLAIN SELECT
    c.id, c.uuid, c.name, c.status, c.template_mode,
    COUNT(cl.id) as total_messages,
    SUM(CASE WHEN cl.status = 'success' THEN 1 ELSE 0 END) as sent_messages,
    COUNT(cl.id) as contact_count,
    w.phone_number as session_phone,
    w.provider_type as session_provider
FROM campaigns c
LEFT JOIN campaign_logs cl ON c.id = cl.campaign_id
LEFT JOIN whatsapp_sessions w ON c.whatsapp_session_id = w.id
WHERE c.workspace_id = ? AND c.deleted_at IS NULL
GROUP BY c.id
ORDER BY c.created_at DESC
LIMIT 15;
```

#### **Queue Performance Optimization**
```php
// Optimized job dispatching
class OptimizedCampaignProcessor
{
    public function processCampaign(Campaign $campaign)
    {
        // Split into batches for better queue performance
        $batchSize = 1000;
        $contacts = $this->getContactsForCampaign($campaign);

        $chunks = $contacts->chunk($batchSize);

        foreach ($chunks as $index => $contactChunk) {
            ProcessCampaignBatchJob::dispatch(
                $campaign->id,
                $contactChunk->pluck('id')->toArray(),
                $index * $batchSize // Offset for tracking
            )->onQueue('campaigns')
             ->delay(now()->addSeconds($index * 5)); // Stagger batches
        }
    }
}
```

### **Scaling Strategies**

#### **Horizontal Scaling - Multiple Queue Workers**
```bash
# Scale up workers based on load
php artisan queue:work --queue=campaigns --sleep=3 --tries=3 &
php artisan queue:work --queue=campaigns --sleep=3 --tries=3 &
php artisan queue:work --queue=campaigns --sleep=3 --tries=3 &

# Supervisor configuration for auto-restart
[program:campaign-workers]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan queue:work --queue=campaigns --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=4
user=www-data
stdout_logfile=/var/log/supervisor/campaign-workers.log
```

#### **Session Pooling - Multiple WhatsApp Connections**
```php
// Session pool management
class WhatsAppSessionPool
{
    public function getOptimalSessionPool(int $messageCount): Collection
    {
        $baseSessionCount = ceil($messageCount / 1000); // 1K messages per session

        return WhatsAppSession::forWorkspace($this->workspaceId)
            ->connected()
            ->where('health_score', '>=', 70)
            ->orderByDesc('health_score')
            ->limit($baseSessionCount)
            ->get();
    }

    public function distributeMessagesAcrossSessions(Collection $sessions, Collection $contacts): array
    {
        $distribution = [];
        $sessionCount = $sessions->count();

        foreach ($contacts as $index => $contact) {
            $sessionIndex = $index % $sessionCount;
            $session = $sessions->get($sessionIndex);

            $distribution[$session->id][] = $contact->id;
        }

        return $distribution;
    }
}
```

#### **Database Scaling - Read Replicas**
```php
// Read replica for analytics
class CampaignAnalyticsService
{
    protected $readConnection;

    public function __construct()
    {
        $this->readConnection = DB::connection('read_replica');
    }

    public function getCampaignAnalytics(Campaign $campaign): array
    {
        return $this->readConnection->table('campaign_logs')
            ->where('campaign_id', $campaign->id)
            ->selectRaw('
                status,
                COUNT(*) as count,
                COUNT(CASE WHEN status = "success" THEN 1 END) as success_count,
                COUNT(CASE WHEN status = "failed" THEN 1 END) as failed_count
            ')
            ->groupBy('status')
            ->get();
    }
}
```

### **Caching Strategy**

#### **Campaign Analytics Caching**
```php
class CampaignAnalyticsCache
{
    public function getCampaignMetrics(Campaign $campaign): array
    {
        $cacheKey = "campaign_metrics_{$campaign->id}_{$campaign->updated_at->timestamp}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($campaign) {
            return [
                'total_messages' => $campaign->campaignLogs()->count(),
                'success_rate' => $this->calculateSuccessRate($campaign),
                'delivery_time' => $this->getAverageDeliveryTime($campaign),
                'provider_breakdown' => $this->getProviderBreakdown($campaign),
            ];
        });
    }

    public function invalidateCampaignCache(Campaign $campaign): void
    {
        Cache::forget("campaign_metrics_{$campaign->id}_*");
    }
}
```

#### **Session Health Caching**
```php
class SessionHealthCache
{
    public function getHealthySessions(int $workspaceId): Collection
    {
        return Cache::remember("healthy_sessions_{$workspaceId}", now()->addMinutes(2), function () use ($workspaceId) {
            return WhatsAppSession::forWorkspace($workspaceId)
                ->connected()
                ->where('health_score', '>=', 70)
                ->orderByDesc('health_score')
                ->get(['id', 'uuid', 'phone_number', 'health_score']);
        });
    }
}
```

### **Performance Monitoring**

#### **Real-time Metrics Dashboard**
```php
class CampaignPerformanceDashboard
{
    public function getRealTimeMetrics(): array
    {
        return [
            'queue_depth' => Queue::size('campaigns'),
            'active_campaigns' => Campaign::where('status', 'ongoing')->count(),
            'processing_speed' => $this->getCurrentProcessingSpeed(), // messages/minute
            'error_rate' => $this->getCurrentErrorRate(), // percentage
            'average_delivery_time' => $this->getAverageDeliveryTime(), // seconds
            'healthy_sessions' => WhatsAppSession::where('health_score', '>=', 70)->count(),
            'system_load' => sys_getloadavg()[0], // CPU load
        ];
    }

    public function getProcessingSpeedTrend(): array
    {
        return Cache::remember('processing_speed_trend', now()->addMinutes(1), function () {
            return [
                'last_minute' => $this->getMessagesPerMinute(now()->subMinute()),
                'last_5_minutes' => $this->getMessagesPerMinute(now()->subMinutes(5)),
                'last_hour' => $this->getMessagesPerMinute(now()->subHour()),
            ];
        });
    }
}
```

---

## 🔍 Monitoring & Debugging

### **Logging Strategy**

#### **Comprehensive Logging**
```php
class CampaignLogger
{
    public function logCampaignCreated(Campaign $campaign): void
    {
        Log::info('Campaign created', [
            'campaign_id' => $campaign->id,
            'workspace_id' => $campaign->workspace_id,
            'name' => $campaign->name,
            'template_mode' => $campaign->template_mode,
            'contact_count' => $campaign->contactGroup?->contacts->count(),
            'scheduled_at' => $campaign->scheduled_at,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function logMessageSent(CampaignLog $campaignLog, array $response): void
    {
        Log::info('Campaign message sent', [
            'campaign_log_id' => $campaignLog->id,
            'campaign_id' => $campaignLog->campaign_id,
            'contact_id' => $campaignLog->contact_id,
            'provider_type' => $response['provider_type'],
            'message_id' => $response['message_id'] ?? null,
            'delivery_time_ms' => $response['delivery_time_ms'] ?? null,
            'session_id' => $campaignLog->whatsapp_session_id,
        ]);
    }

    public function logMessageFailed(CampaignLog $campaignLog, \Exception $exception): void
    {
        Log::error('Campaign message failed', [
            'campaign_log_id' => $campaignLog->id,
            'campaign_id' => $campaignLog->campaign_id,
            'contact_id' => $campaignLog->contact_id,
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'retry_count' => $campaignLog->retry_count,
            'stack_trace' => $exception->getTraceAsString(),
        ]);
    }
}
```

#### **Structured Logging for Analysis**
```php
// Use structured logging for better analysis
Log::channel('campaign-analytics')->info('Campaign performance metrics', [
    'timestamp' => now()->toISOString(),
    'workspace_id' => $workspaceId,
    'campaign_id' => $campaign->id,
    'metrics' => [
        'total_messages' => $totalMessages,
        'success_rate' => $successRate,
        'delivery_time_avg' => $avgDeliveryTime,
        'provider_usage' => [
            'webjs' => $webjsCount,
            'meta_api' => $metaApiCount,
        ],
    ],
]);
```

### **Debugging Tools**

#### **Campaign Debug Endpoint**
```php
// Debug endpoint for troubleshooting
Route::get('/debug/campaign/{uuid}', function ($uuid) {
    $campaign = Campaign::with(['campaignLogs.contact', 'whatsappSession'])
        ->where('uuid', $uuid)
        ->firstOrFail();

    return [
        'campaign' => $campaign->toArray(),
        'configuration' => [
            'template_mode' => $campaign->template_mode,
            'template_structure' => $campaign->getTemplateStructure(),
            'provider_preference' => $campaign->getPreferredProvider(),
        ],
        'session_info' => $campaign->whatsappSession ? [
            'phone_number' => $campaign->whatsappSession->phone_number,
            'provider_type' => $campaign->whatsappSession->provider_type,
            'health_score' => $campaign->whatsappSession->health_score,
            'status' => $campaign->whatsappSession->status,
        ] : null,
        'message_logs' => $campaign->campaignLogs->take(10)->map(function ($log) {
            return [
                'contact' => $log->contact->only(['name', 'phone']),
                'status' => $log->status,
                'retry_count' => $log->retry_count,
                'created_at' => $log->created_at,
                'metadata' => $log->metadata,
            ];
        }),
        'errors' => $campaign->campaignLogs()->where('status', 'failed')
            ->take(5)
            ->map(function ($log) {
                return [
                    'contact' => $log->contact->only(['name', 'phone']),
                    'error_message' => $log->error_message,
                    'retry_count' => $log->retry_count,
                ];
            }),
    ];
});
```

#### **Health Check Endpoint**
```php
Route::get('/health/broadcast', function () {
    $healthStatus = [
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'services' => [
            'database' => $this->checkDatabaseHealth(),
            'queue' => $this->checkQueueHealth(),
            'whatsapp_sessions' => $this->checkWhatsAppSessionsHealth(),
            'node_service' => $this->checkNodeServiceHealth(),
        ],
        'metrics' => [
            'active_campaigns' => Campaign::where('status', 'ongoing')->count(),
            'pending_messages' => CampaignLog::where('status', 'pending')->count(),
            'failed_messages_today' => CampaignLog::where('status', 'failed')
                ->whereDate('created_at', today())
                ->count(),
        ],
    ];

    $hasIssues = collect($healthStatus['services'])->contains(function ($service) {
        return $service['status'] !== 'healthy';
    });

    if ($hasIssues) {
        $healthStatus['status'] = 'degraded';
        return response()->json($healthStatus, 503);
    }

    return response()->json($healthStatus);
});
```

#### **Performance Profiling**
```php
// Add performance profiling to campaign processing
class CampaignProfiler
{
    public function profileCampaignProcessing(Campaign $campaign): void
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Profile contact loading
        $contactStartTime = microtime(true);
        $contacts = $this->getContactsForCampaign($campaign);
        $contactLoadTime = microtime(true) - $contactStartTime;

        // Profile log creation
        $logStartTime = microtime(true);
        $this->createCampaignLogs($campaign, $contacts);
        $logCreationTime = microtime(true) - $logStartTime;

        $totalTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $startMemory;

        Log::channel('performance')->info('Campaign processing profile', [
            'campaign_id' => $campaign->id,
            'contact_count' => $contacts->count(),
            'total_time_seconds' => round($totalTime, 2),
            'contact_load_time_seconds' => round($contactLoadTime, 2),
            'log_creation_time_seconds' => round($logCreationTime, 2),
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
            'contacts_per_second' => round($contacts->count() / $totalTime, 2),
        ]);
    }
}
```

### **Alerting System**

#### **Performance Alerts**
```php
class CampaignAlertSystem
{
    public function checkPerformanceThresholds(): void
    {
        $this->checkErrorRateThreshold();
        $this->checkQueueDepthThreshold();
        $this->checkSessionHealthThreshold();
    }

    private function checkErrorRateThreshold(): void
    {
        $errorRate = $this->getCurrentErrorRate();

        if ($errorRate > 10) { // 10% error rate threshold
            $this->sendAlert('High Error Rate', [
                'error_rate' => $errorRate,
                'threshold' => 10,
                'time_period' => 'last_hour',
            ], 'critical');
        }
    }

    private function checkQueueDepthThreshold(): void
    {
        $queueDepth = Queue::size('campaigns');

        if ($queueDepth > 10000) { // 10K messages threshold
            $this->sendAlert('High Queue Depth', [
                'queue_depth' => $queueDepth,
                'threshold' => 10000,
                'queue' => 'campaigns',
            ], 'warning');
        }
    }

    private function sendAlert(string $title, array $data, string $severity): void
    {
        Log::channel('alerts')->error($title, array_merge($data, [
            'severity' => $severity,
            'timestamp' => now()->toISOString(),
        ]));

        // Send notification to admin
        $this->notifyAdmins($title, $data, $severity);
    }
}
```

---

## 🛠️ Troubleshooting

### **Common Issues & Solutions**

#### **1. Campaign Not Starting**

**Symptoms:**
- Campaign stuck in "scheduled" status
- No messages being sent
- Queue depth increasing

**Debug Steps:**
```php
// Check campaign status
$campaign = Campaign::with('campaignLogs')->where('uuid', $uuid)->first();

if ($campaign->status === 'scheduled') {
    // Check scheduled time
    if ($campaign->scheduled_at->isFuture()) {
        echo "Campaign scheduled for: " . $campaign->scheduled_at;
    } else {
        echo "Campaign should have started. Checking job queue...";
        $jobCount = Queue::size('campaigns');
        echo "Jobs in queue: {$jobCount}";
    }
}

// Check scheduler is running
$lastRun = Cache::get('scheduler_last_run');
if (!$lastRun || $lastRun->diffInMinutes(now()) > 5) {
    echo "⚠️ Scheduler may not be running";
}
```

**Solutions:**
```bash
# Restart queue workers
php artisan queue:restart

# Check scheduler
php artisan schedule:run

# Verify cron job
crontab -l | grep schedule:run

# Process pending campaigns manually
php artisan campaign:process-pending
```

#### **2. High Failure Rate**

**Symptoms:**
- Many messages failing
- Session health score dropping
- Provider connection issues

**Debug Steps:**
```php
// Analyze failure patterns
$failedLogs = CampaignLog::where('status', 'failed')
    ->where('created_at', '>=', now()->subHour())
    ->with(['contact', 'campaign.whatsappSession'])
    ->get();

$failurePatterns = $failedLogs->groupBy(function ($log) {
    return $log->campaign->whatsappSession->provider_type ?? 'unknown';
});

foreach ($failurePatterns as $provider => $logs) {
    echo "{$provider} failures: {$logs->count()}\n";

    $errors = $logs->groupBy('error_message');
    foreach ($errors as $error => $errorLogs) {
        echo "  - {$error}: {$errorLogs->count()}\n";
    }
}
```

**Solutions:**
```php
// Switch to backup provider
$campaign->update([
    'provider_preference' => 'auto', // Let system choose optimal
    'whatsapp_session_id' => null,    // Clear preferred session
]);

// Increase retry intervals
$metadata = $campaign->workspace->metadata ?? [];
$metadata['campaigns']['resend_intervals'] = [10, 30, 60, 120]; // minutes
$campaign->workspace->update(['metadata' => $metadata]);
```

#### **3. WhatsApp Session Issues**

**Symptoms:**
- Session disconnected
- QR code not generating
- Messages not sending

**Debug Steps:**
```php
// Check session health
$session = WhatsAppSession::find($sessionId);

echo "Session Status: {$session->status}\n";
echo "Health Score: {$session->health_score}\n";
echo "Last Activity: {$session->last_activity_at}\n";
echo "Provider: {$session->provider_type}\n";

// Check recent errors
$recentErrors = CampaignLog::where('whatsapp_session_id', $sessionId)
    ->where('status', 'failed')
    ->where('created_at', '>=', now()->subHour())
    ->count();

echo "Recent failures: {$recentErrors}\n";
```

**Solutions:**
```bash
# Reconnect WebJS session
php artisan whatsapp:session:reconnect {session-uuid}

# Regenerate QR code
php artisan whatsapp:session:regenerate-qr {session-uuid}

# Check Node.js service status
curl http://localhost:3000/health
```

#### **4. Performance Issues**

**Symptoms:**
- Slow campaign processing
- High memory usage
- Database timeouts

**Debug Steps:**
```php
// Profile campaign processing
$campaign = Campaign::find($campaignId);

$startTime = microtime(true);
$contactCount = $this->getContactsForCampaign($campaign)->count();
$processingTime = microtime(true) - $startTime;

echo "Contacts: {$contactCount}\n";
echo "Processing time: {$processingTime}s\n";
echo "Contacts/second: " . ($contactCount / $processingTime) . "\n";

// Check memory usage
echo "Memory usage: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB\n";
```

**Solutions:**
```php
// Optimize contact loading
$contacts = Contact::whereHas('contactGroups', function ($query) use ($campaign) {
        $query->where('contact_groups.id', $campaign->contact_group_id);
    })
    ->where('workspace_id', $campaign->workspace_id)
    ->whereNull('deleted_at')
    ->select(['id', 'uuid', 'phone', 'first_name']) // Only select needed columns
    ->chunk(1000, function ($contactChunk) {
        // Process chunk
    });

// Increase queue workers
php artisan queue:work --queue=campaigns --memory=512 --sleep=3
```

### **Debug Commands**

#### **Campaign Debugging**
```bash
# Show campaign status
php artisan campaign:status {uuid}

# Process single campaign
php artisan campaign:process {uuid}

# Show campaign logs
php artisan campaign:logs {uuid} --limit=50

# Campaign performance report
php artisan campaign:report {uuid} --detailed

# Retry failed messages
php artisan campaign:retry-failed {uuid}

# Cancel ongoing campaign
php artisan campaign:cancel {uuid}
```

#### **WhatsApp Session Debugging**
```bash
# List all sessions
php artisan whatsapp:sessions:list

# Check session health
php artisan whatsapp:sessions:health {uuid}

# Test session connection
php artisan whatsapp:sessions:test {uuid}

# Reconnect session
php artisan whatsapp:sessions:reconnect {uuid}

# Get session statistics
php artisan whatsapp:sessions:stats {uuid}
```

#### **Queue Debugging**
```bash
# Show queue status
php artisan queue:status

# Show failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry {job-id}

# Clear specific queue
php artisan queue:clear campaigns

# Monitor queue in real-time
php artisan queue:monitor campaigns
```

### **Emergency Procedures**

#### **System Recovery**
```bash
# 1. Stop all processing
php artisan queue:restart
php artisan campaign:stop-all

# 2. Restore database if needed
php artisan migrate:rollback --step=5
php artisan migrate

# 3. Clear cache
php artisan cache:clear
php artisan config:clear

# 4. Restart services
php artisan queue:work --queue=campaigns &
php artisan queue:work --queue=whatsapp &

# 5. Verify system health
php artisan health:check
```

#### **Manual Campaign Recovery**
```php
// Emergency script to recover stuck campaigns
class CampaignEmergencyRecovery
{
    public function recoverStuckCampaigns(): array
    {
        $stuckCampaigns = Campaign::where('status', 'scheduled')
            ->where('scheduled_at', '<', now()->subHour())
            ->whereNull('deleted_at')
            ->get();

        $recovered = [];

        foreach ($stuckCampaigns as $campaign) {
            try {
                // Reset campaign to initial state
                CampaignLog::where('campaign_id', $campaign->id)->delete();

                $campaign->update(['status' => 'scheduled']);

                // Dispatch processing job
                ProcessCampaignJob::dispatch($campaign->id, $campaign->workspace_id)
                    ->onQueue('campaigns');

                $recovered[] = $campaign->uuid;

                Log::info('Campaign recovered', [
                    'campaign_id' => $campaign->id,
                    'campaign_uuid' => $campaign->uuid,
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to recover campaign', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $recovered;
    }
}
```

---

## 📚 Additional Resources

### **Related Documentation**
- [Development Patterns Guidelines](../architecture/07-development-patterns-guidelines.md)
- [WhatsApp Web JS Integration](../chat-whatsappwebjs-integration/)
- [Dual-Server Architecture](../architecture/06-dual-server-architecture.md)
- [API Documentation](../api/whatsapp-campaign-api.md)

### **Configuration Files**
- [Queue Configuration](../../config/queue.php)
- [WhatsApp Configuration](../../config/whatsapp.php)
- [Database Migrations](../../database/migrations/)

### **Useful Commands**
```bash
# Campaign management
php artisan campaign:send {uuid}
php artisan campaign:pause {uuid}
php artisan campaign:resume {uuid}

# Performance monitoring
php artisan campaign:performance-report
php artisan whatsapp:sessions:health-check

# System maintenance
php artisan campaign:cleanup --days=30
php artisan campaign:optimize
```

---

**Maintainers:** Development Team
**Review Date:** December 14, 2025
**Next Review:** March 14, 2026

For questions or issues, please refer to the [Development Guidelines](../architecture/07-development-patterns-guidelines.md) or contact the development team.