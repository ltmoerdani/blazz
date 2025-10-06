# 📊 Arsitektur Proyek Blazz - Executive Summary

**Document Created:** October 6, 2025  
**Project:** Blazz - Enterprise WhatsApp Business Platform  
**Laravel Version:** 12.29.0  
**Architecture Type:** Hybrid Service-Oriented Modular Architecture

---

## 🎯 Tipe Arsitektur yang Digunakan

**Blazz menggunakan:** **Hybrid Service-Oriented Modular Architecture**

Kombinasi dari:
1. ✅ **Enhanced MVC Pattern** (Foundation)
2. ✅ **Service Layer Pattern** (Business Logic)
3. ✅ **Job Queue System** (Async Processing)
4. ✅ **Module Architecture** (Extensibility)
5. ✅ **Multi-tenancy Design** (Workspace Isolation)
6. ✅ **Event-Driven Communication** (Real-time)

---

## 🏗️ Struktur Layer Utama

### **1. Presentation Layer**
- **Technology:** Vue.js 3, Inertia.js, Tailwind CSS
- **Responsibility:** User interface, API responses
- **Location:** `resources/js/`

### **2. Controller Layer**
- **Pattern:** Thin controllers, delegates to services
- **Responsibility:** HTTP handling, validation, routing
- **Location:** `app/Http/Controllers/`
- **Types:** User Controllers, Admin Controllers, API Controller, Webhook Controller

### **3. Service Layer** ⭐ (Core Business Logic)
- **Pattern:** Service classes dengan workspace context
- **Responsibility:** Business logic, external API integration, data operations
- **Location:** `app/Services/`
- **Key Services:**
  - WhatsappService - WhatsApp Cloud API integration
  - CampaignService - Campaign management & automation
  - ChatService - Real-time messaging logic
  - BillingService - Subscription & billing
  - WorkspaceService - Multi-tenant management

### **4. Background Processing Layer**
- **Technology:** Laravel Queue, Redis
- **Responsibility:** Async operations, heavy processing
- **Location:** `app/Jobs/`
- **Key Jobs:**
  - CreateCampaignLogsJob
  - ProcessCampaignMessagesJob
  - SendCampaignJob
  - Import/Export jobs

### **5. Data Layer**
- **Technology:** Eloquent ORM, MySQL 8.0+, Redis Cache
- **Responsibility:** Data persistence, relationships
- **Location:** `app/Models/`
- **Features:**
  - UUID primary keys
  - Rich relationships
  - Query scopes
  - Soft deletes
  - Timezone handling

### **6. Module Layer**
- **Pattern:** Plugin architecture
- **Responsibility:** Feature extensions, third-party integrations
- **Location:** `modules/`
- **Examples:** EmbeddedSignup, Razorpay, Custom modules

---

## 🔗 Bagaimana Komponen Terhubung

### **Request Flow (Synchronous):**
```
User Action (Vue.js)
  ↓
HTTP Request
  ↓
Middleware Stack (Auth, Validation, Rate Limit)
  ↓
Controller (Thin, delegates to service)
  ↓
Service Layer (Business logic execution)
  ↓
Model/Database (Data operations)
  ↓
Response (Inertia view or JSON)
  ↓
UI Update (Reactive)
```

### **Background Processing Flow (Asynchronous):**
```
User Action → Campaign Created
  ↓
Service dispatches CreateCampaignLogsJob
  ↓
Job creates logs (bulk insert)
  ↓
Dispatches ProcessCampaignMessagesJob
  ↓
Dispatches SendCampaignJob (×100 parallel)
  ↓
Each job calls WhatsApp API
  ↓
Creates Chat records
  ↓
Broadcasts NewChatEvent (real-time)
  ↓
UI updates automatically
```

### **Service-to-Service Communication:**
```
CampaignService
  ├→ calls TemplateService (build template)
  ├→ calls MediaService (upload files)
  └→ calls WhatsappService (send messages)
      └→ calls ChatService (create records)
```

### **Real-time Broadcasting:**
```
Server Event Trigger (New chat message)
  ↓
broadcast(new NewChatEvent($chat))
  ↓
Pusher/Laravel Echo
  ↓
Private workspace channel
  ↓
Vue.js Echo listener
  ↓
UI updates reactively
```

---

## 📁 Struktur Folder yang Direkomendasikan

### **Backend (Laravel):**
```
app/
├── Console/Commands/          → Artisan commands
├── Constants/                 → Application constants
├── Events/                    → Event classes
├── Exceptions/                → Custom exceptions
├── Http/
│   ├── Controllers/
│   │   ├── Admin/            → Admin controllers
│   │   ├── User/             → User controllers
│   │   └── ApiController.php
│   ├── Middleware/           → HTTP middleware
│   └── Requests/             → Form validation
├── Jobs/                     → Queue jobs
│   ├── Campaign/
│   ├── Import/
│   └── Export/
├── Models/                   → Eloquent models
├── Services/                 → Business logic services 🔥
│   ├── Messaging/
│   ├── Campaign/
│   ├── Contact/
│   ├── Billing/
│   └── Core/
└── Traits/                   → Reusable traits
```

### **Frontend (Vue.js):**
```
resources/js/
├── Components/               → Reusable components
│   ├── UI/
│   ├── Chat/
│   └── Campaign/
├── Composables/             → Vue composables
├── Pages/                   → Inertia pages
│   ├── Auth/
│   ├── User/
│   │   ├── Dashboard.vue
│   │   ├── Chat/
│   │   ├── Campaigns/
│   │   └── Contacts/
│   └── Admin/
└── Layouts/                 → Page layouts
```

### **Modules (Extensions):**
```
modules/
├── EmbeddedSignup/
│   ├── Controllers/
│   ├── Services/
│   ├── Providers/
│   └── routes.php
└── Razorpay/
    └── (similar structure)
```

---

## 🎨 Treatment Fitur & Model

### **Adding New Feature - Standard Flow:**

#### **1. Database Layer**
```bash
# Create migration
php artisan make:migration create_auto_replies_table

# Define schema
Schema::create('auto_replies', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('workspace_id')->constrained();
    $table->string('keyword');
    $table->text('message');
    $table->timestamps();
});

# Create model
php artisan make:model AutoReply
```

#### **2. Service Layer** (Business Logic)
```php
// app/Services/AutoReplyService.php
class AutoReplyService
{
    protected $workspaceId;

    public function __construct($workspaceId)
    {
        $this->workspaceId = $workspaceId;
    }

    public function create(array $data)
    {
        try {
            $autoReply = AutoReply::create([
                'workspace_id' => $this->workspaceId,
                ...$data,
            ]);

            return (object) [
                'success' => true,
                'data' => $autoReply,
                'message' => 'Created successfully!',
            ];
        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
```

#### **3. Controller Layer** (HTTP Handling)
```php
// app/Http/Controllers/User/AutoReplyController.php
class AutoReplyController extends Controller
{
    protected $autoReplyService;

    public function __construct()
    {
        $workspaceId = session()->get('current_workspace');
        $this->autoReplyService = new AutoReplyService($workspaceId);
    }

    public function store(StoreAutoReplyRequest $request)
    {
        $result = $this->autoReplyService->create($request->validated());
        
        return redirect()->back()->with('status', [
            'type' => $result->success ? 'success' : 'error',
            'message' => $result->message,
        ]);
    }
}
```

#### **4. Routes**
```php
// routes/web.php
Route::middleware(['auth:user'])->group(function () {
    Route::resource('auto-replies', User\AutoReplyController::class);
});
```

#### **5. Frontend (Vue.js)**
```vue
<!-- resources/js/Pages/User/AutoReplies/Index.vue -->
<template>
  <div>
    <!-- List, create, edit components -->
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  keyword: '',
  message: '',
})

const submit = () => {
  form.post(route('auto-replies.store'))
}
</script>
```

---

## 🔐 Multi-Tenancy Implementation

### **Pattern: Session-based Workspace Context**

```php
// 1. User logs in dan selects workspace
session(['current_workspace' => $workspace->id]);

// 2. Controller gets workspace context
$workspaceId = session()->get('current_workspace');

// 3. Service instantiation dengan workspace ID
$service = new ContactService($workspaceId);

// 4. Service automatically scopes all queries
Contact::where('workspace_id', $this->workspaceId)->get();
```

### **Data Isolation:**
- Setiap table punya `workspace_id` foreign key
- Setiap query WAJIB di-scope by workspace
- API tokens juga di-scope by workspace
- UI hanya menampilkan data dari current workspace

---

## ⚡ Background Processing

### **When to Use Jobs:**
- ✅ Bulk operations (campaign sending)
- ✅ Heavy computations (report generation)
- ✅ External API calls (rate-limited)
- ✅ File processing (import/export)
- ✅ Email sending

### **Job Pattern:**
```php
// app/Jobs/SendCampaignJob.php
class SendCampaignJob implements ShouldQueue
{
    public $tries = 3;
    public $timeout = 120;
    public $backoff = [30, 60, 120];

    public function handle()
    {
        // Business logic
        // Automatic retry on failure
    }

    public function failed(\Throwable $exception)
    {
        // Handle permanent failure
    }
}

// Dispatch job
SendCampaignJob::dispatch($campaignLogId)
    ->onQueue('campaigns')
    ->delay(now()->addSeconds(5));
```

---

## 📊 Management Proyek

### **Development Workflow:**

1. **Feature Planning**
   - Define requirements
   - Design database schema
   - Plan API endpoints
   - Sketch UI/UX

2. **Backend Development**
   - Create migrations
   - Define models dengan relationships
   - Implement service layer
   - Create controllers
   - Write tests

3. **Frontend Development**
   - Create Vue components
   - Build Inertia pages
   - Implement real-time features
   - Style dengan Tailwind

4. **Testing**
   - Unit tests untuk services
   - Feature tests untuk controllers
   - Manual testing

5. **Deployment**
   - Run migrations
   - Clear cache
   - Queue workers restart
   - Monitor errors

### **Code Organization Principles:**

✅ **DO:**
- Keep controllers thin (delegate to services)
- Put business logic dalam services
- Always scope by workspace untuk multi-tenancy
- Use jobs untuk heavy/async operations
- Write tests untuk critical features
- Document complex logic
- Follow naming conventions

❌ **DON'T:**
- Put business logic dalam controllers
- Make global queries (tanpa workspace scope)
- Block user dengan long-running operations
- Ignore error handling
- Skip validation
- Create deeply nested structures

### **Team Collaboration:**

**File Ownership:**
- **Backend Developers:** Services, Models, Jobs, Controllers
- **Frontend Developers:** Vue Components, Pages, Composables
- **Full-stack:** Integration, Testing, Documentation

**Git Workflow:**
- Feature branches: `feature/auto-reply-system`
- Bug fixes: `fix/campaign-sending-error`
- Hotfixes: `hotfix/security-patch`

---

## 🎯 Key Design Decisions

### **Why Service Layer?**
✅ Separates business logic dari controllers  
✅ Reusable across contexts (web, API, console)  
✅ Easier to test  
✅ Supports complex operations  
✅ Better maintainability  

### **Why Job Queue?**
✅ Non-blocking user experience  
✅ Handles high-volume operations  
✅ Automatic retry mechanism  
✅ API rate limiting compliance  
✅ Resource management  

### **Why Multi-Tenancy (Shared Database)?**
✅ Cost-effective untuk SaaS  
✅ Easier maintenance  
✅ Simpler deployment  
✅ Resource sharing efficiency  
✅ Sufficient isolation untuk most use cases  

### **Why Module System?**
✅ Feature isolation  
✅ Independent versioning  
✅ Easy enable/disable  
✅ Third-party integrations  
✅ Scalable development  

---

## 📚 Documentation References

**Complete Documentation Available:**

1. **[Architecture Overview](./01-arsitektur-overview.md)**  
   Complete explanation tentang architecture patterns, layers, dan design decisions

2. **[Component Connections](./02-component-connections.md)**  
   Detailed explanation tentang bagaimana components interact dengan each other

3. **[Folder Structure](./03-folder-structure.md)**  
   Recommended folder organization dan file placement guidelines

4. **[Feature Development Guide](./04-feature-development-guide.md)**  
   Step-by-step guide untuk adding new features dengan complete example

5. **[Visual Diagrams](./05-visual-diagrams.md)**  
   Architecture visualization diagrams untuk easier understanding

6. **[README.md](./README.md)**  
   Quick reference guide dengan common patterns dan templates

---

## 🚀 Quick Start Commands

```bash
# Development
php artisan serve
npm run dev
php artisan queue:work

# Database
php artisan migrate
php artisan db:seed

# Code Generation
php artisan make:controller User/EntityController
php artisan make:model Entity
php artisan make:job ProcessEntityJob
php artisan make:request StoreEntityRequest

# Testing
php artisan test
php artisan test --filter=EntityTest

# Cache
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

---

## 🎉 Kesimpulan

**Blazz Architecture:**
- ✅ **Well-structured** dengan clear separation of concerns
- ✅ **Scalable** untuk enterprise-level applications
- ✅ **Maintainable** dengan consistent patterns
- ✅ **Testable** architecture
- ✅ **Extensible** melalui module system
- ✅ **Secure** dengan multi-layer security
- ✅ **Modern** menggunakan latest tech stack

**Recommended Untuk:**
- Enterprise SaaS applications
- Multi-tenant platforms
- High-volume messaging systems
- Real-time communication platforms
- API-first applications

**Arsitektur ini provides excellent balance antara:**
- Developer experience ↔ Code maintainability
- Feature extensibility ↔ System stability
- Performance optimization ↔ Development speed
- Security requirements ↔ User experience

---

**Last Updated:** October 6, 2025  
**Document Version:** 1.0  
**Author:** Architecture Analysis Team
