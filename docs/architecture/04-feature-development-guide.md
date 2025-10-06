# 🚀 Feature Development Guide - Step-by-Step

## Overview

Document ini provides **complete step-by-step guide** untuk adding new features ke Blazz project, following best practices dan arsitektur yang established.

---

## 🎯 Feature Development Workflow

```
┌─────────────────────────────────────────────────────────────┐
│           FEATURE DEVELOPMENT LIFECYCLE                      │
└─────────────────────────────────────────────────────────────┘

1. Planning & Design
   ├─→ Understand business requirements
   ├─→ Design database schema
   ├─→ Plan API endpoints
   └─→ Sketch UI/UX wireframes

2. Database Layer
   ├─→ Create migration files
   ├─→ Define model relationships
   └─→ Create seeders/factories

3. Service Layer
   ├─→ Create service class
   ├─→ Implement business logic
   └─→ Add error handling

4. Controller Layer
   ├─→ Create controller methods
   ├─→ Add route definitions
   └─→ Implement request validation

5. View Layer (Frontend)
   ├─→ Create Vue components
   ├─→ Build Inertia pages
   └─→ Add real-time features

6. Background Processing (Optional)
   ├─→ Create job classes
   ├─→ Implement queue logic
   └─→ Add retry mechanism

7. Testing & Deployment
   ├─→ Write unit tests
   ├─→ Test integration
   └─→ Deploy to production
```

---

## 📝 Example Feature: "Auto-Reply System"

Mari kita trace **complete implementation** untuk feature baru: **Auto-Reply System** yang automatically responds ke incoming WhatsApp messages berdasarkan keywords.

---

## Step 1: Planning & Design

### **Business Requirements:**
- ✅ Users can create auto-reply rules dengan keywords
- ✅ Each rule has trigger keyword dan response message
- ✅ Rules can be active/inactive
- ✅ Support for multiple rules per workspace
- ✅ Priority-based rule matching
- ✅ Analytics tracking (how many times triggered)

### **Database Design:**

```sql
auto_replies table:
- id (bigint, primary key)
- uuid (string, unique)
- workspace_id (bigint, foreign key)
- keyword (string) → Trigger word
- message (text) → Response message
- match_type (enum: 'exact', 'contains', 'starts_with')
- priority (integer) → Matching priority
- status (enum: 'active', 'inactive')
- trigger_count (integer) → Analytics counter
- created_at (timestamp)
- updated_at (timestamp)
```

### **API Endpoints:**
```
GET    /auto-replies           → List all auto-replies
POST   /auto-replies           → Create new auto-reply
GET    /auto-replies/{uuid}    → Show single auto-reply
PUT    /auto-replies/{uuid}    → Update auto-reply
DELETE /auto-replies/{uuid}    → Delete auto-reply
PATCH  /auto-replies/{uuid}/toggle → Toggle active status
```

### **UI Components:**
- Auto-reply list table dengan search/filter
- Create/Edit modal form
- Toggle active/inactive button
- Analytics dashboard untuk trigger statistics

---

## Step 2: Database Layer Implementation

### **2.1 Create Migration**

```bash
php artisan make:migration create_auto_replies_table
```

```php
// database/migrations/2024_xx_xx_create_auto_replies_table.php
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
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->string('keyword');
            $table->text('message');
            $table->enum('match_type', ['exact', 'contains', 'starts_with'])->default('contains');
            $table->integer('priority')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('trigger_count')->default(0);
            $table->timestamps();
            
            // Indexes untuk performance
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

Run migration:
```bash
php artisan migrate
```

### **2.2 Create Model**

```bash
php artisan make:model AutoReply
```

```php
// app/Models/AutoReply.php
<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoReply extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'trigger_count' => 'integer',
    ];

    // Relationships
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    // Scopes
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

    // Business Methods
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
}
```

### **2.3 Create Factory (for testing)**

```bash
php artisan make:factory AutoReplyFactory
```

```php
// database/factories/AutoReplyFactory.php
<?php

namespace Database\Factories;

use App\Models\AutoReply;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutoReplyFactory extends Factory
{
    protected $model = AutoReply::class;

    public function definition()
    {
        return [
            'workspace_id' => Workspace::factory(),
            'keyword' => $this->faker->word(),
            'message' => $this->faker->sentence(),
            'match_type' => $this->faker->randomElement(['exact', 'contains', 'starts_with']),
            'priority' => $this->faker->numberBetween(0, 10),
            'status' => 'active',
            'trigger_count' => 0,
        ];
    }
}
```

---

## Step 3: Service Layer Implementation

### **3.1 Create Service Class**

```php
// app/Services/AutoReplyService.php
<?php

namespace App\Services;

use App\Models\AutoReply;
use App\Models\Chat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoReplyService
{
    protected $workspaceId;

    public function __construct($workspaceId)
    {
        $this->workspaceId = $workspaceId;
    }

    /**
     * List all auto-replies dengan pagination
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
            $autoReply = AutoReply::create([
                'workspace_id' => $this->workspaceId,
                'keyword' => $data['keyword'],
                'message' => $data['message'],
                'match_type' => $data['match_type'] ?? 'contains',
                'priority' => $data['priority'] ?? 0,
                'status' => $data['status'] ?? 'active',
            ]);

            Log::info('Auto-reply created', [
                'workspace_id' => $this->workspaceId,
                'auto_reply_id' => $autoReply->id,
            ]);

            return (object) [
                'success' => true,
                'data' => $autoReply,
                'message' => 'Auto-reply created successfully!',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create auto-reply', [
                'workspace_id' => $this->workspaceId,
                'error' => $e->getMessage(),
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

            $autoReply->update([
                'keyword' => $data['keyword'] ?? $autoReply->keyword,
                'message' => $data['message'] ?? $autoReply->message,
                'match_type' => $data['match_type'] ?? $autoReply->match_type,
                'priority' => $data['priority'] ?? $autoReply->priority,
                'status' => $data['status'] ?? $autoReply->status,
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
     * Delete auto-reply
     */
    public function delete($uuid)
    {
        try {
            $autoReply = AutoReply::where('uuid', $uuid)
                ->inWorkspace($this->workspaceId)
                ->firstOrFail();

            $autoReply->delete();

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
     * Process incoming message untuk auto-reply matching
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
                // Send auto-reply
                $this->sendAutoReply($chat, $autoReply);
                
                // Increment trigger count
                $autoReply->incrementTriggerCount();
                
                return $autoReply;
            }
        }

        return null;
    }

    /**
     * Send auto-reply message
     */
    protected function sendAutoReply($incomingChat, $autoReply)
    {
        try {
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
     * Get auto-reply analytics
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

---

## Step 4: Controller Layer Implementation

### **4.1 Create Form Request Validation**

```bash
php artisan make:request StoreAutoReplyRequest
php artisan make:request UpdateAutoReplyRequest
```

```php
// app/Http/Requests/StoreAutoReplyRequest.php
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
        ];
    }
}
```

### **4.2 Create Controller**

```bash
php artisan make:controller User/AutoReplyController
```

```php
// app/Http/Controllers/User/AutoReplyController.php
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
            'filters' => $request->only(['search']),
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

### **4.3 Define Routes**

```php
// routes/web.php

Route::middleware(['auth:user', 'workspace.context'])->prefix('user')->name('user.')->group(function () {
    // Auto-reply routes
    Route::prefix('auto-replies')->name('auto-replies.')->group(function () {
        Route::get('/', [User\AutoReplyController::class, 'index'])->name('index');
        Route::post('/', [User\AutoReplyController::class, 'store'])->name('store');
        Route::put('/{uuid}', [User\AutoReplyController::class, 'update'])->name('update');
        Route::delete('/{uuid}', [User\AutoReplyController::class, 'destroy'])->name('destroy');
        Route::patch('/{uuid}/toggle', [User\AutoReplyController::class, 'toggle'])->name('toggle');
    });
});
```

---

## Step 5: Frontend Implementation

### **5.1 Create Vue Page Component**

```vue
<!-- resources/js/Pages/User/AutoReplies/Index.vue -->
<template>
  <AuthenticatedLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
          <div>
            <h2 class="text-2xl font-bold text-gray-900">Auto-Replies</h2>
            <p class="text-gray-600">Automatically respond to incoming messages</p>
          </div>
          <button 
            @click="showCreateModal = true"
            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
          >
            + Create Auto-Reply
          </button>
        </div>

        <!-- Analytics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div class="bg-white rounded-lg shadow p-6">
            <div class="text-gray-500 text-sm">Total Rules</div>
            <div class="text-3xl font-bold">{{ analytics.total_rules }}</div>
          </div>
          <div class="bg-white rounded-lg shadow p-6">
            <div class="text-gray-500 text-sm">Active Rules</div>
            <div class="text-3xl font-bold text-green-600">{{ analytics.active_rules }}</div>
          </div>
          <div class="bg-white rounded-lg shadow p-6">
            <div class="text-gray-500 text-sm">Total Triggers</div>
            <div class="text-3xl font-bold text-blue-600">{{ analytics.total_triggers }}</div>
          </div>
        </div>

        <!-- Search Bar -->
        <div class="mb-6">
          <input 
            v-model="search"
            @input="debounceSearch"
            type="text"
            placeholder="Search auto-replies..."
            class="w-full px-4 py-2 border rounded-lg"
          />
        </div>

        <!-- Auto-Replies Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Keyword
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Message
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Match Type
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Status
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Triggers
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="autoReply in autoReplies.data" :key="autoReply.uuid">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  {{ autoReply.keyword }}
                </td>
                <td class="px-6 py-4 text-sm text-gray-500">
                  {{ truncate(autoReply.message, 50) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <span class="px-2 py-1 bg-gray-100 rounded text-xs">
                    {{ autoReply.match_type }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span 
                    :class="autoReply.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                    class="px-2 py-1 rounded text-xs font-medium"
                  >
                    {{ autoReply.status }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ autoReply.trigger_count }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <button 
                    @click="toggleStatus(autoReply.uuid)"
                    class="text-indigo-600 hover:text-indigo-900 mr-3"
                  >
                    Toggle
                  </button>
                  <button 
                    @click="editAutoReply(autoReply)"
                    class="text-blue-600 hover:text-blue-900 mr-3"
                  >
                    Edit
                  </button>
                  <button 
                    @click="deleteAutoReply(autoReply.uuid)"
                    class="text-red-600 hover:text-red-900"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Pagination -->
          <div class="px-6 py-4 bg-gray-50">
            <Pagination :links="autoReplies.links" />
          </div>
        </div>

        <!-- Create/Edit Modal -->
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
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import AutoReplyModal from './Partials/AutoReplyModal.vue'
import { debounce } from 'lodash'

const props = defineProps({
  autoReplies: Object,
  analytics: Object,
  filters: Object,
})

const search = ref(props.filters.search || '')
const showCreateModal = ref(false)
const editingAutoReply = ref(null)

const debounceSearch = debounce(() => {
  router.get(route('user.auto-replies.index'), { search: search.value }, {
    preserveState: true,
    preserveScroll: true,
  })
}, 300)

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
  })
}

const deleteAutoReply = (uuid) => {
  if (confirm('Are you sure you want to delete this auto-reply?')) {
    router.delete(route('user.auto-replies.destroy', uuid))
  }
}

const truncate = (text, length) => {
  return text.length > length ? text.substring(0, length) + '...' : text
}
</script>
```

### **5.2 Create Modal Component**

```vue
<!-- resources/js/Pages/User/AutoReplies/Partials/AutoReplyModal.vue -->
<template>
  <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-6">
      <h3 class="text-lg font-medium mb-4">
        {{ autoReply ? 'Edit Auto-Reply' : 'Create Auto-Reply' }}
      </h3>

      <form @submit.prevent="submit">
        <!-- Keyword -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Trigger Keyword *
          </label>
          <input 
            v-model="form.keyword"
            type="text"
            class="w-full px-3 py-2 border rounded-lg"
            placeholder="e.g., hello, pricing, support"
            required
          />
          <p v-if="form.errors.keyword" class="text-red-500 text-sm mt-1">
            {{ form.errors.keyword }}
          </p>
        </div>

        <!-- Match Type -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Match Type *
          </label>
          <select v-model="form.match_type" class="w-full px-3 py-2 border rounded-lg">
            <option value="exact">Exact Match</option>
            <option value="contains">Contains</option>
            <option value="starts_with">Starts With</option>
          </select>
        </div>

        <!-- Message -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Reply Message *
          </label>
          <textarea 
            v-model="form.message"
            rows="4"
            class="w-full px-3 py-2 border rounded-lg"
            placeholder="Enter your auto-reply message..."
            required
          />
          <p v-if="form.errors.message" class="text-red-500 text-sm mt-1">
            {{ form.errors.message }}
          </p>
        </div>

        <!-- Priority -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Priority (0-100)
          </label>
          <input 
            v-model.number="form.priority"
            type="number"
            min="0"
            max="100"
            class="w-full px-3 py-2 border rounded-lg"
          />
          <p class="text-gray-500 text-xs mt-1">
            Higher priority rules are matched first
          </p>
        </div>

        <!-- Status -->
        <div class="mb-6">
          <label class="flex items-center">
            <input 
              v-model="form.status"
              type="checkbox"
              true-value="active"
              false-value="inactive"
              class="mr-2"
            />
            <span class="text-sm text-gray-700">Active</span>
          </label>
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-3">
          <button 
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 border rounded-lg hover:bg-gray-50"
          >
            Cancel
          </button>
          <button 
            type="submit"
            :disabled="form.processing"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            {{ form.processing ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  autoReply: Object,
})

const emit = defineEmits(['close', 'saved'])

const form = useForm({
  keyword: props.autoReply?.keyword || '',
  message: props.autoReply?.message || '',
  match_type: props.autoReply?.match_type || 'contains',
  priority: props.autoReply?.priority || 0,
  status: props.autoReply?.status || 'active',
})

const submit = () => {
  if (props.autoReply) {
    form.put(route('user.auto-replies.update', props.autoReply.uuid), {
      onSuccess: () => emit('saved'),
    })
  } else {
    form.post(route('user.auto-replies.store'), {
      onSuccess: () => emit('saved'),
    })
  }
}
</script>
```

---

## Step 6: Integration dengan Existing System

### **6.1 Integrate dengan WebhookController**

Update webhook controller untuk trigger auto-reply check:

```php
// app/Http/Controllers/WebhookController.php

protected function processIncomingMessage($message, $identifier)
{
    // ... existing message processing code ...

    // Create chat record
    $chat = Chat::create([
        'workspace_id' => $workspace->id,
        'contact_id' => $contact->id,
        'message' => $message['text']['body'] ?? '',
        'direction' => 'incoming',
        'status' => 'received',
        'whatsapp_message_id' => $message['id'],
        'created_at' => now(),
    ]);

    // Broadcast new chat event
    broadcast(new NewChatEvent($chat))->toOthers();

    // 🔥 NEW: Check for auto-reply match
    $autoReplyService = new AutoReplyService($workspace->id);
    $autoReplyService->processIncomingMessage($chat);

    return $chat;
}
```

---

## Step 7: Testing

### **7.1 Feature Test**

```bash
php artisan make:test AutoReplyTest
```

```php
// tests/Feature/AutoReplyTest.php
<?php

namespace Tests\Feature;

use App\Models\AutoReply;
use App\Models\User;
use App\Models\Workspace;
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
    public function user_can_toggle_auto_reply_status()
    {
        $this->actingAs($this->user);

        $autoReply = AutoReply::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'active',
        ]);

        $response = $this->patch(route('user.auto-replies.toggle', $autoReply->uuid));

        $response->assertRedirect();
        $this->assertEquals('inactive', $autoReply->fresh()->status);
    }
}
```

Run tests:
```bash
php artisan test --filter=AutoReplyTest
```

---

## 🎯 Feature Development Checklist

### **Planning Phase:**
- [ ] Define business requirements clearly
- [ ] Design database schema
- [ ] Plan API endpoints
- [ ] Sketch UI/UX wireframes
- [ ] Consider scalability dan performance

### **Backend Implementation:**
- [ ] Create migration files
- [ ] Define model dengan relationships
- [ ] Implement service layer dengan business logic
- [ ] Create form request validation
- [ ] Build controller methods
- [ ] Define routes
- [ ] Add error handling dan logging

### **Frontend Implementation:**
- [ ] Create Vue components
- [ ] Build Inertia pages
- [ ] Implement form handling
- [ ] Add real-time updates (if needed)
- [ ] Style dengan Tailwind CSS
- [ ] Add loading states dan feedback

### **Testing & Quality:**
- [ ] Write unit tests
- [ ] Write feature tests
- [ ] Test edge cases
- [ ] Test error scenarios
- [ ] Manual testing di browser

### **Documentation:**
- [ ] Update API documentation
- [ ] Add code comments
- [ ] Document configuration options
- [ ] Write user guide (if needed)

### **Deployment:**
- [ ] Run migrations di production
- [ ] Clear cache
- [ ] Test in production environment
- [ ] Monitor for errors

---

## 🚀 Common Feature Patterns

### **CRUD Operations:**
1. Create migration & model
2. Create service dengan CRUD methods
3. Create form request validation
4. Create controller dengan standard methods
5. Define RESTful routes
6. Build Vue components

### **Background Processing:**
1. Create job class
2. Implement queue logic
3. Add retry mechanism
4. Dispatch from service layer
5. Monitor job execution

### **Real-time Features:**
1. Create event class
2. Implement `ShouldBroadcast`
3. Broadcast from service layer
4. Listen di Vue components dengan Echo
5. Update UI reactively

### **API Endpoints:**
1. Define routes di `routes/api.php`
2. Add authentication middleware
3. Create API controller atau reuse existing
4. Return JSON responses
5. Document API endpoints

---

**Congratulations!** Anda sekarang punya complete guide untuk developing new features di Blazz project following best practices dan established architecture patterns! 🎉
