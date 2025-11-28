# WhatsApp Account Isolation Implementation Guide

## 📋 Overview

This document outlines the implementation of WhatsApp account isolation to prevent cross-contamination of chats between different WhatsApp numbers within the same workspace.

## 🎯 Problem Statement

### Issue Identified
- **Cross-contamination**: Chats from multiple WhatsApp accounts are displayed together in a single list
- **User confusion**: Users cannot distinguish which chat belongs to which WhatsApp number
- **Data integrity**: Messages from different business numbers get mixed up

### Root Cause Analysis
- Backend `sessionId` parameter is optional (defaults to `null`)
- When `sessionId = null`, no filtering occurs on `chats.whatsapp_account_id`
- Frontend doesn't enforce WhatsApp account selection
- Multiple WhatsApp accounts per workspace are supported but not properly isolated

### Evidence from Database
```sql
-- Contacts with chats from multiple WhatsApp accounts (PROOF OF PROBLEM):
Contact ID: 1 (Laksmana) -> 9 different WhatsApp accounts (IDs: 24,27,141,142,144,145,146,149,152)
Contact ID: 6 (6281383963619) -> 5 different WhatsApp accounts (IDs: 24,25,27,106,146)
Contact ID: 2 (628999338255) -> 4 different WhatsApp accounts (IDs: 24,139,149,152)
```

## 🏗️ Current Architecture

### Database Schema
```sql
-- Key tables and relationships:
contacts
├── id
├── workspace_id
├── phone
└── ...

chats
├── id
├── workspace_id
├── whatsapp_account_id  -- FOREIGN KEY for isolation
├── contact_id
├── message_id
└── ...

whatsapp_accounts
├── id
├── workspace_id
├── phone_number
├── status
└── is_primary
```

### Current Code Flow
```
Frontend (/chats)
  → ChatController::index()
  → ChatService::getChatListWithFilters()
  → Contact::contactsWithChats(workspaceId, sessionId = null)
  → query WITHOUT whatsapp_account_id filter
  → Mixed results from ALL WhatsApp accounts
```

## 🛠️ Implementation Plan

### Phase 1: Critical Fix (Immediate)

#### 1.1 Backend - Enforce WhatsApp Account Filter

**File**: `app/Services/ChatService.php`

```php
public function getChatListWithFilters($request, $uuid = null, $searchTerm = null, $sessionId = null)
{
    // NEW: Auto-select WhatsApp account if none specified
    if (!$sessionId) {
        $defaultAccount = WhatsAppAccount::where('workspace_id', $this->workspaceId)
            ->where('status', 'connected')
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'asc')
            ->first();

        $sessionId = $defaultAccount ? $defaultAccount->id : null;
    }

    // CRITICAL: If no connected WhatsApp accounts, return empty response
    if (!$sessionId) {
        return $this->returnEmptyChatList();
    }

    // Continue with existing logic using $sessionId
    $contacts = $contact->contactsWithChats($this->workspaceId, $searchTerm, $ticketingActive, $ticketState, $sortDirection, $role, $allowAgentsViewAllChats, $sessionId);
    // ... rest of method
}

/**
 * Return empty chat list when no WhatsApp accounts available
 */
private function returnEmptyChatList()
{
    $sessions = collect(); // Empty collection

    if (request()->expectsJson()) {
        return response()->json([
            'result' => [
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 50,
                    'has_more_pages' => false,
                ]
            ]
        ], 200);
    }

    return Inertia::render('User/Chat/Index', [
        'title' => 'Chats',
        'rows' => (object)[
            'data' => [],
            'meta' => ['current_page' => 1, 'has_more_pages' => false]
        ],
        'sessions' => $sessions,
        'workspaceId' => $this->workspaceId,
        // ... other props with empty values
    ]);
}
```

#### 1.2 Update Contact Model Validation

**File**: `app/Models/Contact.php`

```php
public function contactsWithChats($workspaceId, $searchTerm = null, $ticketingActive = false, $ticketState = null, $sortDirection = 'asc', $role = 'owner', $allowAgentsViewAllChats = true, $sessionId = null)
{
    // VALIDATION: Require sessionId for non-admin users
    if (!$sessionId && $role !== 'owner') {
        return $this->newQuery()->whereRaw('1 = 0'); // Return empty query
    }

    $query = $this->newQuery()
        ->where('contacts.workspace_id', $workspaceId)
        ->whereHas('chats', function ($q) use ($workspaceId, $sessionId) {
            $q->where('chats.workspace_id', $workspaceId)
              ->whereNull('chats.deleted_at');

            // Filter by session if specified
            if ($sessionId) {
                $q->where('chats.whatsapp_account_id', $sessionId);
            }
        })
        ->with(['lastChat', 'lastInboundChat'])
        ->whereNull('contacts.deleted_at');

    // ... rest of method remains the same
}
```

### Phase 2: Frontend Enhancement

#### 2.1 WhatsApp Account Selector Component

**New File**: `resources/js/Components/ChatComponents/WhatsAppAccountSelector.vue`

```vue
<template>
    <div class="whatsapp-account-selector">
        <label class="block text-sm font-medium text-gray-700 mb-2">
            WhatsApp Number
        </label>
        <div class="relative">
            <select
                v-model="selectedAccountId"
                @change="handleAccountChange"
                class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                :disabled="loading"
            >
                <option value="" disabled>Select WhatsApp Number</option>
                <option
                    v-for="account in availableAccounts"
                    :key="account.id"
                    :value="account.id"
                >
                    {{ formatPhoneNumber(account.phone_number) }}
                    <span v-if="account.unread_count > 0" class="text-red-500">
                        ({{ account.unread_count }} unread)
                    </span>
                    <span v-if="account.is_primary" class="text-indigo-600 font-semibold">
                        - Primary
                    </span>
                </option>
            </select>
            <div v-if="loading" class="absolute inset-y-0 right-0 flex items-center pr-3">
                <svg class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>

        <!-- Account Status Indicator -->
        <div v-if="selectedAccount" class="mt-2 flex items-center text-xs">
            <div :class="[
                'w-2 h-2 rounded-full mr-1',
                selectedAccount.status === 'connected' ? 'bg-green-500' : 'bg-gray-400'
            ]"></div>
            <span :class="selectedAccount.status === 'connected' ? 'text-green-700' : 'text-gray-500'">
                {{ selectedAccount.status === 'connected' ? 'Connected' : 'Disconnected' }}
            </span>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    accounts: {
        type: Array,
        required: true
    },
    selectedId: {
        type: [String, Number],
        default: null
    }
})

const emit = defineEmits(['account-changed'])
const loading = ref(false)
const selectedAccountId = ref(props.selectedId)

const availableAccounts = computed(() => {
    return props.accounts.filter(account => account.status === 'connected')
})

const selectedAccount = computed(() => {
    return props.accounts.find(account => account.id == selectedAccountId.value)
})

const formatPhoneNumber = (phone) => {
    if (!phone) return 'Unknown'
    // Format: +62 812-3456-7890
    return phone.replace(/(\+?\d{1,3})(\d{3})(\d{4})(\d{4})/, '$1 $2-$3-$4')
}

const handleAccountChange = async (accountId) => {
    if (!accountId) return

    loading.value = true
    try {
        // Navigate to new URL with session filter
        router.get('/chats', { sessionId: accountId }, {
            preserveState: false,
            preserveScroll: true
        })

        emit('account-changed', accountId)
    } catch (error) {
        console.error('Failed to switch WhatsApp account:', error)
    } finally {
        loading.value = false
    }
}

// Auto-select primary account if none selected
watch(() => props.accounts, (accounts) => {
    if (!selectedAccountId.value && accounts.length > 0) {
        const primaryAccount = accounts.find(account => account.is_primary)
        const firstConnected = accounts.find(account => account.status === 'connected')

        if (primaryAccount) {
            selectedAccountId.value = primaryAccount.id
        } else if (firstConnected) {
            selectedAccountId.value = firstConnected.id
        }
    }
}, { immediate: true })
</script>
```

#### 2.2 Update Chat Index Page

**File**: `resources/js/Pages/User/Chat/Index.vue`

```vue
<template>
    <AppLayout v-slot:default="slotProps">
        <div class="md:flex md:flex-grow md:overflow-hidden">
            <div class="md:w-[30%] md:flex flex-col h-full bg-white border-r border-l" :class="contact ? 'hidden' : ''">
                <!-- WhatsApp Account Selector -->
                <div v-if="sessions.length > 0" class="p-4 border-b">
                    <WhatsAppAccountSelector
                        :accounts="sessions"
                        :selected-id="currentSessionId"
                        @account-changed="handleAccountChanged"
                    />
                </div>

                <!-- No WhatsApp Accounts Message -->
                <div v-else class="p-4 border-b">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">
                                    No WhatsApp Accounts Connected
                                </h3>
                                <div class="mt-2 text-sm text-yellow-700">
                                    <p>You need to connect at least one WhatsApp account to start chatting.</p>
                                    <Link :href="route('whatsapp.accounts')" class="font-medium text-yellow-700 underline hover:text-yellow-600">
                                        Manage WhatsApp Accounts
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <ChatTable
                    ref="chatTableRef"
                    :rows="rows"
                    :filters="props.filters"
                    :rowCount="props.rowCount"
                    :ticketingIsEnabled="ticketingIsEnabled"
                    :status="props?.status"
                    :chatSortDirection="props.chat_sort_direction"
                    :whatsappAccountId="currentSessionId"
                    @contact-selected="selectContact"
                />
            </div>

            <!-- ... rest of template remains the same ... -->
        </div>
    </AppLayout>
</template>

<script setup>
import WhatsAppAccountSelector from '@/Components/ChatComponents/WhatsAppAccountSelector.vue'
// ... other imports

const props = defineProps({
    // ... existing props
    sessions: {
        type: Array,
        default: () => []
    }
})

const currentSessionId = ref(
    props.filters?.sessionId ||
    (props.sessions.find(s => s.is_primary)?.id) ||
    (props.sessions.find(s => s.status === 'connected')?.id) ||
    null
)

const handleAccountChanged = (accountId) => {
    currentSessionId.value = accountId
    // Additional logic if needed when account changes
}
</script>
```

#### 2.3 Update ChatTable Component

**File**: `resources/js/Components/ChatComponents/ChatTable.vue`

```vue
<script setup>
// Add new prop
const props = defineProps({
    // ... existing props
    whatsappAccountId: {
        type: [String, Number],
        default: null
    }
})

// Add whatsapp account info to contact display
const getWhatsAppAccountInfo = (contact) => {
    if (!contact.last_chat?.whatsapp_account_id) return null

    const account = props.sessions?.find(s => s.id === contact.last_chat.whatsapp_account_id)
    return account ? {
        phone: account.phone_number,
        isPrimary: account.is_primary
    } : null
}
</script>

<template>
    <!-- Add WhatsApp account indicator in contact list -->
    <div v-for="contact in localRows" :key="contact.id" class="contact-item">
        <!-- ... existing contact info ... -->

        <!-- WhatsApp Account Indicator -->
        <div v-if="getWhatsAppAccountInfo(contact)" class="text-xs text-gray-500 mt-1">
            <span class="inline-flex items-center">
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                </svg>
                {{ getWhatsAppAccountInfo(contact).phone }}
                <span v-if="getWhatsAppAccountInfo(contact).isPrimary" class="ml-1 text-indigo-600 font-semibold">
                    (Primary)
                </span>
            </span>
        </div>
    </div>
</template>
```

### Phase 3: Enhanced Features

#### 3.1 Real-time Account Switching

```javascript
// Add to ChatService for real-time switching
public function switchWhatsAppAccount($sessionId, $workspaceId)
{
    // Validate account belongs to workspace
    $account = WhatsAppAccount::where('id', $sessionId)
        ->where('workspace_id', $workspaceId)
        ->where('status', 'connected')
        ->firstOrFail();

    // Get filtered chat list
    return $this->getChatListWithFilters(request(), null, null, $sessionId);
}
```

#### 3.2 Account Statistics Dashboard

```php
// Add to WhatsAppAccount model
public function getAccountStats()
{
    return [
        'total_chats' => $this->chats()->count(),
        'unread_chats' => $this->chats()->where('is_read', false)->where('type', 'inbound')->count(),
        'recent_activity' => $this->chats()->latest()->first()?->created_at,
        'unique_contacts' => $this->chats()->distinct('contact_id')->count(),
    ];
}
```

### Phase 4: Data Cleanup (Optional)

#### 4.1 Historical Data Migration

```bash
# Create migration for data cleanup
php artisan make:migration cleanup_chat_cross_contamination --table=chats

# Migration logic
public function up()
{
    // Strategy 1: Assign chats based on phone number matching
    // Strategy 2: Use timestamp analysis with account creation
    // Strategy 3: Manual verification process for ambiguous cases

    DB::statement('
        UPDATE chats c
        JOIN whatsapp_accounts wa ON (
            c.workspace_id = wa.workspace_id
            AND c.created_at >= wa.created_at
        )
        SET c.whatsapp_account_id = wa.id
        WHERE c.whatsapp_account_id IS NULL
        AND wa.status = "connected"
    ');
}
```

#### 4.2 Validation and Testing

```php
// Add validation test
public function testChatAccountIsolation()
{
    $workspaceId = 1;
    $account1Id = 101;
    $account2Id = 102;

    // Create chats for both accounts with same contact
    $contactId = 1;

    Chat::factory()->create([
        'workspace_id' => $workspaceId,
        'contact_id' => $contactId,
        'whatsapp_account_id' => $account1Id
    ]);

    Chat::factory()->create([
        'workspace_id' => $workspaceId,
        'contact_id' => $contactId,
        'whatsapp_account_id' => $account2Id
    ]);

    // Test isolation
    $account1Chats = Contact::contactsWithChats($workspaceId, null, false, null, 'asc', 'owner', true, $account1Id);
    $account2Chats = Contact::contactsWithChats($workspaceId, null, false, null, 'asc', 'owner', true, $account2Id);

    // Each should only return the contact once, with chats from respective account
    $this->assertEquals(1, $account1Chats->count());
    $this->assertEquals(1, $account2Chats->count());
}
```

## 🧪 Testing Strategy

### Unit Tests
```php
// tests/Unit/ChatAccountIsolationTest.php
class ChatAccountIsolationTest extends TestCase
{
    public function test_chat_list_is_filtered_by_whatsapp_account()
    {
        // Create workspace with multiple WhatsApp accounts
        // Create chats for each account
        // Verify chat lists are properly isolated
    }

    public function test_auto_selection_of_primary_account()
    {
        // Test auto-selection logic when no sessionId provided
    }

    public function test_empty_response_when_no_connected_accounts()
    {
        // Test proper handling when workspace has no connected accounts
    }
}
```

### Integration Tests
```php
// tests/Feature/ChatIsolationFeatureTest.php
class ChatIsolationFeatureTest extends TestCase
{
    public function test_chat_page_with_account_filter()
    {
        // Test full flow: frontend -> backend -> database
        // Verify proper isolation in UI
    }

    public function test_realtime_account_switching()
    {
        // Test switching between accounts without page reload
    }
}
```

### Manual Testing Checklist
- [ ] Workspace with single WhatsApp account
- [ ] Workspace with multiple WhatsApp accounts
- [ ] Primary account auto-selection
- [ ] Manual account switching
- [ ] Chat isolation verification
- [ ] Unread counts per account
- [ ] Real-time switching performance
- [ ] Error handling (disconnected accounts)

## 📊 Performance Considerations

### Database Indexes
```sql
-- Ensure proper indexes for isolation queries
CREATE INDEX idx_chats_workspace_account ON chats(workspace_id, whatsapp_account_id);
CREATE INDEX idx_chats_account_contact ON chats(whatsapp_account_id, contact_id);
CREATE INDEX idx_whatsapp_accounts_workspace_status ON whatsapp_accounts(workspace_id, status, is_primary);
```

### Query Optimization
```php
// Use efficient queries for account switching
$chats = Chat::where('workspace_id', $workspaceId)
    ->where('whatsapp_account_id', $sessionId)
    ->with(['contact' => function($query) {
        $query->select('id', 'first_name', 'phone');
    }])
    ->latest()
    ->paginate(50);
```

### Caching Strategy
```php
// Cache account selection per user
Cache::remember("user_{$userId}_selected_account", 3600, function() {
    return WhatsAppAccount::where('workspace_id', $workspaceId)
        ->where('status', 'connected')
        ->orderBy('is_primary', 'desc')
        ->first();
});
```

## 🚀 Deployment Strategy

### Phase 1 Rollout
1. **Backend Changes Only** (Minimal Risk)
   - Update `ChatService.php` with account auto-selection
   - Add validation to `Contact::contactsWithChats`
   - Test in staging environment

2. **Database Migration** (Zero Downtime)
   - Add indexes for performance
   - Run data validation scripts
   - Monitor query performance

### Phase 2 Frontend Update
1. **Component Rollout** (Feature Flag)
   - Deploy new components behind feature flag
   - Test with select users
   - Monitor performance metrics

2. **Full Rollout**
   - Enable feature for all users
   - Monitor user feedback
   - Adjust based on usage patterns

### Phase 3 Optimization
1. **Performance Tuning**
   - Optimize queries based on real usage
   - Add caching where beneficial
   - Monitor database load

2. **Enhanced Features**
   - Add account statistics
   - Implement real-time switching
   - Add admin controls

## 📝 Migration Notes

### Breaking Changes
- Chat API responses now require `sessionId` parameter
- Frontend must pass WhatsApp account selection
- Chat lists are filtered by WhatsApp account by default

### Backward Compatibility
- Support for legacy queries with `sessionId = null` (deprecated)
- Graceful degradation for workspaces without connected accounts
- Migration path for historical data

### Configuration
```php
// config/chat.php (new file)
return [
    'require_account_filter' => env('REQUIRE_WHATSAPP_ACCOUNT_FILTER', true),
    'default_account_selection' => env('DEFAULT_WHATSAPP_ACCOUNT_SELECTION', 'primary'), // primary, first_connected, none
    'enable_account_switching' => env('ENABLE_WHATSAPP_ACCOUNT_SWITCHING', true),
];
```

## 🔍 Monitoring & Logging

### Key Metrics
- Chat list load times per account
- Account switching frequency
- Error rates for invalid account selections
- User adoption of account switching

### Logging
```php
// Add structured logging for debugging
Log::info('WhatsApp account filter applied', [
    'workspace_id' => $workspaceId,
    'session_id' => $sessionId,
    'account_phone' => $account->phone_number,
    'chat_count' => $chats->count(),
    'execution_time' => $executionTime
]);
```

## 📚 Related Documentation

- [Database Schema](../database-checkpoint/) - Current database structure
- [WhatsApp Multi-Session Setup](../whatsapp/multi-session-setup.md) - Multi-session configuration
- [Frontend Architecture](../frontend/component-architecture.md) - Vue.js component structure
- [Performance Optimization](../performance/database-optimization.md) - Database performance tuning

## 🔄 Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2024-11-29 | Initial implementation plan |
| 1.1.0 | TBD | Backend fixes implemented |
| 1.2.0 | TBD | Frontend enhancements |
| 2.0.0 | TBD | Advanced features & optimization |

---

**Document Status**: 🚧 In Progress
**Last Updated**: 2024-11-29
**Next Review**: 2024-12-06
**Priority**: Critical - Core functionality issue