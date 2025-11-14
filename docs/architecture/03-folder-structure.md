# 📁 Struktur Folder yang Direkomendasikan

## Overview

Document ini provides **comprehensive folder structure** untuk Blazz project, explaining **organization principles**, **file placement guidelines**, dan **scalability considerations**.

---

## 🎯 Current vs Recommended Structure

### **Current Structure (Enhanced MVC + Service Layer)**

```
blazz/
├── app/
│   ├── Console/              → Artisan commands
│   ├── Constants/            → Application constants
│   ├── Events/               → Event classes
│   ├── Exceptions/           → Custom exceptions
│   ├── Exports/              → Excel export classes
│   ├── Helpers/              → Helper functions
│   ├── Http/
│   │   ├── Controllers/      → HTTP controllers
│   │   │   ├── Admin/        → Admin controllers
│   │   │   └── User/         → User controllers
│   │   ├── Middleware/       → HTTP middleware
│   │   └── Requests/         → Form request validation
│   ├── Imports/              → Excel import classes
│   ├── Jobs/                 → Queue jobs
│   ├── Mail/                 → Mailable classes
│   ├── Models/               → Eloquent models
│   ├── Providers/            → Service providers
│   ├── Resolvers/            → Custom resolvers
│   ├── Rules/                → Custom validation rules
│   ├── Services/             → Business logic services
│   │   └── WhatsApp/         → WhatsApp integration services
│   │       ├── Adapters/     → Provider adapters (Meta, WebJS)
│   │       ├── Http/         → WhatsApp HTTP client
│   │       └── Webhook/      → Webhook handlers
│   └── Traits/               → Reusable traits
├── whatsapp-service/         → Node.js WhatsApp service
│   ├── src/
│   │   ├── adapters/         → Provider adapters
│   │   ├── services/         → WhatsApp services
│   │   ├── models/           → Data models
│   │   └── utils/            → Utility functions
│   ├── config/               → Service configuration
│   ├── dist/                 → Compiled JavaScript
│   ├── package.json          → Node.js dependencies
│   └── tsconfig.json         → TypeScript configuration
├── bootstrap/
├── config/                   → Configuration files
├── database/
│   ├── factories/            → Model factories
│   ├── migrations/           → Database migrations
│   └── seeders/              → Database seeders
├── docs/                     → Documentation
│   └── architecture/         → Architecture docs
├── modules/                  → Modular extensions
│   ├── EmbeddedSignup/
│   └── Razorpay/
├── public/                   → Web-accessible files
├── resources/
│   ├── css/                  → Stylesheets
│   ├── js/
│   │   ├── Components/       → Vue components
│   │   ├── Composables/      → Vue composables
│   │   └── Pages/            → Inertia pages
│   ├── views/                → Blade templates
│   └── lang/                 → Translation files
├── routes/
│   ├── api.php              → API routes
│   ├── channels.php         → Broadcast channels
│   ├── console.php          → Console commands
│   └── web.php              → Web routes
├── storage/                  → Application storage
└── tests/                    → Test files
```

---

## 📂 Detailed Folder Structure & Guidelines

### **1. `/app` - Application Core**

#### **`/app/Console`** - Artisan Commands
```
app/Console/
├── Commands/
│   ├── CheckModuleUpdates.php      → Check for module updates
│   ├── CleanupExpiredSessions.php  → Session cleanup
│   └── GenerateMonthlyReports.php  → Monthly report generation
└── Kernel.php                       → Command scheduler registration
```

**Purpose:** Custom artisan commands untuk administrative tasks, scheduled jobs, dan maintenance operations.

**Naming Convention:**
- Verb-based names: `SendReminderEmail.php`, `CleanupOldLogs.php`
- Descriptive signatures: `command:action`

**Example:**
```php
// app/Console/Commands/CleanupExpiredSessions.php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupExpiredSessions extends Command
{
    protected $signature = 'sessions:cleanup {--days=30 : Days to keep}';
    protected $description = 'Clean up expired user sessions';

    public function handle()
    {
        $days = $this->option('days');
        // Cleanup logic
        $this->info("Cleaned up sessions older than {$days} days");
    }
}
```

---

#### **`/app/Constants`** - Application Constants
```
app/Constants/
├── RouteConstants.php        → Route path constants
├── StatusConstants.php       → Status string constants
├── RoleConstants.php         → User role constants
└── PermissionConstants.php   → Permission constants
```

**Purpose:** Centralized constant definitions untuk avoid magic strings dalam code.

**Example:**
```php
// app/Constants/StatusConstants.php
namespace App\Constants;

class StatusConstants
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    
    const CHAT_STATUS_SENT = 'sent';
    const CHAT_STATUS_DELIVERED = 'delivered';
    const CHAT_STATUS_READ = 'read';
    
    public static function getCampaignStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_SUCCESS,
            self::STATUS_FAILED,
        ];
    }
}
```

---

#### **`/app/Events`** - Event Classes
```
app/Events/
├── NewChatEvent.php              → New chat message event
├── CampaignCompletedEvent.php    → Campaign completion event
├── ContactCreatedEvent.php       → New contact event
└── SubscriptionExpiredEvent.php  → Subscription expiry event
```

**Purpose:** Events untuk real-time broadcasting dan loosely-coupled component communication.

**Naming Convention:** `{Entity}{Action}Event.php`

**Example:**
```php
// app/Events/NewChatEvent.php
namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

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

    public function broadcastAs()
    {
        return 'chat.new';
    }
}
```

---

#### **`/app/Exceptions`** - Custom Exceptions
```
app/Exceptions/
├── Handler.php                      → Global exception handler
├── SecurityDisabledException.php    → Security-related exception
├── WorkspaceNotFoundException.php   → Workspace not found
└── InsufficientCreditsException.php → Billing exception
```

**Purpose:** Custom exception classes untuk specific error scenarios.

**Example:**
```php
// app/Exceptions/InsufficientCreditsException.php
namespace App\Exceptions;

use Exception;

class InsufficientCreditsException extends Exception
{
    protected $message = 'Insufficient credits to perform this action';
    protected $code = 402;

    public function render($request)
    {
        return response()->json([
            'error' => $this->message,
            'credits_required' => $this->creditsRequired ?? 0,
        ], $this->code);
    }
}
```

---

#### **`/app/Http/Controllers`** - HTTP Controllers

**Organization Pattern:**
```
app/Http/Controllers/
├── Controller.php                  → Base controller
├── ApiController.php               → API endpoints controller
├── AuthController.php              → Authentication controller
├── WebhookController.php           → Webhook handler
│
├── Admin/                          → Admin panel controllers
│   ├── DashboardController.php
│   ├── UserController.php
│   ├── SettingController.php
│   └── BillingController.php
│
└── User/                           → User panel controllers
    ├── DashboardController.php
    ├── ChatController.php
    ├── CampaignController.php
    ├── ContactController.php
    ├── TemplateController.php
    └── WorkspaceController.php
```

**Controller Responsibilities:**
- ✅ HTTP request handling
- ✅ Input validation
- ✅ Service orchestration
- ✅ Response formatting

**Thin Controller Example:**
```php
// app/Http/Controllers/User/ContactController.php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\ContactService;
use App\Http\Requests\StoreContactRequest;

class ContactController extends Controller
{
    protected $contactService;

    public function __construct()
    {
        $this->contactService = new ContactService(
            session()->get('current_workspace')
        );
    }

    public function index(Request $request)
    {
        $contacts = $this->contactService->list($request->query('search'));
        
        return Inertia::render('User/Contacts/Index', [
            'contacts' => $contacts,
        ]);
    }

    public function store(StoreContactRequest $request)
    {
        $contact = $this->contactService->create($request->validated());
        
        return redirect()->back()->with('success', 'Contact created!');
    }

    public function destroy($uuid)
    {
        $this->contactService->delete($uuid);
        
        return redirect()->back()->with('success', 'Contact deleted!');
    }
}
```

---

#### **`/app/Http/Middleware`** - HTTP Middleware
```
app/Http/Middleware/
├── Authenticate.php                     → Authentication check
├── AuthenticateBearerToken.php         → API token auth
├── WorkspaceContextMiddleware.php      → Workspace scoping
├── RateLimitMiddleware.php             → Rate limiting
├── AuditLoggingMiddleware.php          → Action logging
└── SecurityHeadersMiddleware.php       → Security headers
```

**Purpose:** Request/response filtering dan pre-processing.

**Example:**
```php
// app/Http/Middleware/WorkspaceContextMiddleware.php
namespace App\Http\Middleware;

use Closure;

class WorkspaceContextMiddleware
{
    public function handle($request, Closure $next)
    {
        $workspaceId = session()->get('current_workspace');
        
        if (!$workspaceId) {
            return redirect('/workspaces/select');
        }
        
        // Set workspace context globally
        config(['app.current_workspace' => $workspaceId]);
        
        return $next($request);
    }
}
```

---

#### **`/app/Http/Requests`** - Form Request Validation
```
app/Http/Requests/
├── StoreContactRequest.php
├── UpdateContactRequest.php
├── StoreCampaignRequest.php
├── StoreTemplateRequest.php
└── UpdateWorkspaceRequest.php
```

**Purpose:** Centralized validation logic separation dari controllers.

**Example:**
```php
// app/Http/Requests/StoreContactRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'required|phone:AUTO|unique:contacts,phone',
            'email' => 'nullable|email',
            'contact_group_id' => 'nullable|exists:contact_groups,id',
            'custom_fields' => 'nullable|array',
        ];
    }

    public function messages()
    {
        return [
            'phone.unique' => 'This phone number already exists in your contacts.',
            'phone.phone' => 'Please provide a valid phone number.',
        ];
    }
}
```

---

#### **`/app/Jobs`** - Queue Jobs
```
app/Jobs/
├── Campaign/
│   ├── CreateCampaignLogsJob.php
│   ├── ProcessCampaignMessagesJob.php
│   ├── SendCampaignJob.php
│   └── RetryCampaignLogJob.php
│
├── Import/
│   ├── ImportContactsJob.php
│   └── ImportContactGroupsJob.php
│
├── Export/
│   ├── ExportContactsJob.php
│   └── ExportCampaignReportJob.php
│
└── Notification/
    ├── SendEmailNotificationJob.php
    └── SendWhatsappNotificationJob.php
```

**Purpose:** Background processing tasks dengan retry mechanism.

**Job Design Principles:**
- ✅ Idempotent operations (safe to retry)
- ✅ Small, focused responsibilities
- ✅ Proper timeout and retry configuration
- ✅ Comprehensive error handling

**Example:**
```php
// app/Jobs/Campaign/SendCampaignJob.php
namespace App\Jobs\Campaign;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $campaignLogId;
    public $tries = 3;
    public $timeout = 120;
    public $backoff = [30, 60, 120]; // Retry delays in seconds

    public function __construct($campaignLogId)
    {
        $this->campaignLogId = $campaignLogId;
    }

    public function handle()
    {
        $campaignLog = CampaignLog::find($this->campaignLogId);
        
        if (!$campaignLog || $campaignLog->status !== 'pending') {
            return; // Already processed or invalid
        }

        try {
            $result = app(WhatsappService::class)->sendCampaignMessage($campaignLog);
            
            $campaignLog->update([
                'status' => 'success',
                'sent_at' => now(),
            ]);
            
        } catch (\Exception $e) {
            $campaignLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            
            throw $e; // Re-throw untuk trigger retry
        }
    }

    public function failed(\Throwable $exception)
    {
        // Handle final failure after all retries
        Log::error('Campaign send failed permanently', [
            'campaign_log_id' => $this->campaignLogId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

---

#### **`/app/Models`** - Eloquent Models
```
app/Models/
├── User.php
├── Workspace.php
├── Team.php
├── Contact.php
├── ContactGroup.php
├── Chat.php
├── ChatMedia.php
├── Campaign.php
├── CampaignLog.php
├── Template.php
├── Subscription.php
└── Setting.php
```

**Model Responsibilities:**
- ✅ Database table representation
- ✅ Eloquent relationships
- ✅ Accessors/Mutators
- ✅ Query scopes
- ✅ Model events

**Rich Model Example:**
```php
// app/Models/Contact.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $guarded = [];
    
    protected $casts = [
        'custom_fields' => 'array',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contactGroups()
    {
        return $this->belongsToMany(ContactGroup::class, 'contact_contact_group');
    }

    public function chats()
    {
        return $this->hasMany(Chat::class)->orderBy('created_at', 'desc');
    }

    // Accessors
    public function getFormattedPhoneAttribute()
    {
        return phone($this->phone, 'INTERNATIONAL');
    }

    // Query Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    // Business Methods
    public function getLastChatDate()
    {
        return $this->chats()->latest()->first()?->created_at;
    }

    public function hasUnreadMessages()
    {
        return $this->chats()
            ->where('direction', 'incoming')
            ->where('is_read', false)
            ->exists();
    }
}
```

---

#### **`/app/Services`** - Business Logic Services

**Recommended Organization:**
```
app/Services/
├── Authentication/
│   ├── AuthService.php
│   ├── PasswordResetService.php
│   └── SocialLoginService.php
│
├── Messaging/
│   ├── WhatsappService.php
│   ├── ChatService.php
│   └── TemplateService.php
│
├── Campaign/
│   ├── CampaignService.php
│   └── CampaignAnalyticsService.php
│
├── Contact/
│   ├── ContactService.php
│   ├── ContactGroupService.php
│   └── ContactImportService.php
│
├── Billing/
│   ├── BillingService.php
│   ├── SubscriptionService.php
│   └── InvoiceService.php
│
├── Payment/
│   ├── StripeService.php
│   ├── PayPalService.php
│   └── RazorPayService.php
│
└── Core/
    ├── WorkspaceService.php
    ├── SettingService.php
    └── NotificationService.php
```

**Service Design Pattern:**
```php
// app/Services/Messaging/ChatService.php
namespace App\Services\Messaging;

use App\Models\Chat;
use App\Models\Contact;
use App\Events\NewChatEvent;

class ChatService
{
    protected $workspaceId;

    public function __construct($workspaceId)
    {
        $this->workspaceId = $workspaceId;
    }

    public function sendMessage($contactUuid, $message, $type = 'text')
    {
        $contact = $this->findContact($contactUuid);
        
        $chat = $this->createChat($contact, $message, $type);
        
        $this->sendToWhatsapp($chat);
        
        broadcast(new NewChatEvent($chat))->toOthers();
        
        return $chat;
    }

    protected function findContact($uuid)
    {
        return Contact::where('uuid', $uuid)
            ->where('workspace_id', $this->workspaceId)
            ->firstOrFail();
    }

    protected function createChat($contact, $message, $type)
    {
        return Chat::create([
            'workspace_id' => $this->workspaceId,
            'contact_id' => $contact->id,
            'user_id' => auth()->id(),
            'message' => $message,
            'type' => $type,
            'direction' => 'outgoing',
            'status' => 'pending',
            'created_at' => now(),
        ]);
    }

    protected function sendToWhatsapp($chat)
    {
        $whatsappService = app(WhatsappService::class);
        return $whatsappService->sendMessage($chat);
    }
}
```

---

#### **`/app/Traits`** - Reusable Traits
```
app/Traits/
├── HasUuid.php              → UUID generation trait
├── TemplateTrait.php        → Template building logic
├── MediaUploadTrait.php     → Media handling trait
└── AuditLogTrait.php        → Audit logging trait
```

**Purpose:** Shared functionality yang bisa digunakan across multiple classes.

**Example:**
```php
// app/Traits/HasUuid.php
namespace App\Traits;

use Illuminate\Support\Str;

trait HasUuid
{
    protected static function bootHasUuid()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
```

---

### **2. `/config` - Configuration Files**

```
config/
├── app.php              → Application configuration
├── database.php         → Database connections
├── queue.php            → Queue configuration
├── broadcasting.php     → Broadcasting settings
├── services.php         → Third-party services
├── filesystems.php      → Storage configuration
├── whatsapp.php         → WhatsApp API settings (custom)
└── billing.php          → Billing settings (custom)
```

**Custom Configuration Example:**
```php
// config/whatsapp.php
return [
    'api_version' => env('WHATSAPP_API_VERSION', 'v18.0'),
    'graph_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com'),
    
    'rate_limits' => [
        'messages_per_second' => 80,
        'messages_per_day' => 250000,
    ],
    
    'webhook' => [
        'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'secret' => env('WHATSAPP_WEBHOOK_SECRET'),
    ],
];
```

---

### **3. `/database` - Database Files**

```
database/
├── factories/
│   ├── UserFactory.php
│   ├── ContactFactory.php
│   └── ChatFactory.php
│
├── migrations/
│   ├── 2024_01_01_000001_create_users_table.php
│   ├── 2024_01_01_000002_create_workspaces_table.php
│   ├── 2024_01_01_000003_create_contacts_table.php
│   └── ... (timestamp-ordered migrations)
│
└── seeders/
    ├── DatabaseSeeder.php
    ├── RoleSeeder.php
    ├── SettingSeeder.php
    └── DemoDataSeeder.php
```

**Migration Best Practices:**
- ✅ One table per migration
- ✅ Descriptive names: `create_{table}_table`, `add_{column}_to_{table}`
- ✅ Use `up()` and `down()` methods
- ✅ Add indexes for foreign keys dan frequently queried columns

---

### **4. `/modules` - Modular Extensions**

```
modules/
├── EmbeddedSignup/
│   ├── Controllers/
│   │   └── EmbeddedSignupController.php
│   ├── Services/
│   │   └── EmbeddedSignupService.php
│   ├── Models/              (optional)
│   ├── Migrations/          (optional)
│   ├── Providers/
│   │   └── EmbeddedSignupServiceProvider.php
│   ├── routes.php
│   └── config.php
│
├── Razorpay/
│   ├── Controllers/
│   ├── Services/
│   ├── Providers/
│   └── routes.php
│
└── CustomModule/            → Template for new modules
    ├── README.md
    ├── Controllers/
    ├── Services/
    ├── Providers/
    └── routes.php
```

**Module Structure Template:**
```php
// modules/CustomModule/Providers/CustomModuleServiceProvider.php
namespace Modules\CustomModule\Providers;

use Illuminate\Support\ServiceProvider;

class CustomModuleServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('custom-module', function($app) {
            return new CustomModuleService();
        });
    }

    public function boot()
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        
        // Load config
        $this->mergeConfigFrom(__DIR__.'/../config.php', 'custom-module');
    }
}
```

---

### **5. `/resources` - Frontend Resources**

```
resources/
├── css/
│   ├── app.css              → Main stylesheet
│   └── admin.css            → Admin-specific styles
│
├── js/
│   ├── app.js               → Main JavaScript entry
│   ├── bootstrap.js         → Laravel Echo, Axios setup
│   │
│   ├── Components/          → Reusable Vue components
│   │   ├── UI/
│   │   │   ├── Button.vue
│   │   │   ├── Modal.vue
│   │   │   ├── Dropdown.vue
│   │   │   └── Pagination.vue
│   │   │
│   │   ├── Chat/
│   │   │   ├── ChatList.vue
│   │   │   ├── ChatMessage.vue
│   │   │   ├── MessageComposer.vue
│   │   │   └── MediaPreview.vue
│   │   │
│   │   ├── Campaign/
│   │   │   ├── CampaignForm.vue
│   │   │   ├── CampaignStats.vue
│   │   │   └── TemplateSelector.vue
│   │   │
│   │   └── Contact/
│   │       ├── ContactTable.vue
│   │       ├── ContactForm.vue
│   │       └── ContactImport.vue
│   │
│   ├── Composables/         → Vue composables
│   │   ├── useChat.js
│   │   ├── useCampaign.js
│   │   └── useNotification.js
│   │
│   └── Pages/               → Inertia pages
│       ├── Auth/
│       │   ├── Login.vue
│       │   ├── Register.vue
│       │   └── ForgotPassword.vue
│       │
│       ├── User/
│       │   ├── Dashboard.vue
│       │   ├── Chat/
│       │   │   └── Index.vue
│       │   ├── Campaigns/
│       │   │   ├── Index.vue
│       │   │   ├── Create.vue
│       │   │   └── Show.vue
│       │   ├── Contacts/
│       │   │   ├── Index.vue
│       │   │   ├── Create.vue
│       │   │   └── Edit.vue
│       │   └── Settings/
│       │       ├── Profile.vue
│       │       └── Workspace.vue
│       │
│       └── Admin/
│           ├── Dashboard.vue
│           ├── Users/
│           ├── Settings/
│           └── Billing/
│
├── views/                   → Blade templates
│   ├── app.blade.php        → Main layout
│   ├── emails/              → Email templates
│   └── pdf/                 → PDF templates
│
└── lang/                    → Translation files
    ├── en.json
    ├── id.json
    └── en/
        ├── auth.php
        ├── validation.php
        └── messages.php
```

---

### **6. `/routes` - Route Files**

```
routes/
├── web.php              → Web routes (Inertia pages)
├── api.php              → API routes
├── channels.php         → Broadcasting channels
└── console.php          → Artisan commands
```

**Route Organization Best Practices:**

```php
// routes/web.php
Route::middleware(['guest'])->group(function () {
    // Authentication routes
    Route::get('/login', [AuthController::class, 'showLoginForm']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['auth:user'])->group(function () {
    // User routes
    Route::prefix('user')->name('user.')->group(function () {
        Route::get('/dashboard', [User\DashboardController::class, 'index'])->name('dashboard');
        
        // Chat routes
        Route::prefix('chats')->name('chats.')->group(function () {
            Route::get('/', [User\ChatController::class, 'index'])->name('index');
            Route::post('/send', [User\ChatController::class, 'sendMessage'])->name('send');
        });
        
        // Campaign routes
        Route::resource('campaigns', User\CampaignController::class);
    });
});

Route::middleware(['auth:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Admin routes
});
```

---

## 🎯 File Naming Conventions

### **PHP Files:**
- Controllers: `{Entity}Controller.php` → `ContactController.php`
- Services: `{Entity}Service.php` → `WhatsappService.php`
- Models: `{EntityName}.php` → `Contact.php`, `ContactGroup.php`
- Jobs: `{Action}{Entity}Job.php` → `SendCampaignJob.php`
- Events: `{Entity}{Action}Event.php` → `NewChatEvent.php`
- Middleware: `{Purpose}Middleware.php` → `WorkspaceContextMiddleware.php`
- Requests: `{Action}{Entity}Request.php` → `StoreContactRequest.php`

### **Vue Files:**
- Components: `PascalCase.vue` → `ChatMessage.vue`, `ContactForm.vue`
- Pages: `PascalCase.vue` → `Index.vue`, `Create.vue`, `Show.vue`
- Composables: `camelCase.js` → `useChat.js`, `useNotification.js`

### **Migration Files:**
- Create table: `{timestamp}_create_{table}_table.php`
- Modify table: `{timestamp}_add_{column}_to_{table}_table.php`
- Drop table: `{timestamp}_drop_{table}_table.php`

---

## 📊 Folder Structure Decision Tree

```
Adding New Code?
│
├─→ Business Logic?
│   └─→ app/Services/{Domain}/{Entity}Service.php
│
├─→ Database Model?
│   └─→ app/Models/{Entity}.php
│
├─→ Background Task?
│   └─→ app/Jobs/{Domain}/{Action}{Entity}Job.php
│
├─→ HTTP Endpoint?
│   ├─→ Web route → app/Http/Controllers/{Role}/{Entity}Controller.php
│   └─→ API route → app/Http/Controllers/ApiController.php
│
├─→ Real-time Event?
│   └─→ app/Events/{Entity}{Action}Event.php
│
├─→ Reusable UI Component?
│   └─→ resources/js/Components/{Category}/{Component}.vue
│
├─→ Full Page View?
│   └─→ resources/js/Pages/{Role}/{Entity}/{Action}.vue
│
├─→ Validation Logic?
│   └─→ app/Http/Requests/{Action}{Entity}Request.php
│
├─→ Reusable Function?
│   ├─→ PHP → app/Traits/{Purpose}Trait.php
│   └─→ JS → resources/js/Composables/{purpose}.js
│
└─→ Configuration?
    └─→ config/{feature}.php
```

---

## 🚀 Scalability Considerations

### **Folder Structure untuk Large Scale:**

Ketika project grows, consider additional organization:

```
app/Services/
├── Core/
│   └── Foundation/
│       ├── BaseService.php
│       └── ServiceInterface.php
│
├── Domain/
│   ├── Messaging/
│   │   ├── Services/
│   │   ├── Repositories/
│   │   ├── Events/
│   │   └── Jobs/
│   │
│   ├── Billing/
│   │   ├── Services/
│   │   ├── Repositories/
│   │   └── Policies/
│   │
│   └── Campaign/
│       ├── Services/
│       ├── Repositories/
│       └── Analytics/
│
└── Infrastructure/
    ├── Payment/
    ├── Storage/
    └── Notification/
```

**Domain-Driven Structure Benefits:**
- ✅ Clear domain boundaries
- ✅ Self-contained features
- ✅ Team-based development
- ✅ Easier testing and maintenance

---

## 🎯 Best Practices Summary

### **DO's:**
✅ Group related files together (domain-based organization)  
✅ Use descriptive names that explain purpose  
✅ Follow Laravel naming conventions  
✅ Keep controllers thin, services thick  
✅ Separate concerns (SRP)  
✅ Document complex folder structures  

### **DON'Ts:**
❌ Mix business logic dengan controllers  
❌ Create deeply nested folder structures (max 3-4 levels)  
❌ Use generic names like `Helper.php`, `Utils.php`  
❌ Put everything dalam single service file  
❌ Ignore namespacing conventions  

---

**Next Steps:**
- 🚀 [04-feature-development-guide.md](./04-feature-development-guide.md) - Step-by-step new feature guide
- 🎨 [05-service-pattern-guidelines.md](./05-service-pattern-guidelines.md) - Service layer best practices
- 🔧 [06-model-treatment-guide.md](./06-model-treatment-guide.md) - Model design patterns
