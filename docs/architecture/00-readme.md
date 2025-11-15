# 📋 Architecture Summary & Quick Reference

## 🎯 Executive Summary

**Blazz** menggunakan **Hybrid Service-Oriented Modular Architecture** yang menggabungkan:

1. ✅ **Enhanced MVC Pattern** - Foundation layer
2. ✅ **Service Layer Pattern** - Business logic isolation
3. ✅ **Job Queue System** - Asynchronous processing
4. ✅ **Module Architecture** - Feature extensibility
5. ✅ **Multi-tenancy Design** - Workspace isolation
6. ✅ **Event-Driven Communication** - Real-time capabilities

---

## 📚 Documentation Index

### **Core Architecture Documents:**

1. **[01-arsitektur-overview.md](./01-arsitektur-overview.md)**
   - Complete architecture pattern explanation
   - Layer-by-layer responsibilities
   - Design patterns yang digunakan
   - Multi-tenancy architecture
   - Dual-server architecture overview
   - Multi-provider WhatsApp support
   - Security & scalability considerations

2. **[02-component-connections.md](./02-component-connections.md)**
   - How components connect with each other
   - Controller → Service → Model flow
   - Service-to-service communication
   - Job queue chains
   - Event broadcasting patterns
   - External API integration
   - Cross-server communication patterns

3. **[03-folder-structure.md](./03-folder-structure.md)**
   - Recommended folder organization
   - File placement guidelines
   - Naming conventions
   - Dual-server structure (Laravel + Node.js)
   - WhatsApp service organization
   - Scalability considerations
   - Module structure template

4. **[04-feature-development-guide.md](./04-feature-development-guide.md)**
   - Step-by-step new feature implementation
   - Complete example: Auto-Reply System
   - Testing guidelines
   - Development checklist
   - Common patterns
   - Cross-service feature development

5. **[05-visual-diagrams.md](./05-visual-diagrams.md)**
   - Architecture visualization diagrams
   - Data flow diagrams
   - Component interaction maps
   - Request lifecycle visualization
   - Queue processing flows
   - Dual-server communication diagrams

6. **[06-dual-server-architecture.md](./06-dual-server-architecture.md)** 🔥
   - Comprehensive dual-server architecture explanation
   - Laravel + Node.js integration patterns
   - WhatsApp service implementation details
   - Inter-service communication protocols
   - Provider management system
   - Deployment & scaling strategies
   - Performance optimization techniques

7. **[07-development-patterns-guidelines.md](./07-development-patterns-guidelines.md)** 🛠️
   - Complete development workflow & patterns
   - Step-by-step feature implementation guide
   - Code organization & naming conventions
   - Security patterns & best practices
   - Testing strategies & examples
   - Performance optimization guidelines
   - Complete development checklist
   - DO's & DON'Ts for consistent code quality

---

## 🏗️ Architecture Quick Reference

### **Layer Responsibilities:**

```
┌─────────────────────────────────────────────────────────┐
│  PRESENTATION LAYER                                     │
│  - Vue.js 3 Components                                  │
│  - Inertia.js SSR Bridge                                │
│  - REST API Endpoints                                   │
│  Responsibility: User interface & API responses         │
└───────────────────────┬─────────────────────────────────┘
                        │
┌───────────────────────▼─────────────────────────────────┐
│  CONTROLLER LAYER                                       │
│  - User Controllers (app/Http/Controllers/User/)        │
│  - Admin Controllers (app/Http/Controllers/Admin/)      │
│  - API Controller (app/Http/Controllers/ApiController)  │
│  Responsibility: HTTP handling, validation, routing     │
└───────────────────────┬─────────────────────────────────┘
                        │
┌───────────────────────▼─────────────────────────────────┐
│  SERVICE LAYER ⭐                                       │
│  - WhatsappService, CampaignService, ChatService        │
│  - BillingService, WorkspaceService, etc.               │
│  Responsibility: Business logic, API integration        │
└───────────────────────┬─────────────────────────────────┘
                        │
┌───────────────────────▼─────────────────────────────────┐
│  BACKGROUND PROCESSING LAYER                            │
│  - Queue Jobs (Campaign, Import, Export)                │
│  - Event System (NewChatEvent, etc.)                    │
│  Responsibility: Async processing, broadcasting         │
└───────────────────────┬─────────────────────────────────┘
                        │
┌───────────────────────▼─────────────────────────────────┐
│  DATA LAYER                                             │
│  - Eloquent Models (Contact, Chat, Campaign, etc.)      │
│  - Database (MySQL)                                     │
│  - Cache (Redis)                                        │
│  Responsibility: Data persistence & retrieval           │
└─────────────────────────────────────────────────────────┘
```

---

## 🔧 Development Patterns

### **1. Creating New Feature:**

```php
// Step 1: Migration
php artisan make:migration create_auto_replies_table

// Step 2: Model
php artisan make:model AutoReply

// Step 3: Service
// Create: app/Services/AutoReplyService.php

// Step 4: Controller
php artisan make:controller User/AutoReplyController

// Step 5: Form Request
php artisan make:request StoreAutoReplyRequest

// Step 6: Routes (routes/web.php)
Route::resource('auto-replies', User\AutoReplyController::class);

// Step 7: Vue Components (resources/js/Pages/User/AutoReplies/)
// - Index.vue
// - Create.vue
// - Edit.vue
```

---

### **2. Service Pattern Template:**

```php
// app/Services/{Entity}Service.php
<?php

namespace App\Services;

use App\Models\{Entity};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class {Entity}Service
{
    protected $workspaceId;

    public function __construct($workspaceId)
    {
        $this->workspaceId = $workspaceId;
    }

    public function list($search = null, $perPage = 15)
    {
        return {Entity}::where('workspace_id', $this->workspaceId)
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->paginate($perPage);
    }

    public function create(array $data)
    {
        try {
            DB::beginTransaction();

            $entity = {Entity}::create([
                'workspace_id' => $this->workspaceId,
                ...$data,
            ]);

            DB::commit();

            Log::info('{Entity} created', ['id' => $entity->id]);

            return (object) [
                'success' => true,
                'data' => $entity,
                'message' => '{Entity} created successfully!',
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create {entity}', [
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to create {entity}: ' . $e->getMessage(),
            ];
        }
    }

    public function update($uuid, array $data)
    {
        try {
            $entity = {Entity}::where('uuid', $uuid)
                ->where('workspace_id', $this->workspaceId)
                ->firstOrFail();

            $entity->update($data);

            return (object) [
                'success' => true,
                'data' => $entity->fresh(),
                'message' => '{Entity} updated successfully!',
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to update {entity}: ' . $e->getMessage(),
            ];
        }
    }

    public function delete($uuid)
    {
        try {
            $entity = {Entity}::where('uuid', $uuid)
                ->where('workspace_id', $this->workspaceId)
                ->firstOrFail();

            $entity->delete();

            return (object) [
                'success' => true,
                'message' => '{Entity} deleted successfully!',
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to delete {entity}: ' . $e->getMessage(),
            ];
        }
    }
}
```

---

### **3. Controller Pattern Template:**

```php
// app/Http/Controllers/User/{Entity}Controller.php
<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store{Entity}Request;
use App\Http\Requests\Update{Entity}Request;
use App\Services\{Entity}Service;
use Illuminate\Http\Request;
use Inertia\Inertia;

class {Entity}Controller extends Controller
{
    protected ${entity}Service;

    public function __construct()
    {
        $workspaceId = session()->get('current_workspace');
        $this->{entity}Service = new {Entity}Service($workspaceId);
    }

    public function index(Request $request)
    {
        ${entities} = $this->{entity}Service->list($request->query('search'));
        
        return Inertia::render('User/{Entities}/Index', [
            '{entities}' => ${entities},
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Store{Entity}Request $request)
    {
        $result = $this->{entity}Service->create($request->validated());
        
        return redirect()->back()->with('status', [
            'type' => $result->success ? 'success' : 'error',
            'message' => $result->message,
        ]);
    }

    public function update(Update{Entity}Request $request, $uuid)
    {
        $result = $this->{entity}Service->update($uuid, $request->validated());
        
        return redirect()->back()->with('status', [
            'type' => $result->success ? 'success' : 'error',
            'message' => $result->message,
        ]);
    }

    public function destroy($uuid)
    {
        $result = $this->{entity}Service->delete($uuid);
        
        return redirect()->back()->with('status', [
            'type' => $result->success ? 'success' : 'error',
            'message' => $result->message,
        ]);
    }
}
```

---

### **4. Model Pattern Template:**

```php
// app/Models/{Entity}.php
<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class {Entity} extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    // Query Scopes
    public function scopeInWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Accessors
    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at->format('M d, Y');
    }

    // Business Methods
    public function isActive()
    {
        return $this->status === 'active';
    }
}
```

---

### **5. Job Pattern Template:**

```php
// app/Jobs/{Action}{Entity}Job.php
<?php

namespace App\Jobs;

use App\Models\{Entity};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class {Action}{Entity}Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ${entity}Id;
    public $tries = 3;
    public $timeout = 120;
    public $backoff = [30, 60, 120];

    public function __construct(${entity}Id)
    {
        $this->{entity}Id = ${entity}Id;
    }

    public function handle()
    {
        ${entity} = {Entity}::find($this->{entity}Id);
        
        if (!${entity}) {
            Log::warning('{Entity} not found for job', [
                '{entity}_id' => $this->{entity}Id,
            ]);
            return;
        }

        try {
            // Business logic here
            
            Log::info('{Action} {entity} completed', [
                '{entity}_id' => $this->{entity}Id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to {action} {entity}', [
                '{entity}_id' => $this->{entity}Id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw untuk trigger retry
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('{Action} {entity} job failed permanently', [
            '{entity}_id' => $this->{entity}Id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

---

## 🎯 Common Commands

### **Development:**
```bash
# Start development server
php artisan serve

# Watch frontend assets
npm run dev

# Queue worker
php artisan queue:work

# Clear all cache
php artisan optimize:clear
```

### **Database:**
```bash
# Run migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Fresh migration with seeding
php artisan migrate:fresh --seed

# Create migration
php artisan make:migration create_table_name
```

### **Code Generation:**
```bash
# Create controller
php artisan make:controller User/EntityController

# Create model
php artisan make:model Entity

# Create form request
php artisan make:request StoreEntityRequest

# Create job
php artisan make:job ProcessEntityJob

# Create event
php artisan make:event EntityCreatedEvent
```

### **Testing:**
```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=EntityTest

# Run tests with coverage
php artisan test --coverage
```

---

## 🔐 Multi-Tenancy Pattern

### **Always Scope Queries:**

```php
// ✅ GOOD: Scoped by workspace
$contacts = Contact::where('workspace_id', $this->workspaceId)
    ->where('status', 'active')
    ->get();

// ❌ BAD: Global query (security risk!)
$contacts = Contact::where('status', 'active')->get();
```

### **Service Constructor Pattern:**

```php
class ContactService
{
    protected $workspaceId;

    public function __construct($workspaceId)
    {
        $this->workspaceId = $workspaceId;
    }

    // All methods automatically use $this->workspaceId
}
```

### **Controller Instantiation:**

```php
class ContactController extends Controller
{
    protected $contactService;

    public function __construct()
    {
        // Get workspace from session
        $workspaceId = session()->get('current_workspace');
        
        // Inject workspace ID into service
        $this->contactService = new ContactService($workspaceId);
    }
}
```

---

## 📊 Performance Best Practices

### **1. Eager Loading:**

```php
// ✅ GOOD: Eager loading
$campaigns = Campaign::with(['template', 'contactGroup', 'workspace'])
    ->where('workspace_id', $workspaceId)
    ->get();

// ❌ BAD: N+1 query problem
$campaigns = Campaign::where('workspace_id', $workspaceId)->get();
foreach ($campaigns as $campaign) {
    echo $campaign->template->name; // Query per iteration
}
```

### **2. Query Caching:**

```php
use Illuminate\Support\Facades\Cache;

$stats = Cache::remember(
    "dashboard.stats.{$workspaceId}",
    now()->addMinutes(5),
    function() use ($workspaceId) {
        return [
            'contacts' => Contact::where('workspace_id', $workspaceId)->count(),
            'campaigns' => Campaign::where('workspace_id', $workspaceId)->count(),
        ];
    }
);
```

### **3. Chunk Processing:**

```php
// Process large datasets in chunks
Contact::where('workspace_id', $workspaceId)
    ->chunk(100, function ($contacts) {
        foreach ($contacts as $contact) {
            // Process contact
        }
    });
```

---

## 🚨 Error Handling Pattern

### **Service Layer:**

```php
public function create(array $data)
{
    try {
        DB::beginTransaction();

        // Business logic
        
        DB::commit();

        return (object) [
            'success' => true,
            'data' => $entity,
            'message' => 'Created successfully!',
        ];

    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Operation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return (object) [
            'success' => false,
            'message' => 'Operation failed: ' . $e->getMessage(),
        ];
    }
}
```

### **Controller Layer:**

```php
public function store(Request $request)
{
    $result = $this->service->create($request->validated());
    
    return redirect()->back()->with('status', [
        'type' => $result->success ? 'success' : 'error',
        'message' => $result->message,
    ]);
}
```

---

## 🎨 Vue Component Pattern

### **Page Component:**

```vue
<template>
  <AuthenticatedLayout>
    <div class="py-12">
      <!-- Page content -->
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  entities: Object,
  filters: Object,
})

const search = ref(props.filters.search || '')

const handleSearch = () => {
  router.get(route('entities.index'), { search: search.value }, {
    preserveState: true,
    preserveScroll: true,
  })
}
</script>
```

---

## 📝 Documentation Guidelines

### **Code Comments:**

```php
/**
 * Create new campaign dengan template dan contact group
 *
 * @param array $data Campaign data (template_id, contact_group_id, metadata)
 * @return object Result object dengan success status dan message
 * @throws \Exception when campaign creation fails
 */
public function create(array $data)
{
    // Implementation
}
```

### **Service Documentation:**

```php
/**
 * CampaignService
 *
 * Handles all campaign-related operations including:
 * - Campaign creation and scheduling
 * - Template building with contact variables
 * - Bulk message sending via queue
 * - Campaign analytics and reporting
 *
 * @package App\Services
 */
class CampaignService
{
    // Implementation
}
```

---

## 🎯 Architecture Decision Records

### **Why Service Layer Pattern?**
- ✅ Separates business logic from controllers
- ✅ Reusable across different contexts (web, API, console)
- ✅ Easier to test in isolation
- ✅ Supports complex business operations
- ✅ Maintains thin controllers

### **Why Job Queue System?**
- ✅ Non-blocking user experience
- ✅ Handles high-volume operations
- ✅ Automatic retry mechanism
- ✅ API rate limiting compliance
- ✅ Resource management

### **Why Module Architecture?**
- ✅ Feature isolation
- ✅ Independent versioning
- ✅ Easy enable/disable
- ✅ Third-party integrations
- ✅ Scalable development

### **Why Multi-Tenancy (Shared Database)?**
- ✅ Cost-effective untuk SaaS
- ✅ Easier maintenance
- ✅ Simpler deployment
- ✅ Resource sharing efficiency
- ✅ Sufficient data isolation untuk most use cases

---

## 🛠️ Development Quick Reference

### **🎯 Standard Development Workflow:**

```bash
# 1. Planning Phase
# - Define requirements & API contracts
# - Design database schema
# - Identify cross-service impacts

# 2. Database Layer
php artisan make:migration create_{table}_table
php artisan make:model {Model} -mfs

# 3. Service Layer (Business Logic)
# Create: app/Services/{Entity}Service.php

# 4. Controller Layer (HTTP Handling)
php artisan make:controller User/{Entity}Controller
php artisan make:request Store{Entity}Request

# 5. Background Processing (if needed)
php artisan make:job {Action}{Entity}Job

# 6. Frontend Layer
# Create: resources/js/Pages/User/{Entity}/
# - Index.vue, Create.vue, Edit.vue

# 7. Testing
php artisan make:test {Entity}Test
php artisan test --filter={Entity}Test
```

### **📁 Mandatory Code Patterns:**

```php
// ✅ Service (always workspace-scoped)
class AutoReplyService {
    public function __construct($workspaceId) {
        $this->workspaceId = $workspaceId;
    }

    public function create(array $data) {
        try {
            DB::beginTransaction();
            // Business logic here
            DB::commit();

            return (object) [
                'success' => true,
                'data' => $result,
                'message' => 'Success message',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Operation failed', ['error' => $e->getMessage()]);

            return (object) [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}

// ✅ Model (workspace-scoped with scopes)
class AutoReply extends Model {
    use HasUuid, SoftDeletes;

    public function scopeInWorkspace($query, $workspaceId) {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeActive($query) {
        return $query->where('status', 'active');
    }
}

// ✅ Controller (thin logic, delegates to service)
class AutoReplyController extends Controller {
    protected $autoReplyService;

    public function __construct() {
        $this->autoReplyService = new AutoReplyService(
            session()->get('current_workspace')
        );
    }

    public function store(StoreAutoReplyRequest $request) {
        $result = $this->autoReplyService->create($request->validated());

        return redirect()->back()->with('status', [
            'type' => $result->success ? 'success' : 'error',
            'message' => $result->message,
        ]);
    }
}
```

### **🔐 Security Rules (MANDATORY):**

```php
// ✅ ALWAYS scope queries by workspace
AutoReply::where('workspace_id', $this->workspaceId)->get();

// ✅ ALWAYS validate input
$request->validate([
    'keyword' => 'required|string|max:255',
    'message' => 'required|string|max:1000',
]);

// ✅ ALWAYS use parameterized queries (Eloquent handles this)
// NEVER use raw SQL with user input

// ✅ ALWAYS sanitize output
{{ clean($userInput) }} // Use clean() helper
```

### **❌ NEVER DO (Critical Rules):**

- ❌ Create global queries (AutoReply::all())
- ❌ Skip workspace scoping
- ❌ Put business logic in controllers
- ❌ Use eval() or dangerous functions
- ❌ Skip input validation
- ❌ Create N+1 query problems
- ❌ Ignore error handling
- ❌ Mix concerns (UI logic in services)

### **📊 Performance Guidelines:**

```php
// ✅ Prevent N+1 queries
$campaigns = Campaign::with(['template', 'contactGroup'])->get();

// ✅ Use query caching
$stats = Cache::remember("stats.{$workspaceId}", 300, function() {
    return ['total' => Model::count()];
});

// ✅ Process large datasets in chunks
Model::chunk(100, function ($items) {
    foreach ($items as $item) {
        // Process item
    }
});
```

---

## 📚 Further Reading

### **📖 Documentation:**
- **[07-development-patterns-guidelines.md](./07-development-patterns-guidelines.md)** - Complete development guide with patterns & examples
- **Laravel Documentation:** https://laravel.com/docs
- **Inertia.js Documentation:** https://inertiajs.com
- **Vue.js 3 Documentation:** https://vuejs.org
- **Tailwind CSS Documentation:** https://tailwindcss.com

---

## 🤝 Contributing Guidelines

### **Before Adding New Features:**
1. ✅ Review existing architecture patterns
2. ✅ Follow folder structure conventions
3. ✅ Use service layer untuk business logic
4. ✅ Always scope by workspace untuk multi-tenancy
5. ✅ Write tests untuk critical features
6. ✅ Document complex logic
7. ✅ Consider performance implications

### **Code Review Checklist:**
- [ ] Follows architecture patterns
- [ ] Uses service layer appropriately
- [ ] Proper error handling
- [ ] Workspace scoping implemented
- [ ] No N+1 query problems
- [ ] Proper validation
- [ ] Adequate logging
- [ ] Documentation updated

---

## 📞 Support & Questions

Jika ada pertanyaan tentang arsitektur atau implementation patterns, refer to:
1. Documentation files di `docs/architecture/`
2. Existing code examples dalam project
3. Laravel best practices documentation
4. Team discussions/code reviews

---

**Last Updated:** November 10, 2025
**Architecture Version:** v2.0 (Dual-Server)
**Laravel Version:** 12.29.0
**Node.js Version:** 18+
**Key Feature:** Multi-Provider WhatsApp Integration
