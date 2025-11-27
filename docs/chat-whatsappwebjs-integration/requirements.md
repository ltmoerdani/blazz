# BUSINESS REQUIREMENTS - Chat WhatsApp Web JS Integration

## 📋 EXECUTIVE SUMMARY

**Document Purpose:** Requirement bisnis untuk integrasi WhatsApp Web JS dengan existing chat feature  
**Audience:** Product owner, stakeholders, development team  
**Scope:** Load semua chat dari WhatsApp seperti WhatsApp Web, dengan dualisme provider (Meta API + WhatsApp Web JS)  
**Status:** REQUIREMENTS BASELINE v1.0  
**Tanggal:** 22 Oktober 2025

---

## 🎯 PROJECT OBJECTIVES

### Primary Goals
1. **WhatsApp Web Experience:** User dapat melihat SEMUA chat (private + group) yang ada di WhatsApp mereka (seperti WhatsApp Web) ketika nomor terhubung via WhatsApp Web JS
2. **Provider Dualisme:** Tetap support existing Meta API sambil menambahkan WhatsApp Web JS sebagai default
3. **Zero Data Loss:** Existing Meta API users tidak terdampak, data tetap utuh
4. **Real-Time Sync:** Chat baru dari WhatsApp (private & group) otomatis muncul di inbox tanpa delay
5. **Multi-Number Support:** User bisa connect multiple WhatsApp numbers dan filter chat by number
6. **Group Chat Support:** User bisa sync dan manage WhatsApp group chats dengan full participant info

### Success Criteria
- ✅ User bisa scan QR code dan langsung melihat existing chats incl. groups (< 30 detik initial sync)
- ✅ New incoming WhatsApp messages (private & group) muncul real-time di chat inbox
- ✅ Existing Meta API functionality tidak broken (100% backward compatible)
- ✅ User bisa filter chat by WhatsApp number dengan UI yang jelas
- ✅ Chat history tetap accessible setelah disconnect dan reconnect
- ✅ Group chats visually differentiated dengan icon dan participant count
- ✅ Sync limits configurable (Phase 1: 500 chats/30 days, Future: unlimited)

---

## 📊 CODEBASE ANALYSIS FINDINGS (REQUIRED SECTION)

### Similar Features Identified:

**Existing Chat System:**
- **Controller:** `app/Http/Controllers/User/ChatController.php` (lines 17-68)
  - Method: `index()` - Main chat inbox dengan contact list
  - Method: `sendMessage()` - Send via existing WhatsappService
  - Method: `loadMoreMessages()` - Pagination untuk chat history

- **Service:** `app/Services/ChatService.php` (lines 1-630)
  - Method: `getChatList()` - Query contacts with chats, support search
  - Method: `getChatMessages()` - Load messages dengan pagination (10 per page)
  - Method: `sendMessage()` - Integrate dengan WhatsappService (Meta API)

- **Model:** `app/Models/Chat.php` (lines 1-50)
  - Relationship: `belongsTo(Contact::class)`
  - Relationship: `belongsTo(WhatsAppAccount::class)` ✅ ALREADY EXISTS
  - Field: `type` (inbound/outbound), `status`, `metadata`

- **Frontend:** `resources/js/Pages/User/Chat/Index.vue` (lines 1-195)
  - Component: ChatTable (contact list sidebar)
  - Component: ChatThread (message thread)
  - Component: ChatForm (send message form)
  - Real-time: Laravel Echo + Pusher integration

**WhatsApp Web JS Integration Status:**
- **Model:** `app/Models/WhatsAppAccount.php` ✅ ALREADY EXISTS
- **Migration:** `2025_10_13_000000_create_whatsapp_accounts_table.php` ✅ COMPLETED
- **Foreign Keys:** `chats.whatsapp_account_id` ✅ ALREADY EXISTS
- **Node.js Service:** `/whatsapp-service/server.js` ✅ EXISTS but needs chat sync implementation
- **Provider Abstraction:** ❌ NOT EXISTS (needs implementation)
- **Chat Sync Service:** ❌ NOT EXISTS (CRITICAL GAP)

### Database Schema Verified:

**Table: chats (verified via model + migrations)**
```sql
-- Existing columns:
id, uuid, workspace_id, contact_id, whatsapp_account_id ✅
type (inbound/outbound), status, metadata, 
created_at, updated_at, deleted_at

-- MISSING column (needs migration):
provider_type VARCHAR(20) DEFAULT 'meta' ❌ CRITICAL
```

**Table: whatsapp_accounts (verified)**
```sql
-- All required columns exist:
id, uuid, workspace_id, session_id, phone_number
provider_type ✅ ('meta' | 'webjs')
status ✅ (qr_scanning | authenticated | connected | disconnected)
qr_code, session_data (encrypted), metadata (JSON)
is_primary, is_active
last_activity_at, last_connected_at
created_by, created_at, updated_at, deleted_at
```

**Table: contacts (verified)**
```sql
-- Relevant columns:
id, workspace_id, phone, first_name
source_session_id ❓ (needs verification - untuk track origin)
source_type ❓ (needs verification - 'meta' | 'webjs')
latest_chat_created_at ✅ (indexed)
```

### Service Dependencies:

**Existing Services to Integrate:**
```php
// app/Services/WhatsappService.php
- sendMessage($uuid, $message, $userId) // Meta API implementation
- sendMedia($uuid, $type, $fileName, $path, $url, $location)
```

**NEW Services Needed:**
```php
// app/Services/WhatsAppProviderService.php ❌ NOT EXISTS
- selectProvider($workspace, $contact) // Auto-select Meta vs WebJS
- sendMessage($contact, $message, $provider) // Abstraction layer

// app/Services/WhatsAppWebJSProvider.php ❌ NOT EXISTS  
- sendMessage($session, $contact, $message)
- getChats($sessionId, $limit = 500) // Sync existing chats
- syncChatHistory($sessionId, $days = 30)

// app/Services/WhatsAppChatSyncService.php ❌ NOT EXISTS
- syncInitialChats($session) // Initial 500 chats or 30 days
- syncIncrementalChats($session) // Periodic sync every 6 hours
- processIncomingMessage($webhook) // Real-time new message
```

**Frontend Dependencies:**
```javascript
// Response Formats (needs consistency):
// Chat List API response
{
  "rows": [ /* ContactResource */ ],
  "chatThread": [ /* ChatLog with messages */ ],
  "hasMoreMessages": boolean,
  "nextPage": number
}

// Real-time Broadcasting Events (needs enhancement):
// Existing: 'qr-code-generated', 'session-status-changed'
// NEW: 'new-chat-received', 'chat-synced'
```

---

## 🚨 ASSUMPTION ELIMINATIONS APPLIED

**From assumption.md:**

### ASM-1: Chat Sync Behavior → VERIFIED
- **Original Assumption:** Chat akan auto-sync seperti WhatsApp Web
- **Verification Result:** ✅ CONFIRMED - WhatsApp Web.js library support `client.getChats()`
- **Evidence:** whatsapp-service/server.js (lines 1-669) has event handlers, but NO `client.on('ready')` chat sync implementation YET
- **Action Required:** Implement chat sync di Node.js service

### ASM-2: Provider Type Field → VERIFIED  
- **Original Assumption:** `chats.provider_type` sudah exists
- **Verification Result:** ❌ COLUMN NOT EXISTS
- **Evidence:** MySQL query `SHOW COLUMNS FROM chats` tidak return `provider_type`
- **Action Required:** Create migration untuk add `provider_type` column

### ASM-6: Node.js Service Status → VERIFIED
- **Original Assumption:** Node.js service sudah running
- **Verification Result:** ❌ SERVICE NOT RUNNING
- **Evidence:** `curl http://localhost:3000/health` → "Service not running"
- **Action Required:** Start Node.js service via PM2 atau manual

### ASM-7: Database Schema → VERIFIED
- **Original Assumption:** Semua tables dan foreign keys sudah complete
- **Verification Result:** ⚠️ PARTIAL
  - ✅ `whatsapp_accounts` table EXISTS
  - ✅ `chats.whatsapp_account_id` foreign key EXISTS
  - ❌ `chats.provider_type` column MISSING
  - ❓ `contacts.source_session_id` dan `source_type` needs verification
- **Action Required:** Create migration untuk missing columns

---

## 👥 USER REQUIREMENTS

### REQ-1: Initial Chat Sync
**As a user, I want to** see all my existing WhatsApp chats when I connect my number **so that** saya tidak perlu scroll atau search lagi seperti di WhatsApp Web.

**User Story:**
```
User connects WhatsApp via QR code
  ↓
System fetches last 30 days OR 500 chats (whichever limit hit first)
  ↓
System creates Contact records untuk new contacts
  ↓
System imports Chat records dengan metadata
  ↓
User sees chat list di inbox dalam < 30 seconds
```

**Acceptance Criteria:**
- [ ] REQ-1.1: Initial sync dimulai otomatis setelah `session status = connected`
- [ ] REQ-1.2: System sync maksimal 500 chats ATAU 30 hari history untuk **Phase 1** (CONFIGURABLE - dapat di-set unlimited)
- [ ] REQ-1.3: Sync berjalan dalam batch (20 chats per request) untuk avoid timeout
- [ ] REQ-1.4: Contact auto-created jika belum ada di database
- [ ] REQ-1.5: Progress indicator ditampilkan: "Syncing chats... 45/120 (38%)"
- [ ] REQ-1.6: User bisa skip sync dan langsung gunakan chat (sync continues in background)
- [ ] REQ-1.7: Sync status tersimpan di `whatsapp_accounts.metadata.sync_status`
- [ ] REQ-1.8: Config support unlimited sync (`null` value = fetch all available chats)

**Business Rules:**
- **Phase 1 (Conservative):** Default sync window: 30 hari ATAU 500 chats (mana yang tercapai duluan)
- **Future Phases:** Config dapat di-set `null` untuk unlimited sync
- Workspace bisa override window: min 7 hari, max unlimited (via config)
- Media files TIDAK didownload pada initial sync (only metadata)
- Sync progress: `pending` → `syncing` → `completed` | `failed`
- **Reality:** WhatsApp Web.js `getChats()` returns ALL chats - limit adalah performance decision, NOT library limitation

**Technical Specifications:**
```javascript
// Node.js - Initial Chat Sync (Phase 1: Conservative Limits)
client.on('ready', async () => {
    const chats = await client.getChats(); // Returns ALL chats (no built-in limit)
    
    // Phase 1: Apply configurable limits for performance
    const config = await getWorkspaceConfig(workspaceId);
    const syncWindow = config.sync_window_days || 30;  // null = unlimited
    const maxChats = config.max_chats || 500;          // null = unlimited
    
    let syncedCount = 0;
    const cutoffDate = syncWindow ? Date.now() - (syncWindow * 24 * 60 * 60 * 1000) : null;
    
    // Filter chats based on config (or sync ALL if limits are null)
    const chatsToSync = chats.filter(chat => {
        if (maxChats && syncedCount >= maxChats) return false;
        if (cutoffDate && chat.timestamp < cutoffDate / 1000) return false;
        return true;
    });
    
    console.log(`Syncing ${chatsToSync.length} chats (Limit: ${maxChats || 'unlimited'}, Window: ${syncWindow || 'unlimited'} days)`);
    
    for (const chat of chatsToSync) {
        if (syncedCount >= maxChats) break;
        if (chat.timestamp < cutoffDate) continue;
        
        const messages = await chat.fetchMessages({ limit: 50 });
        
        await sendChatToLaravel(sessionId, {
            chat_id: chat.id._serialized,
            contact_number: chat.id.user,
            contact_name: chat.name,
            messages: messages,
            unread_count: chat.unreadCount,
            last_message_at: chat.timestamp
        });
        
        syncedCount++;
    }
});
```

```php
// Laravel - Process Chat Sync
// POST /api/whatsapp/sync/chats
public function syncChats(Request $request) {
    $session = WhatsAppAccount::where('session_id', $request->session_id)
        ->firstOrFail();
    
    // Create or update contact
    $contact = Contact::firstOrCreate(
        [
            'workspace_id' => $session->workspace_id,
            'phone' => $this->formatPhone($request->contact_number)
        ],
        [
            'first_name' => $request->contact_name,
            'source_session_id' => $session->id,
            'source_type' => 'webjs'
        ]
    );
    
    // Import messages
    foreach ($request->messages as $message) {
        Chat::updateOrCreate(
            ['wam_id' => $message['id']],
            [
                'workspace_id' => $session->workspace_id,
                'whatsapp_account_id' => $session->id,
                'contact_id' => $contact->id,
                'type' => $message['from_me'] ? 'outbound' : 'inbound',
                'metadata' => json_encode($message),
                'provider_type' => 'webjs',
                'status' => 'delivered',
                'created_at' => Carbon::createFromTimestamp($message['timestamp'])
            ]
        );
    }
    
    return response()->json(['synced' => count($request->messages)]);
}
```

---

### REQ-2: Real-Time Incoming Messages
**As a user, I want to** receive new WhatsApp messages in real-time di chat inbox **so that** saya bisa balas customer segera.

**User Story:**
```
Customer kirim WhatsApp message ke nomor yang connected
  ↓
WhatsApp Web JS `message` event triggered
  ↓
Node.js calls Laravel webhook dengan message data
  ↓
Laravel create Chat record
  ↓
Laravel broadcast NewChatEvent via Reverb/Pusher
  ↓
Frontend receives event dan update chat list
  ↓
User sees new message dengan notification sound
```

**Acceptance Criteria:**
- [ ] REQ-2.1: New message muncul di chat list dalam < 2 detik setelah WhatsApp terima
- [ ] REQ-2.2: Unread count badge update otomatis
- [ ] REQ-2.3: Contact auto-created jika first-time sender
- [ ] REQ-2.4: Message ditampilkan di chat thread jika sedang open conversation
- [ ] REQ-2.5: Browser notification untuk new message (jika tab inactive)
- [ ] REQ-2.6: Notification sound playback (configurable)

**Technical Specifications:**
```javascript
// Node.js - Message Event Handler
client.on('message', async (message) => {
    await axios.post('http://localhost:8000/api/whatsapp/webhooks/message', {
        workspace_id: workspaceId,
        session_id: sessionId,
        message: {
            id: message.id._serialized,
            from: message.from,
            body: message.body,
            timestamp: message.timestamp,
            from_me: message.fromMe,
            type: message.type,
            has_media: message.hasMedia
        }
    });
});
```

```php
// Laravel - Webhook Handler
public function handleIncomingMessage(Request $request) {
    $session = WhatsAppAccount::where('session_id', $request->session_id)
        ->firstOrFail();
    
    $contact = $this->getOrCreateContact($request->message['from'], $session);
    
    $chat = Chat::create([
        'workspace_id' => $session->workspace_id,
        'whatsapp_account_id' => $session->id,
        'contact_id' => $contact->id,
        'wam_id' => $request->message['id'],
        'type' => 'inbound',
        'metadata' => json_encode($request->message),
        'provider_type' => 'webjs',
        'status' => 'delivered',
        'is_read' => 0
    ]);
    
    // Broadcast to frontend
    broadcast(new NewChatEvent($chat, $contact, $session->workspace_id));
    
    return response()->json(['chat_id' => $chat->id]);
}
```

---

### REQ-3: Multi-Number Chat Filtering
**As a user, I want to** filter chats by WhatsApp number **so that** saya bisa fokus handle chat dari nomor tertentu.

**User Story:**
```
User opens chat inbox
  ↓
Dropdown filter shows: "All Conversations" + list of connected numbers
  ↓
User selects specific WhatsApp number
  ↓
Chat list filtered untuk show only chats dari number tersebut
  ↓
Unread count badge shows per-number statistics
```

**Acceptance Criteria:**
- [ ] REQ-3.1: Dropdown filter di chat sidebar dengan options:
  - "All Conversations" (default)
  - "+62 812-XXXX (5 unread)"
  - "+62 813-YYYY (0 unread)"
- [ ] REQ-3.2: Filter state tersimpan di session (persist setelah page reload)
- [ ] REQ-3.3: Chat list query optimized dengan index pada `whatsapp_account_id`
- [ ] REQ-3.4: Empty state jika tidak ada chat untuk selected number
- [ ] REQ-3.5: Clear filter button untuk kembali ke "All Conversations"

**Technical Specifications:**
```php
// ChatService@getChatList with session filter
public function getChatList($request, $uuid = null, $searchTerm = null, $sessionId = null)
{
    $contacts = Contact::with(['lastChat', 'whatsappAccount'])
        ->where('workspace_id', $this->workspaceId)
        ->whereHas('chats', function ($query) use ($sessionId) {
            $query->where('deleted_at', null);
            
            if ($sessionId) {
                $query->where('whatsapp_account_id', $sessionId);
            }
        })
        ->when($searchTerm, function ($q) use ($searchTerm) {
            $q->where(function ($query) use ($searchTerm) {
                $query->where('first_name', 'like', "%{$searchTerm}%")
                      ->orWhere('phone', 'like', "%{$searchTerm}%");
            });
        })
        ->orderBy('latest_chat_created_at', 'desc')
        ->paginate(50);
    
    return $contacts;
}
```

```vue
<!-- ChatTable.vue - Filter Dropdown -->
<template>
  <div class="chat-filters mb-4">
    <label class="block text-sm font-medium mb-2">Filter by Number</label>
    <select v-model="selectedSessionId" @change="filterChats" class="w-full rounded border">
      <option value="">All Conversations</option>
      <option v-for="session in sessions" :key="session.id" :value="session.id">
        {{ formatPhone(session.phone_number) }} 
        <span v-if="session.unread_count > 0">({{ session.unread_count }} unread)</span>
      </option>
    </select>
  </div>
</template>

<script setup>
const selectedSessionId = ref(sessionStorage.getItem('chat_filter_session') || '');

function filterChats() {
    sessionStorage.setItem('chat_filter_session', selectedSessionId.value);
    router.visit(route('chats.index'), {
        data: { session_id: selectedSessionId.value },
        preserveState: true
    });
}
</script>
```

---

### REQ-4: Send Message via Correct Provider
**As a user, I want** messages sent menggunakan WhatsApp number yang sama dengan incoming chat **so that** conversation context tetap terjaga.

**User Story:**
```
User opens chat dari contact yang chat via WhatsApp Web JS number
  ↓
User type message dan klik Send
  ↓
System detect chat.whatsapp_account_id
  ↓
System route message via WhatsApp Web JS provider (NOT Meta API)
  ↓
Message sent dari same number yang contact gunakan
```

**Acceptance Criteria:**
- [ ] REQ-4.1: System auto-detect `chat.whatsapp_account_id` untuk determine provider
- [ ] REQ-4.2: Provider selection abstraction layer (Meta API vs WebJS)
- [ ] REQ-4.3: Jika session disconnected, show warning: "WhatsApp number tidak aktif"
- [ ] REQ-4.4: Fallback to primary number jika original session tidak available
- [ ] REQ-4.5: Chat metadata menyimpan which session_id used untuk send
- [ ] REQ-4.6: Error handling untuk failed message dengan retry option

**Technical Specifications:**
```php
// app/Services/WhatsAppProviderService.php
class WhatsAppProviderService
{
    public function sendMessage(Contact $contact, $message, $sessionId = null)
    {
        // Determine provider from session
        if ($sessionId) {
            $session = WhatsAppAccount::find($sessionId);
            
            if (!$session || $session->status !== 'connected') {
                throw new SessionNotActiveException(
                    "WhatsApp number tidak aktif, hubungkan kembali"
                );
            }
            
            if ($session->provider_type === 'webjs') {
                return $this->sendViaWebJS($session, $contact, $message);
            } else {
                return $this->sendViaMeta($session, $contact, $message);
            }
        }
        
        // Fallback to primary session
        $primarySession = WhatsAppAccount::where('workspace_id', $this->workspaceId)
            ->where('is_primary', true)
            ->where('status', 'connected')
            ->first();
        
        if (!$primarySession) {
            throw new NoActiveSessionException(
                "Tidak ada WhatsApp number yang aktif"
            );
        }
        
        return $this->sendMessage($contact, $message, $primarySession->id);
    }
    
    private function sendViaWebJS($session, $contact, $message)
    {
        $response = Http::post(config('whatsapp.node_service_url') . '/api/messages/send', [
            'session_id' => $session->session_id,
            'recipient_phone' => $contact->phone,
            'message' => $message,
            'type' => 'text'
        ]);
        
        if ($response->successful()) {
            return (object) [
                'success' => true,
                'message_id' => $response->json('message_id'),
                'provider' => 'webjs'
            ];
        }
        
        throw new MessageSendException($response->json('error'));
    }
}
```

---

### REQ-5: Provider Dualisme Support
**As a system admin, I want** existing Meta API users tetap berfungsi tanpa perubahan **so that** tidak ada downtime atau data loss.

**Acceptance Criteria:**
- [ ] REQ-5.1: Existing `chats` dengan `provider_type = meta` tetap accessible
- [ ] REQ-5.2: Meta API send message functionality tidak broken
- [ ] REQ-5.3: Chat list menampilkan chat dari both providers seamlessly
- [ ] REQ-5.4: Contact dapat memiliki chat dari multiple providers
- [ ] REQ-5.5: Campaign distribution support both providers
- [ ] REQ-5.6: Migration script untuk add `provider_type` dengan default = 'meta'

**Business Rules:**
- Existing chats get `provider_type = 'meta'` via migration
- New WebJS chats get `provider_type = 'webjs'`
- Contact dapat chat ke multiple numbers (junction table `contact_sessions`)
- Provider selection priority: session-specific > primary > any active

---

### REQ-6: Group Chat Support (NEW)
**As a user, I want** to sync and manage WhatsApp group chats **so that** I can handle both individual and group conversations in one inbox.

**User Story:**
```
User connects WhatsApp via WhatsApp Web.js
  ↓
System fetches ALL chats (individual + groups) via client.getChats()
  ↓
System detects group chats via chat.isGroup property
  ↓
System creates WhatsAppGroup records dengan participant info
  ↓
User sees group chats di inbox dengan group icon indicator
  ↓
User can view/send messages in group chats
```

**Acceptance Criteria:**
- [ ] REQ-6.1: System fetch group chats via `client.getChats()` dengan detection `chat.isGroup === true`
- [ ] REQ-6.2: Group chats tampil di inbox dengan group icon indicator (visual differentiation)
- [ ] REQ-6.3: Group info accessible: name, description, participants count, creation date
- [ ] REQ-6.4: Messages in group show sender name/phone (not just group name)
- [ ] REQ-6.5: System store group metadata: `whatsapp_groups` table dengan `participants` JSON
- [ ] REQ-6.6: Group chat differentiated from private chat via `chats.chat_type` field
- [ ] REQ-6.7: Send message to group works dengan proper formatting
- [ ] REQ-6.8: Group participant list viewable di chat detail panel

**Business Rules:**
- Group chats identified by `chat.isGroup === true` dari WhatsApp Web.js
- `chats.chat_type = 'group'` untuk group chats
- `chats.contact_id = NULL` untuk group chats (use `group_id` instead)
- Group metadata stored in `whatsapp_groups` table:
  - `group_jid`: WhatsApp unique group identifier
  - `name`: Group name
  - `participants`: JSON array dengan [{phone, name, isAdmin, joinedAt}]
  - `owner_phone`: Group creator phone
  - `description`: Group description (optional)

**Technical Specifications:**

```javascript
// Node.js - Group Chat Detection & Sync
client.on('ready', async () => {
    const chats = await client.getChats();
    
    for (const chat of chats) {
        if (chat.isGroup) {
            // GROUP CHAT
            const groupData = {
                session_id: sessionId,
                workspace_id: workspaceId,
                group_jid: chat.id._serialized,
                name: chat.name,
                description: chat.description || null,
                owner_phone: chat.owner || null,
                participants: chat.participants.map(p => ({
                    phone: p.id.user,
                    name: p.pushname || p.id.user,
                    isAdmin: p.isAdmin,
                    joinedAt: p.t || null
                })),
                created_at: new Date(chat.timestamp * 1000)
            };
            
            await axios.post(`${LARAVEL_URL}/api/whatsapp/groups/sync`, groupData);
        } else {
            // PRIVATE CHAT (existing logic)
            // ...
        }
    }
});

// Message Receive - Detect if from group
client.on('message', async (msg) => {
    const chat = await msg.getChat();
    
    if (chat.isGroup) {
        const messageData = {
            group_jid: chat.id._serialized,
            sender_phone: msg.author || msg.from, // Participant who sent
            sender_name: msg._data.notifyName || null,
            message_body: msg.body,
            chat_type: 'group',
            // ... other fields
        };
    } else {
        // Private chat message
        // ...
    }
});
```

```php
// Laravel - Group Sync Endpoint
// app/Http/Controllers/API/WhatsAppGroupController.php
public function syncGroup(Request $request)
{
    $validated = $request->validate([
        'session_id' => 'required|exists:whatsapp_accounts,id',
        'workspace_id' => 'required|exists:workspaces,id',
        'group_jid' => 'required|string',
        'name' => 'required|string',
        'description' => 'nullable|string',
        'owner_phone' => 'nullable|string',
        'participants' => 'required|array',
    ]);
    
    $group = WhatsAppGroup::updateOrCreate(
        [
            'group_jid' => $validated['group_jid'],
            'workspace_id' => $validated['workspace_id'],
        ],
        [
            'whatsapp_account_id' => $validated['session_id'],
            'name' => $validated['name'],
            'description' => $validated['description'],
            'owner_phone' => $validated['owner_phone'],
            'participants' => $validated['participants'],
        ]
    );
    
    return response()->json(['success' => true, 'group_id' => $group->id]);
}
```

```php
// app/Models/WhatsAppGroup.php
class WhatsAppGroup extends Model
{
    protected $fillable = [
        'uuid', 'workspace_id', 'whatsapp_account_id', 
        'group_jid', 'name', 'description', 'owner_phone', 'participants'
    ];
    
    protected $casts = [
        'participants' => 'array', // JSON cast
    ];
    
    public function chats()
    {
        return $this->hasMany(Chat::class, 'group_id');
    }
    
    public function session()
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }
    
    public function getParticipantCountAttribute()
    {
        return count($this->participants);
    }
}
```

```vue
<!-- ChatTable.vue - Group Chat Display -->
<template>
  <div class="chat-list">
    <div v-for="chat in rows" :key="chat.id" class="chat-item">
      <!-- Group Indicator -->
      <div class="chat-icon">
        <icon-group v-if="chat.chat_type === 'group'" class="text-blue-500" />
        <icon-user v-else class="text-gray-500" />
      </div>
      
      <div class="chat-info">
        <h3 class="font-semibold">
          {{ chat.chat_type === 'group' ? chat.group_name : chat.contact_name }}
          <span v-if="chat.chat_type === 'group'" class="text-xs text-gray-500">
            ({{ chat.participants_count }} members)
          </span>
        </h3>
        
        <p class="text-sm text-gray-600">
          <!-- Show sender name for group messages -->
          <span v-if="chat.chat_type === 'group' && chat.last_sender_name" class="font-medium">
            {{ chat.last_sender_name }}:
          </span>
          {{ chat.last_message }}
        </p>
      </div>
    </div>
  </div>
</template>
```

**Database Schema Requirements:**
```sql
-- Migration 1: Add chat_type to chats
ALTER TABLE chats ADD COLUMN chat_type ENUM('private', 'group') DEFAULT 'private' AFTER type;
ALTER TABLE chats ADD COLUMN group_id BIGINT UNSIGNED NULL AFTER contact_id;
ALTER TABLE chats ADD INDEX idx_chat_type (workspace_id, chat_type, created_at);

-- Migration 2: Create whatsapp_groups table
CREATE TABLE whatsapp_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    whatsapp_account_id BIGINT UNSIGNED NOT NULL,
    group_jid VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    owner_phone VARCHAR(50) NULL,
    participants JSON NOT NULL,
    invite_code VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (whatsapp_account_id) REFERENCES whatsapp_accounts(id) ON DELETE CASCADE,
    INDEX idx_workspace_session (workspace_id, whatsapp_account_id)
);
```

**UI/UX Enhancements:**
- Group icon indicator (👥) next to group chat names
- Participant count badge: "(5 members)"
- Sender name prefix in last message preview: "John: Hello everyone"
- Group info panel accessible via click on group name
- Participant list dengan admin indicators
- Group description viewable
- Different color scheme for group chats (optional)

---

## 🔄 INTEGRATION REQUIREMENTS

### IR-1: Node.js Service Integration
**The system must** integrate dengan WhatsApp Web JS Node.js service untuk chat sync dan messaging.

**Acceptance Criteria:**
- [ ] Node.js service running dan accessible via `http://localhost:3000`
- [ ] Health check endpoint `/health` return status OK
- [ ] Chat sync API endpoint `/api/sessions/:id/chats` implemented
- [ ] Message send endpoint `/api/messages/send` dengan response < 2s
- [ ] Webhook callback ke Laravel untuk events (authenticated, ready, message, disconnected)

---

### IR-2: Real-Time Broadcasting
**All real-time features must** work dengan Laravel Reverb (default) atau Pusher.

**Acceptance Criteria:**
- [ ] Events: `NewChatEvent`, `SessionStatusChangedEvent`, `QRCodeGeneratedEvent`
- [ ] Channel: `workspace.{id}` dengan proper authorization
- [ ] Frontend Laravel Echo subscribed ke channels
- [ ] Broadcast driver configurable via Admin Settings
- [ ] Real-time update < 2 seconds dari event trigger ke UI update

---

### IR-3: Database Consistency
**All data must** remain consistent across providers dan features.

**Acceptance Criteria:**
- [ ] Migration untuk add `chats.provider_type` column
- [ ] Migration untuk add `contacts.source_session_id` dan `source_type`
- [ ] Index optimization untuk query by `provider_type` dan `whatsapp_account_id`
- [ ] Foreign key constraints maintained
- [ ] Zero data loss during migration

---

## 📊 PERFORMANCE REQUIREMENTS

### PR-1: Initial Sync Performance
- **Sync Speed:** 500 chats dalam < 60 seconds (average 8-10 chats/second)
- **Batch Size:** 20 chats per API request untuk avoid timeout
- **Memory Usage:** < 200MB increase during sync
- **Timeout:** Max 5 minutes untuk complete sync, continue in background jika timeout

### PR-2: Real-Time Message Latency
- **Receive Latency:** < 2 seconds dari WhatsApp receive ke UI display
- **Send Latency:** < 2 seconds dari user click Send ke message delivered
- **Broadcasting Latency:** < 500ms dari Laravel event broadcast ke frontend receive

### PR-3: Chat List Query Performance
- **Load Time:** < 500ms untuk load 50 contacts dengan latest message
- **Filter Performance:** < 300ms untuk filter by session_id
- **Pagination:** Support infinite scroll dengan 50 items per page

---

## 🔒 SECURITY REQUIREMENTS

### SR-1: Workspace Isolation
- All queries MUST filter by `workspace_id` untuk prevent cross-workspace access
- Session validation untuk ensure user owns the session
- Chat access control based on workspace membership

### SR-2: Data Protection
- Session data encrypted at rest (AES-256)
- API communication menggunakan HTTPS
- HMAC authentication untuk Node.js <-> Laravel communication

---

## 🛠️ OPERATIONAL REQUIREMENTS

### OR-1: Service Availability
- Node.js service auto-restart on crash (PM2)
- Health monitoring dengan alerting
- Graceful degradation jika Node.js service down (fallback to Meta API)

### OR-2: Monitoring & Logging
- Log all chat sync activities
- Track sync success/failure rates
- Monitor message delivery rates per provider
- Alert on sync failures atau high error rates

---

## 🧪 TESTING REQUIREMENTS

### TR-1: Functional Testing
- [ ] End-to-end chat sync flow tested dengan real WhatsApp number
- [ ] Provider dualisme tested (Meta API + WebJS concurrent)
- [ ] Multi-number filtering tested dengan multiple sessions
- [ ] Real-time message broadcasting tested

### TR-2: Performance Testing
- [ ] Load test dengan 500 chats sync
- [ ] Stress test dengan 100+ concurrent messages
- [ ] Memory leak testing untuk long-running sessions

---

## 📈 SUCCESS METRICS

### Business Metrics
- **User Adoption:** 80% users connect via WhatsApp Web JS dalam 30 hari
- **Customer Satisfaction:** > 4.5/5 rating untuk chat experience
- **Response Time:** Average reply time < 5 minutes

### Technical Metrics
- **Sync Success Rate:** > 95% successful initial sync
- **Message Delivery Rate:** > 98% messages delivered
- **Real-Time Latency:** < 2 seconds average
- **Zero Regression:** 0 broken existing features

---

## 🎯 ACCEPTANCE CRITERIA SUMMARY

### Must Have (Launch Blockers):
- [ ] Initial chat sync works reliably (500 chats / 30 days)
- [ ] Real-time incoming messages displayed correctly
- [ ] Send message via correct provider (WebJS vs Meta)
- [ ] Multi-number filter functional
- [ ] Backward compatibility dengan Meta API maintained

### Should Have (Important for UX):
- [ ] Sync progress indicator
- [ ] Browser notifications untuk new messages
- [ ] Fallback to primary number jika session disconnected
- [ ] Clear error messages dengan actionable instructions

### Could Have (Enhancement):
- [ ] Advanced sync configuration (custom window)
- [ ] Media download on-demand
- [ ] Chat export functionality
- [ ] Analytics per WhatsApp number

---

**Document Status:** REQUIREMENTS COMPLETE  
**Total Requirements:** 5 User Requirements + 3 Integration + 3 Performance + 2 Security + 2 Operational  
**Testability:** All requirements have clear acceptance criteria  
**Ready for Design:** ✅ YES  
**Critical Gaps Identified:** 2 (provider_type column, Node.js chat sync implementation)

**References:** 
- docs/chat-whatsappwebjs-integration/assumption.md (ASM-1, ASM-2, ASM-6, ASM-7)
- docs/chat-whatsappwebjs-integration/design.md (DES-1, DES-2, DES-3) ⏳ TO BE CREATED
- docs/chat-whatsappwebjs-integration/tasks.md (TASK-1, TASK-2, TASK-3) ⏳ TO BE CREATED
