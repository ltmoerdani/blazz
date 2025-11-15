# 🧹 Sessions → Accounts Migration Cleanup Plan

**Document Version:** 1.0  
**Created:** November 15, 2025  
**Status:** Active Cleanup Required  
**Migration Completion:** 70% (Database: 100%, Code: 40-85%)

---

## 📋 Executive Summary

Setelah migrasi database dari `whatsapp_accounts` → `whatsapp_accounts` selesai, ditemukan **legacy code** yang masih menggunakan terminologi "session" di berbagai layer aplikasi. Dokumen ini berisi **comprehensive cleanup plan** untuk menyelesaikan migrasi secara menyeluruh.

**Target:** Mencapai **100% consistency** dalam naming convention dan menghilangkan semua legacy references.

---

## 🎯 Cleanup Objectives

1. ✅ **Consistency** - Semua code menggunakan terminology "account" bukan "session"
2. ✅ **Maintainability** - Reduce confusion untuk future developers
3. ✅ **Documentation** - Update semua docs untuk reflect actual implementation
4. ✅ **Frontend-Backend Contract** - Sync API contracts dan data structures
5. ✅ **Zero Breaking Changes** - Cleanup tidak boleh break existing functionality

---

## 📊 Current State Analysis

### **Layer-by-Layer Status**

| Layer | Files Affected | Completion | Priority | Estimated Effort |
|-------|---------------|------------|----------|------------------|
| Database Schema | 0 | ✅ 100% | - | ✅ Done |
| Models | 0 | ✅ 100% | - | ✅ Done |
| Services | 3 | ⚠️ 40% | 🔴 Critical | 4 hours |
| Controllers | 3 | ⚠️ 85% | ⚠️ High | 2 hours |
| Jobs & Events | 3 | ⚠️ 50% | ⚠️ High | 2 hours |
| Frontend (Vue) | 6+ | 🔴 20% | 🔴 Critical | 6 hours |
| Routes | 1 | ⚠️ 90% | 📝 Medium | 30 mins |
| Documentation | 10+ | 🔴 10% | 📝 Medium | 4 hours |

**Total Estimated Effort:** ~19 hours

---

## 🔴 CRITICAL PRIORITY - Frontend Cleanup

### **Impact:** User-facing code, API contracts, form submissions

### **Files to Update:**

#### **1. `resources/js/Components/CampaignForm.vue`**

**Current Issues:**
```javascript
// ❌ LEGACY CODE
props: {
    whatsappAccounts: Array  // Should be whatsappAccounts
}

const hasWebJsSessions = computed(...)  // Should be hasWebJsAccounts
const hasMetaApiSessions = computed(...)  // Should be hasMetaApiAccounts

form: {
    whatsapp_account_id: null  // Should be whatsapp_account_id
}
```

**Required Changes:**
```javascript
// ✅ UPDATED CODE
props: {
    whatsappAccounts: Array  // Changed from whatsappAccounts
}

const hasWebJsAccounts = computed(() => 
    props.whatsappAccounts?.some(a => a.provider_type === 'webjs') || false
)

const hasMetaApiAccounts = computed(() => 
    props.whatsappAccounts?.some(a => a.provider_type === 'meta_api') || false
)

form: {
    whatsapp_account_id: null  // Changed from whatsapp_account_id
}
```

**UI Text Updates:**
```vue
<!-- ❌ OLD -->
<span>{{ $t('Manage WhatsApp accounts') }}</span>
<span>{{ $t('WhatsApp Web JS sessions found') }}</span>
<span>{{ $t('Specific WhatsApp account (Optional)') }}</span>
<span>{{ $t('Auto-select best session') }}</span>
<span>{{ $t(' - No active sessions available') }}</span>

<!-- ✅ NEW -->
<span>{{ $t('Manage WhatsApp Accounts') }}</span>
<span>{{ $t('WhatsApp Web JS accounts found') }}</span>
<span>{{ $t('Specific WhatsApp Account (Optional)') }}</span>
<span>{{ $t('Auto-select best account') }}</span>
<span>{{ $t(' - No active accounts available') }}</span>
```

**Route Updates:**
```vue
<!-- ❌ OLD -->
<Link href="/settings/whatsapp/sessions">

<!-- ✅ NEW -->
<Link href="/settings/whatsapp/accounts">
```

---

#### **2. `resources/js/Components/ChatComponents/ChatTable.vue`**

**Current Issues:**
```javascript
// ❌ LEGACY CODE
props: {
    sessions: Array  // Should be accounts
}

const selectedSessionId = ref(props.filters?.session_id || '')  // Should be selectedAccountId

filters: {
    session_id: props.filters?.session_id || ''  // Should be account_id
}

const filterBySession = () => {  // Should be filterByAccount
    params.value.session_id = selectedSessionId.value
}
```

**Required Changes:**
```javascript
// ✅ UPDATED CODE
props: {
    accounts: Array  // Changed from sessions
}

const selectedAccountId = ref(props.filters?.account_id || '')

filters: {
    account_id: props.filters?.account_id || ''
}

const filterByAccount = () => {
    params.value.account_id = selectedAccountId.value
}
```

**Template Updates:**
```vue
<!-- ❌ OLD -->
<div v-if="sessions && sessions.length > 0">
    <select v-model="selectedSessionId" @change="filterBySession">
        <option v-for="session in sessions" :key="session.id" :value="session.id">
            {{ formatPhone(session.phone_number) }}
        </option>
    </select>
</div>

<!-- ✅ NEW -->
<div v-if="accounts && accounts.length > 0">
    <select v-model="selectedAccountId" @change="filterByAccount">
        <option v-for="account in accounts" :key="account.id" :value="account.id">
            {{ formatPhone(account.phone_number) }}
        </option>
    </select>
</div>
```

---

#### **3. `resources/js/Pages/User/Campaign/Create.vue`**

**Changes:**
```vue
<!-- ❌ OLD -->
<CampaignForm
    :whatsappAccounts="whatsappAccounts"
/>

<script setup>
const props = defineProps(['templates', 'contactGroups', 'settings', 'whatsappAccounts']);
</script>

<!-- ✅ NEW -->
<CampaignForm
    :whatsappAccounts="whatsappAccounts"
/>

<script setup>
const props = defineProps(['templates', 'contactGroups', 'settings', 'whatsappAccounts']);
</script>
```

---

#### **4. `resources/js/Pages/User/Templates/Add.vue`**

**Changes:**
```vue
<!-- ❌ OLD -->
<div v-if="!settings?.whatsapp && (!whatsappAccounts || whatsappAccounts.length === 0)">
<Link href="/settings/whatsapp/sessions">

const props = defineProps(['languages', 'settings', 'whatsappAccounts']);
const hasWebJsSessions = props.whatsappAccounts && props.whatsappAccounts.length > 0;

<!-- ✅ NEW -->
<div v-if="!settings?.whatsapp && (!whatsappAccounts || whatsappAccounts.length === 0)">
<Link href="/settings/whatsapp/accounts">

const props = defineProps(['languages', 'settings', 'whatsappAccounts']);
const hasWebJsAccounts = props.whatsappAccounts && props.whatsappAccounts.length > 0;
```

---

#### **5. `resources/js/Pages/User/Settings/WhatsAppAccounts.vue`**

**Current Issues:**
```javascript
// ❌ LEGACY CODE
const sessionsList = ref([])  // Should be accountsList
const connectedSessionsCount = computed(...)  // Should be connectedAccountsCount
const canAddSessionComputed = computed(...)  // Should be canAddAccountComputed

const addSession = () => { ... }  // Should be addAccount

// UI text references
"WhatsApp Numbers (On-premise Multi-Session)"  // Should mention "Accounts"
```

**Required Changes:**
```javascript
// ✅ UPDATED CODE
const accountsList = ref([])
const connectedAccountsCount = computed(() => 
    accountsList.value.filter(a => a.status === 'connected').length
)
const canAddAccountComputed = computed(() => 
    accountsList.value.length < maxAccounts
)

const addAccount = () => { 
    router.visit('/settings/whatsapp/accounts/add')
}
```

**Template Updates:**
```vue
<!-- ❌ OLD -->
<div v-if="!sessionsList || sessionsList.length === 0">
<div>{{ sessionsList.length }}</div>
<div>{{ sessionsList.some(s => s.status === 'connected') ? 'Active' : 'Inactive' }}</div>
<h3>{{ $t('WhatsApp Numbers (On-premise Multi-Session)') }}</h3>
<button @click="addSession">Add Session</button>
<li v-for="session in sessionsList" :key="session.uuid">

<!-- ✅ NEW -->
<div v-if="!accountsList || accountsList.length === 0">
<div>{{ accountsList.length }}</div>
<div>{{ accountsList.some(a => a.status === 'connected') ? 'Active' : 'Inactive' }}</div>
<h3>{{ $t('WhatsApp Accounts (Multi-Account Management)') }}</h3>
<button @click="addAccount">Add Account</button>
<li v-for="account in accountsList" :key="account.uuid">
```

---

## ⚠️ HIGH PRIORITY - Backend Services Cleanup

### **Files to Update:**

#### **1. `app/Services/WhatsApp/ProviderSelectionService.php`**

**This is the MOST CRITICAL backend file** - Contains 100+ references to "session"

**Current Issues:**
```php
// ❌ LEGACY CODE - Lines 28-47
$sessions = $this->getActiveSessions($workspaceId);

if ($sessions->isEmpty()) {
    Log::warning('No active WhatsApp accounts found for workspace', [
        'workspace_id' => $workspaceId
    ]);
    return null;
}

$preferredSessions = $sessions->where('provider_type', $preferredProvider);

if ($preferredSessions->isEmpty()) {
    Log::info('No sessions found with preferred provider', [
        'preferred_provider' => $preferredProvider
    ]);
    $preferredSessions = $sessions;
}

// Score and rank sessions
$rankedSessions = $preferredSessions->map(function ($session) use ($campaign) {
    return [
        'session' => $session,
        'score' => $this->calculateSessionScore($session, $campaign)
    ];
});
```

**Required Changes:**
```php
// ✅ UPDATED CODE
$accounts = $this->getActiveAccounts($workspaceId);

if ($accounts->isEmpty()) {
    Log::warning('No active WhatsApp accounts found for workspace', [
        'workspace_id' => $workspaceId
    ]);
    return null;
}

$preferredAccounts = $accounts->where('provider_type', $preferredProvider);

if ($preferredAccounts->isEmpty()) {
    Log::info('No accounts found with preferred provider', [
        'preferred_provider' => $preferredProvider
    ]);
    $preferredAccounts = $accounts;
}

// Score and rank accounts
$rankedAccounts = $preferredAccounts->map(function ($account) use ($campaign) {
    return [
        'account' => $account,
        'score' => $this->calculateAccountScore($account, $campaign)
    ];
});
```

**Method Renames Required:**
```php
// ❌ OLD METHOD NAMES
private function getActiveSessions(int $workspaceId): Collection
private function calculateSessionScore(WhatsAppAccount $session, Campaign $campaign): int
public function getFallbackSessions(Campaign $campaign, WhatsAppAccount $primarySession): Collection

// ✅ NEW METHOD NAMES
private function getActiveAccounts(int $workspaceId): Collection
private function calculateAccountScore(WhatsAppAccount $account, Campaign $campaign): int
public function getFallbackAccounts(Campaign $campaign, WhatsAppAccount $primaryAccount): Collection
```

**Log Context Updates:**
```php
// ❌ OLD LOG MESSAGES
'No active WhatsApp accounts found'
'sessions with preferred provider'
'Multiple healthy sessions available'
'Session availability analysis'
'session_id' => $session->id

// ✅ NEW LOG MESSAGES
'No active WhatsApp accounts found'
'accounts with preferred provider'
'Multiple healthy accounts available'
'Account availability analysis'
'account_id' => $account->id
```

**Full Method Example - Before & After:**

```php
// ❌ BEFORE (Line 87-102)
private function getActiveSessions(int $workspaceId): Collection
{
    return WhatsAppAccount::forWorkspace($workspaceId)
        ->where('status', 'connected')
        ->where('is_active', true)
        ->get();
}

private function calculateSessionScore(WhatsAppAccount $session, Campaign $campaign): int
{
    $score = 0;
    
    // Health score contribution (0-50 points)
    $score += min($session->health_score ?? 50, 50);
    
    // Provider preference (0-20 points)
    if ($session->provider_type === $campaign->preferred_provider) {
        $score += 20;
    }
    
    return $score;
}

// ✅ AFTER
private function getActiveAccounts(int $workspaceId): Collection
{
    return WhatsAppAccount::forWorkspace($workspaceId)
        ->where('status', 'connected')
        ->where('is_active', true)
        ->get();
}

private function calculateAccountScore(WhatsAppAccount $account, Campaign $campaign): int
{
    $score = 0;
    
    // Health score contribution (0-50 points)
    $score += min($account->health_score ?? 50, 50);
    
    // Provider preference (0-20 points)
    if ($account->provider_type === $campaign->preferred_provider) {
        $score += 20;
    }
    
    return $score;
}
```

**Comments & Documentation:**
```php
// ❌ OLD COMMENTS
// Get all active sessions for workspace
// Score and rank sessions
// Get fallback sessions in order of preference
// Only process incoming messages if session is available

// ✅ NEW COMMENTS
// Get all active accounts for workspace
// Score and rank accounts
// Get fallback accounts in order of preference
// Only process incoming messages if account is available
```

---

#### **2. `app/Services/WhatsApp/WhatsAppAccountService.php`**

**Method Name Updates:**
```php
// ❌ OLD
public function getWorkspaceSessions()
public function getSessionStatistics(string $uuid)

// ✅ NEW
public function getWorkspaceAccounts()
public function getAccountStatistics(string $uuid)
```

---

## ⚠️ HIGH PRIORITY - Jobs & Events

### **1. `app/Events/WhatsAppQRGeneratedEvent.php`**

**Changes:**
```php
// ❌ OLD (Line 58)
public function broadcastWith(): array
{
    return [
        'qr_code_base64' => $this->qrCodeBase64,
        'expires_in_seconds' => $this->expiresInSeconds,
        'workspace_id' => $this->workspaceId,
        'session_id' => $this->sessionId,  // ❌ Should be account_id
        'timestamp' => now()->toISOString(),
    ];
}

// ✅ NEW
public function broadcastWith(): array
{
    return [
        'qr_code_base64' => $this->qrCodeBase64,
        'expires_in_seconds' => $this->expiresInSeconds,
        'workspace_id' => $this->workspaceId,
        'account_id' => $this->sessionId,  // ✅ Updated key (keep variable name for BC)
        'timestamp' => now()->toISOString(),
    ];
}
```

**Property Documentation:**
```php
// ❌ OLD
public string $sessionId;  // Keep variable name but update comments

/**
 * @param string $sessionId WhatsApp account ID
 */

// ✅ NEW
public string $sessionId;  // Internal: Account ID (kept as sessionId for BC)

/**
 * @param string $sessionId WhatsApp account ID (parameter name kept for BC)
 */
```

---

### **2. `app/Events/WhatsAppAccountStatusChangedEvent.php`**

**Broadcast Event Name:**
```php
// ❌ OLD (Line 47)
public function broadcastAs(): string
{
    return 'session-status-changed';
}

// ✅ NEW
public function broadcastAs(): string
{
    return 'account-status-changed';
}
```

**Broadcast Data:**
```php
// ❌ OLD (Line 57)
public function broadcastWith(): array
{
    return [
        'session_id' => $this->accountId,  // ❌ Wrong key
        'status' => $this->status,
        'workspace_id' => $this->workspaceId,
        'phone_number' => $this->phoneNumber,
        'metadata' => $this->metadata,
        'timestamp' => now()->toISOString(),
    ];
}

// ✅ NEW
public function broadcastWith(): array
{
    return [
        'account_id' => $this->accountId,  // ✅ Correct key
        'status' => $this->status,
        'workspace_id' => $this->workspaceId,
        'phone_number' => $this->phoneNumber,
        'metadata' => $this->metadata,
        'timestamp' => now()->toISOString(),
    ];
}
```

---

### **3. `app/Jobs/WhatsAppChatSyncJob.php`**

**Log Context Updates:**
```php
// ❌ OLD (Multiple lines: 106, 159, 305, 322)
Log::info('Starting chat sync', [
    'session_id' => $this->sessionId,  // ❌
    'workspace_id' => $this->workspaceId,
    'chat_count' => count($this->chats)
]);

Log::error('Chat sync failed', [
    'session_id' => $this->sessionId,  // ❌
    'error' => $e->getMessage()
]);

// ✅ NEW
Log::info('Starting chat sync', [
    'account_id' => $this->sessionId,  // ✅ Updated key
    'workspace_id' => $this->workspaceId,
    'chat_count' => count($this->chats)
]);

Log::error('Chat sync failed', [
    'account_id' => $this->sessionId,  // ✅ Updated key
    'error' => $e->getMessage()
]);
```

**Class Documentation:**
```php
// ❌ OLD
/**
 * @var int WhatsApp account ID
 */
protected $sessionId;

/**
 * @param int $sessionId WhatsApp account ID
 */
public function __construct(int $sessionId, int $workspaceId, array $chats)

// ✅ NEW
/**
 * @var int WhatsApp account ID
 */
protected $sessionId;

/**
 * @param int $sessionId WhatsApp account ID (kept for backward compatibility)
 */
public function __construct(int $sessionId, int $workspaceId, array $chats)
```

---

### **4. `app/Jobs/SendCampaignJob.php`**

**Log Context Updates:**
```php
// ❌ OLD (Lines 168, 394, 408, 409, 443)
Log::info('Campaign processing', [
    'session_id' => $this->selectedAccount?->id,  // ❌
    'campaign_id' => $campaign->id
]);

Log::warning('Primary account failed, trying fallback', [
    'primary_session_id' => $this->selectedAccount->id,  // ❌
    'fallback_session_id' => $fallbackSession->id,  // ❌
]);

// ✅ NEW
Log::info('Campaign processing', [
    'account_id' => $this->selectedAccount?->id,  // ✅
    'campaign_id' => $campaign->id
]);

Log::warning('Primary account failed, trying fallback', [
    'primary_account_id' => $this->selectedAccount->id,  // ✅
    'fallback_account_id' => $fallbackAccount->id,  // ✅
]);
```

**Method Name Updates:**
```php
// ❌ OLD
$fallbackSessions = $this->providerService->getFallbackSessions($campaign, $this->selectedAccount);

foreach ($fallbackSessions as $fallbackSession) {
    // Try fallback session
}

// ✅ NEW
$fallbackAccounts = $this->providerService->getFallbackAccounts($campaign, $this->selectedAccount);

foreach ($fallbackAccounts as $fallbackAccount) {
    // Try fallback account
}
```

---

## 📝 MEDIUM PRIORITY - Controllers

### **1. `app/Http/Controllers/User/WhatsAppAccountManagementController.php`**

**Config Key Updates:**
```php
// ❌ OLD (Lines 382-383)
if ($decodedMetadata && isset($decodedMetadata['limits']['whatsapp_accounts'])) {
    $maxSessions = (int) $decodedMetadata['limits']['whatsapp_accounts'];
}

// Line 402
$maxSessions = $decodedMetadata['default_whatsapp_accounts_limit'] ?? 1;

// ✅ NEW
if ($decodedMetadata && isset($decodedMetadata['limits']['whatsapp_accounts'])) {
    $maxAccounts = (int) $decodedMetadata['limits']['whatsapp_accounts'];
}

$maxAccounts = $decodedMetadata['default_whatsapp_accounts_limit'] ?? 1;
```

**Variable Renames:**
```php
// ❌ OLD
$maxSessions = ...;
$currentSessions = WhatsAppAccount::where(...)->count();

if ($currentSessions >= $maxSessions) {
    return response()->json(['error' => 'Maximum sessions limit reached']);
}

// ✅ NEW
$maxAccounts = ...;
$currentAccounts = WhatsAppAccount::where(...)->count();

if ($currentAccounts >= $maxAccounts) {
    return response()->json(['error' => 'Maximum accounts limit reached']);
}
```

---

### **2. `app/Http/Controllers/User/WhatsAppAccountController.php`**

**Similar Updates to WhatsAppAccountManagementController:**
```php
// Lines 574-575, 594
// Same pattern as above - replace all session references with account
```

---

### **3. `app/Http/Controllers/Common/RegistrationController.php`**

**Metadata Structure Update:**
```php
// ❌ OLD (Line 243)
$metadata = [
    'default_whatsapp_accounts_limit' => 1,
    // ... other settings
];

// ✅ NEW
$metadata = [
    'default_whatsapp_accounts_limit' => 1,
    // ... other settings
];
```

---

## 📝 MEDIUM PRIORITY - Routes

### **Update Route Names (if needed)**

**Check:**
```php
// routes/web.php or api.php
Route::get('/settings/whatsapp/sessions', ...);  // ❌ OLD

// Should be:
Route::get('/settings/whatsapp/accounts', ...);  // ✅ NEW
```

---

## 📝 MEDIUM PRIORITY - Documentation

### **Files to Update:**

#### **1. `docs/broadcast/provider-selection-algorithm.md`**

**Mass Replace Operations:**
```bash
# Find & Replace patterns (50+ occurrences)
WhatsAppAccount → WhatsAppAccount (class references)
$session → $account (variable names)
whatsapp_account_id → whatsapp_account_id (database columns)
'session' → 'account' (in method names and strings)
sessions → accounts (plural forms)
```

**Example Changes:**
```php
// ❌ OLD
use App\Models\WhatsAppAccount;

private function selectPrimaryWebJSSession(Campaign $campaign): ?WhatsAppAccount
{
    return WhatsAppAccount::forWorkspace($campaign->workspace_id)
        ->where('provider_type', 'webjs')
        ->where('status', 'connected')
        ->orderBy('health_score', 'desc')
        ->first();
}

// ✅ NEW
use App\Models\WhatsAppAccount;

private function selectPrimaryWebJSAccount(Campaign $campaign): ?WhatsAppAccount
{
    return WhatsAppAccount::forWorkspace($campaign->workspace_id)
        ->where('provider_type', 'webjs')
        ->where('status', 'connected')
        ->orderBy('health_score', 'desc')
        ->first();
}
```

---

#### **2. `docs/broadcast/README.md`**

**Schema Documentation Updates:**
```markdown
<!-- ❌ OLD -->
#### **whatsapp_accounts**
CREATE TABLE whatsapp_accounts (
    id BIGINT UNSIGNED PRIMARY KEY,
    ...
);

<!-- ✅ NEW -->
#### **whatsapp_accounts**
CREATE TABLE whatsapp_accounts (
    id BIGINT UNSIGNED PRIMARY KEY,
    ...
);
```

**Foreign Key References:**
```markdown
<!-- ❌ OLD -->
FOREIGN KEY (whatsapp_account_id) REFERENCES whatsapp_accounts(id)

<!-- ✅ NEW -->
FOREIGN KEY (whatsapp_account_id) REFERENCES whatsapp_accounts(id)
```

**Code Examples:**
```markdown
<!-- ❌ OLD -->
$optimalSession = WhatsAppAccount::forWorkspace($workspaceId)
    ->orderBy('health_score', 'desc')
    ->first();

<!-- ✅ NEW -->
$optimalAccount = WhatsAppAccount::forWorkspace($workspaceId)
    ->orderBy('health_score', 'desc')
    ->first();
```

---

#### **3. Other Documentation Files**

**Files that need updates:**
- `docs/architecture/*.md` - Architecture diagrams and explanations
- `docs/api/*.md` - API endpoint documentation
- `docs/database/*.md` - Database schema documentation
- `docs/development/*.md` - Development guides
- `README.md` - Main project README (if has session references)

---

## 🛠️ Automated Cleanup Strategy

### **Phase 1: Automated Mass Replace (Safe Operations)**

Create cleanup script: `scripts/cleanup-sessions-to-accounts.sh`

```bash
#!/bin/bash

# Configuration
DRY_RUN=${DRY_RUN:-false}
BACKUP_DIR="backups/cleanup-$(date +%Y%m%d-%H%M%S)"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== Sessions → Accounts Cleanup Script ===${NC}"
echo "Dry run: $DRY_RUN"
echo "Backup directory: $BACKUP_DIR"
echo ""

# Create backup
if [ "$DRY_RUN" = false ]; then
    echo -e "${YELLOW}Creating backup...${NC}"
    mkdir -p "$BACKUP_DIR"
    
    # Backup files before modification
    cp -r resources/js/Components "$BACKUP_DIR/"
    cp -r resources/js/Pages "$BACKUP_DIR/"
    cp -r app/Services/WhatsApp "$BACKUP_DIR/"
    cp -r app/Events "$BACKUP_DIR/"
    cp -r app/Jobs "$BACKUP_DIR/"
    
    echo -e "${GREEN}✓ Backup created${NC}\n"
fi

# Function to replace in file
replace_in_file() {
    local file=$1
    local pattern=$2
    local replacement=$3
    
    if [ "$DRY_RUN" = true ]; then
        echo -e "${YELLOW}[DRY RUN]${NC} Would replace in $file:"
        echo "  Pattern: $pattern"
        echo "  Replacement: $replacement"
        grep -n "$pattern" "$file" 2>/dev/null | head -3
        echo ""
    else
        # macOS compatible sed
        sed -i '' "s/$pattern/$replacement/g" "$file"
        echo -e "${GREEN}✓${NC} Updated: $file"
    fi
}

# Frontend Vue Components - Critical replacements
echo -e "${GREEN}=== Phase 1: Frontend Components ===${NC}"

# CampaignForm.vue
CAMPAIGN_FORM="resources/js/Components/CampaignForm.vue"
replace_in_file "$CAMPAIGN_FORM" "whatsappAccounts" "whatsappAccounts"
replace_in_file "$CAMPAIGN_FORM" "hasWebJsSessions" "hasWebJsAccounts"
replace_in_file "$CAMPAIGN_FORM" "hasMetaApiSessions" "hasMetaApiAccounts"
replace_in_file "$CAMPAIGN_FORM" "whatsapp_account_id" "whatsapp_account_id"
replace_in_file "$CAMPAIGN_FORM" "Manage WhatsApp accounts" "Manage WhatsApp Accounts"
replace_in_file "$CAMPAIGN_FORM" "WhatsApp Web JS sessions" "WhatsApp Web JS accounts"
replace_in_file "$CAMPAIGN_FORM" "Specific WhatsApp account" "Specific WhatsApp Account"
replace_in_file "$CAMPAIGN_FORM" "Auto-select best session" "Auto-select best account"
replace_in_file "$CAMPAIGN_FORM" "No active sessions" "No active accounts"

# ChatTable.vue
CHAT_TABLE="resources/js/Components/ChatComponents/ChatTable.vue"
replace_in_file "$CHAT_TABLE" "props.sessions" "props.accounts"
replace_in_file "$CHAT_TABLE" "selectedSessionId" "selectedAccountId"
replace_in_file "$CHAT_TABLE" "filterBySession" "filterByAccount"
replace_in_file "$CHAT_TABLE" "session_id:" "account_id:"
replace_in_file "$CHAT_TABLE" 'v-for="session in sessions"' 'v-for="account in accounts"'
replace_in_file "$CHAT_TABLE" ':key="session.id"' ':key="account.id"'
replace_in_file "$CHAT_TABLE" ':value="session.id"' ':value="account.id"'
replace_in_file "$CHAT_TABLE" 'session.phone_number' 'account.phone_number'
replace_in_file "$CHAT_TABLE" 'session.provider_type' 'account.provider_type'
replace_in_file "$CHAT_TABLE" 'session.unread_count' 'account.unread_count'

echo ""

# Backend Services
echo -e "${GREEN}=== Phase 2: Backend Services ===${NC}"

# ProviderSelectionService.php - Most critical
PROVIDER_SERVICE="app/Services/WhatsApp/ProviderSelectionService.php"
replace_in_file "$PROVIDER_SERVICE" '\$sessions' '\$accounts'
replace_in_file "$PROVIDER_SERVICE" 'getActiveSessions' 'getActiveAccounts'
replace_in_file "$PROVIDER_SERVICE" 'calculateSessionScore' 'calculateAccountScore'
replace_in_file "$PROVIDER_SERVICE" 'getFallbackSessions' 'getFallbackAccounts'
replace_in_file "$PROVIDER_SERVICE" 'preferredSessions' 'preferredAccounts'
replace_in_file "$PROVIDER_SERVICE" 'rankedSessions' 'rankedAccounts'
replace_in_file "$PROVIDER_SERVICE" 'webjsSessions' 'webjsAccounts'
replace_in_file "$PROVIDER_SERVICE" 'metaApiSessions' 'metaApiAccounts'
replace_in_file "$PROVIDER_SERVICE" 'healthySessions' 'healthyAccounts'
replace_in_file "$PROVIDER_SERVICE" 'inactiveSessions' 'inactiveAccounts'
replace_in_file "$PROVIDER_SERVICE" 'WhatsApp accounts' 'WhatsApp accounts'
replace_in_file "$PROVIDER_SERVICE" 'session available' 'account available'
replace_in_file "$PROVIDER_SERVICE" "'session'" "'account'"

echo ""

# Events
echo -e "${GREEN}=== Phase 3: Events & Jobs ===${NC}"

# WhatsAppQRGeneratedEvent
QR_EVENT="app/Events/WhatsAppQRGeneratedEvent.php"
replace_in_file "$QR_EVENT" "'session_id'" "'account_id'"

# WhatsAppAccountStatusChangedEvent
STATUS_EVENT="app/Events/WhatsAppAccountStatusChangedEvent.php"
replace_in_file "$STATUS_EVENT" "'session-status-changed'" "'account-status-changed'"
replace_in_file "$STATUS_EVENT" "'session_id' => " "'account_id' => "

# WhatsAppChatSyncJob
SYNC_JOB="app/Jobs/WhatsAppChatSyncJob.php"
replace_in_file "$SYNC_JOB" "'session_id' => " "'account_id' => "
replace_in_file "$SYNC_JOB" "WhatsApp account ID" "WhatsApp account ID"

# SendCampaignJob
CAMPAIGN_JOB="app/Jobs/SendCampaignJob.php"
replace_in_file "$CAMPAIGN_JOB" "'session_id' => " "'account_id' => "
replace_in_file "$CAMPAIGN_JOB" "primary_session_id" "primary_account_id"
replace_in_file "$CAMPAIGN_JOB" "fallback_session_id" "fallback_account_id"
replace_in_file "$CAMPAIGN_JOB" '\$fallbackSessions' '\$fallbackAccounts'
replace_in_file "$CAMPAIGN_JOB" '\$fallbackSession' '\$fallbackAccount'

echo ""

# Controllers
echo -e "${GREEN}=== Phase 4: Controllers ===${NC}"

ACCOUNT_MGMT="app/Http/Controllers/User/WhatsAppAccountManagementController.php"
replace_in_file "$ACCOUNT_MGMT" "whatsapp_accounts" "whatsapp_accounts"
replace_in_file "$ACCOUNT_MGMT" "default_whatsapp_accounts_limit" "default_whatsapp_accounts_limit"
replace_in_file "$ACCOUNT_MGMT" '\$maxSessions' '\$maxAccounts'
replace_in_file "$ACCOUNT_MGMT" '\$currentSessions' '\$currentAccounts'
replace_in_file "$ACCOUNT_MGMT" "Maximum sessions limit" "Maximum accounts limit"

ACCOUNT_CTRL="app/Http/Controllers/User/WhatsAppAccountController.php"
replace_in_file "$ACCOUNT_CTRL" "whatsapp_accounts" "whatsapp_accounts"
replace_in_file "$ACCOUNT_CTRL" "default_whatsapp_accounts_limit" "default_whatsapp_accounts_limit"

REG_CTRL="app/Http/Controllers/Common/RegistrationController.php"
replace_in_file "$REG_CTRL" "default_whatsapp_accounts_limit" "default_whatsapp_accounts_limit"

echo ""
echo -e "${GREEN}=== Cleanup Complete ===${NC}"

if [ "$DRY_RUN" = true ]; then
    echo -e "${YELLOW}This was a DRY RUN. No files were modified.${NC}"
    echo "To apply changes, run: DRY_RUN=false ./scripts/cleanup-sessions-to-accounts.sh"
else
    echo -e "${GREEN}Files have been updated!${NC}"
    echo "Backup location: $BACKUP_DIR"
    echo ""
    echo "Next steps:"
    echo "1. Review changes: git diff"
    echo "2. Run tests: php artisan test"
    echo "3. Test frontend: npm run dev"
    echo "4. Commit changes: git add . && git commit -m 'refactor: cleanup sessions to accounts terminology'"
fi
```

**Usage:**
```bash
# Dry run first (see what would change)
DRY_RUN=true ./scripts/cleanup-sessions-to-accounts.sh

# Apply changes
DRY_RUN=false ./scripts/cleanup-sessions-to-accounts.sh
```

---

### **Phase 2: Manual Review (Requires Developer Judgment)**

After automated cleanup, manually review:

1. **Complex Logic** - Service methods with business logic
2. **API Contracts** - Ensure no breaking changes
3. **Event Listeners** - Frontend WebSocket listeners
4. **Test Files** - Update test assertions
5. **Config Files** - Check for hardcoded values

---

## ✅ Verification Checklist

After cleanup, verify each layer:

### **Database Layer**
- [ ] No table named `whatsapp_accounts` exists
- [ ] All foreign keys reference `whatsapp_accounts`
- [ ] No column named `whatsapp_account_id` exists
- [ ] Migration history preserved

### **Backend Layer**
- [ ] All models use `whatsappAccount()` relationship
- [ ] No references to `WhatsAppAccount` class
- [ ] All services use "account" terminology
- [ ] All controllers use "account" terminology
- [ ] All jobs use "account_id" in logs
- [ ] All events broadcast correct keys

### **Frontend Layer**
- [ ] All Vue props use "accounts" not "sessions"
- [ ] All form fields use `whatsapp_account_id`
- [ ] All UI text says "Accounts" not "Sessions"
- [ ] All API calls use correct field names
- [ ] All computed properties renamed

### **Documentation Layer**
- [ ] All docs use "account" terminology
- [ ] All code examples updated
- [ ] All schema diagrams updated
- [ ] API documentation reflects changes

### **Testing Layer**
- [ ] All tests pass: `php artisan test`
- [ ] Frontend builds: `npm run build`
- [ ] No console errors in browser
- [ ] Manual testing of affected features

---

## 🧪 Testing Strategy

### **Unit Tests**
```bash
# Test services
php artisan test --filter=ProviderSelectionServiceTest
php artisan test --filter=WhatsAppAccountServiceTest

# Test models
php artisan test --filter=WhatsAppAccountTest
php artisan test --filter=ContactTest
```

### **Feature Tests**
```bash
# Test campaign creation
php artisan test --filter=CampaignTest

# Test account management
php artisan test --filter=WhatsAppAccountManagementTest
```

### **Integration Tests**
```bash
# Test full flow
php artisan test --filter=BroadcastCampaignIntegrationTest
```

### **Frontend Testing**
```bash
# Build and check for errors
npm run build

# Run frontend tests (if available)
npm run test

# Manual testing checklist:
# 1. Navigate to Campaign Create page
# 2. Verify "WhatsApp Accounts" dropdown shows
# 3. Create a test campaign
# 4. Check browser console for errors
# 5. Verify WebSocket events use correct keys
```

---

## 📊 Progress Tracking

### **Daily Checkpoint Template**

```markdown
## Cleanup Progress - [Date]

### Completed Today
- [ ] Frontend: CampaignForm.vue
- [ ] Frontend: ChatTable.vue
- [ ] Backend: ProviderSelectionService.php
- [ ] Events: WhatsAppQRGeneratedEvent.php
- [ ] ...

### Testing Results
- Unit Tests: ✅ PASS / ❌ FAIL
- Feature Tests: ✅ PASS / ❌ FAIL
- Manual Testing: ✅ PASS / ❌ FAIL

### Issues Found
- Issue 1: [Description]
- Issue 2: [Description]

### Next Steps
1. [Next task]
2. [Next task]
```

---

## 🚀 Deployment Plan

### **Pre-Deployment**
1. ✅ Complete all cleanup tasks
2. ✅ Pass all tests
3. ✅ Code review completed
4. ✅ Documentation updated
5. ✅ Backup created

### **Deployment Steps**
```bash
# 1. Create backup
php artisan backup:run

# 2. Pull latest changes
git pull origin staging-chats-fix

# 3. Install dependencies
composer install --no-dev
npm ci --production
npm run build

# 4. Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# 5. Restart services
php artisan queue:restart
php artisan horizon:terminate  # if using Horizon

# 6. Monitor logs
tail -f storage/logs/laravel.log
```

### **Post-Deployment Verification**
```bash
# Check application health
php artisan health:check

# Verify database
php artisan db:show

# Test critical paths
curl -X POST https://your-domain.com/api/campaigns/create
```

---

## 🆘 Rollback Plan

If issues occur after cleanup:

### **Quick Rollback**
```bash
# Restore from backup
cp -r $BACKUP_DIR/Components/* resources/js/Components/
cp -r $BACKUP_DIR/Pages/* resources/js/Pages/
cp -r $BACKUP_DIR/WhatsApp/* app/Services/WhatsApp/
cp -r $BACKUP_DIR/Events/* app/Events/
cp -r $BACKUP_DIR/Jobs/* app/Jobs/

# Rebuild frontend
npm run build

# Clear caches
php artisan cache:clear
php artisan config:clear
```

### **Git Rollback**
```bash
# If committed
git revert [commit-hash]

# If not pushed
git reset --hard HEAD~1
```

---

## 📝 Final Notes

### **Important Reminders**

1. **Backward Compatibility**: Internal variable names (like `$sessionId`) kept for BC, only external interfaces changed
2. **Database**: Already migrated ✅, no further schema changes needed
3. **API Contracts**: Frontend-backend contracts must match exactly
4. **WebSocket Events**: Event names and payloads must be coordinated
5. **Config Keys**: Metadata keys must be updated in both code and database

### **Success Criteria**

Cleanup is considered complete when:
- ✅ 0 references to "session" in user-facing code
- ✅ All tests passing
- ✅ No console errors
- ✅ Documentation updated
- ✅ Code review approved
- ✅ Successfully deployed to staging

---

## 📚 References

- [Migration Plan Overview](./00-overview.md)
- [Migration Checklist](./01-checklist.md)
- [Migration Commands](./02-commands.md)
- [Risk Assessment](./03-risk-assessment.md)
- [Development Guidelines](../../architecture/07-development-patterns-guidelines.md)

---

**Document Maintainer:** Development Team  
**Last Updated:** November 15, 2025  
**Next Review:** After cleanup completion
