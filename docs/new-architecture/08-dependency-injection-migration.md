# 🔧 **DEPENDENCY INJECTION MIGRATION - COMPLETE PLAN**

## 🎯 **OVERVIEW**

Comprehensive migration plan untuk menghilangkan **73 manual service instantiations** dan mengimplementasikan **proper dependency injection** pattern di seluruh codebase menggunakan copy-paste adjustment approach.

---

## ⚠️ **MIGRATION PRINCIPLES**

### **✅ SAFE MIGRATION APPROACH:**

1. **Copy existing logic** - tidak menulis ulang functionality
2. **Add constructor injection** - preserve existing behavior
3. **Gradual transition** - test each change
4. **Zero downtime** - maintain availability
5. **Backward compatibility** - keep deprecated methods during transition

### **❌ AVOID THESE PITFALLS:**

1. **Big bang changes** - berisiko tinggi
2. **Removing existing code** sebelum validation
3. **Changing method signatures** - breaking changes
4. **Skipping testing phase** - regression risk
5. **Ignoring service provider updates** - incomplete migration

---

## 📊 **MIGRATION SCOPE ANALYSIS**

### **🎯 Total Migration Items: 73 Instances**

#### **📱 WhatsApp Services (28 instances)**
- Controllers: 7 instances
- Jobs: 8 instances
- Services: 4 instances
- Adapters: 1 instance
- Legacy files: 8 instances

#### **💰 Payment Services (15 instances)**
- StripeService: 6 instances
- PayPalService: 3 instances
- RazorPayService: 2 instances
- FlutterwaveService: 2 instances
- PayStackService: 1 instance
- CoinbaseService: 1 instance

#### **🏢 Business Logic Services (20 instances)**
- AutoReplyService: 2 instances
- CampaignService: 3 instances
- UserService: 4 instances
- BillingService: 3 instances
- TeamService: 2 instances
- SettingService: 2 instances
- Other services: 4 instances

#### **🔧 Utility Services (10 instances)**
- ContactFieldService: 2 instances
- TemplateService: 3 instances
- EmailService: 1 instance
- NotificationService: 1 instance
- Other utilities: 3 instances

---

## 🚀 **PHASE 1: SERVICE PROVIDER SETUP**

### **📋 Step 1: Create Domain-Specific Service Providers**

#### **💰 PaymentServiceProvider**
```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\StripeService;
use App\Services\PayPalService;
use App\Services\RazorPayService;
use App\Services\FlutterwaveService;
use App\Services\PayStackService;
use App\Services\CoinbaseService;
use App\Models\workspace;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Stripe Service
        $this->app->singleton(StripeService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new StripeService($workspace->id);
        });

        // PayPal Service
        $this->app->singleton(PayPalService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new PayPalService($workspace->id);
        });

        // RazorPay Service
        $this->app->singleton(RazorPayService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new RazorPayService($workspace->id);
        });

        // Flutterwave Service
        $this->app->singleton(FlutterwaveService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new FlutterwaveService($workspace->id);
        });

        // PayStack Service
        $this->app->singleton(PayStackService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new PayStackService($workspace->id);
        });

        // Coinbase Service
        $this->app->singleton(CoinbaseService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new CoinbaseService($workspace->id);
        });
    }
}
```

#### **🏢 BusinessServiceProvider**
```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AutoReplyService;
use App\Services\CampaignService;
use App\Services\UserService;
use App\Services\BillingService;
use App\Services\TeamService;
use App\Services\SettingService;
use App\Services\SecurityService;
use App\Services\WorkspaceService;
use App\Models\workspace;

class BusinessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Auto Reply Service
        $this->app->singleton(AutoReplyService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new AutoReplyService($workspace->id);
        });

        // Campaign Service
        $this->app->singleton(CampaignService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new CampaignService($workspace->id);
        });

        // User Service
        $this->app->singleton(UserService::class, function ($app) {
            return new UserService($app->make('App\Models\Role'));
        });

        // Billing Service
        $this->app->singleton(BillingService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new BillingService($workspace->id);
        });

        // Team Service
        $this->app->singleton(TeamService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new TeamService($workspace->id);
        });

        // Setting Service
        $this->app->singleton(SettingService::class, function ($app) {
            return new SettingService();
        });

        // Security Service
        $this->app->singleton(SecurityService::class, function ($app) {
            return new SecurityService();
        });

        // Workspace Service
        $this->app->singleton(WorkspaceService::class, function ($app) {
            return new WorkspaceService();
        });
    }
}
```

#### **🔧 UtilityServiceProvider**
```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ContactFieldService;
use App\Services\TemplateService;
use App\Services\EmailService;
use App\Services\NotificationService;
use App\Services\CouponService;
use App\Services\TaxService;
use App\Services\TicketService;
use App\Models\workspace;

class UtilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Contact Field Service
        $this->app->singleton(ContactFieldService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new ContactFieldService($workspace->id);
        });

        // Template Service (Updated to use WhatsApp services)
        $this->app->singleton(TemplateService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new TemplateService(
                $workspace->id,
                $app->make(\App\Services\WhatsApp\TemplateManagementService::class)
            );
        });

        // Email Service
        $this->app->singleton(EmailService::class, function ($app) {
            return new EmailService();
        });

        // Notification Service
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService();
        });

        // Coupon Service
        $this->app->singleton(CouponService::class, function ($app) {
            return new CouponService();
        });

        // Tax Service
        $this->app->singleton(TaxService::class, function ($app) {
            return new TaxService();
        });

        // Ticket Service
        $this->app->singleton(TicketService::class, function ($app) {
            return new TicketService();
        });
    }
}
```

### **📋 Step 2: Register Service Providers**

#### **Update config/app.php**
```php
// config/app.php

'providers' => [
    // ... existing providers

    /*
     * Application Service Providers...
     */
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\BroadcastServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\RouteServiceProvider::class,

    // NEW: Domain-specific service providers
    App\Providers\PaymentServiceProvider::class,
    App\Providers\BusinessServiceProvider::class,
    App\Providers\UtilityServiceProvider::class,

    /*
     * Package Service Providers...
     */
],
```

---

## 🚀 **PHASE 2: CONTROLLER MIGRATION**

### **🎯 Step 1: Payment Controllers Migration**

#### **💳 Admin/BillingController Migration**
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BillingService;
use App\Services\StripeService;
use App\Services\PayPalService;
use App\Services\RazorPayService;
use App\Services\FlutterwaveService;
use App\Services\PayStackService;
use App\Services\CoinbaseService;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(
        private BillingService $billingService,
        private StripeService $stripeService,
        private PayPalService $paypalService,
        private RazorPayService $razorPayService,
        private FlutterwaveService $flutterwaveService,
        private PayStackService $payStackService,
        private CoinbaseService $coinbaseService
    ) {
        // Remove manual instantiation from constructor
        /*
        OLD:
        $this->billingService = new BillingService();
        $this->subscriptionService = new SubscriptionService();
        */
    }

    /**
     * Process payment with Stripe
     */
    public function processStripePayment(Request $request)
    {
        // OLD: Keep for reference during transition
        /*
        $stripeService = new StripeService($workspaceId);
        return $stripeService->processPayment($request->all());
        */

        // NEW: Use injected service
        return $this->stripeService->processPayment($request->all());
    }

    /**
     * Process payment with PayPal
     */
    public function processPayPalPayment(Request $request)
    {
        // OLD: Keep for reference
        /*
        $paypalService = new PayPalService($workspaceId);
        return $paypalService->processPayment($request->all());
        */

        // NEW: Use injected service
        return $this->paypalService->processPayment($request->all());
    }

    /**
     * Process payment with RazorPay
     */
    public function processRazorPayPayment(Request $request)
    {
        // NEW: Use injected service
        return $this->razorPayService->processPayment($request->all());
    }

    /**
     * Process payment with Flutterwave
     */
    public function processFlutterwavePayment(Request $request)
    {
        // NEW: Use injected service
        return $this->flutterwaveService->processPayment($request->all());
    }

    /**
     * Process payment with PayStack
     */
    public function processPayStackPayment(Request $request)
    {
        // NEW: Use injected service
        return $this->payStackService->processPayment($request->all());
    }

    /**
     * Process payment with Coinbase
     */
    public function processCoinbasePayment(Request $request)
    {
        // NEW: Use injected service
        return $this->coinbaseService->processPayment($request->all());
    }

    // ... other methods remain unchanged initially
}
```

#### **👥 Admin/UserController Migration**
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Services\TeamService;
use App\Services\RoleService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService,
        private TeamService $teamService,
        private RoleService $roleService
    ) {
        // Remove manual instantiation
        /*
        OLD:
        $this->userService = new UserService($role);
        */
    }

    /**
     * Create new user
     */
    public function store(Request $request)
    {
        // OLD: Keep for reference
        /*
        $this->userService = new UserService($role);
        return $this->userService->createUser($request->all());
        */

        // NEW: Use injected service
        return $this->userService->createUser($request->all());
    }

    /**
     * Update user
     */
    public function update(Request $request, $id)
    {
        // NEW: Use injected service
        return $this->userService->updateUser($id, $request->all());
    }

    // ... other methods
}
```

### **🎯 Step 2: User Controllers Migration**

#### **👤 User/ContactFieldController Migration**
```php
<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\ContactFieldService;
use Illuminate\Http\Request;

class ContactFieldController extends Controller
{
    public function __construct(
        private ContactFieldService $contactFieldService
    ) {
        // Remove manual instantiation
        /*
        OLD:
        private function contactFieldService() {
            return new ContactFieldService($this->getCurrentworkspaceId());
        }
        */
    }

    /**
     * Get contact fields
     */
    public function index()
    {
        // OLD: Keep for reference
        /*
        return $this->contactFieldService()->getFields();
        */

        // NEW: Use injected service
        return $this->contactFieldService->getFields();
    }

    /**
     * Store contact field
     */
    public function store(Request $request)
    {
        // NEW: Use injected service
        return $this->contactFieldService->createField($request->all());
    }

    // ... other methods
}
```

#### **📋 User/TemplateController Migration**
```php
<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\TemplateService;
use App\Services\WhatsApp\TemplateManagementService;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function __construct(
        private TemplateService $templateService
    ) {
        // Remove manual instantiation
        /*
        OLD:
        private function templateService() {
            return new TemplateService(session()->get('current_workspace'));
        }
        */
    }

    /**
     * Get templates
     */
    public function index()
    {
        // OLD: Keep for reference
        /*
        return $this->templateService()->getTemplates();
        */

        // NEW: Use injected service
        return $this->templateService->getTemplates();
    }

    /**
     * Store template
     */
    public function store(Request $request)
    {
        // NEW: Use injected service
        return $this->templateService->createTemplate($request->all());
    }

    // ... other methods
}
```

---

## 🚀 **PHASE 3: SERVICE-TO-SERVICE MIGRATION**

### **🔧 Step 1: Update Service Dependencies**

#### **🔄 ChatService Complete Migration**
```php
<?php

namespace App\Services;

use App\Services\WhatsApp\MessageSendingService;
use App\Services\WhatsApp\MediaProcessingService;
use App\Services\WhatsApp\WhatsAppHealthService;

class ChatService
{
    private MessageSendingService $messageService;
    private MediaProcessingService $mediaService;
    private WhatsAppHealthService $healthService;

    public function __construct(
        private $workspaceId,
        MessageSendingService $messageService,
        MediaProcessingService $mediaService,
        WhatsAppHealthService $healthService
    ) {
        $this->messageService = $messageService;
        $this->mediaService = $mediaService;
        $this->healthService = $healthService;

        // Remove manual instantiation
        /*
        OLD:
        $this->whatsappService = new WhatsappService(
            $accessToken, $apiVersion, $appId,
            $phoneNumberId, $wabaId, $this->workspaceId
        );
        */
    }

    /**
     * Send message
     */
    public function sendMessage($contactId, $message, $userId = null, $type = 'text')
    {
        // OLD: Keep for reference
        /*
        return $this->whatsappService->sendMessage($contactId, $message, $userId, $type);
        */

        // NEW: Use specific service
        return $this->messageService->sendMessage($contactId, $message, $userId, $type);
    }

    /**
     * Send media
     */
    public function sendMedia($contactId, $mediaFile, $caption = null)
    {
        // NEW: Use specific service
        return $this->mediaService->sendMedia($contactId, $mediaFile, $caption);
    }

    /**
     * Check WhatsApp health
     */
    public function checkWhatsAppHealth()
    {
        // NEW: Use specific service
        return $this->healthService->checkConnection();
    }

    // ... other methods updated similarly
}
```

#### **📋 TemplateService Complete Migration**
```php
<?php

namespace App\Services;

use App\Services\WhatsApp\TemplateManagementService;

class TemplateService
{
    private TemplateManagementService $templateService;

    public function __construct(
        private $workspaceId,
        TemplateManagementService $templateService
    ) {
        $this->templateService = $templateService;

        // Remove manual instantiation
        /*
        OLD:
        $this->whatsappService = new WhatsappService(
            $accessToken, $apiVersion, $appId,
            $phoneNumberId, $wabaId, $this->workspaceId
        );
        */
    }

    /**
     * Get templates
     */
    public function getTemplates()
    {
        // OLD: Keep for reference
        /*
        return $this->whatsappService->getTemplates();
        */

        // NEW: Use specific service
        return $this->templateService->getTemplates();
    }

    /**
     * Create template
     */
    public function createTemplate($templateData)
    {
        // NEW: Use specific service
        return $this->templateService->createTemplate($templateData);
    }

    /**
     * Send template message
     */
    public function sendTemplateMessage($contactId, $templateName, $variables = [])
    {
        // NEW: Use specific service
        return $this->templateService->sendTemplateMessage($contactId, $templateName, $variables);
    }

    // ... other methods updated similarly
}
```

### **🔄 Step 2: Update AppServiceProvider**

#### **📋 Clean AppServiceProvider**
```php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Models\workspace;
use App\Resolvers\PaymentPlatformResolver;

// Keep only core services that don't fit into domain providers
use App\Services\ContactService;
use App\Services\ChatService;
use App\Services\MediaService;
use App\Services\SubscriptionService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Core Services (keep these here)
        $this->app->singleton(ContactService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new ContactService($workspace->id);
        });

        $this->app->singleton(ChatService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new ChatService(
                $workspace->id,
                $app->make(\App\Services\WhatsApp\MessageSendingService::class),
                $app->make(\App\Services\WhatsApp\MediaProcessingService::class),
                $app->make(\App\Services\WhatsApp\WhatsAppHealthService::class)
            );
        });

        $this->app->singleton(MediaService::class, function ($app) {
            return new MediaService();
        });

        $this->app->singleton(SubscriptionService::class, function ($app) {
            return new SubscriptionService();
        });

        // Payment Platform Resolver (keep here)
        $this->app->singleton(PaymentPlatformResolver::class, function ($app) {
            return new PaymentPlatformResolver();
        });
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        if (!App::environment('local')) {
            URL::forceScheme('https');
        }
    }
}
```

---

## 🚀 **PHASE 4: MIDDLEWARE & HELPERS**

### **🔧 Step 1: Update Middleware**

#### **🛡️ CheckSubscriptionStatus Middleware**
```php
<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;

class CheckSubscriptionStatus
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {
        // Remove manual instantiation
        /*
        OLD:
        $this->subscriptionService = new SubscriptionService();
        */
    }

    public function handle(Request $request, Closure $next)
    {
        // OLD: Keep for reference
        /*
        $subscriptionService = new SubscriptionService();
        $status = $subscriptionService->checkStatus($workspace);
        */

        // NEW: Use injected service
        $status = $this->subscriptionService->checkStatus($request->workspace);

        if (!$status->isActive()) {
            return redirect()->route('subscription.expired');
        }

        return $next($request);
    }
}
```

---

## 🚀 **PHASE 5: VALIDATION & TESTING**

### **✅ Step 1: Create Migration Tests**

#### **🧪 ServiceInjectionTest.php**
```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\StripeService;
use App\Services\PayPalService;
use App\Services\UserService;
use App\Services\ChatService;

class ServiceInjectionTest extends TestCase
{
    public function test_stripe_service_injection()
    {
        $service = app(StripeService::class);
        $this->assertInstanceOf(StripeService::class, $service);
    }

    public function test_paypal_service_injection()
    {
        $service = app(PayPalService::class);
        $this->assertInstanceOf(PayPalService::class, $service);
    }

    public function test_user_service_injection()
    {
        $service = app(UserService::class);
        $this->assertInstanceOf(UserService::class, $service);
    }

    public function test_chat_service_has_whatsapp_dependencies()
    {
        $chatService = app(ChatService::class);

        // Test that WhatsApp services are injected
        $this->assertObjectHasProperty('messageService', $chatService);
        $this->assertObjectHasProperty('mediaService', $chatService);
        $this->assertObjectHasProperty('healthService', $chatService);
    }

    public function test_service_singleton_behavior()
    {
        $service1 = app(StripeService::class);
        $service2 = app(StripeService::class);

        // Should be the same instance (singleton)
        $this->assertSame($service1, $service2);
    }
}
```

#### **🧪 ControllerMigrationTest.php**
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\User\ContactFieldController;

class ControllerMigrationTest extends TestCase
{
    public function test_billing_controller_has_payment_services()
    {
        $controller = app(BillingController::class);

        // Test that all payment services are injected
        $this->assertObjectHasProperty('stripeService', $controller);
        $this->assertObjectHasProperty('paypalService', $controller);
        $this->assertObjectHasProperty('razorPayService', $controller);
        $this->assertObjectHasProperty('flutterwaveService', $controller);
        $this->assertObjectHasProperty('payStackService', $controller);
        $this->assertObjectHasProperty('coinbaseService', $controller);
    }

    public function test_contact_field_controller_has_service()
    {
        $controller = app(ContactFieldController::class);

        $this->assertObjectHasProperty('contactFieldService', $controller);
    }

    public function test_billing_controller_payment_processing()
    {
        // Mock payment processing
        $this->mock(\App\Services\StripeService::class, function ($mock) {
            $mock->shouldReceive('processPayment')
                 ->once()
                 ->andReturn(['status' => 'success']);
        });

        $response = $this->postJson('/admin/billing/stripe', [
            'amount' => 100,
            'currency' => 'USD'
        ]);

        $response->assertStatus(200);
    }
}
```

### **✅ Step 2: Performance Testing**

#### **⚡ ServiceResolutionPerformanceTest.php**
```php
<?php

namespace Tests\Performance;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ServiceResolutionPerformanceTest extends TestCase
{
    public function test_service_resolution_performance()
    {
        $iterations = 100;

        // Test new DI approach
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $service = app(\App\Services\StripeService::class);
        }
        $diTime = microtime(true) - $start;

        // Test old manual instantiation (simulate)
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $workspace = \App\Models\Workspace::first();
            $service = new \App\Services\StripeService($workspace->id);
        }
        $manualTime = microtime(true) - $start;

        // DI should be faster or at least not significantly slower
        $this->assertLessThanOrEqual($manualTime * 1.2, $diTime);
    }

    public function test_memory_usage_comparison()
    {
        $memoryBefore = memory_get_usage();

        // Create multiple services using DI
        for ($i = 0; $i < 50; $i++) {
            $stripeService = app(\App\Services\StripeService::class);
            $paypalService = app(\App\Services\PayPalService::class);
        }

        $memoryAfter = memory_get_usage();
        $memoryUsed = $memoryAfter - $memoryBefore;

        // Should use reasonable memory (less than 50MB for 100 service resolutions)
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed);
    }
}
```

---

## 🚀 **PHASE 6: CLEANUP & FINALIZATION**

### **🧹 Step 1: Remove Deprecated Code**

#### **🗑️ Remove Manual Instantiation Methods**
```php
// Remove these methods from all controllers:

/**
 * REMOVE THESE METHODS:
 */
private function whatsappService() {
    return new WhatsappService(/* parameters */);
}

private function templateService() {
    return new TemplateService($workspaceId);
}

private function contactFieldService() {
    return new ContactFieldService($workspaceId);
}

private function userService() {
    return new UserService($role);
}

// ... and all other similar methods
```

#### **🗑️ Remove Duplicate Files**
```bash
# AFTER thorough testing, remove duplicate files:
rm app/SendCampaignJob.php          # Keep app/Jobs/SendCampaignJob.php
rm app/ProcessSingleCampaignLogJob.php  # Keep app/Jobs/ProcessSingleCampaignLogJob.php
rm app/RetryCampaignLogJob.php      # Keep app/Jobs/RetryCampaignLogJob.php
```

#### **🗑️ Clean Up Service Providers**
```php
// Remove service registrations from AppServiceProvider that are now in domain providers

// REMOVE these from AppServiceProvider.php:
$this->app->singleton(StripeService::class, ...);
$this->app->singleton(PayPalService::class, ...);
$this->app->singleton(UserService::class, ...);
// ... etc

// KEEP core services that don't fit domain categorization
```

### **🧹 Step 2: Update Documentation**

#### **📚 Update Service Usage Documentation**
```markdown
# Service Usage Guidelines

## ✅ Correct Pattern (Dependency Injection)

```php
class MyController extends Controller
{
    public function __construct(
        private MyService $myService
    ) {}

    public function myMethod()
    {
        return $this->myService->doSomething();
    }
}
```

## ❌ Deprecated Pattern (Manual Instantiation)

```php
class MyController extends Controller
{
    private function myService()
    {
        return new MyService($parameter); // DEPRECATED
    }

    public function myMethod()
    {
        return $this->myService()->doSomething(); // DEPRECATED
    }
}
```

## 📋 Available Services by Domain

### Payment Services:
- `StripeService`
- `PayPalService`
- `RazorPayService`
- `FlutterwaveService`
- `PayStackService`
- `CoinbaseService`

### Business Services:
- `UserService`
- `BillingService`
- `CampaignService`
- `TeamService`
- `AutoReplyService`

### WhatsApp Services:
- `MessageSendingService`
- `TemplateManagementService`
- `MediaProcessingService`
- `BusinessProfileService`
- `WhatsAppHealthService`

### Utility Services:
- `ContactFieldService`
- `TemplateService`
- `EmailService`
- `NotificationService`
```

---

## 📋 **COMPLETE MIGRATION CHECKLIST**

### **✅ Phase 1: Service Provider Setup**
- [ ] Create PaymentServiceProvider
- [ ] Create BusinessServiceProvider
- [ ] Create UtilityServiceProvider
- [ ] Update config/app.php
- [ ] Test service provider registration

### **✅ Phase 2: Controller Migration**
- [ ] Migrate Admin/BillingController (7 services)
- [ ] Migrate Admin/UserController (3 services)
- [ ] Migrate User/ContactFieldController (1 service)
- [ ] Migrate User/TemplateController (1 service)
- [ ] Migrate all other controllers with manual instantiation
- [ ] Test all controller functionality

### **✅ Phase 3: Service-to-Service Migration**
- [ ] Update ChatService dependencies
- [ ] Update TemplateService dependencies
- [ ] Update AutoReplyService dependencies
- [ ] Update all other services with manual instantiation
- [ ] Update AppServiceProvider (clean up)
- [ ] Test service integration

### **✅ Phase 4: Middleware & Helpers**
- [ ] Update CheckSubscriptionStatus middleware
- [ ] Update any other middleware with manual instantiation
- [ ] Update helper functions
- [ ] Test middleware functionality

### **✅ Phase 5: Validation & Testing**
- [ ] Create service injection tests
- [ ] Create controller migration tests
- [ ] Create performance tests
- [ ] Run full test suite
- [ ] Manual testing of critical flows

### **✅ Phase 6: Cleanup & Finalization**
- [ ] Remove deprecated methods (after 2 weeks)
- [ ] Remove duplicate files
- [ ] Clean up service providers
- [ ] Update documentation
- [ ] Update developer guides

---

## 🎯 **SUCCESS METRICS**

### **✅ Technical Metrics**
- **0 manual service instantiation** (dari 73 instances)
- **100% services terdaftar** di appropriate providers
- **100% controllers menggunakan constructor injection**
- **100% services menggunakan dependency injection**
- **Test coverage > 85%** untuk migrated services

### **✅ Performance Metrics**
- **Service resolution < 10ms** untuk semua services
- **Memory usage < 100MB** untuk application startup
- **Request processing improvement > 15%**
- **Zero performance regression**

### **✅ Quality Metrics**
- **Zero breaking changes**
- **Same API response formats**
- **Improved error handling**
- **Better debugging capabilities**
- **Enhanced maintainability**

---

## 🚨 **RISK MITIGATION**

### **⚠️ High-Risk Operations**
1. **Service provider changes** - Test thoroughly before deployment
2. **Controller constructor changes** - Ensure all dependencies are met
3. **Job dependency injection** - Test queue processing
4. **Middleware updates** - Ensure authentication flows work

### **🔒 Safety Measures**
1. **Feature flags** for critical changes
2. **Staging environment testing** before production
3. **Rollback procedures** for each phase
4. **Monitoring for errors** during deployment
5. **Gradual deployment** with traffic splitting

### **📊 Monitoring**
- **Service resolution time**
- **Memory usage patterns**
- **Error rates by service**
- **Request processing times**
- **Queue processing performance**

---

## 🔗 **CROSS-REFERENCE**

### **📋 Related Documents:**
1. **[06-critical-gap-analysis.md](./06-critical-gap-analysis.md)** - Complete gap findings
2. **[07-service-integration-guide.md](./07-service-integration-guide.md)** - WhatsApp service integration
3. **[09-practical-fixes-checklist.md](./09-practical-fixes-checklist.md)** - Implementation checklist

### **🎯 Implementation Order:**
1. **Complete WhatsApp service integration** (Phase 1 from guide 07)
2. **Execute this dependency injection migration** (this guide)
3. **Follow practical checklist** for validation

---

## 🎉 **CONCLUSION**

**Dependency injection migration ini akan menghilangkan 73 manual instantiations** dan menciptakan **arsitektur yang konsisten, testable, dan maintainable**.

**Expected Timeline:** 2-3 minggu untuk complete migration dengan proper testing dan validation.

**Key Benefits:**
- **Improved performance** dengan singleton pattern
- **Better testability** dengan proper dependency injection
- **Consistent architecture** across entire codebase
- **Enhanced maintainability** dengan clear service boundaries
- **Zero breaking changes** dengan gradual migration approach

**Final Outcome:** Implementation score improvement dari **6.5/10 menjadi 9.5/10** dengan arsitektur enterprise-grade.