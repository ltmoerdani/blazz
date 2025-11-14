# WhatsApp Sessions → Accounts Migration: Quick Reference Guide

## 🚀 Quick Start Implementation Guide

This guide provides rapid implementation steps for developers working on the WhatsApp Sessions to Accounts migration.

---

## ⚡ Immediate Action Items

### Phase 1: Database Migration (Priority: CRITICAL)

#### 1. Create Migration File
```bash
php artisan make:migration rename_whatsapp_sessions_to_accounts
```

**File**: `database/migrations/YYYY_MM_DD_HHMMSS_rename_whatsapp_sessions_to_accounts.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Create new accounts table
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->onDelete('cascade');
            $table->string('account_name')->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->enum('provider_type', ['meta', 'webjs'])->default('webjs');
            $table->enum('connection_status', ['connecting', 'connected', 'disconnected', 'error'])->default('disconnected');
            $table->text('qr_code')->nullable();
            $table->longText('session_data')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'connection_status']);
            $table->index(['provider_type', 'is_active']);
            $table->index(['workspace_id', 'is_primary']);
        });

        // Step 2: Migrate data
        DB::statement("
            INSERT INTO whatsapp_accounts (
                id, uuid, workspace_id, account_name, phone_number, provider_type,
                connection_status, qr_code, session_data, is_primary, is_active,
                last_activity_at, last_connected_at, metadata, created_by,
                created_at, updated_at, deleted_at
            )
            SELECT
                id, uuid, workspace_id, session_name as account_name, phone_number, provider_type,
                CASE
                    WHEN status = 'qr_scanning' THEN 'connecting'
                    WHEN status = 'authenticated' THEN 'connected'
                    WHEN status = 'failed' THEN 'error'
                    ELSE status
                END as connection_status,
                qr_code, session_data, is_primary, is_active,
                last_activity_at, last_connected_at, metadata, created_by,
                created_at, updated_at, deleted_at
            FROM whatsapp_sessions
        ");

        // Step 3: Update foreign keys
        Schema::table('chats', function (Blueprint $table) {
            $table->renameColumn('whatsapp_session_id', 'whatsapp_account_id');
        });

        Schema::table('campaign_logs', function (Blueprint $table) {
            $table->renameColumn('whatsapp_session_id', 'whatsapp_account_id');
        });

        // Step 4: Rename junction table
        Schema::rename('contact_sessions', 'contact_accounts');
        Schema::table('contact_accounts', function (Blueprint $table) {
            $table->renameColumn('whatsapp_session_id', 'whatsapp_account_id');
        });

        // Step 5: Update contacts table
        Schema::table('contacts', function (Blueprint $table) {
            $table->renameColumn('source_session_id', 'source_account_id');
        });

        // Step 6: Drop old table (after verification)
        Schema::dropIfExists('whatsapp_sessions');
    }

    public function down(): void
    {
        // Rollback implementation
        Schema::dropIfExists('whatsapp_accounts');
        // ... implement full rollback
    }
};
```

#### 2. Update Model
**File**: `app/Models/WhatsAppAccount.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WhatsAppAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'uuid', 'workspace_id', 'account_name', 'phone_number',
        'provider_type', 'connection_status', 'qr_code', 'session_data',
        'is_primary', 'is_active', 'last_activity_at',
        'last_connected_at', 'metadata', 'created_by'
    ];

    protected $casts = [
        'session_data' => 'encrypted:array',
        'metadata' => 'array',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'last_activity_at' => 'datetime',
        'last_connected_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid()->toString();
            }
        });
    }

    // Relationships
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function chats(): HasMany { return $this->hasMany(Chat::class, 'whatsapp_account_id'); }
    public function campaignLogs(): HasMany { return $this->hasMany(CampaignLog::class, 'whatsapp_account_id'); }
    public function contactAccounts(): HasMany { return $this->hasMany(ContactAccount::class, 'whatsapp_account_id'); }
    public function contacts(): BelongsToMany {
        return $this->belongsToMany(Contact::class, 'contact_accounts')
            ->withPivot(['first_interaction_at', 'last_interaction_at', 'total_messages'])
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeConnected($query) { return $query->where('connection_status', 'connected'); }
    public function scopePrimary($query) { return $query->where('is_primary', true); }
    public function scopeByProvider($query, $provider) { return $query->where('provider_type', $provider); }
    public function scopeForWorkspace($query, $workspaceId) { return $query->where('workspace_id', $workspaceId); }

    // Accessors
    public function getFormattedPhoneNumberAttribute(): ?string
    {
        if (!$this->phone_number) return null;
        $phone = preg_replace('/[^\d+]/', '', $this->phone_number);
        return strlen($phone) >= 10 ? '+' . ltrim($phone, '+') : $phone;
    }

    public function getHealthScoreAttribute(): int
    {
        $score = 100;
        if ($this->connection_status !== 'connected') $score -= 50;
        if ($this->last_activity_at && $this->last_activity_at->lt(now()->subHour())) $score -= 20;

        if ($this->metadata) {
            if (isset($this->metadata['recent_failures']) && $this->metadata['recent_failures'] > 0) {
                $score -= min($this->metadata['recent_failures'] * 10, 30);
            }
        }

        return max(0, $score);
    }

    public function isHealthy(): bool { return $this->health_score >= 70 && $this->connection_status === 'connected'; }
}
```

### Phase 2: Service Layer (Priority: HIGH)

**File**: `app/Services/WhatsApp/WhatsAppAccountService.php`

```php
<?php

namespace App\Services\WhatsApp;

use App\Events\WhatsAppQRGeneratedEvent;
use App\Events\WhatsAppAccountStatusChangedEvent;
use App\Models\WhatsAppAccount;
use App\Services\ProviderSelector;
use App\Services\Adapters\WebJSAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WhatsAppAccountService
{
    private $providerSelector;
    private $workspaceId;

    public function __construct(ProviderSelector $providerSelector, $workspaceId = null)
    {
        $this->providerSelector = $providerSelector;
        $this->workspaceId = $workspaceId ?: session('current_workspace');
    }

    public function getWorkspaceAccounts()
    {
        return WhatsAppAccount::forWorkspace($this->workspaceId)
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($account) => $this->formatAccountData($account));
    }

    public function createAccount(Request $request): object
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'account_name' => 'nullable|string|max:255',
            'provider_type' => 'required|string|in:webjs,meta',
            'is_primary' => 'boolean'
        ]);

        if ($validator->fails()) {
            return (object) [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ];
        }

        $validated = $validator->validated();

        $existingAccount = WhatsAppAccount::where('workspace_id', $this->workspaceId)
            ->where('phone_number', $validated['phone_number'])
            ->first();

        if ($existingAccount) {
            return (object) [
                'success' => false,
                'message' => 'Account with this phone number already exists'
            ];
        }

        try {
            $account = WhatsAppAccount::create([
                'uuid' => Str::uuid()->toString(),
                'workspace_id' => $this->workspaceId,
                'account_name' => $validated['account_name'] ?? $validated['phone_number'],
                'phone_number' => $validated['phone_number'],
                'provider_type' => $validated['provider_type'],
                'is_primary' => $validated['is_primary'] ?? false,
                'connection_status' => 'disconnected',
                'is_active' => false,
                'created_by' => Auth::id(),
            ]);

            if ($account->is_primary) {
                WhatsAppAccount::where('workspace_id', $this->workspaceId)
                    ->where('id', '!=', $account->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            return (object) [
                'success' => true,
                'message' => 'Account created successfully',
                'data' => $this->formatAccountData($account)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create WhatsApp account', [
                'error' => $e->getMessage(),
                'workspace_id' => $this->workspaceId,
                'phone_number' => $validated['phone_number']
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to create account: ' . $e->getMessage()
            ];
        }
    }

    // ... other methods (getAccount, setPrimary, disconnect, etc.)

    private function formatAccountData($account): array
    {
        return [
            'id' => $account->id,
            'uuid' => $account->uuid,
            'phone_number' => $account->phone_number,
            'account_name' => $account->account_name,
            'provider_type' => $account->provider_type,
            'connection_status' => $account->connection_status,
            'is_primary' => $account->is_primary,
            'is_active' => $account->is_active,
            'formatted_phone_number' => $account->formatted_phone_number,
            'health_score' => $account->health_score,
            'created_at' => $account->created_at,
        ];
    }
}
```

### Phase 3: Routes (Priority: HIGH)

**File**: `routes/web.php` (Update existing WhatsApp session routes)

```php
// REPLACE existing whatsapp session routes with:

// WhatsApp Account Management Routes
Route::prefix('settings/whatsapp-accounts')->name('whatsapp.accounts.')->group(function () {
    Route::get('/', [App\Http\Controllers\User\WhatsAppAccountManagementController::class, 'index'])->name('index');
    Route::post('/', [App\Http\Controllers\User\WhatsAppAccountManagementController::class, 'store'])->name('store');
    Route::get('/{uuid}', [App\Http\Controllers\User\WhatsAppAccountManagementController::class, 'show'])->name('show');
    Route::post('/{uuid}/set-primary', [App\Http\Controllers\User\WhatsAppAccountStatusController::class, 'setPrimary'])->name('set-primary');
    Route::post('/{uuid}/disconnect', [App\Http\Controllers\User\WhatsAppAccountStatusController::class, 'disconnect'])->name('disconnect');
    Route::delete('/{uuid}', [App\Http\Controllers\User\WhatsAppAccountManagementController::class, 'destroy'])->name('destroy');
    Route::post('/{uuid}/reconnect', [App\Http\Controllers\User\WhatsAppAccountStatusController::class, 'reconnect'])->name('reconnect');
    Route::post('/{uuid}/regenerate-qr', [App\Http\Controllers\User\WhatsAppAccountStatusController::class, 'regenerateQR'])->name('regenerate-qr');
    Route::get('/{uuid}/statistics', [App\Http\Controllers\User\WhatsAppAccountStatusController::class, 'statistics'])->name('statistics');
});
```

**File**: `routes/api.php` (Update existing session API routes)

```php
// REPLACE existing whatsapp session routes with:

Route::prefix('whatsapp/accounts')->middleware(['whatsapp.hmac'])->group(function () {
    // Account management for Node.js service
    Route::get('/{accountId}/status', [App\Http\Controllers\Api\v1\WhatsApp\AccountController::class, 'getAccountStatus']);
    Route::get('/active', [App\Http\Controllers\Api\v1\WhatsApp\AccountController::class, 'getActiveAccounts']);
    Route::post('/{accountId}/mark-disconnected', [App\Http\Controllers\Api\v1\WhatsApp\AccountController::class, 'markDisconnected']);

    // Broadcasting events
    Route::post('/broadcast', function (Request $request) {
        $event = $request->input('event');
        $data = $request->input('data');

        switch ($event) {
            case 'qr-code-generated':
                broadcast(new \App\Events\WhatsAppQRGeneratedEvent(
                    $data['qr_code'],
                    $data['expires_in'] ?? 300,
                    $data['workspace_id'],
                    $data['account_id']
                ));
                break;

            case 'account-status-changed':
                broadcast(new \App\Events\WhatsAppAccountStatusChangedEvent(
                    $data['account_id'],
                    $data['status'],
                    $data['workspace_id'],
                    $data['phone_number'] ?? null,
                    $data['metadata'] ?? []
                ));
                break;

            default:
                return response()->json(['error' => 'Unknown event'], 400);
        }

        return response()->json(['status' => 'broadcasted']);
    });
});
```

### Phase 4: Frontend Component (Priority: HIGH)

**File**: `resources/js/Pages/User/Settings/WhatsAppAccounts.vue`

```vue
<template>
    <SettingLayout :modules="props.modules">
        <div class="md:h-[90vh]">
            <div class="flex justify-center items-center mb-8">
                <div class="md:w-[60em]">
                    <!-- Empty State -->
                    <div v-if="!accountsList || accountsList.length === 0" class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4">
                        <div class="flex items-center px-4 pt-2 pb-4">
                            <div class="w-[70%]">
                                <h2 class="text-[17px]">{{ $t('Setup WhatsApp Numbers') }}</h2>
                                <span class="flex items-center mt-1">
                                    {{ $t('Setup your WhatsApp numbers to be able to receive and send messages.') }}
                                </span>
                            </div>
                            <div class="ml-auto" v-if="canAddAccountComputed">
                                <button @click="addAccount" class="bg-primary text-white p-2 rounded-lg text-sm mt-5 flex px-3 w-fit">
                                    {{ $t('Add WhatsApp Number') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div v-if="accountsList && accountsList.length > 0" class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4">
                        <div class="grid grid-cols-4 items-center px-4 gap-x-4 py-2 border-b relative">
                            <div class="border-r">
                                <div>{{ $t('Total Numbers') }}</div>
                                <div>{{ accountsList.length }}</div>
                            </div>
                            <div class="border-r">
                                <div>{{ $t('Connected') }}</div>
                                <div>{{ connectedAccountsCount }}</div>
                            </div>
                            <div class="border-r">
                                <div>{{ $t('Primary Number') }}</div>
                                <div>{{ accountsList.find(a => a.is_primary)?.formatted_phone_number || 'None' }}</div>
                            </div>
                            <div>
                                <div>{{ $t('Status') }}</div>
                                <div class="bg-slate-50 py-1 px-2 rounded-md w-[fit-content] text-xs">
                                    {{ accountsList.some(a => a.connection_status === 'connected') ? 'Active' : 'Inactive' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Accounts List -->
                    <div class="bg-white shadow overflow-hidden sm:rounded-md">
                        <ul class="divide-y divide-gray-200">
                            <li v-for="account in accountsList" :key="account.uuid" class="px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="ml-4">
                                            <div class="flex items-center">
                                                <p class="text-sm font-medium text-gray-900">
                                                    {{ account.formatted_phone_number || 'Unknown Number' }}
                                                </p>
                                                <span v-if="account.is_primary" class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    {{ $t('Primary') }}
                                                </span>
                                            </div>
                                            <div class="flex items-center mt-1">
                                                <span :class="getStatusClass(account.connection_status)">
                                                    {{ $t(account.connection_status) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <!-- Action buttons here -->
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </SettingLayout>
</template>

<script setup>
import SettingLayout from "./Layout.vue";
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
    accounts: Array,
    canAddAccount: Boolean,
    modules: Array,
    workspaceId: Number,
})

const accountsList = ref([...props.accounts])
const showAddModal = ref(false)

const connectedAccountsCount = computed(() => {
    return accountsList.value.filter(a => a.connection_status === 'connected').length
})

const canAddAccountComputed = computed(() => {
    return connectedAccountsCount.value < 10 // Adjust based on plan limits
})

const getStatusClass = (status) => {
    const classes = {
        'connected': 'bg-green-100 text-green-800',
        'connecting': 'bg-yellow-100 text-yellow-800',
        'disconnected': 'bg-red-100 text-red-800',
        'error': 'bg-red-100 text-red-800'
    }
    return `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${classes[status] || 'bg-gray-100 text-gray-800'}`
}

const addAccount = async () => {
    // Implementation here
}

// Other methods: setPrimary, disconnect, deleteAccount, etc.
</script>
```

---

## 🔧 Quick Commands

### Database Migration
```bash
# Create migration
php artisan make:migration rename_whatsapp_sessions_to_accounts

# Run migration
php artisan migrate

# Rollback if needed
php artisan migrate:rollback
```

### Model Testing
```bash
# Test model relationships
php artisan tinker
>>> $account = App\Models\WhatsAppAccount::first();
>>> $account->workspace;
>>> $account->chats;
>>> $account->formatted_phone_number;
>>> $account->health_score;
```

### Route Testing
```bash
# Test new routes
php artisan route:list | grep whatsapp

# Test specific route
curl -X GET "http://localhost:8000/settings/whatsapp-accounts" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## ⚠️ Critical Considerations

### Must-Do Before Migration
1. **Backup Database**: Full production backup
2. **Test on Staging**: Complete testing on staging environment
3. **Communicate**: Notify users about upcoming changes
4. **Monitor Setup**: Enhanced monitoring during migration

### Potential Breaking Changes
1. **API Endpoints**: All session endpoints moved to accounts
2. **Foreign Keys**: Database relationships updated
3. **Frontend Routes**: All UI routes changed
4. **WebSocket Events**: Event names updated

### Rollback Plan
1. **Database**: Migration rollback script ready
2. **Code**: Git tags for easy rollback
3. **Configuration**: Backup of original configs
4. **Communication**: User communication plan

---

## 📝 Development Checklist

### Pre-Migration Checklist
- [ ] Full database backup completed
- [ ] Staging environment ready
- [ ] All tests passing on current code
- [ ] Monitoring tools configured
- [ ] Rollback procedures documented

### Migration Day Checklist
- [ ] Put application in maintenance mode
- [ ] Run database migration
- [ ] Deploy updated code
- [ ] Clear all caches
- [ ] Run smoke tests
- [ ] Enable application monitoring
- [ ] Take application out of maintenance mode

### Post-Migration Checklist
- [ ] Verify all WhatsApp functionality works
- [ ] Check API endpoints responding correctly
- [ ] Test frontend user interface
- [ ] Monitor error logs
- [ ] Collect user feedback
- [ ] Update documentation

---

## 🆘 Troubleshooting

### Common Issues & Solutions

#### Migration Errors
```
Error: Cannot drop table 'whatsapp_sessions' because of foreign key constraints
Solution: Check all foreign key references and update them first
```

#### Missing Relationships
```
Error: Call to undefined relationship WhatsAppSession::chats
Solution: Update all model references from WhatsAppSession to WhatsAppAccount
```

#### Route Not Found
```
Error: 404 Not Found on /settings/whatsapp-sessions
Solution: Update frontend code to use /settings/whatsapp-accounts
```

#### API Response Changes
```
Error: Expected 'status' field but got 'connection_status'
Solution: Update frontend code to use new field names
```

---

**Document Version**: 1.1
**Last Updated**: 2025-11-14
**Status**: Ready for Implementation
**Contact**: Development Team