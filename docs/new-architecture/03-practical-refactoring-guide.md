# 🛠️ **PRACTICAL REFACTORING GUIDE (COPY-PASTE APPROACH)**

## 🎯 **IMPORTANT CLARIFICATION**

**INI ADALAH REFACTORING, BUKAN PEMBUATAN APLIKASI BARU!**

Fokus utama:
- ✅ **Move & reorganize existing files**
- ✅ **Split large classes dengan copy-paste methods**
- ✅ **Update dependency injection**
- ✅ **Improve code organization**

**JANGAN:**
- ❌ Tulis ulang frontend components
- ❌ Buat halaman baru
- ❌ Ubah Vue.js logic
- ❌ Redesign database schema

---

## 📁 **FILE REORGANIZATION (MOVE, NOT CREATE)**

### **1. Controller Restructuring**

#### **Current Structure:**
```
app/Http/Controllers/ (68 files - campur aduk)
├── ApiController.php (764 lines)
├── AuthController.php
├── ChatController.php
├── WhatsAppWebJSController.php (703 lines)
├── WebhookController.php (447 lines)
├── User/ (sudah ada beberapa)
├── Admin/ (sudah ada beberapa)
└── ...
```

#### **Target Structure (MOVE FILES):**
```bash
# PINDAHKAN file yang sudah ada:
mv app/Http/Controllers/ApiController.php app/Http/Controllers/Api/
mv app/Http/Controllers/WebhookController.php app/Http/Controllers/Api/
mv app/Http/Controllers/WhatsAppWebJSController.php app/Http/Controllers/Api/WhatsApp/

# Buat folder baru jika perlu:
mkdir -p app/Http/Controllers/Api/WhatsApp
mkdir -p app/Http/Controllers/Common

# Pindahkan API-related controllers:
mv app/Http/Controllers/*Controller.php app/Http/Controllers/Api/ (untuk API endpoints)
mv app/Http/Controllers/User/*Controller.php app/Http/Controllers/User/ (sudah ada)
mv app/Http/Controllers/Admin/*Controller.php app/Http/Controllers/Admin/ (sudah ada)
```

#### **Update Namespace dan Routes:**
```php
// SEBELUM:
namespace App\Http\Controllers;

// SESUDAH (update namespace di file yang dipindah):
namespace App\Http\Controllers\Api;
namespace App\Http\Controllers\Api\WhatsApp;

// UPDATE routes/web.php:
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Controllers\Api\WhatsAppWebJSController;
```

### **2. Service Layer Splitting (COPY-PASTE METHODS)**

#### **Current Problem:**
```php
// app/Services/WhatsappService.php (1,565 lines)
class WhatsappService {
    // 1. Messaging methods (lines 70-167)
    public function sendMessage($contactUuId, $messageContent, $userId = null, $type="text", $buttons = [], $header = [], $footer = null, $buttonLabel = null) { ... }
    public function sendTemplateMessage($contactUuId, $templateContent, $userId = null, $campaignId = null, $mediaId = null) { ... }
    public function sendMedia($contactUuId, $mediaType, $mediaFileName, $mediaUrl, $location, $caption = null, $transcription = null) { ... }

    // 2. Template methods (lines 176-287)
    public function createTemplate(Request $request) { ... }
    public function updateTemplate(Request $request, $uuid) { ... }
    public function syncTemplates() { ... }
    public function deleteTemplate($uuid) { ... }

    // 3. Media methods (lines 400-521)
    public function getMedia($mediaId) { ... }
    public function viewMedia($mediaId) { ... }
    public function initiateResumableUploadSession($file) { ... }

    // 4. Business Profile methods (lines 573-1285)
    public function getBusinessProfile() { ... }
    public function updateBusinessProfile(Request $request) { ... }
    public function getPhoneNumberId() { ... }
    public function getPhoneNumberStatus() { ... }

    // 5. Health & monitoring methods (lines 1123-1194)
    public function checkHealth() { ... }
    public function subscribeToWaba() { ... }
    public function getWabaSubscriptions() { ... }
}
```

#### **Solution: Split dengan Copy-Paste**

```bash
# STEP 1: Buat folder baru
mkdir -p app/Services/WhatsApp

# STEP 2: Buat file baru dan copy methods
```

##### **File 1: MessageSendingService.php**
```php
<?php

namespace App\Services\WhatsApp;

use App\Models\Contact;
use App\Models\Chat;
use App\Models\Campaign;
use App\Events\NewChatEvent;
use App\Helpers\WebhookHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// COPY EXISTING METHODS DARI WhatsappService.php
class MessageSendingService {
    // COPY constructor logic dan properties
    private $accessToken;
    private $apiVersion;
    private $appId;
    private $phoneNumberId;
    private $workspaceId;
    private $wabaId;

    public function __construct($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId) {
        // COPY existing constructor logic
        $this->accessToken = $accessToken;
        $this->apiVersion = $apiVersion;
        $this->appId = $appId;
        $this->phoneNumberId = $phoneNumberId;
        $this->wabaId = $wabaId;
        $this->workspaceId = $workspaceId;
    }

    // COPY existing method sendMessage (lines 70-167)
    public function sendMessage($contactUuId, $messageContent, $userId = null, $type="text", $buttons = [], $header = [], $footer = null, $buttonLabel = null) {
        // COPY entire existing method implementation
        $contact = Contact::where('uuid', $contactUuId)->first();
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $headers = $this->setHeaders();
        // ... copy semua existing logic

        return $responseObject;
    }

    // COPY existing method sendTemplateMessage (lines 176-235)
    public function sendTemplateMessage($contactUuId, $templateContent, $userId = null, $campaignId = null, $mediaId = null) {
        // COPY entire existing method
        $contact = Contact::where('uuid', $contactUuId)->first();
        // ... copy semua existing logic
    }

    // COPY existing method sendMedia (lines 400-477)
    public function sendMedia($contactUuId, $mediaType, $mediaFileName, $mediaUrl, $location, $caption = null, $transcription = null) {
        // COPY entire existing method
        $contact = Contact::where('uuid', $contactUuId)->first();
        // ... copy semua existing logic
    }

    // COPY helper methods yang dibutuhkan
    private function setHeaders() {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ];
    }

    private function sendHttpRequest($method, $url, $data = [], $headers = []) {
        // COPY existing method
    }
}
```

##### **File 2: TemplateManagementService.php**
```php
<?php

namespace App\Services\WhatsApp;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// COPY TEMPLATE METHODS DARI WhatsappService.php
class TemplateManagementService {
    // COPY constructor yang sama
    private $accessToken;
    private $apiVersion;
    private $appId;
    private $phoneNumberId;
    private $workspaceId;
    private $wabaId;

    public function __construct($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId) {
        // COPY existing constructor
    }

    // COPY existing method createTemplate (lines 573-785)
    public function createTemplate(Request $request) {
        // COPY entire existing method implementation
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/message_templates";
        // ... copy semua existing logic
    }

    // COPY existing method updateTemplate (lines 787-1012)
    public function updateTemplate(Request $request, $uuid) {
        // COPY entire existing method
    }

    // COPY existing method syncTemplates (lines 1014-1087)
    public function syncTemplates() {
        // COPY entire existing method
    }

    // COPY existing method deleteTemplate (lines 1095-1113)
    public function deleteTemplate($uuid) {
        // COPY entire existing method
    }
}
```

##### **File 3: BusinessProfileService.php**
```php
<?php

namespace App\Services\WhatsApp;

use App\Models\workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// COPY BUSINESS PROFILE METHODS
class BusinessProfileService {
    // COPY constructor
    private $accessToken;
    private $apiVersion;
    private $appId;
    private $phoneNumberId;
    private $workspaceId;
    private $wabaId;

    public function __construct($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId) {
        // COPY existing constructor
    }

    // COPY existing method getBusinessProfile (lines 1194-1223)
    public function getBusinessProfile() {
        // COPY entire existing method
    }

    // COPY existing method updateBusinessProfile (lines 1225-1285)
    public function updateBusinessProfile(Request $request) {
        // COPY entire existing method
    }

    // COPY existing method getPhoneNumberId (lines 1302-1332)
    public function getPhoneNumberId() {
        // COPY entire existing method
    }

    // COPY existing method getPhoneNumberStatus (lines 1334-1363)
    public function getPhoneNumberStatus() {
        // COPY entire existing method
    }
}
```

#### **STEP 3: Update Service Provider**
```php
// app/Providers/AppServiceProvider.php
public function register(): void {
    // Bind new services dengan existing logic
    $this->app->singleton(MessageSendingService::class, function ($app) {
        // Get existing configuration
        $workspace = Workspace::find(session()->get('current_workspace'));
        $config = json_decode($workspace->metadata, true);
        $whatsappConfig = $config['whatsapp'];

        return new MessageSendingService(
            $whatsappConfig['access_token'],
            $whatsappConfig['api_version'] ?? 'v18.0',
            $whatsappConfig['app_id'],
            $whatsappConfig['phone_number_id'],
            $whatsappConfig['waba_id'],
            $workspace->id
        );
    });

    $this->app->singleton(TemplateManagementService::class, function ($app) {
        // Same logic as above
    });

    $this->app->singleton(BusinessProfileService::class, function ($app) {
        // Same logic as above
    });
}
```

#### **STEP 4: Update Controllers**
```php
// SEBELUM:
class ChatController extends BaseController {
    public function sendMessage(Request $request) {
        $whatsappService = new WhatsappService(...);
        return $whatsappService->sendMessage(...);
    }
}

// SESUDAH (update constructor injection):
class ChatController extends BaseController {
    public function __construct(
        private MessageSendingService $messageSendingService,
        private TemplateManagementService $templateManagementService
    ) {}

    public function sendMessage(Request $request) {
        return $this->messageSendingService->sendMessage(...);
    }

    public function sendTemplate(Request $request) {
        return $this->templateManagementService->sendTemplateMessage(...);
    }
}
```

#### **STEP 5: Deprecate Old Service**
```php
// app/Services/WhatsappService.php
class WhatsappService {
    /**
     * @deprecated Use MessageSendingService instead
     */
    public function sendMessage($contactUuId, $messageContent, $userId = null, $type="text", $buttons = [], $header = [], $footer = null, $buttonLabel = null) {
        // Delegate to new service
        $service = app(MessageSendingService::class);
        return $service->sendMessage($contactUuId, $messageContent, $userId, $type, $buttons, $header, $footer, $buttonLabel);
    }

    /**
     * @deprecated Use TemplateManagementService instead
     */
    public function createTemplate(Request $request) {
        $service = app(TemplateManagementService::class);
        return $service->createTemplate($request);
    }

    // ... delegate semua methods ke services baru
}
```

---

## 🔧 **VALIDATION REFACTORING (COPY-PASTE LOGIC)**

### **Extract Validation Logic dari Controllers**

#### **Current Problem:**
```php
// app/Http/Controllers/ApiController.php (lines 82-110)
public function storeContact(Request $request, $uuid = null) {
    $validator = Validator::make($request->all(), [
        'first_name' => $request->isMethod('post') ? 'required' : 'required|sometimes',
        'phone' => [
            'required',
            'string',
            'max:255',
            'phone:AUTO',
            new UniquePhone($request->workspace, $uuid),
        ],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'statusCode' => 400,
            'message' => __('The given data was invalid.'),
            'errors' => $validator->errors()
        ], 400);
    }

    // Business logic...
}
```

#### **Solution: Copy Validation Logic to Form Request**

```bash
# Buat form request:
php artisan make:request StoreContactRequest
php artisan make:request UpdateContactRequest
```

##### **app/Http/Requests/StoreContactRequest.php**
```php
<?php

namespace App\Http\Requests;

use App\Rules\UniquePhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest {
    // COPY existing validation logic dari controller
    public function rules(): array {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => [
                'required',
                'string',
                'max:255',
                'phone:AUTO',
                new UniquePhone($this->workspace()),
            ],
            'email' => 'nullable|email|max:255',
        ];
    }

    public function authorize(): bool {
        return true; // Copy existing authorization logic
    }

    // COPY existing custom messages dari controller
    public function messages(): array {
        return [
            'first_name.required' => 'First name is required',
            'phone.required' => 'Phone number is required',
            'phone.unique' => 'A contact with this phone number already exists',
        ];
    }
}
```

##### **Update Controller:**
```php
// SEBELUM:
public function storeContact(Request $request, $uuid = null) {
    $validator = Validator::make($request->all(), [ /* validation rules */ ]);
    if ($validator->fails()) { /* error handling */ }
    // Business logic...
}

// SESUDAH:
public function storeContact(StoreContactRequest $request) {
    // Validation sudah otomatis dilakukan oleh FormRequest
    $validated = $request->validated();

    // Business logic tetap sama...
    $contact = Contact::create([
        'workspace_id' => $request->workspace,
        'first_name' => $validated['first_name'],
        'phone' => $validated['phone'],
        // ...
    ]);

    return response()->json([
        'statusCode' => 200,
        'message' => 'Contact created successfully',
        'data' => $contact
    ]);
}
```

---

## 🧪 **TESTING REFACTORING (ADD TESTS, NOT REWRITE)**

### **Add Tests untuk Existing Functionality**

#### **1. Test untuk Service Methods**
```php
// tests/Unit/Services/WhatsApp/MessageSendingServiceTest.php
<?php

namespace Tests\Unit\Services\WhatsApp;

use Tests\TestCase;
use App\Services\WhatsApp\MessageSendingService;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MessageSendingServiceTest extends TestCase {
    use RefreshDatabase;

    private MessageSendingService $service;

    protected function setUp(): void {
        parent::setUp();

        // Setup service dengan existing configuration
        $this->service = new MessageSendingService(
            'test_token',
            'v18.0',
            'test_app_id',
            'test_phone_id',
            'test_waba_id',
            1
        );
    }

    public function test_send_message_with_valid_contact(): void {
        // Setup existing contact
        $contact = Contact::factory()->create();

        // Mock HTTP call
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'test_msg_id']]
            ])
        ]);

        // Call existing method
        $result = $this->service->sendMessage($contact->uuid, 'Test message');

        // Assert existing behavior
        $this->assertTrue($result->success);
        $this->assertDatabaseHas('chats', [
            'contact_id' => $contact->id,
            'type' => 'outbound'
        ]);
    }
}
```

#### **2. Test untuk Controller Methods**
```php
// tests/Feature/ContactControllerTest.php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContactControllerTest extends TestCase {
    use RefreshDatabase;

    public function test_store_contact_with_valid_data(): void {
        // Setup existing user
        $user = User::factory()->create();

        // Test existing endpoint dengan existing logic
        $response = $this->actingAs($user)
            ->postJson('/api/contacts', [
                'first_name' => 'John',
                'phone' => '+1234567890'
            ]);

        // Assert existing behavior
        $response->assertStatus(200);
        $this->assertDatabaseHas('contacts', [
            'first_name' => 'John',
            'phone' => '+1234567890'
        ]);
    }
}
```

---

## 📋 **REFACTORING CHECKLIST (COPY-PASTE APPROACH)**

### **File Reorganization:**
- [ ] Move controllers ke appropriate folders
- [ ] Update namespaces di moved files
- [ ] Update route imports
- [ ] Update controller references

### **Service Splitting:**
- [ ] Copy existing methods ke new service classes
- [ ] Update constructor logic di new services
- [ ] Register new services di service providers
- [ ] Update controller dependencies
- [ ] Add deprecation methods ke old service
- [ ] Update all controller references

### **Validation Extraction:**
- [ ] Create Form Request classes
- [ ] Copy validation logic dari controllers
- [ ] Copy custom rules dan messages
- [ ] Update controller methods signature
- [ ] Test validation behavior tetap sama

### **Testing Addition:**
- [ ] Create unit tests untuk existing service methods
- [ ] Create feature tests untuk existing endpoints
- [ ] Test existing behavior tidak berubah
- [ ] Add tests untuk new service classes

### **Documentation Updates:**
- [ ] Update existing documentation
- [ ] Document file structure changes
- [ ] Update service usage examples
- [ ] Document deprecation timeline

---

## 🚀 **IMPLEMENTATION STEPS**

### **Week 1: File Reorganization**
```bash
# Hari 1-2: Move controllers
mv app/Http/Controllers/ApiController.php app/Http/Controllers/Api/
mv app/Http/Controllers/WebhookController.php app/Http/Controllers/Api/

# Hari 3-4: Update namespaces dan routes
# Hari 5: Test semua movement works
```

### **Week 2: Service Splitting**
```bash
# Hari 1-3: Copy methods ke new services
# Hari 4: Update service providers
# Hari 5: Update controller dependencies
```

### **Week 3: Validation & Testing**
```bash
# Hari 1-2: Extract validation logic
# Hari 3-4: Add tests untuk existing functionality
# Hari 5: Final testing dan cleanup
```

---

## ✅ **KEY TAKEAWAYS**

1. **COPY-PASTE, NOT REWRITE** - Semua existing logic dipertahankan
2. **MOVE FILES, DON'T CREATE** - Reorganisasi struktur yang sudah ada
3. **PRESERVE BEHAVIOR** - Functionality harus tetap sama
4. **INCREMENTAL APPROACH** - Lakukan perubahan sedikit demi sedikit
5. **TEST EXISTING BEHAVIOR** - Pastikan tidak ada regression

**GOAL:** Clean code organization tanpa mengubah existing functionality!