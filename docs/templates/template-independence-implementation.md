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

## Implementation Steps

### Step 1: Database Migration

**File:** `database/migrations/xxxx_xx_xx_update_templates_for_drafts.php`

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
            $table->string('meta_id', 128)->nullable()->change();
            
            // Add provider_type to track template source
            $table->string('provider_type', 50)->default('local')->after('status');
            // Values: 'local', 'meta_api', 'webjs'
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('meta_id', 128)->nullable(false)->change();
            $table->dropColumn('provider_type');
        });
    }
};
```

---

### Step 2: Update Template Model

**File:** `app/Models/Template.php`

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

    protected $guarded = [];

    // Status constants
    const STATUS_DRAFT = 'DRAFT';
    const STATUS_PENDING = 'PENDING';
    const STATUS_APPROVED = 'APPROVED';
    const STATUS_REJECTED = 'REJECTED';

    // Provider types
    const PROVIDER_LOCAL = 'local';
    const PROVIDER_META_API = 'meta_api';
    const PROVIDER_WEBJS = 'webjs';

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
     * Scope: Query by workspace
     */
    public function scopeInWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Check if template can be used with specific provider
     */
    public function canUseWithProvider(string $provider): bool
    {
        switch ($provider) {
            case self::PROVIDER_META_API:
                // Meta API requires APPROVED status
                return $this->status === self::STATUS_APPROVED && !empty($this->meta_id);
            
            case self::PROVIDER_WEBJS:
                // WebJS can use any template (draft or published)
                return true;
            
            default:
                return false;
        }
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
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING => 'Pending Review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => 'Unknown',
        };
    }
}
```

---

### Step 3: Update TemplateService

**File:** `app/Services/TemplateService.php`

Add new method for saving drafts:

```php
/**
 * Save template as draft (local database only, no Meta API call)
 */
public function saveDraft(Request $request)
{
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

    // Build components array
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
        'provider_type' => Template::PROVIDER_LOCAL,
        'created_by' => auth()->id(),
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Template saved as draft',
        'data' => [
            'uuid' => $template->uuid,
            'status' => $template->status,
        ]
    ]);
}

/**
 * Publish draft template to Meta API
 */
public function publishToMeta(string $uuid)
{
    $template = Template::where('uuid', $uuid)
        ->where('workspace_id', $this->workspaceId)
        ->firstOrFail();

    if (!$template->isDraft()) {
        return response()->json([
            'success' => false,
            'message' => 'Template is already published'
        ], 400);
    }

    // Check if Meta API is configured
    $workspace = Workspace::find($this->workspaceId);
    $config = $workspace->metadata ? json_decode($workspace->metadata, true) : [];
    
    if (empty($config['whatsapp']['access_token']) || empty($config['whatsapp']['waba_id'])) {
        return response()->json([
            'success' => false,
            'message' => 'Meta API is not configured. Please configure WhatsApp connection first.'
        ], 400);
    }

    // Create request from stored template data
    $templateData = json_decode($template->metadata, true);
    $request = new Request([
        'name' => $template->name,
        'category' => $template->category,
        'language' => $template->language,
        'components' => $templateData,
    ]);

    // Submit to Meta API
    $response = $this->templateService->createTemplate($request);

    if ($response->success) {
        $template->update([
            'meta_id' => $response->data['id'] ?? null,
            'status' => Template::STATUS_PENDING,
            'provider_type' => Template::PROVIDER_META_API,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Template submitted to Meta for review',
            'data' => [
                'uuid' => $template->uuid,
                'status' => $template->status,
            ]
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => 'Failed to publish template',
        'error' => $response->error ?? 'Unknown error'
    ], 500);
}

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
