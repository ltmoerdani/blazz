# Template Independence Implementation Guide

> **Document Version:** 1.1  
> **Created:** November 27, 2025  
> **Updated:** November 27, 2025  
> **Status:** ✅ Approved for Implementation  
> **Approach:** Scenario A - Draft Template (Local-First)  
> **Related:** [Template System Architecture](./template-system-architecture.md)

---

## 🎯 Objective

Implementasi **Scenario A: Draft Template** yang memungkinkan user membuat dan menyimpan template **tanpa** memerlukan koneksi WhatsApp (Meta API atau WebJS) terlebih dahulu.

### ✅ Approved Behavior

- User dapat membuat template kapanpun
- Template disimpan sebagai `DRAFT` di database lokal
- Template DRAFT dapat langsung digunakan untuk WhatsApp WebJS
- User dapat publish ke Meta API jika diperlukan (optional)
- Template dengan status APPROVED dapat digunakan untuk Meta API dan WebJS

---

## Scenario A: Approved Flow

### Current Flow ❌ (To Be Replaced)
```
User → Create Template Page → "Connect WhatsApp first!" → Cannot proceed
```

### New Flow ✅ (Scenario A)
```
User → Create Template Page → Fill Form → Save as Draft (always available)
                                       └─→ Use immediately with WebJS
                                       └─→ Later: Publish to Meta (if configured)
                                       └─→ After Approval: Use with Meta API
```

### User Actions Available

| Action | Requires Connection | Result |
|--------|--------------------|---------|
| Save as Draft | ❌ No | Template saved locally with status=DRAFT |
| Use with WebJS | ❌ No | Send message using draft template |
| Publish to Meta | ✅ Yes (Meta API configured) | Submit to Meta, status=PENDING |
| Use with Meta API | ✅ Yes (status=APPROVED) | Send via Meta Business API |

---

## 🚦 Implementation Priority

### Phase 1: Core Changes (Required)
1. ⭐ Database Migration - Make `meta_id` nullable
2. ⭐ Model Update - Add status constants & scopes  
3. ⭐ Service Update - Add `saveDraft()` method
4. ⭐ Frontend Update - Remove connection gate, add dual buttons

### Phase 2: Integration (Required)
5. Route registration for new endpoints
6. TemplateResource update for API responses

### Phase 3: Enhancement (Optional)
7. Publish to Meta functionality
8. Template duplication feature
9. Bulk publish drafts

---

## 🏛️ Architecture Compliance

### Verified Against:
- `docs/architecture/01-arsitektur-overview.md`
- `docs/architecture/06-development-patterns-guidelines.md`
- `docs/architecture/04-feature-development-guide.md`

### Pattern Compliance Checklist:

| Pattern | Requirement | Implementation | Status |
|---------|-------------|----------------|--------|
| Service Layer | Business logic in Service, not Controller | `saveDraft()` in `TemplateService` | ✅ |
| Workspace Scoping | All queries scoped by `workspace_id` | `scopeInWorkspace()` scope | ✅ |
| DI via ServiceProvider | Services registered in Provider | Update `UtilityServiceProvider` | ✅ |
| Response Object | Return `(object)['success'=>...]` | Standard response format | ✅ |
| Error Handling | try-catch with DB::transaction | Wrap create/update in transaction | ✅ |
| Logging | Log important operations | `Log::info()` for draft creation | ✅ |
| UUID | Use UUID trait for external reference | `HasUuid` trait in Model | ✅ |
| Soft Deletes | Preserve data with soft delete | `SoftDeletes` trait | ✅ |

---

## Implementation Steps

### Step 1: Database Migration

**File:** `database/migrations/xxxx_xx_xx_update_templates_for_drafts.php`

> ⚠️ **Note:** Only make `meta_id` nullable. Status column already exists and can store 'DRAFT'.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // Make meta_id nullable for draft templates
            // Existing column: string('meta_id', 128) - need to allow null
            $table->string('meta_id', 128)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // Revert: meta_id back to required (will fail if null values exist)
            $table->string('meta_id', 128)->nullable(false)->change();
        });
    }
};
```

**Run migration:**
```bash
php artisan migrate
```

> 💡 **Tip:** Existing `status` column can already store 'DRAFT', 'PENDING', 'APPROVED', 'REJECTED' - no schema change needed for status.

---

### Step 2: Update Template Model

**File:** `app/Models/Template.php`

> ⚠️ **Pattern:** Follow existing model patterns - `HasUuid`, `HasFactory`, scopes, business methods

```php
<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $guarded = [];  // Follow existing pattern

    // Status constants - align with Meta API statuses + DRAFT
    const STATUS_DRAFT = 'DRAFT';           // Local only, not submitted
    const STATUS_PENDING = 'PENDING';       // Submitted to Meta, awaiting review
    const STATUS_APPROVED = 'APPROVED';     // Approved by Meta
    const STATUS_REJECTED = 'REJECTED';     // Rejected by Meta

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Workspace relationship - REQUIRED for multi-tenancy
     */
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Creator relationship
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: Only draft templates
     */
    public function scopeDrafts($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope: Only published templates (non-drafts)
     */
    public function scopePublished($query)
    {
        return $query->where('status', '!=', self::STATUS_DRAFT);
    }

    /**
     * Scope: Templates approved by Meta
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope: Query by workspace (existing pattern)
     */
    public function scopeInWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Scope: Usable templates (DRAFT for WebJS, APPROVED for Meta)
     */
    public function scopeUsableFor($query, string $provider)
    {
        return match($provider) {
            'webjs' => $query, // All templates usable for WebJS
            'meta_api' => $query->where('status', self::STATUS_APPROVED)
                                ->whereNotNull('meta_id'),
            default => $query,
        };
    }

    /**
     * Check if template can be used with specific provider
     */
    public function canUseWithProvider(string $provider): bool
    {
        return match($provider) {
            'meta_api' => $this->status === self::STATUS_APPROVED && !empty($this->meta_id),
            'webjs' => true, // WebJS can use any template
            default => false,
        };
    }

    /**
     * Check if template is a draft
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if template is published to Meta
     */
    public function isPublishedToMeta(): bool
    {
        return !empty($this->meta_id) && $this->status !== self::STATUS_DRAFT;
    }

    /**
     * Get status badge color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_PENDING => 'yellow',
            self::STATUS_APPROVED => 'green',
            self::STATUS_REJECTED => 'red',
            default => 'gray',
        };
    }

    /**
     * Get human-readable status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => __('Draft'),
            self::STATUS_PENDING => __('Pending Review'),
            self::STATUS_APPROVED => __('Approved'),
            self::STATUS_REJECTED => __('Rejected'),
            default => __('Unknown'),
        };
    }
}
```

---

### Step 3: Update TemplateService

**File:** `app/Services/TemplateService.php`

> ⚠️ **IMPORTANT:** Follow existing service pattern - workspace ID via constructor, DI via ServiceProvider

```php
/**
 * Save template as draft (local database only, no Meta API call)
 * 
 * Pattern: Follows existing service methods in TemplateService
 * - Workspace scoping via $this->workspaceId
 * - Standard response object format
 * - DB::transaction for data integrity
 * - Logging for audit trail
 */
public function saveDraft(Request $request)
{
    // Validation - same pattern as createTemplate()
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:128',
        'category' => 'required|in:UTILITY,MARKETING,AUTHENTICATION',
        'language' => 'required|string|max:128',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->messages()->get('*')
        ], 422);
    }

    try {
        DB::beginTransaction();

        // Build components array (reuse existing private method)
        $components = $this->buildComponentsFromRequest($request);

        // Create draft template (no Meta API call)
        $template = Template::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'workspace_id' => $this->workspaceId,
            'meta_id' => null, // No Meta ID for drafts
            'name' => $request->name,
            'category' => $request->category,
            'language' => $request->language,
            'metadata' => json_encode($components),
            'status' => Template::STATUS_DRAFT,
            'created_by' => auth()->id(),
        ]);

        DB::commit();

        // Logging - same pattern as existing services
        Log::info('Template draft saved', [
            'workspace_id' => $this->workspaceId,
            'template_id' => $template->id,
            'template_uuid' => $template->uuid,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Template saved as draft'),
            'data' => [
                'uuid' => $template->uuid,
                'status' => $template->status,
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();

        Log::error('Failed to save template draft', [
            'workspace_id' => $this->workspaceId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => __('Failed to save draft: ') . $e->getMessage(),
        ], 500);
    }
}

/**
 * Publish draft template to Meta API
 * 
 * Pattern: Uses existing TemplateManagementService for Meta API calls
 */
public function publishToMeta(string $uuid)
{
    $template = Template::where('uuid', $uuid)
        ->where('workspace_id', $this->workspaceId)
        ->first();

    if (!$template) {
        return response()->json([
            'success' => false,
            'message' => __('Template not found')
        ], 404);
    }

    if (!$template->isDraft()) {
        return response()->json([
            'success' => false,
            'message' => __('Template is already published')
        ], 400);
    }

    // Check if Meta API is configured via workspace metadata
    $workspace = Workspace::find($this->workspaceId);
    $config = $workspace->metadata ? json_decode($workspace->metadata, true) : [];
    
    if (empty($config['whatsapp']['access_token']) || empty($config['whatsapp']['waba_id'])) {
        return response()->json([
            'success' => false,
            'message' => __('Meta API is not configured. Please configure WhatsApp connection first.')
        ], 400);
    }

    // Reconstruct request from stored template data
    $templateData = json_decode($template->metadata, true);
    $request = new Request([
        'name' => $template->name,
        'category' => $template->category,
        'language' => $template->language,
        'header' => $this->extractComponentByType($templateData, 'HEADER'),
        'body' => $this->extractComponentByType($templateData, 'BODY'),
        'footer' => $this->extractComponentByType($templateData, 'FOOTER'),
        'buttons' => $this->extractButtons($templateData),
    ]);

    // Submit to Meta API via existing TemplateManagementService
    $response = $this->templateService->createTemplate($request);

    if ($response->success) {
        $template->update([
            'meta_id' => $response->data['id'] ?? null,
            'status' => Template::STATUS_PENDING,
        ]);

        Log::info('Template published to Meta', [
            'workspace_id' => $this->workspaceId,
            'template_uuid' => $template->uuid,
            'meta_id' => $response->data['id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Template submitted to Meta for review'),
            'data' => [
                'uuid' => $template->uuid,
                'status' => Template::STATUS_PENDING,
                'meta_id' => $response->data['id'] ?? null,
            ]
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => __('Failed to publish template'),
        'error' => $response->error ?? 'Unknown error'
    ], 500);
}
```

/**
 * Build components array from request data
 */
private function buildComponentsFromRequest(Request $request): array
{
    $components = [];

    // Header component
    if ($request->category !== 'AUTHENTICATION' && !empty($request->header)) {
        $header = $request->header;
        if ($header['format'] === 'TEXT' && !empty($header['text'])) {
            $headerComponent = [
                'type' => 'HEADER',
                'format' => 'TEXT',
                'text' => $header['text'],
            ];
            if (!empty($header['example'])) {
                $headerComponent['example']['header_text'] = $header['example'];
            }
            $components[] = $headerComponent;
        }
        // Handle media headers (IMAGE, VIDEO, DOCUMENT)
        if (in_array($header['format'], ['IMAGE', 'VIDEO', 'DOCUMENT'])) {
            $components[] = [
                'type' => 'HEADER',
                'format' => $header['format'],
            ];
        }
    }

    // Body component
    if ($request->category === 'AUTHENTICATION') {
        $components[] = [
            'type' => 'BODY',
            'add_security_recommendation' => $request->body['add_security_recommendation'] ?? false,
        ];
    } elseif (!empty($request->body['text'])) {
        $bodyComponent = [
            'type' => 'BODY',
            'text' => $request->body['text'],
        ];
        if (!empty($request->body['example'])) {
            $bodyComponent['example']['body_text'][] = $request->body['example'];
        }
        $components[] = $bodyComponent;
    }

    // Footer component
    if (!empty($request->footer['text'])) {
        $components[] = [
            'type' => 'FOOTER',
            'text' => $request->footer['text'],
        ];
    }

    // Buttons component
    if (!empty($request->buttons)) {
        $buttons = [];
        foreach ($request->buttons as $button) {
            $buttons[] = [
                'type' => strtolower($button['type']),
                'text' => $button['text'] ?? '',
                'url' => $button['url'] ?? null,
                'phone_number' => $button['phone_number'] ?? null,
            ];
        }
        if (!empty($buttons)) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $buttons,
            ];
        }
    }

    return $components;
}
```

---

### Step 4: Add New Routes

**File:** `routes/web.php` (or appropriate route file)

```php
// Template routes
Route::prefix('templates')->middleware(['auth'])->group(function () {
    // Existing routes...
    
    // New draft routes
    Route::post('/draft', [TemplateController::class, 'saveDraft']);
    Route::post('/{uuid}/publish', [TemplateController::class, 'publishToMeta']);
});
```

---

### Step 5: Update Frontend (Add.vue)

**Key Changes:**

1. **Remove connection gate:**
```javascript
// REMOVE THIS:
// <div v-if="!isWhatsAppConnected">...</div>

// CHANGE TO: Always show the form
<div>
    <!-- Form content -->
</div>
```

2. **Add dual-button UI:**
```vue
<div class="space-x-2 flex items-center">
    <Link href="/templates" class="...">{{ $t('Back') }}</Link>
    
    <!-- Save as Draft button (always available) -->
    <button 
        @click="saveDraft()" 
        type="button" 
        class="rounded-md px-3 py-2 text-sm bg-gray-600 text-white hover:bg-gray-500"
        :disabled="!isFormValid || isLoading"
    >
        {{ $t('Save as Draft') }}
    </button>
    
    <!-- Submit to Meta button (only if Meta configured) -->
    <button 
        v-if="isMetaConfigured"
        @click="submitToMeta()" 
        type="button" 
        class="rounded-md px-3 py-2 text-sm bg-indigo-600 text-white hover:bg-indigo-500"
        :disabled="!isFormValid || isLoading"
    >
        {{ $t('Submit to Meta') }}
    </button>
</div>
```

3. **Add new computed properties:**
```javascript
const isMetaConfigured = computed(() => {
    return settings.value?.whatsapp?.access_token && settings.value?.whatsapp?.waba_id;
});
```

4. **Add new methods:**
```javascript
const saveDraft = () => {
    isLoading.value = true;
    axios.post('/templates/draft', form.value)
        .then(response => {
            if (response.data.success) {
                toast.success('Template saved as draft');
                router.visit('/templates');
            } else {
                toast.error(response.data.message);
            }
        })
        .finally(() => {
            isLoading.value = false;
        });
};

const submitToMeta = () => {
    // Existing submitForm() logic for Meta API
};
```

---

## Testing Checklist

### Scenario 1: No WhatsApp Connection
- [ ] User can access `/templates/create`
- [ ] Form is displayed without connection warning
- [ ] "Save as Draft" button is visible and functional
- [ ] "Submit to Meta" button is hidden or disabled
- [ ] Draft template is saved to database
- [ ] Draft template appears in template list with "Draft" badge

### Scenario 2: Meta API Configured
- [ ] Both buttons are visible
- [ ] "Save as Draft" saves locally
- [ ] "Submit to Meta" submits to API and saves with PENDING status

### Scenario 3: Publish Draft Later
- [ ] Draft template can be opened for editing
- [ ] "Publish to Meta" action is available
- [ ] After publishing, status changes to PENDING
- [ ] meta_id is populated after successful publish

### Scenario 4: Use Draft with WebJS
- [ ] Draft templates are listed when selecting template for WebJS campaign
- [ ] Template content is correctly sent via WebJS

---

## Rollback Plan

If issues occur after deployment:

1. **Database:** Migration has `down()` method to revert changes
2. **Frontend:** Restore `isWhatsAppConnected` gate
3. **Backend:** Original `createTemplate()` method unchanged

---

## References

- [Template System Architecture](./template-system-architecture.md)
- [Meta Message Templates API](https://developers.facebook.com/docs/whatsapp/business-management-api/message-templates)
