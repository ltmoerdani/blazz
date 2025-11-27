# 📐 Development Patterns & Guidelines

## Overview

Document ini adalah **pedoman wajib** untuk semua development activities di Blazz WhatsApp Business Platform. Mengikuti patterns ini akan memastikan **konsistensi code**, **maintainability**, dan **scalability** sesuai dengan arsitektur **Hybrid Service-Oriented Dual-Server Architecture**.

**Target Audience:** Developers, AI assistants, Code reviewers, System architects
**Last Updated:** November 10, 2025
**Architecture Version:** v2.0 (Dual-Server)

---

## 🎯 Core Development Principles

### **1. Architecture Adherence**
- ✅ **Always follow** dual-server pattern (Laravel + Node.js)
- ✅ **Never bypass** service layer untuk business logic
- ✅ **Always scope** database queries by workspace
- ✅ **Use consistent** error handling patterns

### **2. Code Quality Standards**
- ✅ **Clean Code**: Readable, simple, maintainable
- ✅ **DRY Principle**: Don't repeat yourself
- ✅ **SOLID Principles**: Single responsibility, Open-closed, etc.
- ✅ **Test Coverage**: Critical features must be tested

### **3. Performance Mindset**
- ✅ **Asynchronous processing** untuk heavy operations
- ✅ **Eager loading** untuk prevent N+1 queries
- ✅ **Caching strategies** untuk frequently accessed data
- ✅ **Rate limiting** untuk API endpoints

---

## 🏗️ Feature Development Workflow

### **Phase 1: Planning & Design**

#### **✅ DO's:**
```bash
# 1. Understand requirements thoroughly
- Create user stories with acceptance criteria
- Define API contracts (request/response)
- Plan database schema changes
- Identify cross-service impacts (Laravel ↔ Node.js)

# 2. Create technical design document
- Architecture impact analysis
- Database migration plan
- API endpoint definitions
- Frontend component requirements
```

#### **❌ DON'Ts:**
- ❌ Start coding tanpa clear requirements
- ❌ Skip database design phase
- ❌ Ignore cross-service dependencies
- ❌ Forget about backward compatibility

### **Phase 2: Database Implementation**

#### **✅ Standard Database Pattern:**

```php
// 1. Migration File Naming
YYYY_MM_DD_HHMMSS_create_{table_name}_table.php
YYYY_MM_DD_HHMMSS_add_{column_name}_to_{table_name}_table.php
YYYY_MM_DD_HHMMSS_drop_{table_name}_table.php

// 2. Migration Structure
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('auto_replies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                    // Always use UUID
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade'); // Always workspace-scoped
            $table->string('keyword');
            $table->text('message');
            $table->enum('match_type', ['exact', 'contains', 'starts_with'])->default('contains');
            $table->integer('priority')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('trigger_count')->default(0);
            $table->timestamps();

            // Performance indexes
            $table->index(['workspace_id', 'status']);
            $table->index('keyword');
        });
    }

    public function down()
    {
        Schema::dropIfExists('auto_replies');
    }
};
```

#### **✅ Standard Model Pattern:**

```php
<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutoReply extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $guarded = []; // Never use fillable, use guarded instead

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'trigger_count' => 'integer',
        'metadata' => 'array', // For flexible data storage
    ];

    // ✅ ALWAYS include workspace relationship
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    // ✅ ALWAYS include workspace-scoped scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    // ✅ Business methods in model (simple operations)
    public function matches($incomingMessage)
    {
        $message = strtolower($incomingMessage);
        $keyword = strtolower($this->keyword);

        return match ($this->match_type) {
            'exact' => $message === $keyword,
            'contains' => str_contains($message, $keyword),
            'starts_with' => str_starts_with($message, $keyword),
            default => false,
        };
    }

    public function incrementTriggerCount()
    {
        $this->increment('trigger_count');
    }

    public function toggle()
    {
        $this->status = $this->status === 'active' ? 'inactive' : 'active';
        $this->save();
    }

    // ✅ Accessors for formatted data
    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at->format('M d, Y H:i');
    }
}
```

### **Phase 3: Service Layer Implementation**

#### **✅ Standard Service Pattern:**

```php
<?php

namespace App\Services;

use App\Models\AutoReply;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoReplyService
{
    protected $workspaceId;

    // ✅ ALWAYS accept workspace ID in constructor
    public function __construct($workspaceId)
    {
        $this->workspaceId = $workspaceId;
    }

    /**
     * List auto-replies with pagination and search
     */
    public function list($search = null, $perPage = 15)
    {
        return AutoReply::inWorkspace($this->workspaceId)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('keyword', 'like', "%{$search}%")
                      ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->orderedByPriority()
            ->paginate($perPage);
    }

    /**
     * Create new auto-reply rule
     */
    public function create(array $data)
    {
        try {
            DB::beginTransaction();

            $autoReply = AutoReply::create([
                'workspace_id' => $this->workspaceId,
                'keyword' => $data['keyword'],
                'message' => $data['message'],
                'match_type' => $data['match_type'] ?? 'contains',
                'priority' => $data['priority'] ?? 0,
                'status' => $data['status'] ?? 'active',
            ]);

            DB::commit();

            // ✅ ALWAYS log important operations
            Log::info('Auto-reply created', [
                'workspace_id' => $this->workspaceId,
                'auto_reply_id' => $autoReply->id,
                'keyword' => $autoReply->keyword,
            ]);

            return (object) [
                'success' => true,
                'data' => $autoReply,
                'message' => 'Auto-reply created successfully!',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create auto-reply', [
                'workspace_id' => $this->workspaceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to create auto-reply: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update existing auto-reply
     */
    public function update($uuid, array $data)
    {
        try {
            $autoReply = AutoReply::where('uuid', $uuid)
                ->inWorkspace($this->workspaceId)
                ->firstOrFail();

            $autoReply->update($data);

            Log::info('Auto-reply updated', [
                'workspace_id' => $this->workspaceId,
                'auto_reply_id' => $autoReply->id,
            ]);

            return (object) [
                'success' => true,
                'data' => $autoReply->fresh(),
                'message' => 'Auto-reply updated successfully!',
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to update auto-reply: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete auto-reply (soft delete)
     */
    public function delete($uuid)
    {
        try {
            $autoReply = AutoReply::where('uuid', $uuid)
                ->inWorkspace($this->workspaceId)
                ->firstOrFail();

            $autoReply->delete();

            Log::info('Auto-reply deleted', [
                'workspace_id' => $this->workspaceId,
                'auto_reply_id' => $autoReply->id,
            ]);

            return (object) [
                'success' => true,
                'message' => 'Auto-reply deleted successfully!',
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to delete auto-reply: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Toggle auto-reply status
     */
    public function toggle($uuid)
    {
        try {
            $autoReply = AutoReply::where('uuid', $uuid)
                ->inWorkspace($this->workspaceId)
                ->firstOrFail();

            $autoReply->toggle();

            return (object) [
                'success' => true,
                'data' => $autoReply,
                'message' => "Auto-reply {$autoReply->status}!",
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to toggle auto-reply: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * ✅ Complex business operations that require cross-service calls
     */
    public function processIncomingMessage($chat)
    {
        // Only process incoming messages
        if ($chat->direction !== 'incoming') {
            return null;
        }

        // Get active auto-replies ordered by priority
        $autoReplies = AutoReply::inWorkspace($this->workspaceId)
            ->active()
            ->orderedByPriority()
            ->get();

        // Find first matching rule
        foreach ($autoReplies as $autoReply) {
            if ($autoReply->matches($chat->message)) {
                // Send auto-reply via WhatsApp service
                $this->sendAutoReply($chat, $autoReply);

                // Increment trigger count
                $autoReply->incrementTriggerCount();

                return $autoReply;
            }
        }

        return null;
    }

    /**
     * ✅ Cross-service communication pattern
     */
    protected function sendAutoReply($incomingChat, $autoReply)
    {
        try {
            // Use ChatService untuk send message (calls Node.js service)
            $chatService = new ChatService($this->workspaceId);

            $result = $chatService->sendMessage(
                $incomingChat->contact->uuid,
                $autoReply->message,
                'text'
            );

            if ($result->success) {
                Log::info('Auto-reply sent', [
                    'workspace_id' => $this->workspaceId,
                    'auto_reply_id' => $autoReply->id,
                    'chat_id' => $incomingChat->id,
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to send auto-reply', [
                'workspace_id' => $this->workspaceId,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * ✅ Analytics and reporting methods
     */
    public function getAnalytics()
    {
        return [
            'total_rules' => AutoReply::inWorkspace($this->workspaceId)->count(),
            'active_rules' => AutoReply::inWorkspace($this->workspaceId)->active()->count(),
            'total_triggers' => AutoReply::inWorkspace($this->workspaceId)->sum('trigger_count'),
            'top_rules' => AutoReply::inWorkspace($this->workspaceId)
                ->orderBy('trigger_count', 'desc')
                ->limit(5)
                ->get(['keyword', 'trigger_count']),
        ];
    }
}
```

### **Phase 4: Controller Implementation**

#### **✅ Standard Controller Pattern:**

```php
<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAutoReplyRequest;
use App\Http\Requests\UpdateAutoReplyRequest;
use App\Services\AutoReplyService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AutoReplyController extends Controller
{
    protected $autoReplyService;

    // ✅ ALWAYS initialize service with workspace context
    public function __construct()
    {
        $workspaceId = session()->get('current_workspace');
        $this->autoReplyService = new AutoReplyService($workspaceId);
    }

    /**
     * Display listing of auto-replies
     */
    public function index(Request $request)
    {
        $autoReplies = $this->autoReplyService->list(
            $request->query('search'),
            $request->query('per_page', 15)
        );

        $analytics = $this->autoReplyService->getAnalytics();

        return Inertia::render('User/AutoReplies/Index', [
            'autoReplies' => $autoReplies,
            'analytics' => $analytics,
            'filters' => $request->only(['search', 'per_page']),
        ]);
    }

    /**
     * Store new auto-reply
     */
    public function store(StoreAutoReplyRequest $request)
    {
        $result = $this->autoReplyService->create($request->validated());

        return redirect()->back()->with('status', [
            'type' => $result->success ? 'success' : 'error',
            'message' => $result->message,
        ]);
    }

    /**
     * Update existing auto-reply
     */
    public function update(UpdateAutoReplyRequest $request, $uuid)
    {
        $result = $this->autoReplyService->update($uuid, $request->validated());

        return redirect()->back()->with('status', [
            'type' => $result->success ? 'success' : 'error',
            'message' => $result->message,
        ]);
    }

    /**
     * Delete auto-reply
     */
    public function destroy($uuid)
    {
        $result = $this->autoReplyService->delete($uuid);

        return redirect()->back()->with('status', [
            'type' => $result->success ? 'success' : 'error',
            'message' => $result->message,
        ]);
    }

    /**
     * Toggle auto-reply status
     */
    public function toggle($uuid)
    {
        $result = $this->autoReplyService->toggle($uuid);

        return redirect()->back()->with('status', [
            'type' => $result->success ? 'success' : 'error',
            'message' => $result->message,
        ]);
    }
}
```

#### **✅ Form Request Validation Pattern:**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAutoReplyRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        return [
            'keyword' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'match_type' => 'required|in:exact,contains,starts_with',
            'priority' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|in:active,inactive',
        ];
    }

    public function messages()
    {
        return [
            'keyword.required' => 'Please enter a trigger keyword.',
            'message.required' => 'Please enter a reply message.',
            'match_type.in' => 'Invalid match type selected.',
            'priority.max' => 'Priority cannot exceed 100.',
        ];
    }
}
```

### **Phase 5: Frontend Implementation**

#### **✅ Vue Component Pattern:**

```vue
<!-- resources/js/Pages/User/AutoReplies/Index.vue -->
<template>
  <AuthenticatedLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="flex justify-between items-center mb-6">
          <div>
            <h2 class="text-2xl font-bold text-gray-900">Auto-Replies</h2>
            <p class="text-gray-600">Automatically respond to incoming messages</p>
          </div>
          <button
            @click="showCreateModal = true"
            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
          >
            + Create Auto-Reply
          </button>
        </div>

        <!-- Analytics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <StatCard
            title="Total Rules"
            :value="analytics.total_rules"
            color="blue"
          />
          <StatCard
            title="Active Rules"
            :value="analytics.active_rules"
            color="green"
          />
          <StatCard
            title="Total Triggers"
            :value="analytics.total_triggers"
            color="purple"
          />
        </div>

        <!-- Search and Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
          <div class="flex gap-4">
            <input
              v-model="search"
              @input="debounceSearch"
              type="text"
              placeholder="Search auto-replies..."
              class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <select
              v-model="perPage"
              @change="updatePerPage"
              class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
            >
              <option value="15">15 per page</option>
              <option value="30">30 per page</option>
              <option value="50">50 per page</option>
            </select>
          </div>
        </div>

        <!-- Data Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <TableHeader
                  title="Keyword"
                  :sortable="true"
                  @sort="handleSort('keyword')"
                />
                <TableHeader
                  title="Message"
                  :sortable="false"
                />
                <TableHeader
                  title="Match Type"
                  :sortable="true"
                  @sort="handleSort('match_type')"
                />
                <TableHeader
                  title="Status"
                  :sortable="true"
                  @sort="handleSort('status')"
                />
                <TableHeader
                  title="Triggers"
                  :sortable="true"
                  @sort="handleSort('trigger_count')"
                />
                <TableHeader
                  title="Actions"
                  :sortable="false"
                  :align="'text-right'"
                />
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="autoReply in autoReplies.data" :key="autoReply.uuid">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  {{ autoReply.keyword }}
                </td>
                <td class="px-6 py-4 text-sm text-gray-500">
                  <div class="max-w-xs truncate" :title="autoReply.message">
                    {{ autoReply.message }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <span
                    class="px-2 py-1 bg-gray-100 rounded text-xs font-medium"
                    :class="getMatchTypeClass(autoReply.match_type)"
                  >
                    {{ autoReply.match_type }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <StatusBadge
                    :status="autoReply.status"
                    @toggle="toggleStatus(autoReply.uuid)"
                  />
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ autoReply.trigger_count.toLocaleString() }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex justify-end space-x-2">
                    <ActionButton
                      @click="editAutoReply(autoReply)"
                      label="Edit"
                      icon="edit"
                      color="blue"
                    />
                    <ActionButton
                      @click="deleteAutoReply(autoReply.uuid)"
                      label="Delete"
                      icon="trash"
                      color="red"
                      :confirm="true"
                    />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Pagination -->
          <div class="px-6 py-4 bg-gray-50">
            <Pagination :links="autoReplies.links" />
          </div>
        </div>

        <!-- Modals -->
        <AutoReplyModal
          v-if="showCreateModal || editingAutoReply"
          :autoReply="editingAutoReply"
          @close="closeModal"
          @saved="handleSaved"
        />
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import TableHeader from '@/Components/UI/TableHeader.vue'
import StatusBadge from '@/Components/UI/StatusBadge.vue'
import ActionButton from '@/Components/UI/ActionButton.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import AutoReplyModal from './Partials/AutoReplyModal.vue'

const props = defineProps({
  autoReplies: Object,
  analytics: Object,
  filters: Object,
})

// Reactive state
const search = ref(props.filters.search || '')
const perPage = ref(props.filters.per_page || 15)
const showCreateModal = ref(false)
const editingAutoReply = ref(null)

// Debounced search
const debounceSearch = debounce(() => {
  router.get(route('user.auto-replies.index'), {
    search: search.value,
    per_page: perPage.value
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}, 300)

// Methods
const updatePerPage = () => {
  debounceSearch()
}

const handleSort = (field) => {
  // Implement sorting logic
}

const getMatchTypeClass = (type) => {
  const classes = {
    'exact': 'bg-blue-100 text-blue-800',
    'contains': 'bg-green-100 text-green-800',
    'starts_with': 'bg-yellow-100 text-yellow-800',
  }
  return classes[type] || 'bg-gray-100 text-gray-800'
}

const editAutoReply = (autoReply) => {
  editingAutoReply.value = autoReply
}

const closeModal = () => {
  showCreateModal.value = false
  editingAutoReply.value = null
}

const handleSaved = () => {
  closeModal()
  router.reload()
}

const toggleStatus = (uuid) => {
  router.patch(route('user.auto-replies.toggle', uuid), {}, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      // Show success notification
    }
  })
}

const deleteAutoReply = (uuid) => {
  router.delete(route('user.auto-replies.destroy', uuid))
}
</script>
```

---

## 🔄 Node.js Service Integration Patterns

### **✅ When to Use Node.js Service:**

1. **WhatsApp Operations**
   - Send/receive messages
   - Account management
   - QR code generation
   - Provider switching

2. **Real-time Features**
   - WebSocket connections
   - Event streaming
   - Live status updates

3. **Heavy Processing**
   - Media file processing
   - Complex calculations
   - External API integrations

### **✅ Laravel-to-Node.js Communication Pattern:**

```php
// Laravel Service Client
class WhatsAppServiceClient
{
    private string $baseUrl;
    private string $internalToken;

    public function __construct()
    {
        $this->baseUrl = config('whatsapp.node_service_url');
        $this->internalToken = config('whatsapp.internal_token');
    }

    public function sendMessage(string $workspaceId, string $contactUuid, string $message): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->internalToken,
            'Accept' => 'application/json',
        ])->timeout(30) // Always set timeout
        ->post("{$this->baseUrl}/internal/send-message", [
            'workspace_id' => $workspaceId,
            'contact_uuid' => $contactUuid,
            'message' => $message,
            'type' => 'text',
        ]);

        if (!$response->successful()) {
            throw new WhatsAppServiceException(
                'WhatsApp service error: ' . $response->body(),
                $response->status()
            );
        }

        return $response->json();
    }
}
```

### **✅ Node.js Service Pattern:**

```typescript
// whatsapp-service/src/services/WhatsAppMessageService.ts
export class WhatsAppMessageService {
    private providers: Map<string, WhatsAppProviderInterface> = new Map();

    async sendMessage(workspaceId: string, contactUuid: string, message: string, type: string): Promise<any> {
        try {
            const provider = await this.getProvider(workspaceId);

            // Validate inputs
            this.validateMessageInput(message, type);

            // Get contact information
            const contact = await this.getContactByUuid(contactUuid);

            // Send message via provider
            const result = await provider.sendMessage(contact.phone, message, type);

            // Log operation
            this.logMessageSent(workspaceId, contactUuid, result);

            return {
                success: true,
                messageId: result.messageId,
                provider: provider.getType(),
            };

        } catch (error) {
            this.logError('sendMessage', error, { workspaceId, contactUuid });
            throw error;
        }
    }

    private async getProvider(workspaceId: string): Promise<WhatsAppProviderInterface> {
        if (this.providers.has(workspaceId)) {
            return this.providers.get(workspaceId)!;
        }

        const workspaceConfig = await this.fetchWorkspaceConfig(workspaceId);
        const provider = this.createProvider(workspaceConfig);

        await provider.initialize();
        this.providers.set(workspaceId, provider);

        return provider;
    }

    private validateMessageInput(message: string, type: string): void {
        if (!message || message.trim().length === 0) {
            throw new ValidationError('Message cannot be empty');
        }

        if (message.length > 1000) {
            throw new ValidationError('Message too long (max 1000 characters)');
        }

        if (!['text', 'image', 'document'].includes(type)) {
            throw new ValidationError('Invalid message type');
        }
    }
}
```

---

## 🔧 Job & Queue Patterns

### **✅ Standard Job Pattern:**

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAutoReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $chatId;
    public $autoReplyId;
    public $workspaceId;

    // ✅ ALWAYS configure job properties
    public $tries = 3;
    public $timeout = 120;
    public $backoff = [30, 60, 120]; // Exponential backoff
    public $retryAfter = 60;

    // ✅ Use specific queue for better performance
    public function __construct($chatId, $autoReplyId, $workspaceId)
    {
        $this->chatId = $chatId;
        $this->autoReplyId = $autoReplyId;
        $this->workspaceId = $workspaceId;
    }

    public function handle()
    {
        try {
            $chat = Chat::find($this->chatId);
            $autoReply = AutoReply::find($this->autoReplyId);

            if (!$chat || !$autoReply) {
                Log::warning('AutoReply job: Missing data', [
                    'chat_id' => $this->chatId,
                    'auto_reply_id' => $this->autoReplyId,
                ]);
                return;
            }

            // Verify workspace access
            if ($chat->workspace_id !== $this->workspaceId || $autoReply->workspace_id !== $this->workspaceId) {
                Log::error('AutoReply job: Workspace mismatch', [
                    'job_workspace' => $this->workspaceId,
                    'chat_workspace' => $chat->workspace_id,
                    'auto_reply_workspace' => $autoReply->workspace_id,
                ]);
                return;
            }

            // Send auto-reply via WhatsApp service
            $chatService = new ChatService($this->workspaceId);
            $result = $chatService->sendMessage(
                $chat->contact->uuid,
                $autoReply->message,
                'text'
            );

            if ($result->success) {
                Log::info('Auto-reply sent successfully', [
                    'chat_id' => $this->chatId,
                    'auto_reply_id' => $this->autoReplyId,
                    'workspace_id' => $this->workspaceId,
                ]);
            } else {
                throw new \Exception('Failed to send auto-reply: ' . $result->message);
            }

        } catch (\Exception $e) {
            Log::error('AutoReply job failed', [
                'chat_id' => $this->chatId,
                'auto_reply_id' => $this->autoReplyId,
                'workspace_id' => $this->workspaceId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw untuk trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        Log::error('AutoReply job failed permanently', [
            'chat_id' => $this->chatId,
            'auto_reply_id' => $this->autoReplyId,
            'workspace_id' => $this->workspaceId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Optional: Send notification to user about failed auto-reply
        // $this->notifyUserAboutFailure();
    }
}
```

### **✅ Job Dispatching Pattern:**

```php
// In Service Method
public function processIncomingMessage($chat)
{
    // ... existing logic ...

    if ($matchingAutoReply) {
        // ✅ ALWAYS use queue for WhatsApp operations
        SendAutoReplyJob::dispatch($chat->id, $matchingAutoReply->id, $this->workspaceId)
            ->onQueue('auto-replies') // Specific queue
            ->delay(now()->addSeconds(2)); // Small delay for better UX
    }
}
```

---

## 📁 File Organization Patterns

### **✅ DO's - File Placement:**

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── User/           // User-facing features
│   │   │   └── AutoReplyController.php
│   │   ├── Admin/          // Admin features
│   │   └── ApiController.php // API endpoints
│   ├── Requests/           // Form validation
│   │   ├── StoreAutoReplyRequest.php
│   │   └── UpdateAutoReplyRequest.php
│   └── Middleware/         // HTTP middleware
├── Services/               // Business logic
│   ├── AutoReplyService.php
│   └── WhatsApp/
│       ├── Http/          // WhatsApp HTTP client
│       └── Adapters/      // Provider adapters
├── Jobs/                   // Background jobs
│   └── SendAutoReplyJob.php
├── Models/                 // Eloquent models
│   └── AutoReply.php
└── Events/                 // Event classes
    └── AutoReplyTriggeredEvent.php

resources/js/Pages/User/AutoReplies/
├── Index.vue              // Main listing page
├── Create.vue             // Create form
├── Edit.vue               // Edit form
└── Partials/
    ├── AutoReplyModal.vue // Reusable modal
    └── AutoReplyForm.vue  // Form component
```

### **❌ DON'Ts - File Organization:**

- ❌ Create controllers in wrong namespace
- ❌ Put business logic in controllers
- ❌ Create services without workspace context
- ❌ Mix frontend and backend logic
- ❌ Create deeply nested folder structures
- ❌ Use generic names like `Helper.php`, `Util.php`

---

## 🎯 Coding Standards & Best Practices

### **✅ Naming Conventions:**

```php
// Classes: PascalCase
AutoReplyService.php
AutoReplyController.php
SendAutoReplyJob.php
StoreAutoReplyRequest.php

// Methods: camelCase
public function createAutoReply()
public function processIncomingMessage()
public function toggleStatus()

// Variables: camelCase
$autoReplyService
$workspaceId
$isActive

// Constants: UPPER_SNAKE_CASE
const MAX_RETRY_ATTEMPTS = 3;
const DEFAULT_TIMEOUT = 120;
```

### **✅ Error Handling Pattern:**

```php
public function create(array $data)
{
    try {
        DB::beginTransaction();

        // Business logic here

        DB::commit();

        return (object) [
            'success' => true,
            'data' => $result,
            'message' => 'Operation completed successfully',
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

### **✅ Logging Pattern:**

```php
// ALWAYS log important operations
Log::info('Auto-reply created', [
    'workspace_id' => $this->workspaceId,
    'auto_reply_id' => $autoReply->id,
    'user_id' => auth()->id(),
]);

// Log errors with context
Log::error('Failed to send WhatsApp message', [
    'workspace_id' => $workspaceId,
    'contact_uuid' => $contactUuid,
    'error' => $e->getMessage(),
    'provider' => $providerType,
]);

// Log warnings for edge cases
Log::warning('Auto-reply not found', [
    'uuid' => $uuid,
    'workspace_id' => $this->workspaceId,
]);
```

### **✅ Database Query Pattern:**

```php
// ✅ GOOD: Always scope by workspace
$autoReplies = AutoReply::inWorkspace($this->workspaceId)
    ->active()
    ->orderedByPriority()
    ->get();

// ✅ GOOD: Use eager loading to prevent N+1
$campaigns = Campaign::with(['template', 'contactGroup', 'workspace'])
    ->where('workspace_id', $this->workspaceId)
    ->paginate(15);

// ✅ GOOD: Use transactions for multi-step operations
try {
    DB::beginTransaction();

    $campaign = Campaign::create([...]);
    $campaignLogs = CampaignLog::insert([...]);

    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}

// ❌ BAD: Global queries (security risk!)
$autoReplies = AutoReply::all(); // NEVER do this!

// ❌ BAD: N+1 query problem
$campaigns = Campaign::all();
foreach ($campaigns as $campaign) {
    echo $campaign->template->name; // This creates N+1 queries
}
```

---

## 🔐 Security Patterns

### **✅ ALWAYS Follow Security Rules:**

```php
// 1. ✅ ALWAYS scope queries by workspace
public function list()
{
    return AutoReply::where('workspace_id', $this->workspaceId)->get();
}

// 2. ✅ ALWAYS validate input
public function rules()
{
    return [
        'keyword' => 'required|string|max:255|regex:/^[a-zA-Z0-9\s]+$/',
        'message' => 'required|string|max:1000',
    ];
}

// 3. ✅ ALWAYS use parameterized queries (Eloquent handles this)
// NEVER use raw SQL with user input

// 4. ✅ ALWAYS sanitize output
{{ clean($autoReply->message) }} // Use clean() helper

// 5. ✅ ALWAYS check authorization
public function authorize()
{
    return auth()->user()->can('manage-auto-replies', $this->workspace);
}
```

### **❌ NEVER Do These:**

- ❌ Skip workspace scoping
- ❌ Use user input directly in queries
- ❌ Return sensitive data in API responses
- ❌ Ignore input validation
- ❌ Store passwords in plain text
- ❌ Use eval() or similar dangerous functions

---

## 🧪 Testing Patterns

### **✅ Feature Test Pattern:**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\AutoReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoReplyTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();

        // Set workspace context
        session(['current_workspace' => $this->workspace->id]);
    }

    /** @test */
    public function user_can_create_auto_reply()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('user.auto-replies.store'), [
            'keyword' => 'hello',
            'message' => 'Hi! How can I help you?',
            'match_type' => 'contains',
            'priority' => 5,
            'status' => 'active',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('auto_replies', [
            'workspace_id' => $this->workspace->id,
            'keyword' => 'hello',
            'message' => 'Hi! How can I help you?',
        ]);
    }

    /** @test */
    public function auto_reply_matches_incoming_message()
    {
        $autoReply = AutoReply::factory()->create([
            'workspace_id' => $this->workspace->id,
            'keyword' => 'pricing',
            'match_type' => 'contains',
            'status' => 'active',
        ]);

        $this->assertTrue($autoReply->matches('What is your pricing?'));
        $this->assertFalse($autoReply->matches('Hello there'));
    }

    /** @test */
    public function user_cannot_access_other_workspace_auto_replies()
    {
        $otherWorkspace = Workspace::factory()->create();
        $otherAutoReply = AutoReply::factory()->create([
            'workspace_id' => $otherWorkspace->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->get(route('user.auto-replies.index'));

        $response->assertInertia(function ($page) use ($otherAutoReply) {
            return !collect($page->props['autoReplies']['data'])
                ->contains('id', $otherAutoReply->id);
        });
    }
}
```

### **✅ Unit Test Pattern:**

```php
<?php

namespace Tests\Unit;

use App\Services\AutoReplyService;
use App\Models\Workspace;
use Tests\TestCase;

class AutoReplyServiceTest extends TestCase
{
    protected $workspace;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->service = new AutoReplyService($this->workspace->id);
    }

    /** @test */
    public function it_can_create_auto_reply()
    {
        $data = [
            'keyword' => 'test',
            'message' => 'Test message',
            'match_type' => 'exact',
        ];

        $result = $this->service->create($data);

        $this->assertTrue($result->success);
        $this->assertEquals('test', $result->data->keyword);
        $this->assertEquals($this->workspace->id, $result->data->workspace_id);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $result = $this->service->create([]);

        $this->assertFalse($result->success);
        $this->assertStringContains('required', $result->message);
    }
}
```

---

## 📊 Performance Guidelines

### **✅ Database Optimization:**

```php
// ✅ Use appropriate indexes in migrations
$table->index(['workspace_id', 'status']); // Composite index for common queries
$table->index('keyword'); // Single column index
$table->unique(['workspace_id', 'keyword']); // Unique constraint

// ✅ Use query caching for expensive operations
$analytics = Cache::remember(
    "auto-reply-analytics.{$this->workspaceId}",
    now()->addMinutes(5),
    function() {
        return [
            'total' => AutoReply::inWorkspace($this->workspaceId)->count(),
            'active' => AutoReply::inWorkspace($this->workspaceId)->active()->count(),
        ];
    }
);

// ✅ Use chunk processing for large datasets
AutoReply::inWorkspace($this->workspaceId)
    ->chunk(100, function ($autoReplies) {
        foreach ($autoReplies as $autoReply) {
            // Process batch
        }
    });
```

### **✅ API Response Optimization:**

```php
// ✅ Use resource pagination
public function index(Request $request)
{
    return AutoReplyResource::collection(
        AutoReply::inWorkspace($this->workspaceId)
            ->paginate($request->get('per_page', 15))
    );
}

// ✅ Select only needed columns
$autoReplies = AutoReply::select(['id', 'uuid', 'keyword', 'status', 'trigger_count'])
    ->inWorkspace($this->workspaceId)
    ->get();
```

---

## 🔄 Complete Feature Development Checklist

### **Phase 1: Planning**
- [ ] Requirements clearly defined
- [ ] Database schema designed
- [ ] API contracts defined
- [ ] Cross-service impacts identified
- [ ] Security considerations documented

### **Phase 2: Backend Implementation**
- [ ] Migration created and tested
- [ ] Model with relationships and scopes
- [ ] Service layer with business logic
- [ ] Form request validation
- [ ] Controller with thin logic
- [ ] Routes defined and tested
- [ ] Jobs created for async operations
- [ ] Error handling implemented
- [ ] Logging added

### **Phase 3: Frontend Implementation**
- [ ] Vue components created
- [ ] Inertia pages implemented
- [ ] Real-time features added
- [ ] Responsive design implemented
- [ ] Loading states handled
- [ ] Error states handled

### **Phase 4: Integration & Testing**
- [ ] Unit tests written
- [ ] Feature tests written
- [ ] Cross-service integration tested
- [ ] Manual testing completed
- [ ] Performance testing done
- [ ] Security testing done

### **Phase 5: Documentation & Deployment**
- [ ] API documentation updated
- [ ] Code comments added
- [ ] User documentation created
- [ ] Deployment plan created
- [ ] Monitoring configured
- [ ] Rollback plan prepared

---

## 🚨 Common Pitfalls to Avoid

### **❌ Architecture Violations:**
- Never put business logic in controllers
- Never bypass service layer
- Never create global queries (always scope by workspace)
- Never mix concerns (UI logic in services, etc.)

### **❌ Security Mistakes:**
- Never trust user input
- Never expose sensitive data
- Never skip authorization checks
- Never use eval() or similar dangerous functions

### **❌ Performance Issues:**
- Never create N+1 query problems
- Never load unnecessary data
- Never skip caching for expensive operations
- Never ignore database indexing

### **❌ Code Quality Issues:**
- Never write unclear or unreadable code
- Never duplicate code (DRY principle)
- Never ignore error handling
- Never skip testing

---

## 📚 Reference & Quick Links

### **Documentation:**
- [Architecture Overview](./01-arsitektur-overview.md)
- [Component Connections](./02-component-connections.md)
- [Folder Structure](./03-folder-structure.md)
- [Dual-Server Architecture](./05-dual-server-architecture.md)

### **Laravel Resources:**
- [Laravel Documentation](https://laravel.com/docs)
- [Laravel Best Practices](https://laravel.com/docs/master/best-practices)
- [Laravel Package Development](https://laravel.com/docs/master/packages)

### **Tools & Commands:**
```bash
# Development commands
php artisan serve                    # Start development server
npm run dev                         # Watch frontend assets
php artisan queue:work               # Start queue worker

# Code generation
php artisan make:controller User/AutoReplyController
php artisan make:model AutoReply -mfs # Model with migration, factory, seeder
php artisan make:request StoreAutoReplyRequest
php artisan make:job SendAutoReplyJob

# Testing
php artisan test                    # Run all tests
php artisan test --filter=AutoReplyTest
php artisan test --coverage         # Run with coverage

# Database
php artisan migrate                  # Run migrations
php artisan migrate:fresh --seed    # Fresh database with seeders
php artisan db:seed                  # Run seeders
```

---

**Remember:** Consistency is key to maintainable code. Always follow these patterns and guidelines when implementing new features in the Blazz WhatsApp Business Platform.

**Last Updated:** November 10, 2025
**Architecture Version:** v2.0 (Dual-Server)
**Next Review:** December 10, 2025