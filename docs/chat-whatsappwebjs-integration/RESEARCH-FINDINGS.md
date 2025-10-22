# RESEARCH FINDINGS - WhatsApp Web.js Capabilities

**Date:** 22 Oktober 2025  
**Research Question:** Apakah kita bisa mendapatkan lebih dari 500 chats / 30 days? Dan apakah support group chats?

---

## 🔍 FINDINGS SUMMARY

### 1. ✅ **TIDAK ADA LIMIT untuk Chat Sync**

**Evidence dari WhatsApp Web.js Documentation:**

```javascript
// Method: client.getChats()
// Returns: Promise<Array<Chat>>
// Description: Get all current chat instances

const chats = await client.getChats();
// Returns ALL chats - no limit parameter, no pagination
```

**Source:** https://docs.wwebjs.dev/Client.html#getChats

**Key Points:**
- ✅ `client.getChats()` returns **ALL** chats without limit
- ✅ No built-in pagination or limit parameter
- ✅ Dapat fetch semua chats yang ada di WhatsApp account
- ⚠️ Performance tergantung jumlah chats (bisa 1000+, 5000+, etc.)

---

### 2. ✅ **TIDAK ADA LIMIT untuk Message History per Chat**

**Evidence:**

```javascript
// Method: chat.fetchMessages(searchOptions)
// searchOptions: { limit: Number } - OPTIONAL parameter controlled by US

const messages = await chat.fetchMessages({
    limit: 100  // We can set ANY number here
});

// Examples from community:
// - limit: 50 (default in many implementations)
// - limit: 1000 (fetch last 1000 messages)
// - No limit specified = fetch all available messages
```

**Source:** https://docs.wwebjs.dev/Chat.html#fetchMessages

**Key Points:**
- ✅ `fetchMessages()` accepts `limit` as **OPTIONAL** parameter
- ✅ Kita yang control berapa banyak messages per chat
- ✅ Bisa fetch ALL messages jika tidak set limit (limited by WhatsApp server retention)
- ⚠️ WhatsApp Web itself has server-side retention limits (estimated ~1 year for most messages)

---

### 3. ✅ **FULL GROUP CHAT SUPPORT**

**Evidence dari Documentation:**

```javascript
// Class: GroupChat extends Chat
// Features:
class GroupChat {
    // Group Management
    addParticipants(participantIds, options)
    removeParticipants(participantIds)
    promoteParticipants(participantIds)  // Make admin
    demoteParticipants(participantIds)   // Remove admin
    
    // Group Info
    getInviteCode()
    setSubject(subject)          // Change group name
    setDescription(description)  // Change group description
    setPicture(media)            // Change group picture
    
    // Group Settings
    setMessagesAdminsOnly(adminsOnly)
    setInfoAdminsOnly(adminsOnly)
    
    // All Chat methods inherited:
    fetchMessages(searchOptions)
    sendMessage(content, options)
    // etc.
}
```

**Source:** https://docs.wwebjs.dev/GroupChat.html

**Features Supported:**
- ✅ Join groups by invite
- ✅ Get/revoke group invite codes
- ✅ Modify group info (subject, description, picture)
- ✅ Modify group settings (send permissions, edit permissions)
- ✅ Add/remove/promote/demote participants
- ✅ Mention users dalam group
- ✅ Get group participants list
- ✅ Leave group
- ✅ Group membership requests (approve/reject)

**Chat Type Detection:**
```javascript
// Differentiate between private chat and group
if (chat.isGroup) {
    // This is a GroupChat instance
    const participants = chat.participants;
    const owner = chat.owner;
    const description = chat.description;
} else {
    // This is a PrivateChat instance
}
```

---

## 📊 COMPARISON: Assumption vs Reality

| Aspect | Our Assumption (in docs) | WhatsApp Web.js Reality |
|--------|-------------------------|------------------------|
| **Chat Limit** | 500 chats max | ❌ NO LIMIT - fetch ALL chats |
| **Time Window** | 30 days history | ❌ NO LIMIT - determined by WhatsApp server retention |
| **Message Limit** | Configurable per chat | ✅ CORRECT - we control via `limit` parameter |
| **Group Support** | Not explicitly documented | ✅ FULL SUPPORT via GroupChat class |
| **Limit Source** | Library limitation | ❌ **ARTIFICIAL LIMIT** set by US for performance |

---

## 💡 IMPLICATIONS

### 1. **Config Changes Needed**

**Current Config (ARTIFICIAL LIMITS):**
```php
// config/whatsapp.php
'sync' => [
    'initial_window_days' => 30,        // OUR limit, not library limit
    'max_chats_per_sync' => 500,        // OUR limit, not library limit
    'batch_size' => 50,                 // For performance
    'rate_limit_per_second' => 10,      // For WhatsApp rate limiting
],
```

**Proposed Changes:**
```php
'sync' => [
    // Option 1: Unlimited (fetch ALL)
    'initial_window_days' => null,      // null = fetch all available
    'max_chats_per_sync' => null,       // null = fetch all chats
    
    // Option 2: Configurable with higher limits
    'initial_window_days' => env('WHATSAPP_SYNC_WINDOW_DAYS', 90),  // 90 days
    'max_chats_per_sync' => env('WHATSAPP_SYNC_MAX_CHATS', 2000),   // 2000 chats
    
    // Performance tuning (keep these)
    'batch_size' => 50,
    'rate_limit_per_second' => 10,
    'messages_per_chat' => 50,          // NEW: limit messages per chat initially
],
```

---

### 2. **Group Chat Requirements Update Needed**

**Requirements.md needs to add:**

```markdown
### REQ-6: Group Chat Support (NEW)

**As a** workspace admin  
**I want** to sync and manage group chats via WhatsApp Web.js  
**So that** I can handle both individual and group conversations in the same inbox

**Acceptance Criteria:**
- REQ-6.1: System can fetch group chats via `client.getChats()` dengan `chat.isGroup === true`
- REQ-6.2: Group chats ditampilkan di inbox dengan indicator (icon group)
- REQ-6.3: Group info (name, participants, description) tersedia di chat detail
- REQ-6.4: Messages di group chat include sender info (participant name)
- REQ-6.5: System can differentiate group messages dari private messages
```

---

### 3. **Database Schema Enhancement**

**Current Schema:**
```sql
chats:
- id
- contact_id  // For private chats only
- type        // 'inbound'|'outbound'
```

**Proposed Enhancement:**
```sql
chats:
- id
- contact_id       // NULL for group chats
- group_id         // NEW: foreign key to whatsapp_groups table
- chat_type        // NEW: 'private'|'group'  
- type             // 'inbound'|'outbound'

whatsapp_groups:  // NEW TABLE
- id
- uuid
- workspace_id
- whatsapp_session_id
- group_jid        // WhatsApp group identifier
- name             // Group name
- description
- owner_phone      // Group creator
- participants     // JSON: [{phone, isAdmin, joinedAt}]
- created_at
- updated_at
```

---

## 🎯 RECOMMENDED ACTIONS

### IMMEDIATE (Update Documentation):

1. ✅ Update `assumption.md`:
   - Remove assumption tentang "500 chats limit" sebagai library limitation
   - Clarify bahwa limit adalah **PERFORMANCE DECISION**, bukan technical limitation
   - Add assumption tentang group chat support (VERIFIED ✅)

2. ✅ Update `requirements.md`:
   - Add REQ-6: Group Chat Support
   - Update REQ-1 tentang sync limits (make it clear it's configurable)
   - Add technical constraints tentang group chat handling

3. ✅ Create migration plan:
   - `chats.chat_type` column
   - `whatsapp_groups` table
   - `group_participants` table (optional, bisa use JSON in groups table)

### SHORT-TERM (Design Phase):

4. Design group chat UI:
   - Group indicator icon
   - Participant list display
   - Group info panel
   - Sender name in group messages

5. Design sync strategy:
   - Should we sync ALL chats by default?
   - Pagination strategy for initial load
   - Incremental sync approach

### MEDIUM-TERM (Implementation):

6. Implement group chat sync
7. Implement group chat UI
8. Add config untuk flexible limits
9. Performance testing dengan large datasets (1000+ chats, 10+ groups)

---

## 📚 REFERENCES

1. **WhatsApp Web.js Documentation:**
   - Client API: https://docs.wwebjs.dev/Client.html
   - Chat API: https://docs.wwebjs.dev/Chat.html
   - GroupChat API: https://docs.wwebjs.dev/GroupChat.html

2. **GitHub Repository:**
   - https://github.com/pedroslopez/whatsapp-web.js
   - Features list confirms group support
   
3. **Guide:**
   - https://guide.wwebjs.dev/

---

**Conclusion:**  
✅ Kita **BISA** mendapatkan lebih dari 500 chats / 30 days  
✅ Kita **BISA** sync group chats dengan full features  
✅ Limit 500/30 days adalah **KITA yang set** untuk performance, **BUKAN** limitasi library  
✅ Group chat **FULLY SUPPORTED** dengan complete API

**Next Action:** Update assumption.md dan requirements.md untuk reflect findings ini.
