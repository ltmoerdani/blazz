# 📚 **ULTIMATE REFACTORING REFERENCE GUIDE**

## 🎯 **DOCUMENT PURPOSE**

Ini adalah **dokumen referensi utama dan lengkap** untuk refactoring Blazz WhatsApp Business Platform menggunakan **copy-paste approach**.

**PRINSIP FUNDAMENTAL:**
- ✅ **REFACTOR, NOT REWRITE** - Preserve semua existing functionality
- ✅ **COPY-PASTE METHODS** - Pindahkan existing logic ke struktur yang lebih baik
- ✅ **MOVE FILES** - Reorganisasi file ke folder yang lebih logical
- ❌ **NO NEW FEATURES** - Jangan tambahkan functionality baru
- ❌ **NO UI CHANGES** - Jangan ubah Vue.js components atau halaman

---

## 📊 **CURRENT STATE ANALYSIS**

### **Codebase Statistics (UPDATED & ACCURATE):**
- **Total PHP Files:** 563 files (bukan 15,117)
- **Controller Files:** 58 files (95% sudah terorganisir di folder Admin/, Api/, User/) ✅
- **Service Files:** 47 files
- **Test Files:** 10 files (fungsional untuk WhatsApp)
- **Largest Files:**
  - `SendCampaignJob.php`: 401 lines ⚠️ (REDUCED!)
  - `WhatsappService.php`: 1,565 lines (CRITICAL)
  - `ApiController.php`: 764 lines (HIGH)
  - `WhatsAppWebJSController.php`: 703 lines (HIGH)
  - `ChatService.php`: 515 lines (HIGH)

### **Current Folder Structure (UPDATED):**
```
app/
├── Http/
│   ├── Controllers/ (58 files - 95% sudah terorganisir) ✅
│   │   ├── Admin/ (17 files) ✅
│   │   ├── User/ (23 files) ✅
│   │   ├── Api/v1/ (18 files) ✅
│   │   ├── Common/ (5 files) ✅
│   │   ├── ApiController.php (764 lines) ⚠️
│   │   ├── WhatsAppWebJSController.php (703 lines) ⚠️
│   │   └── ... (perlu reorganisasi)
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Services/ (47 files)
│   ├── Adapters/
│   │   ├── MetaAPIAdapter.php (300 lines)
│   │   └── WebJSAdapter.php (328 lines)
│   ├── WhatsappService.php (1,565 lines) ⚠️
│   ├── ChatService.php (515 lines) ⚠️
│   ├── SubscriptionService.php (422 lines)
│   └── ... (other services)
├── Models/ (55 files)
├── Contracts/ (1 file)
├── Jobs/ (11 files)
└── Events/ (multiple files)
```

---

## 🎯 **REFACTORING TARGETS**

### **🔥 Priority 1: Critical Files to Split**

#### **1.1 WhatsappService.php (1,565 lines) - MOST CRITICAL**
```php
// CURRENT: 1 massive class dengan 5 responsibilities
class WhatsappService {
    // Messaging: ~300 lines (methods: sendMessage, sendTemplate, sendMedia)
    // Templates: ~400 lines (createTemplate, updateTemplate, syncTemplates)
    // Media Processing: ~200 lines (getMedia, uploadMedia, downloadMedia)
    // Business Profile: ~300 lines (getProfile, updateProfile, phone management)
    // Health/Monitoring: ~200 lines (checkHealth, subscriptions)
    // Utility/Helper: ~165 lines (setHeaders, sendHttpRequest)
}

// TARGET: 6 focused classes dengan copied methods
app/Services/WhatsApp/
├── MessageSendingService.php (copy messaging methods)
├── TemplateManagementService.php (copy template methods)
├── MediaProcessingService.php (copy media methods)
├── BusinessProfileService.php (copy profile methods)
├── WhatsAppHealthService.php (copy health methods)
└── WhatsAppSessionService.php (copy session methods)
```

#### **1.2 ApiController.php (764 lines) - HIGH PRIORITY**
```php
// CURRENT: Mixed responsibilities
class ApiController {
    // Contact management: ~200 lines
    // Template operations: ~150 lines
    // AutoReply: ~100 lines
    // WhatsApp operations: ~150 lines
    // Validation: ~100 lines
    // Utility methods: ~64 lines
}

// TARGET: Split by functionality
app/Http/Controllers/Api/
├── ContactController.php (copy contact methods)
├── TemplateController.php (copy template methods)
├── AutoReplyController.php (copy autoReply methods)
├── WhatsAppMessageController.php (copy WhatsApp methods)
└── keep existing ApiController.php (reduced to <200 lines)
```

#### **1.3 WhatsAppWebJSController.php (703 lines) - HIGH PRIORITY**
```php
// CURRENT: Mixed webhook handling
class WhatsAppWebJSController {
    // QR code handling: ~100 lines
    // Message processing: ~200 lines
    // Contact provisioning: ~150 lines
    // Session management: ~100 lines
    // Health monitoring: ~80 lines
    // Validation/utility: ~73 lines
}

// TARGET: Specialized controllers
app/Http/Controllers/Api/WhatsApp/
├── WebhookController.php (copy webhook processing)
├── QRCodeController.php (copy QR handling)
├── ContactProvisioningController.php (copy contact methods)
├── SessionController.php (copy session methods)
└── HealthController.php (copy health methods)
```

### **🔥 Priority 2: Large Services to Split**

#### **2.1 ChatService.php (515 lines)**
```php
// TARGET: Split chat operations
app/Services/Chat/
├── ChatRetrievalService.php (copy getChatList methods)
├── MessageSendingService.php (copy send methods)
├── ChatFilteringService.php (copy filter logic)
└── ChatValidationService.php (copy validation logic)
```

#### **2.2 StripeService.php (491 lines)**
```php
// TARGET: Split payment operations
app/Services/Payment/
├── StripePaymentService.php (copy payment methods)
├── StripeSubscriptionService.php (copy subscription methods)
├── StripeWebhookService.php (copy webhook methods)
└── StripeValidationService.php (copy validation methods)
```

---

## 📁 **DETAILED FOLDER RESTRUCTURE PLAN**

### **✅ BOLEH: File Movement & Reorganization**

#### **Controller Restructure (MOVE FILES)**
```bash
# CURRENT STRUCTURE:
app/Http/Controllers/ (67 files campur aduk)
├── ApiController.php (764 lines) ⚠️
├── AuthController.php (485 lines)
├── BillingController.php
├── ChatController.php
├── ContactController.php
├── DashboardController.php
├── PluginController.php
├── WhatsAppWebJSController.php (703 lines) ⚠️
├── Admin/ (some files)
├── User/ (some files)
└── Api/ (some files)

# TARGET STRUCTURE (MOVE existing files):
app/Http/Controllers/
├── Api/                                    # MOVE existing API controllers
│   ├── v1/                               # Versioning untuk API
│   │   ├── ApiController.php              # MOVE (reduce size)
│   │   ├── ContactController.php          # MOVE dari root
│   │   ├── TemplateController.php         # MOVE dari root
│   │   ├── AutoReplyController.php        # MOVE dari root
│   │   └── WhatsApp/                      # NEW - WhatsApp API controllers
│   │       ├── WebhookController.php       # COPY methods dari WhatsAppWebJSController
│   │       ├── MessageController.php       # COPY methods dari WhatsAppWebJSController
│   │       ├── SessionController.php       # COPY methods dari WhatsAppWebJSController
│   │       ├── QRCodeController.php        # COPY methods dari WhatsAppWebJSController
│   │       └── HealthController.php        # COPY methods dari WhatsAppWebJSController
│   └── Internal/                          # Internal API endpoints
│       └── WebhookController.php           # MOVE dari root
├── User/                                   # ALREADY EXISTS - Add missing files
│   ├── ChatController.php                  # EXISTS - keep
│   ├── ContactController.php               # MOVE dari root
│   ├── CampaignController.php              # MOVE dari root
│   ├── TemplateController.php             # MOVE dari root
│   ├── DashboardController.php             # MOVE dari root
│   ├── ProfileController.php               # MOVE dari root
│   ├── SettingsController.php              # MOVE dari root
│   └── SubscriptionController.php          # MOVE dari root
├── Admin/                                  # ALREADY EXISTS - Add missing files
│   ├── UserController.php                  # MOVE dari root
│   ├── WorkspaceController.php             # MOVE dari root
│   ├── BillingController.php               # MOVE dari root
│   ├── DashboardController.php             # MOVE dari root
│   └── SettingsController.php              # MOVE dari root
└── Common/                                 # NEW - Shared controllers
    ├── AuthController.php                  # MOVE dari root
    ├── DashboardController.php             # MOVE dari root
    └── FrontendController.php              # MOVE dari root
```

#### **Service Restructure (COPY-PASTE METHODS)**
```bash
# CURRENT STRUCTURE:
app/Services/ (47 files)
├── WhatsappService.php (1,565 lines) ⚠️
├── ChatService.php (515 lines) ⚠️
├── StripeService.php (491 lines) ⚠️
├── SubscriptionService.php (422 lines)
├── Adapters/
│   ├── MetaAPIAdapter.php (300 lines)
│   └── WebJSAdapter.php (328 lines)
└── ... (other services)

# TARGET STRUCTURE (COPY existing methods):
app/Services/
├── WhatsApp/                               # NEW - WhatsApp focused services
│   ├── MessageSendingService.php          # COPY methods dari WhatsappService
│   ├── TemplateManagementService.php      # COPY methods dari WhatsappService
│   ├── MediaProcessingService.php         # COPY methods dari WhatsappService
│   ├── BusinessProfileService.php         # COPY methods dari WhatsappService
│   ├── WhatsAppHealthService.php          # COPY methods dari WhatsappService
│   ├── WhatsAppSessionService.php         # COPY methods dari WhatsappService
│   └── Adapters/                         # MOVE existing adapters
│       ├── MetaAPIAdapter.php             # MOVE
│       └── WebJSAdapter.php               # MOVE
├── Chat/                                   # NEW - Chat focused services
│   ├── ChatRetrievalService.php           # COPY methods dari ChatService
│   ├── MessageSendingService.php          # COPY methods dari ChatService
│   ├── ChatFilteringService.php           # COPY methods dari ChatService
│   └── ChatValidationService.php          # COPY methods dari ChatService
├── Payment/                                # NEW - Payment focused services
│   ├── StripePaymentService.php           # COPY methods dari StripeService
│   ├── StripeSubscriptionService.php      # COPY methods dari StripeService
│   ├── StripeWebhookService.php           # COPY methods dari StripeService
│   └── PaymentValidationService.php       # COPY validation methods
├── Campaign/                               # NEW - Campaign focused services
│   ├── CampaignExecutionService.php        # COPY methods dari existing services
│   ├── TemplateManagementService.php      # COPY methods dari existing services
│   └── CampaignValidationService.php      # COPY validation methods
├── User/                                   # NEW - User focused services
│   ├── UserManagementService.php          # COPY methods dari UserService
│   ├── TeamManagementService.php          # COPY methods dari existing services
│   └── UserValidationService.php          # COPY validation methods
└── Core/                                   # Keep existing core services
    ├── AuthService.php                    # KEEP
    ├── WorkspaceService.php               # KEEP
    ├── SettingService.php                 # KEEP
    └── ... (other existing services)
```

### **❌ TIDAK BOLEH: Folder Creation Yang Tidak Perlu**

```bash
# JANGAN BUAT:
app/Frontend/           # Tidak ada frontend logic di backend
app/UI/                 # Tidak ada UI components
app/Components/         # Tidak ada React/Vue components
app/Api/V2/            # Tidak ada API versioning baru
app/Modules/            # Tidak ada modularisasi baru
app/Packages/           # Tidak ada package development
app/Custom/             # Tidak ada custom logic
app/Utils/              # Tidak ada utility functions baru
```

---

## 🔧 **STEP-BY-STEP REFACTORING PROCESS**

### **PHASE 1: Preparation (Week 1) - ✅ COMPLETED**

#### **Day 1: Backup & Analysis**
```bash
# ✅ COMPLETED: Create backup branch
git checkout -b refactor/code-organization
git checkout -b backup/before-refactor

# ✅ COMPLETED: Document current functionality
php artisan route:list --json > routes-before.json
find app/ -name "*.php" -exec wc -l {} + > file-sizes-before.txt

# ✅ COMPLETED: Identify all file dependencies
grep -r "new.*Service(" app/Http/Controllers/ > service-dependencies.txt
grep -r "use.*Service;" app/Http/Controllers/ > service-imports.txt
```

#### **Day 2: Create Folder Structure**
```bash
# ✅ COMPLETED: Create new folders (kosong)
mkdir -p app/Http/Controllers/Api/v1/WhatsApp
mkdir -p app/Http/Controllers/Common
mkdir -p app/Services/WhatsApp
mkdir -p app/Services/Chat
mkdir -p app/Services/Payment
mkdir -p app/Services/Campaign
mkdir -p app/Services/User
mkdir -p app/Services/Core
```

#### **Day 3-5: File Movement (Phase 1 - Safe Moves)**
```bash
# ✅ COMPLETED: File Movement Results:
# • 55 controllers moved to logical folders
# • 5 User controllers moved (Contact, Campaign, Template, Subscription, etc.)
# • 17 Admin controllers moved (User, Workspace, Billing, etc.)
# • 18 Api/v1 controllers moved (including PaymentController)
# • 5 Common controllers moved (Auth, Dashboard, Frontend, etc.)
# • All namespaces updated
# • All routes updated
# • ESLint/Intelephense issues fixed

# ✅ COMPLETED: Implementation Results:
# • 95% controllers organized (55/58 files)
# • 7 core services registered in AppServiceProvider
# • Constructor injection implemented
# • Zero breaking changes
# • All existing functionality preserved

# Tests after movement (completed)
php artisan route:list
php artisan config:clear
```

### **PHASE 2: Service Splitting (Week 2)**

#### **Day 1-2: Split WhatsappService.php**
```php
// STEP 1: Copy methods to new services

// CREATE: app/Services/WhatsApp/MessageSendingService.php
class MessageSendingService {
    // COPY existing constructor dari WhatsappService
    public function __construct($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId) {
        // COPY exact same constructor logic
    }

    // COPY existing methods (lines 70-167):
    public function sendMessage($contactUuId, $messageContent, $userId = null, $type="text", $buttons = [], $header = [], $footer = null, $buttonLabel = null) {
        // COPY exact existing implementation
    }

    // COPY existing method sendTemplateMessage (lines 176-235)
    public function sendTemplateMessage($contactUuId, $templateContent, $userId = null, $campaignId = null, $mediaId = null) {
        // COPY exact existing implementation
    }

    // COPY existing method sendMedia (lines 400-477)
    public function sendMedia($contactUuId, $mediaType, $mediaFileName, $mediaUrl, $location, $caption = null, $transcription = null) {
        // COPY exact existing implementation
    }

    // COPY helper methods yang dibutuhkan
    private function setHeaders() { /* COPY existing */ }
    private function sendHttpRequest($method, $url, $data = [], $headers = []) { /* COPY existing */ }
}

// CREATE: app/Services/WhatsApp/TemplateManagementService.php
class TemplateManagementService {
    // COPY constructor yang sama
    // COPY method createTemplate (lines 573-785)
    // COPY method updateTemplate (lines 787-1012)
    // COPY method syncTemplates (lines 1014-1087)
    // COPY method deleteTemplate (lines 1095-1113)
}

// CREATE: app/Services/WhatsApp/BusinessProfileService.php
class BusinessProfileService {
    // COPY constructor yang sama
    // COPY method getBusinessProfile (lines 1194-1223)
    // COPY method updateBusinessProfile (lines 1225-1285)
    // COPY method getPhoneNumberId (lines 1302-1332)
    // COPY method getPhoneNumberStatus (lines 1334-1363)
}
```

#### **Day 3-4: Update Service Provider**
```php
// app/Providers/AppServiceProvider.php
public function register(): void {
    // Register new services dengan existing logic
    $this->app->singleton(MessageSendingService::class, function ($app) {
        // GET existing configuration dari session/WhatsAppSession
        $workspaceId = session()->get('current_workspace');
        $session = WhatsAppSession::where('workspace_id', $workspaceId)
            ->where('provider_type', 'meta')
            ->first();

        if (!$session) {
            throw new Exception('No WhatsApp session configured');
        }

        $sessionData = $session->session_data;

        return new MessageSendingService(
            $sessionData['access_token'],
            $sessionData['api_version'] ?? 'v18.0',
            $sessionData['app_id'],
            $sessionData['phone_number_id'],
            $sessionData['waba_id'],
            $workspaceId
        );
    });

    // Register other services dengan cara yang sama
    $this->app->singleton(TemplateManagementService::class, function ($app) {
        // Same configuration logic
    });

    $this->app->singleton(BusinessProfileService::class, function ($app) {
        // Same configuration logic
    });
}
```

#### **Day 5: Update Controllers**
```php
// UPDATE controllers untuk menggunakan new services
class ChatController extends BaseController {
    public function __construct(
        private MessageSendingService $messageSendingService,
        private TemplateManagementService $templateManagementService
    ) {}

    public function sendMessage(Request $request) {
        // UPDATE untuk menggunakan new service
        return $this->messageSendingService->sendMessage(
            $request->contact_uuid,
            $request->message,
            auth()->id()
        );
    }

    public function sendTemplateMessage(Request $request, $uuid) {
        // UPDATE untuk menggunakan new service
        return $this->templateManagementService->sendTemplateMessage(
            $uuid,
            $request->template_content,
            auth()->id(),
            $request->campaign_id ?? null
        );
    }
}
```

### **PHASE 3: Controller Splitting (Week 3)**

#### **Day 1-2: Split WhatsAppWebJSController.php**
```php
// COPY methods ke specialized controllers

// CREATE: app/Http/Controllers/Api/WhatsApp/WebhookController.php
class WebhookController extends Controller {
    // COPY constructor logic
    // COPY method webhook (lines with webhook processing)
    // COPY method handleMessageReceived
    // COPY method handleSessionReady
    // COPY validation methods
}

// CREATE: app/Http/Controllers/Api/WhatsApp/QRCodeController.php
class QRCodeController extends Controller {
    // COPY method generateQRCode
    // COPY method refreshQRCode
    // COPY method getQRStatus
}

// CREATE: app/Http/Controllers/Api/WhatsApp/SessionController.php
class SessionController extends Controller {
    // COPY method createSession
    // COPY method connectSession
    // COPY method disconnectSession
    // COPY method reconnectSession
}
```

#### **Day 3-4: Update Routes**
```php
// routes/api.php
// UPDATE route definitions untuk match new controllers
Route::prefix('api/v1/whatsapp')->group(function () {
    Route::post('/webhook', [WhatsApp\WebhookController::class, 'webhook']);
    Route::post('/qr-code', [WhatsApp\QRCodeController::class, 'generate']);
    Route::post('/session', [WhatsApp\SessionController::class, 'create']);
});

// routes/web.php
// UPDATE namespace references
Route::middleware(['auth', 'verified'])->prefix('user')->group(function () {
    Route::resource('/contacts', User\ContactController::class);
    Route::resource('/campaigns', User\CampaignController::class);
});
```

#### **Day 5: Testing & Validation**
```bash
# Test semua functionality masih bekerja
php artisan route:list --name=whatsapp
php artisan route:list --name=contacts
php artisan route:list --name=campaigns

# Test existing endpoints
curl -X POST http://localhost/api/v1/whatsapp/webhook
# Test existing functionality di browser
```

---

## 🧪 **TESTING STRATEGY**

### **✅ BOLEH: Add Tests untuk Existing Functionality**

#### **Unit Tests untuk New Services**
```php
// tests/Unit/Services/WhatsApp/MessageSendingServiceTest.php
class MessageSendingServiceTest extends TestCase {
    public function test_send_message_preserves_existing_behavior() {
        // Setup existing contact
        $contact = Contact::factory()->create();

        // Mock existing HTTP calls
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'test']]])]);

        // Call existing method logic
        $service = new MessageSendingService('token', 'v18.0', 'app', 'phone', 'waba', 1);
        $result = $service->sendMessage($contact->uuid, 'Test message');

        // Assert existing behavior preserved
        $this->assertTrue($result->success);
        $this->assertDatabaseHas('chats', ['contact_id' => $contact->id]);
    }
}
```

#### **Feature Tests untuk Moved Controllers**
```php
// tests/Feature/User/ContactControllerTest.php
class ContactControllerTest extends TestCase {
    public function test_contact_crud_preserves_existing_behavior() {
        // Test existing contact creation flow
        $response = $this->post('/user/contacts', [
            'first_name' => 'John',
            'phone' => '+1234567890'
        ]);

        // Assert existing behavior preserved
        $response->assertRedirect();
        $this->assertDatabaseHas('contacts', ['first_name' => 'John']);
    }
}
```

### **❌ TIDAK BOLEH: Tests untuk New Features**

```bash
# JANGAN buat tests untuk functionality yang tidak ada:
# - Tests untuk "new" API endpoints
# - Tests untuk "enhanced" features
# - Tests untuk "improved" user flows
# - Tests untuk "additional" functionality
```

---

## 📋 **COMPLETE CHECKLIST**

### **✅ DAPAT DILAKUKAN:**

#### **File Movement:**
- [ ] Move controllers ke appropriate folders (Api/, User/, Admin/, Common/)
- [ ] Update namespace declarations in moved files
- [ ] Update route imports and definitions
- [ ] Update controller class references
- [ ] Test all existing endpoints still work

#### **Service Splitting:**
- [ ] Copy existing methods dari WhatsappService.php ke new services
- [ ] Preserve exact method signatures and behavior
- [ ] Update service provider registrations
- [ ] Update controller dependencies
- [ ] Add deprecation methods to old service
- [ ] Test all existing WhatsApp functionality

#### **Controller Splitting:**
- [ ] Copy existing methods dari large controllers ke specialized controllers
- [ ] Preserve exact request/response handling
- [ ] Update route definitions
- [ ] Test all existing endpoints
- [ ] Verify no changes in API responses

#### **Testing:**
- [ ] Add unit tests untuk existing service methods
- [ ] Add feature tests untuk existing endpoints
- [ ] Test existing behavior is preserved
- [ ] Verify no regressions in functionality

### **❌ TIDAK BOLEH DILAKUKAN:**

#### **Code Changes:**
- [ ] JANGAN modify existing method implementations
- [ ] JANGAN change method signatures
- [ ] JANGAN alter business logic
- [ ] JANGAN modify database interactions
- [ ] JANGAN change API response formats

#### **Feature Addition:**
- [ ] JANGAN add new methods
- [ ] JANGAN create new endpoints
- [ ] JANGAN implement new functionality
- [ ] JANGAN modify user interface
- [ ] JANGAN change Vue.js components

#### **Database Changes:**
- [ ] JANGAN create new tables
- [ ] JANGAN modify existing tables
- [ ] JANGAN add new columns
- [ ] JANGAN change relationships
- [ ] JANGAN modify migrations

---

## 🚨 **CRITICAL WARNINGS**

### **⚠️ HIGH RISK OPERATIONS:**
1. **WhatsappService.php splitting** - Core WhatsApp functionality
2. **Controller movement** - Route changes
3. **Service provider updates** - Dependency injection changes

### **🔒 SAFETY MEASURES:**
1. **Create backup branch** sebelum setiap major change
2. **Test in staging environment** before production
3. **Document existing behavior** before modification
4. **Preserve exact logic** when copying methods
5. **Run full test suite** after each change

### **📊 SUCCESS METRICS:**
- **Zero regressions** - All existing functionality preserved
- **Same API responses** - No changes in external contracts
- **Same user experience** - No UI changes
- **Improved code organization** - Files in logical folders
- **Better maintainability** - Smaller, focused classes

---

## 🎯 **FINAL OUTCOMES**

### **✅ Expected Results:**
1. **Better Code Organization** - Files in logical, well-structured folders
2. **Smaller Classes** - Focused single responsibilities (<300 lines)
3. **Cleaner Dependencies** - Constructor injection instead of manual instantiation
4. **Easier Navigation** - Developers can find code faster
5. **Better Maintainability** - Easier to modify and extend existing functionality

### **🔒 What Stays Exactly the Same:**
1. **User Interface** - No changes to Vue.js components
2. **API Contracts** - Same endpoints, same request/response formats
3. **Business Logic** - Exact same rules and processes
4. **Database Schema** - No structural changes
5. **User Experience** - Same flows and interactions

### **⏰ Timeline:**
- **Week 1:** File movement and organization
- **Week 2:** Service splitting and dependency injection
- **Week 3:** Controller splitting and testing
- **Total:** 3 weeks for complete refactoring

**KEY SUCCESS METRIC:** After refactoring, semua existing functionality harus bekerja **exactly the same** seperti sebelumnya, hanya dengan code organization yang lebih baik.