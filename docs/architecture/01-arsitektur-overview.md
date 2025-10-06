# 🏗️ Arsitektur Proyek Blazz - Complete Overview

## Ringkasan Eksekutif

**Blazz** adalah enterprise-grade **multi-tenant WhatsApp Business Platform** yang menggunakan **Hybrid Service-Oriented Architecture** dengan **Module-Based Extension System**. Arsitektur ini menggabungkan kekuatan **MVC Pattern** sebagai foundation dengan **Service Layer Pattern** untuk business logic isolation, **Job Queue System** untuk asynchronous processing, dan **Modular Architecture** untuk feature extensibility.

---

## 🎯 Arsitektur Pattern yang Digunakan

### **1. Core Architecture: Enhanced MVC + Service Layer**

Blazz mengimplementasikan **Enhanced MVC Pattern** dengan additional **Service Layer** untuk memisahkan business logic dari controller logic:

```
┌─────────────────────────────────────────────────────────────┐
│                     CLIENT LAYER                             │
│  (Browser, Mobile App, External API Consumers)              │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│                  PRESENTATION LAYER                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Vue.js 3   │  │  Inertia.js  │  │ REST API     │      │
│  │  Components  │  │  SSR Bridge  │  │  Endpoints   │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│                  CONTROLLER LAYER                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │    User      │  │    Admin     │  │     API      │      │
│  │ Controllers  │  │ Controllers  │  │ Controllers  │      │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘      │
│         │                 │                  │               │
│         └─────────────────┼──────────────────┘               │
└───────────────────────────┼──────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                   SERVICE LAYER 🔥                           │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  WhatsApp    │  │  Campaign    │  │   Billing    │      │
│  │   Service    │  │   Service    │  │   Service    │      │
│  ├──────────────┤  ├──────────────┤  ├──────────────┤      │
│  │  Template    │  │    Chat      │  │  Workspace   │      │
│  │   Service    │  │   Service    │  │   Service    │      │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘      │
│         │                 │                  │               │
│         └─────────────────┼──────────────────┘               │
└───────────────────────────┼──────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│            BACKGROUND PROCESSING LAYER                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Queue      │  │   Events     │  │   Jobs       │      │
│  │   System     │  │   System     │  │   Workers    │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│                    DATA LAYER                                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Eloquent   │  │   Database   │  │    Redis     │      │
│  │    Models    │  │   (MySQL)    │  │    Cache     │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│              EXTERNAL INTEGRATION LAYER                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  WhatsApp    │  │   Payment    │  │   Storage    │      │
│  │  Cloud API   │  │   Gateways   │  │  (S3/Local)  │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
```

---

## 🔧 Komponen Arsitektur Utama

### **1. Controller Layer - Request Handling**

**Responsibilities:**
- HTTP request validation
- User authentication & authorization checking
- Orchestrating service calls
- Returning responses (Inertia views or JSON)

**Pattern Implementation:**
```php
// Example: app/Http/Controllers/User/ChatController.php
class ChatController extends BaseController
{
    private function chatService()
    {
        return new ChatService(session()->get('current_workspace'));
    }

    public function sendMessage(Request $request)
    {
        return $this->chatService()->sendMessage($request);
    }
}
```

**Key Characteristics:**
- ✅ **Thin Controllers** - Minimal business logic
- ✅ **Service Injection** - Delegates to service layer
- ✅ **Single Responsibility** - Focus on HTTP concerns only

---

### **2. Service Layer - Business Logic Hub** 🎯

**Responsibilities:**
- Business logic implementation
- Complex operations orchestration
- External API integration
- Data transformation & processing
- Transaction management

**Current Services:**
```
app/Services/
├── AuthService.php              → Authentication & session management
├── WhatsappService.php          → WhatsApp Cloud API integration
├── CampaignService.php          → Campaign creation & management
├── ChatService.php              → Real-time messaging logic
├── TemplateService.php          → Template parsing & building
├── BillingService.php           → Billing & invoicing operations
├── WorkspaceService.php         → Multi-tenant workspace management
├── ContactService.php           → Contact management operations
└── NotificationService.php      → Notification dispatching
```

**Pattern Implementation:**
```php
// Example: app/Services/WhatsappService.php
class WhatsappService
{
    private $accessToken;
    private $phoneNumberId;
    private $workspaceId;

    public function __construct($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId)
    {
        $this->accessToken = $accessToken;
        $this->phoneNumberId = $phoneNumberId;
        $this->workspaceId = $workspaceId;
    }

    public function sendMessage($contactUuid, $messageContent, $userId = null, $type = "text")
    {
        $contact = Contact::where('uuid', $contactUuid)->first();
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";
        
        // Complex message building logic
        // API integration
        // Error handling
        // Event broadcasting
    }
}
```

**Service Design Principles:**
- ✅ **Single Responsibility** - One service per domain/feature
- ✅ **Dependency Injection** - Constructor-based dependencies
- ✅ **Stateful Services** - Workspace context awareness
- ✅ **API Abstraction** - Hides external API complexity

---

### **3. Model Layer - Data Representation**

**Responsibilities:**
- Database table representation
- Eloquent relationships
- Model accessors/mutators
- Query scopes
- Model events

**Model Relationships Pattern:**
```php
// Example: app/Models/Campaign.php
class Campaign extends Model
{
    use HasUuid, HasFactory;

    protected $guarded = [];
    public $timestamps = false;

    // Relationships
    public function workspace() {
        return $this->belongsTo(Workspace::class);
    }

    public function template() {
        return $this->belongsTo(Template::class);
    }

    public function contactGroup() {
        return $this->belongsTo(ContactGroup::class);
    }

    public function campaignLogs() {
        return $this->hasMany(CampaignLog::class);
    }

    // Business methods
    public function sentCount() {
        return $this->campaignLogs()
            ->where('status', 'success')
            ->whereHas('chat', function ($query) {
                $query->whereIn('status', ['accepted', 'sent', 'delivered', 'read']);
            })
            ->count();
    }
}
```

**Model Design Characteristics:**
- ✅ **UUID Primary Keys** - Using `HasUuid` trait for external reference
- ✅ **Rich Relationships** - Comprehensive Eloquent relationships
- ✅ **Business Methods** - Domain-specific calculations
- ✅ **Timezone Handling** - Automatic timezone conversion via accessors
- ✅ **Soft Deletes** - Data preservation with `SoftDeletes` trait

---

### **4. Job Queue System - Asynchronous Processing** ⚡

**Responsibilities:**
- Heavy computation offloading
- Background task processing
- Campaign message delivery
- API rate limit handling
- Retry failed operations

**Queue Architecture:**
```
app/Jobs/
├── CreateCampaignLogsJob.php        → Bulk campaign log creation
├── ProcessCampaignMessagesJob.php   → Batch message processing
├── SendCampaignJob.php              → Individual message sending
├── ProcessSingleCampaignLogJob.php  → Single log processing
└── RetryCampaignLogJob.php          → Failed message retry mechanism
```

**Job Pattern Implementation:**
```php
// Example: app/Jobs/SendCampaignJob.php
class SendCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $campaignLogId;
    public $tries = 3;          // Maximum retry attempts
    public $timeout = 120;      // Execution timeout

    public function __construct($campaignLogId)
    {
        $this->campaignLogId = $campaignLogId;
    }

    public function handle()
    {
        $campaignLog = CampaignLog::find($this->campaignLogId);
        
        // Business logic execution
        // WhatsApp API calls
        // Status tracking
        // Error handling with retry mechanism
    }
}
```

**Queue Design Principles:**
- ✅ **Reliable Processing** - Job retry mechanism with exponential backoff
- ✅ **State Management** - Campaign log status tracking
- ✅ **Failure Handling** - Failed job logging and manual retry options
- ✅ **Rate Limiting** - API throttling compliance
- ✅ **Chunked Processing** - Large campaigns broken into manageable batches

---

### **5. Module System - Feature Extensibility** 🔌

**Responsibilities:**
- Third-party integrations
- Optional feature additions
- Payment gateway extensions
- External service connectors

**Module Structure:**
```
modules/
├── EmbeddedSignup/
│   ├── Controllers/
│   │   └── EmbeddedSignupController.php
│   ├── Services/
│   │   └── EmbeddedSignupService.php
│   ├── Requests/
│   ├── Providers/
│   │   └── EmbeddedSignupServiceProvider.php
│   └── routes.php
│
└── Razorpay/
    ├── Controllers/
    ├── Services/
    └── routes.php
```

**Module Design Pattern:**
```php
// Composer autoloading
"autoload": {
    "psr-4": {
        "Modules\\": "modules/"
    }
}

// Module Service Provider
namespace Modules\EmbeddedSignup\Providers;

class EmbeddedSignupServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('embedded-signup', function() {
            return new EmbeddedSignupService();
        });
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
    }
}
```

**Module Benefits:**
- ✅ **Separation of Concerns** - Isolated feature development
- ✅ **Plugin Architecture** - Enable/disable without core changes
- ✅ **Independent Versioning** - Module-specific updates
- ✅ **Third-party Integrations** - Easy payment gateway additions

---

### **6. API Layer - External Integration**

**Responsibilities:**
- RESTful API endpoints
- Bearer token authentication
- External system integration
- Webhook handling

**API Structure:**
```php
// routes/api.php
Route::middleware([AuthenticateBearerToken::class])->group(function () {
    Route::post('/send', [ApiController::class, 'sendMessage']);
    Route::post('/send/template', [ApiController::class, 'sendTemplateMessage']);
    Route::post('/campaigns', [ApiController::class, 'storeCampaign']);
    
    Route::get('/contacts', [ApiController::class, 'listContacts']);
    Route::post('/contacts', [ApiController::class, 'storeContact']);
    
    Route::get('/contact-groups', [ApiController::class, 'listContactGroups']);
});
```

**API Design Characteristics:**
- ✅ **RESTful Conventions** - Standard HTTP methods
- ✅ **Bearer Token Auth** - Workspace-based authentication
- ✅ **Versioning Support** - Future API version management
- ✅ **Consistent Responses** - Standardized JSON response format

---

### **7. Event System - Real-time Communication**

**Event-Driven Architecture:**
```php
// app/Events/NewChatEvent.php
class NewChatEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chat;

    public function __construct($chat)
    {
        $this->chat = $chat;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('workspace.' . $this->chat->workspace_id);
    }
}
```

**Broadcasting dengan Pusher:**
- ✅ **Real-time Updates** - Instant chat notifications
- ✅ **Private Channels** - Workspace-isolated broadcasts
- ✅ **Event-Driven** - Loosely coupled component communication

---

## 🎨 Design Patterns yang Digunakan

### **1. Service Layer Pattern**
- Memisahkan business logic dari controllers
- Reusable business operations
- Testable service methods

### **2. Repository Pattern (Implicit via Eloquent)**
- Models act as repositories
- Query scope reusability
- Database abstraction

### **3. Factory Pattern**
- Database seeders dan factories
- Test data generation

### **4. Observer Pattern**
- Model events (created, updated, deleted)
- Real-time broadcasting

### **5. Strategy Pattern**
- Multiple payment gateway implementations
- Storage driver abstraction (local, S3)

### **6. Dependency Injection Pattern**
- Constructor injection di services
- Laravel service container

### **7. Job Queue Pattern**
- Asynchronous task processing
- Retry mechanism implementation

---

## 🔐 Multi-Tenancy Architecture

### **Workspace Isolation Strategy:**

```php
// Session-based workspace context
$workspaceId = session()->get('current_workspace');

// Services are workspace-aware
$chatService = new ChatService($workspaceId);

// Database queries automatically scoped
Chat::where('workspace_id', $workspaceId)->get();

// User dapat switch workspace
Route::put('/profile/workspace', [ProfileController::class, 'updateWorkspace']);
```

**Multi-Tenancy Characteristics:**
- ✅ **Shared Database** - Single database dengan workspace_id column
- ✅ **Session-based Context** - Current workspace tracking
- ✅ **Data Isolation** - Query scoping pada setiap operation
- ✅ **User Workspace Switching** - Seamless workspace transition

---

## 📊 Data Flow Example: Campaign Creation

Mari trace bagaimana campaign creation flow dari user request sampai message delivery:

```
1. USER ACTION
   └─> User creates campaign via Vue.js form
       └─> Form data: template, contacts, schedule

2. CONTROLLER LAYER
   └─> CampaignController@store receives request
       ├─> Validates request data
       ├─> Checks user permissions
       └─> Calls CampaignService

3. SERVICE LAYER
   └─> CampaignService@store
       ├─> DB::transaction starts
       ├─> Creates Campaign record
       ├─> Stores metadata (template parameters)
       ├─> Uploads media files (if any) to storage
       └─> Dispatches CreateCampaignLogsJob

4. BACKGROUND JOB #1
   └─> CreateCampaignLogsJob@handle
       ├─> Fetches ContactGroup contacts
       ├─> Creates CampaignLog per contact (bulk insert)
       └─> Dispatches ProcessCampaignMessagesJob

5. BACKGROUND JOB #2
   └─> ProcessCampaignMessagesJob@handle
       ├─> Fetches pending campaign logs (chunked)
       ├─> Dispatches SendCampaignJob per log
       └─> Implements rate limiting

6. BACKGROUND JOB #3
   └─> SendCampaignJob@handle
       ├─> Builds template with contact variables
       ├─> Calls WhatsappService@sendTemplateMessage
       ├─> Logs API response
       ├─> Updates CampaignLog status
       └─> Broadcasts NewChatEvent (if success)

7. REAL-TIME UPDATE
   └─> NewChatEvent broadcast
       ├─> Pusher sends to workspace channel
       └─> Vue.js component updates chat UI

8. WEBHOOK HANDLING
   └─> WhatsApp Cloud API webhook callback
       ├─> WebhookController receives status update
       ├─> Updates Chat status (sent → delivered → read)
       └─> Broadcasts status change event
```

**Key Observations:**
- **Async Processing** - Heavy operations tidak block user
- **Atomic Operations** - Database transactions ensure consistency
- **Event Broadcasting** - Real-time UI updates
- **Error Handling** - Failed jobs dapat di-retry
- **Status Tracking** - Complete message delivery lifecycle

---

## 🛡️ Security Architecture

### **Authentication & Authorization:**

```php
// Multiple guard support
'guards' => [
    'user' => ['driver' => 'session', 'provider' => 'users'],
    'admin' => ['driver' => 'session', 'provider' => 'admins'],
],

// Role-based access control
$user->teams()->where('workspace_id', $workspaceId)->first()->role;

// API authentication
Route::middleware([AuthenticateBearerToken::class])->group(function () {
    // Protected API routes
});
```

**Security Features:**
- ✅ **Multi-guard Authentication** - User dan Admin separation
- ✅ **RBAC** - Role-based permissions via Team model
- ✅ **2FA Support** - Two-factor authentication (TFA)
- ✅ **API Token Auth** - Workspace-scoped bearer tokens
- ✅ **CSRF Protection** - Laravel built-in protection
- ✅ **SQL Injection Prevention** - Eloquent ORM escaping
- ✅ **XSS Protection** - Output sanitization dengan `clean()` helper
- ✅ **Rate Limiting** - API and route throttling
- ✅ **Audit Logging** - AuditLoggingMiddleware tracks actions

---

## 📈 Scalability Considerations

### **Horizontal Scaling Capabilities:**

1. **Stateless Application**
   - Session storage di Redis/Database
   - No local file dependency (S3 support)
   - Load balancer ready

2. **Queue Workers Scaling**
   - Multiple queue workers dapat run parallel
   - Redis queue driver untuk distributed processing
   - Job-specific queue assignment

3. **Database Optimization**
   - Indexed columns untuk fast queries
   - Query caching via Redis
   - Read replica support ready

4. **Asset Optimization**
   - CDN-ready static assets
   - Vite build optimization
   - Laravel Mix asset versioning

5. **Caching Strategy**
   - Redis caching layer
   - PerformanceCacheService untuk heavy queries
   - Cache tags untuk selective invalidation

---

## 🎯 Kesimpulan Arsitektur

**Blazz menggunakan pendekatan arsitektur yang mature:**

1. ✅ **Enhanced MVC + Service Layer** - Clear separation of concerns
2. ✅ **Job Queue System** - Asynchronous processing capability
3. ✅ **Module Architecture** - Extensible plugin system
4. ✅ **Multi-tenancy Design** - Workspace isolation yang robust
5. ✅ **Event-Driven Communication** - Real-time capabilities
6. ✅ **RESTful API** - External integration support
7. ✅ **Security-First Approach** - Multiple security layers
8. ✅ **Scalability Ready** - Horizontal scaling support

**Tipe Arsitektur:** **Hybrid Service-Oriented Modular Architecture**

Arsitektur ini adalah **highly recommended** untuk:
- ✅ Enterprise applications
- ✅ Multi-tenant SaaS platforms
- ✅ High-volume messaging systems
- ✅ Real-time communication platforms
- ✅ API-first applications

Struktur ini provides **excellent balance** antara:
- Developer experience dan code maintainability
- Feature extensibility dan system stability
- Performance optimization dan development speed
- Security requirements dan user experience

---

**Next Documents:**
- 📋 [02-component-connections.md](./02-component-connections.md) - Detailed component interaction
- 📁 [03-folder-structure.md](./03-folder-structure.md) - Recommended folder organization
- 🚀 [04-feature-development-guide.md](./04-feature-development-guide.md) - How to add new features
- 🎨 [05-service-pattern-guidelines.md](./05-service-pattern-guidelines.md) - Service layer best practices
