# Implementasi Group Profile - WhatsApp Group Chat Enhancement

## Executive Summary

Dokumen ini menganalisis fitur-fitur Group Chat yang tersedia di WhatsApp Web.js dan merancang implementasi lengkap untuk panel detail group yang menyerupai WhatsApp Web official.

**Status**: 📋 Planning & Design  
**Tanggal**: 19 November 2025  
**Priority**: High - UX Enhancement

---

## 1. Analisis Fitur WhatsApp Web.js untuk Group Chat

### 1.1 Properties yang Tersedia (Read-Only)

| Property | Type | Deskripsi | Status Implementasi |
|----------|------|-----------|-------------------|
| `name` | string | Nama group | ✅ Implemented |
| `description` | string | Deskripsi group | ⚠️ Stored but not displayed |
| `owner` | ContactId | Owner/creator group | ❌ Not implemented |
| `participants` | Array<GroupParticipant> | Daftar anggota dengan role | ⚠️ Stored but limited display |
| `createdAt` | Date | Tanggal pembuatan group | ❌ Not implemented |
| `isGroup` | boolean | Flag group chat | ✅ Implemented |
| `pinned` | boolean | Status pinned | ✅ Inherited from Chat |
| `isMuted` | boolean | Status mute | ✅ Inherited from Chat |
| `muteExpiration` | number | Unix timestamp mute expiry | ✅ Inherited from Chat |
| `archived` | boolean | Status archived | ✅ Inherited from Chat |
| `isReadOnly` | boolean | Group readonly status | ❌ Not implemented |

### 1.2 GroupParticipant Structure

```typescript
type GroupParticipant = {
    id: ContactId,           // Phone number participant
    isAdmin: boolean,        // Admin biasa
    isSuperAdmin: boolean    // Super admin (creator)
}
```

### 1.3 Methods yang Tersedia

#### A. Group Information (Read-Only)
| Method | Return | Support | Implementasi |
|--------|--------|---------|-------------|
| `getInviteCode()` | string | ✅ | Bisa implementasi "Share Link" |
| `getGroupMembershipRequests()` | Array | ✅ | Untuk pending requests |
| `getPinnedMessages()` | Array | ✅ | Pesan yang di-pin di group |

#### B. Group Settings Management
| Method | Permission Required | Support | Implementasi |
|--------|-------------------|---------|-------------|
| `setSubject(subject)` | Admin | ✅ | Edit nama group |
| `setDescription(description)` | Admin | ✅ | Edit deskripsi |
| `setPicture(media)` | Admin | ✅ | Upload foto group |
| `deletePicture()` | Admin | ✅ | Hapus foto group |

#### C. Group Privacy Settings
| Method | Permission | Support | Use Case |
|--------|-----------|---------|----------|
| `setMessagesAdminsOnly(bool)` | Super Admin | ✅ | Only admins can send messages |
| `setInfoAdminsOnly(bool)` | Admin | ✅ | Only admins can edit info |
| `setAddMembersAdminsOnly(bool)` | Admin | ✅ | Only admins can add members |

#### D. Participant Management
| Method | Permission | Support | Use Case |
|--------|-----------|---------|----------|
| `addParticipants(ids, options)` | Admin | ✅ | Add members dengan invite v4 |
| `removeParticipants(ids)` | Admin | ✅ | Remove/kick members |
| `promoteParticipants(ids)` | Super Admin | ✅ | Jadikan admin |
| `demoteParticipants(ids)` | Super Admin | ✅ | Hapus dari admin |
| `approveGroupMembershipRequests()` | Admin | ✅ | Approve join requests |
| `rejectGroupMembershipRequests()` | Admin | ✅ | Reject join requests |

#### E. General Chat Actions
| Method | Support | Notes |
|--------|---------|-------|
| `leave()` | ✅ | Exit dari group |
| `archive()` | ✅ | Archive chat |
| `mute(date)` | ✅ | Mute notifications |
| `pin()` | ✅ | Pin chat |

---

## 2. Perbandingan dengan WhatsApp Web Official

### 2.1 Fitur WhatsApp Web Official yang BISA Diimplementasi

✅ **Group Info Section**
- Foto group (view, upload, delete)
- Nama group (view, edit)
- Deskripsi group (view, edit)
- Created by & date
- Total participants dengan list

✅ **Participants Management**
- List semua members dengan role badge (Admin/Member)
- Add participants
- Remove participants
- Promote to admin
- Demote from admin
- View contact details untuk setiap member

✅ **Group Settings**
- Edit group info (admin only)
- Send messages (everyone/admins only)
- Add members (everyone/admins only)
- Mute notifications
- Custom notifications
- Pin chat
- Archive chat

✅ **Additional Features**
- Share group invite link
- Approve/reject join requests (jika ada)
- Exit group
- Report group

### 2.2 Fitur WhatsApp Web Official yang TIDAK Tersedia di API

❌ **Tidak Bisa Diimplementasi**
- View media, links, docs yang dishare di group
- Search messages dalam group
- Disappearing messages settings
- Group call
- Community linking
- Business group features

---

## 3. Database Schema Enhancement

### 3.1 Current Schema

```sql
-- Tabel contacts (sudah ada)
contacts (
    id INT PRIMARY KEY,
    workspace_id INT,
    phone VARCHAR,
    first_name VARCHAR,
    type ENUM('individual', 'group') DEFAULT 'individual',
    group_metadata JSON NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)
```

### 3.2 Enhanced group_metadata Structure

```json
{
    "group_id": "120363404793044052@g.us",
    "description": "Group description text",
    "owner": "62816108641@c.us",
    "created_at": "2024-11-15T10:30:00Z",
    "participants": [
        {
            "id": "62816108641@c.us",
            "isAdmin": true,
            "isSuperAdmin": true,
            "joined_at": "2024-11-15T10:30:00Z"
        },
        {
            "id": "62811801641@c.us",
            "isAdmin": false,
            "isSuperAdmin": false,
            "joined_at": "2024-11-15T11:00:00Z"
        }
    ],
    "settings": {
        "messagesAdminsOnly": false,
        "infoAdminsOnly": false,
        "addMembersAdminsOnly": true
    },
    "invite_code": "xxxxxxxxxxxxxx",
    "last_updated": "2024-11-19T15:00:00Z"
}
```

---

## 4. Backend Implementation Plan

### 4.1 New API Endpoints

```php
// app/Http/Controllers/Api/v1/GroupController.php

/**
 * Get detailed group information
 * GET /api/groups/{uuid}
 */
public function show(string $uuid): JsonResponse
{
    $contact = Contact::where('uuid', $uuid)
        ->where('type', 'group')
        ->with('workspace')
        ->firstOrFail();
    
    // Fetch latest data from WhatsApp
    $groupData = $this->whatsappService->getGroupInfo($contact->phone);
    
    // Update local database
    $contact->update([
        'group_metadata' => $groupData
    ]);
    
    return response()->json([
        'group' => $contact,
        'participants' => $this->formatParticipants($groupData['participants']),
        'settings' => $groupData['settings'],
    ]);
}

/**
 * Update group subject
 * PUT /api/groups/{uuid}/subject
 */
public function updateSubject(string $uuid, Request $request): JsonResponse
{
    $validated = $request->validate([
        'subject' => 'required|string|max:100'
    ]);
    
    $contact = Contact::where('uuid', $uuid)
        ->where('type', 'group')
        ->firstOrFail();
    
    $result = $this->whatsappService->setGroupSubject(
        $contact->phone,
        $validated['subject']
    );
    
    if ($result['success']) {
        $contact->update([
            'first_name' => $validated['subject']
        ]);
    }
    
    return response()->json($result);
}

/**
 * Update group description
 * PUT /api/groups/{uuid}/description
 */
public function updateDescription(string $uuid, Request $request): JsonResponse
{
    // Similar to updateSubject
}

/**
 * Update group picture
 * POST /api/groups/{uuid}/picture
 */
public function updatePicture(string $uuid, Request $request): JsonResponse
{
    $validated = $request->validate([
        'image' => 'required|image|max:5120' // 5MB
    ]);
    
    $contact = Contact::where('uuid', $uuid)
        ->where('type', 'group')
        ->firstOrFail();
    
    $imagePath = $request->file('image')->store('group-pictures', 'public');
    $base64Image = base64_encode(file_get_contents(storage_path('app/public/' . $imagePath)));
    
    $result = $this->whatsappService->setGroupPicture(
        $contact->phone,
        $base64Image
    );
    
    return response()->json($result);
}

/**
 * Add participants to group
 * POST /api/groups/{uuid}/participants
 */
public function addParticipants(string $uuid, Request $request): JsonResponse
{
    $validated = $request->validate([
        'phone_numbers' => 'required|array',
        'phone_numbers.*' => 'required|string'
    ]);
    
    $contact = Contact::where('uuid', $uuid)
        ->where('type', 'group')
        ->firstOrFail();
    
    $result = $this->whatsappService->addGroupParticipants(
        $contact->phone,
        $validated['phone_numbers']
    );
    
    return response()->json($result);
}

/**
 * Remove participant from group
 * DELETE /api/groups/{uuid}/participants/{phone}
 */
public function removeParticipant(string $uuid, string $phone): JsonResponse
{
    // Implementation
}

/**
 * Promote participant to admin
 * POST /api/groups/{uuid}/participants/{phone}/promote
 */
public function promoteParticipant(string $uuid, string $phone): JsonResponse
{
    // Implementation
}

/**
 * Demote participant from admin
 * POST /api/groups/{uuid}/participants/{phone}/demote
 */
public function demoteParticipant(string $uuid, string $phone): JsonResponse
{
    // Implementation
}

/**
 * Update group settings
 * PUT /api/groups/{uuid}/settings
 */
public function updateSettings(string $uuid, Request $request): JsonResponse
{
    $validated = $request->validate([
        'messages_admins_only' => 'sometimes|boolean',
        'info_admins_only' => 'sometimes|boolean',
        'add_members_admins_only' => 'sometimes|boolean',
    ]);
    
    $contact = Contact::where('uuid', $uuid)
        ->where('type', 'group')
        ->firstOrFail();
    
    $results = [];
    
    if (isset($validated['messages_admins_only'])) {
        $results['messages'] = $this->whatsappService->setMessagesAdminsOnly(
            $contact->phone,
            $validated['messages_admins_only']
        );
    }
    
    // Similar for other settings...
    
    return response()->json($results);
}

/**
 * Get group invite link
 * GET /api/groups/{uuid}/invite-link
 */
public function getInviteLink(string $uuid): JsonResponse
{
    $contact = Contact::where('uuid', $uuid)
        ->where('type', 'group')
        ->firstOrFail();
    
    $inviteCode = $this->whatsappService->getGroupInviteCode($contact->phone);
    
    return response()->json([
        'invite_link' => "https://chat.whatsapp.com/{$inviteCode}",
        'invite_code' => $inviteCode
    ]);
}

/**
 * Leave group
 * POST /api/groups/{uuid}/leave
 */
public function leave(string $uuid): JsonResponse
{
    $contact = Contact::where('uuid', $uuid)
        ->where('type', 'group')
        ->firstOrFail();
    
    $result = $this->whatsappService->leaveGroup($contact->phone);
    
    if ($result['success']) {
        // Soft delete atau mark as left
        $contact->delete();
    }
    
    return response()->json($result);
}
```

### 4.2 WhatsApp Service Enhancement

```php
// app/Services/WhatsApp/WhatsAppServiceClient.php

public function getGroupInfo(string $groupId): array
{
    $response = $this->client->post('/groups/info', [
        'groupId' => $groupId
    ]);
    
    return $response->json();
}

public function setGroupSubject(string $groupId, string $subject): array
{
    $response = $this->client->post('/groups/set-subject', [
        'groupId' => $groupId,
        'subject' => $subject
    ]);
    
    return $response->json();
}

public function setGroupDescription(string $groupId, string $description): array
{
    $response = $this->client->post('/groups/set-description', [
        'groupId' => $groupId,
        'description' => $description
    ]);
    
    return $response->json();
}

public function setGroupPicture(string $groupId, string $base64Image): array
{
    $response = $this->client->post('/groups/set-picture', [
        'groupId' => $groupId,
        'image' => $base64Image
    ]);
    
    return $response->json();
}

public function deleteGroupPicture(string $groupId): array
{
    $response = $this->client->post('/groups/delete-picture', [
        'groupId' => $groupId
    ]);
    
    return $response->json();
}

public function addGroupParticipants(string $groupId, array $phoneNumbers): array
{
    $response = $this->client->post('/groups/add-participants', [
        'groupId' => $groupId,
        'participants' => $phoneNumbers
    ]);
    
    return $response->json();
}

public function removeGroupParticipants(string $groupId, array $phoneNumbers): array
{
    $response = $this->client->post('/groups/remove-participants', [
        'groupId' => $groupId,
        'participants' => $phoneNumbers
    ]);
    
    return $response->json();
}

public function promoteGroupParticipants(string $groupId, array $phoneNumbers): array
{
    $response = $this->client->post('/groups/promote-participants', [
        'groupId' => $groupId,
        'participants' => $phoneNumbers
    ]);
    
    return $response->json();
}

public function demoteGroupParticipants(string $groupId, array $phoneNumbers): array
{
    $response = $this->client->post('/groups/demote-participants', [
        'groupId' => $groupId,
        'participants' => $phoneNumbers
    ]);
    
    return $response->json();
}

public function setMessagesAdminsOnly(string $groupId, bool $enabled): array
{
    $response = $this->client->post('/groups/set-messages-admins-only', [
        'groupId' => $groupId,
        'enabled' => $enabled
    ]);
    
    return $response->json();
}

public function setInfoAdminsOnly(string $groupId, bool $enabled): array
{
    $response = $this->client->post('/groups/set-info-admins-only', [
        'groupId' => $groupId,
        'enabled' => $enabled
    ]);
    
    return $response->json();
}

public function setAddMembersAdminsOnly(string $groupId, bool $enabled): array
{
    $response = $this->client->post('/groups/set-add-members-admins-only', [
        'groupId' => $groupId,
        'enabled' => $enabled
    ]);
    
    return $response->json();
}

public function getGroupInviteCode(string $groupId): string
{
    $response = $this->client->post('/groups/invite-code', [
        'groupId' => $groupId
    ]);
    
    return $response->json()['inviteCode'];
}

public function leaveGroup(string $groupId): array
{
    $response = $this->client->post('/groups/leave', [
        'groupId' => $groupId
    ]);
    
    return $response->json();
}
```

### 4.3 Node.js Service Routes

```javascript
// whatsapp-service/src/routes/groups.js

router.post('/groups/info', async (req, res) => {
    const { sessionId, groupId } = req.body;
    
    try {
        const client = sessionManager.getSession(sessionId);
        const chat = await client.getChatById(groupId);
        
        if (!chat.isGroup) {
            return res.status(400).json({ error: 'Not a group chat' });
        }
        
        const groupData = {
            id: chat.id._serialized,
            name: chat.name,
            description: chat.description,
            owner: chat.owner._serialized,
            createdAt: chat.createdAt,
            participants: chat.participants.map(p => ({
                id: p.id._serialized,
                isAdmin: p.isAdmin,
                isSuperAdmin: p.isSuperAdmin
            })),
            settings: {
                messagesAdminsOnly: chat.groupMetadata.announce,
                infoAdminsOnly: chat.groupMetadata.restrict,
                addMembersAdminsOnly: chat.groupMetadata.memberAddMode === 'admin_add'
            }
        };
        
        res.json(groupData);
    } catch (error) {
        logger.error('Error getting group info:', error);
        res.status(500).json({ error: error.message });
    }
});

router.post('/groups/set-subject', async (req, res) => {
    const { sessionId, groupId, subject } = req.body;
    
    try {
        const client = sessionManager.getSession(sessionId);
        const chat = await client.getChatById(groupId);
        
        const success = await chat.setSubject(subject);
        
        res.json({ success, message: success ? 'Subject updated' : 'Failed to update subject' });
    } catch (error) {
        logger.error('Error setting group subject:', error);
        res.status(500).json({ error: error.message });
    }
});

router.post('/groups/set-description', async (req, res) => {
    const { sessionId, groupId, description } = req.body;
    
    try {
        const client = sessionManager.getSession(sessionId);
        const chat = await client.getChatById(groupId);
        
        const success = await chat.setDescription(description);
        
        res.json({ success, message: success ? 'Description updated' : 'Failed to update description' });
    } catch (error) {
        logger.error('Error setting group description:', error);
        res.status(500).json({ error: error.message });
    }
});

router.post('/groups/set-picture', async (req, res) => {
    const { sessionId, groupId, image } = req.body;
    
    try {
        const client = sessionManager.getSession(sessionId);
        const chat = await client.getChatById(groupId);
        
        const media = new MessageMedia('image/jpeg', image);
        const success = await chat.setPicture(media);
        
        res.json({ success, message: success ? 'Picture updated' : 'Failed to update picture' });
    } catch (error) {
        logger.error('Error setting group picture:', error);
        res.status(500).json({ error: error.message });
    }
});

router.post('/groups/delete-picture', async (req, res) => {
    const { sessionId, groupId } = req.body;
    
    try {
        const client = sessionManager.getSession(sessionId);
        const chat = await client.getChatById(groupId);
        
        const success = await chat.deletePicture();
        
        res.json({ success, message: success ? 'Picture deleted' : 'Failed to delete picture' });
    } catch (error) {
        logger.error('Error deleting group picture:', error);
        res.status(500).json({ error: error.message });
    }
});

router.post('/groups/add-participants', async (req, res) => {
    const { sessionId, groupId, participants } = req.body;
    
    try {
        const client = sessionManager.getSession(sessionId);
        const chat = await client.getChatById(groupId);
        
        const result = await chat.addParticipants(participants, {
            autoSendInviteV4: true,
            comment: ''
        });
        
        res.json({ success: true, result });
    } catch (error) {
        logger.error('Error adding participants:', error);
        res.status(500).json({ error: error.message });
    }
});

router.post('/groups/remove-participants', async (req, res) => {
    const { sessionId, groupId, participants } = req.body;
    
    try {
        const client = sessionManager.getSession(sessionId);
        const chat = await client.getChatById(groupId);
        
        const result = await chat.removeParticipants(participants);
        
        res.json({ success: result.status === 200, result });
    } catch (error) {
        logger.error('Error removing participants:', error);
        res.status(500).json({ error: error.message });
    }
});

router.post('/groups/promote-participants', async (req, res) => {
    const { sessionId, groupId, participants } = req.body;
    
    try {
        const client = sessionManager.getSession(sessionId);
        const chat = await client.getChatById(groupId);
        
        const result = await chat.promoteParticipants(participants);
        
        res.json({ success: result.status === 200, result });
    } catch (error) {
        logger.error('Error promoting participants:', error);
        res.status(500).json({ error: error.message });
    }
});

router.post('/groups/demote-participants', async (req, res) => {
    const { sessionId, groupId, participants } = req.body;
    
    try {
        const client = sessionManager.getSession(sessionId);
        const chat = await client.getChatById(groupId);
        
        const result = await chat.demoteParticipants(participants);
        
        res.json({ success: result.status === 200, result });
    } catch (error) {
        logger.error('Error demoting participants:', error);
        res.status(500).json({ error: error.message });
    }
});

router.post('/groups/set-messages-admins-only', async (req, res) => {
    const { sessionId, groupId, enabled } = req.body;
    
    try {
        const client = sessionManager.getSession(sessionId);
        const chat = await client.getChatById(groupId);
        
        const success = await chat.setMessagesAdminsOnly(enabled);
        
        res.json({ success });
    } catch (error) {
        logger.error('Error setting messages admins only:', error);
        res.status(500).json({ error: error.message });
    }
});

router.post('/groups/set-info-admins-only', async (req, res) => {
    const { sessionId, groupId, enabled } = req.body;
    
    try {
        const client = sessionManager.getSession(sessionId);
        const chat = await client.getChatById(groupId);
        
        const success = await chat.setInfoAdminsOnly(enabled);
        
        res.json({ success });
    } catch (error) {
        logger.error('Error setting info admins only:', error);
        res.status(500).json({ error: error.message });
    }
});

router.post('/groups/set-add-members-admins-only', async (req, res) => {
    const { sessionId, groupId, enabled } = req.body;
    
    try {
        const client = sessionManager.getSession(sessionId);
        const chat = await client.getChatById(groupId);
        
        const success = await chat.setAddMembersAdminsOnly(enabled);
        
        res.json({ success });
    } catch (error) {
        logger.error('Error setting add members admins only:', error);
        res.status(500).json({ error: error.message });
    }
});

router.post('/groups/invite-code', async (req, res) => {
    const { sessionId, groupId } = req.body;
    
    try {
        const client = sessionManager.getSession(sessionId);
        const chat = await client.getChatById(groupId);
        
        const inviteCode = await chat.getInviteCode();
        
        res.json({ inviteCode });
    } catch (error) {
        logger.error('Error getting invite code:', error);
        res.status(500).json({ error: error.message });
    }
});

router.post('/groups/leave', async (req, res) => {
    const { sessionId, groupId } = req.body;
    
    try {
        const client = sessionManager.getSession(sessionId);
        const chat = await client.getChatById(groupId);
        
        await chat.leave();
        
        res.json({ success: true, message: 'Left group successfully' });
    } catch (error) {
        logger.error('Error leaving group:', error);
        res.status(500).json({ error: error.message });
    }
});

module.exports = router;
```

---

## 5. Frontend Implementation

### 5.1 New Component: GroupProfile.vue

```vue
<!-- resources/js/Components/ChatComponents/GroupProfile.vue -->
<script setup>
import { ref, computed, watchEffect } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextArea from '@/Components/FormTextArea.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownItem from '@/Components/DropdownItem.vue';
import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
    contact: {
        type: Object,
        required: true
    }
});

const contact = ref(props.contact);
const groupData = ref(null);
const isLoading = ref(false);
const showEditNameModal = ref(false);
const showEditDescModal = ref(false);
const showAddMemberModal = ref(false);
const showSettingsModal = ref(false);
const showInviteLinkModal = ref(false);
const inviteLink = ref('');

// Parse group metadata
const metadata = computed(() => {
    if (!contact.value.group_metadata) return {};
    if (typeof contact.value.group_metadata === 'string') {
        return JSON.parse(contact.value.group_metadata);
    }
    return contact.value.group_metadata;
});

// Computed properties
const participants = computed(() => metadata.value.participants || []);
const owner = computed(() => metadata.value.owner || '');
const description = computed(() => metadata.value.description || trans('No description'));
const createdAt = computed(() => {
    if (!metadata.value.created_at) return '';
    return new Date(metadata.value.created_at).toLocaleDateString();
});

const settings = computed(() => metadata.value.settings || {
    messagesAdminsOnly: false,
    infoAdminsOnly: false,
    addMembersAdminsOnly: true
});

// Check if current user is admin
const currentUserPhone = computed(() => {
    // Get from auth or session
    return ''; // TODO: implement
});

const isAdmin = computed(() => {
    const participant = participants.value.find(p => p.id === currentUserPhone.value);
    return participant && (participant.isAdmin || participant.isSuperAdmin);
});

const isSuperAdmin = computed(() => {
    const participant = participants.value.find(p => p.id === currentUserPhone.value);
    return participant && participant.isSuperAdmin;
});

// Forms
const editNameForm = useForm({
    subject: contact.value.first_name
});

const editDescForm = useForm({
    description: description.value
});

const addMemberForm = useForm({
    phone_numbers: []
});

const settingsForm = useForm({
    messages_admins_only: settings.value.messagesAdminsOnly,
    info_admins_only: settings.value.infoAdminsOnly,
    add_members_admins_only: settings.value.addMembersAdminsOnly
});

watchEffect(() => {
    contact.value = props.contact;
});

// Methods
const updateGroupName = () => {
    isLoading.value = true;
    
    axios.put(`/api/groups/${contact.value.uuid}/subject`, {
        subject: editNameForm.subject
    })
    .then(response => {
        if (response.data.success) {
            contact.value.first_name = editNameForm.subject;
            showEditNameModal.value = false;
        }
    })
    .catch(error => {
        console.error('Error updating group name:', error);
    })
    .finally(() => {
        isLoading.value = false;
    });
};

const updateGroupDescription = () => {
    isLoading.value = true;
    
    axios.put(`/api/groups/${contact.value.uuid}/description`, {
        description: editDescForm.description
    })
    .then(response => {
        if (response.data.success) {
            // Update local metadata
            const meta = metadata.value;
            meta.description = editDescForm.description;
            contact.value.group_metadata = meta;
            showEditDescModal.value = false;
        }
    })
    .catch(error => {
        console.error('Error updating description:', error);
    })
    .finally(() => {
        isLoading.value = false;
    });
};

const uploadGroupPicture = (event) => {
    const file = event.target.files[0];
    if (!file) return;
    
    const formData = new FormData();
    formData.append('image', file);
    
    isLoading.value = true;
    
    axios.post(`/api/groups/${contact.value.uuid}/picture`, formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    })
    .then(response => {
        if (response.data.success) {
            // Reload contact data
            router.reload();
        }
    })
    .catch(error => {
        console.error('Error uploading picture:', error);
    })
    .finally(() => {
        isLoading.value = false;
    });
};

const removeParticipant = (phone) => {
    if (!confirm(trans('Are you sure you want to remove this member?'))) {
        return;
    }
    
    isLoading.value = true;
    
    axios.delete(`/api/groups/${contact.value.uuid}/participants/${phone}`)
    .then(response => {
        if (response.data.success) {
            // Remove from local participants
            const meta = metadata.value;
            meta.participants = meta.participants.filter(p => p.id !== phone);
            contact.value.group_metadata = meta;
        }
    })
    .catch(error => {
        console.error('Error removing participant:', error);
    })
    .finally(() => {
        isLoading.value = false;
    });
};

const promoteParticipant = (phone) => {
    isLoading.value = true;
    
    axios.post(`/api/groups/${contact.value.uuid}/participants/${phone}/promote`)
    .then(response => {
        if (response.data.success) {
            // Update local participant
            const meta = metadata.value;
            const participant = meta.participants.find(p => p.id === phone);
            if (participant) {
                participant.isAdmin = true;
            }
            contact.value.group_metadata = meta;
        }
    })
    .catch(error => {
        console.error('Error promoting participant:', error);
    })
    .finally(() => {
        isLoading.value = false;
    });
};

const demoteParticipant = (phone) => {
    isLoading.value = true;
    
    axios.post(`/api/groups/${contact.value.uuid}/participants/${phone}/demote`)
    .then(response => {
        if (response.data.success) {
            // Update local participant
            const meta = metadata.value;
            const participant = meta.participants.find(p => p.id === phone);
            if (participant) {
                participant.isAdmin = false;
            }
            contact.value.group_metadata = meta;
        }
    })
    .catch(error => {
        console.error('Error demoting participant:', error);
    })
    .finally(() => {
        isLoading.value = false;
    });
};

const getInviteLink = () => {
    isLoading.value = true;
    
    axios.get(`/api/groups/${contact.value.uuid}/invite-link`)
    .then(response => {
        inviteLink.value = response.data.invite_link;
        showInviteLinkModal.value = true;
    })
    .catch(error => {
        console.error('Error getting invite link:', error);
    })
    .finally(() => {
        isLoading.value = false;
    });
};

const copyInviteLink = () => {
    navigator.clipboard.writeText(inviteLink.value);
    // Show toast notification
};

const updateSettings = () => {
    isLoading.value = true;
    
    axios.put(`/api/groups/${contact.value.uuid}/settings`, {
        messages_admins_only: settingsForm.messages_admins_only,
        info_admins_only: settingsForm.info_admins_only,
        add_members_admins_only: settingsForm.add_members_admins_only
    })
    .then(response => {
        showSettingsModal.value = false;
        // Update local settings
        const meta = metadata.value;
        meta.settings = {
            messagesAdminsOnly: settingsForm.messages_admins_only,
            infoAdminsOnly: settingsForm.info_admins_only,
            addMembersAdminsOnly: settingsForm.add_members_admins_only
        };
        contact.value.group_metadata = meta;
    })
    .catch(error => {
        console.error('Error updating settings:', error);
    })
    .finally(() => {
        isLoading.value = false;
    });
};

const leaveGroup = () => {
    if (!confirm(trans('Are you sure you want to leave this group?'))) {
        return;
    }
    
    isLoading.value = true;
    
    axios.post(`/api/groups/${contact.value.uuid}/leave`)
    .then(response => {
        if (response.data.success) {
            router.visit('/chats');
        }
    })
    .catch(error => {
        console.error('Error leaving group:', error);
    })
    .finally(() => {
        isLoading.value = false;
    });
};

const getParticipantRoleBadge = (participant) => {
    if (participant.isSuperAdmin) return 'Group Admin';
    if (participant.isAdmin) return 'Admin';
    return '';
};
</script>

<template>
    <div class="overflow-y-auto h-screen w-full bg-gray-50">
        <!-- Group Header -->
        <div class="bg-white pb-6 border-b">
            <!-- Profile Picture -->
            <div class="flex justify-center w-full pt-6">
                <div class="relative group">
                    <div class="rounded-full p-1">
                        <img v-if="contact.avatar" 
                             class="rounded-full w-32 h-32 object-cover" 
                             :src="contact.avatar">
                        <div v-else class="rounded-full w-32 h-32 bg-gray-300 flex items-center justify-center">
                            <svg class="w-16 h-16 text-gray-500" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                        </div>
                    </div>
                    
                    <!-- Edit Picture Overlay (only for admins) -->
                    <div v-if="isAdmin" 
                         class="absolute inset-0 bg-black bg-opacity-50 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition cursor-pointer">
                        <label for="group-picture-upload" class="cursor-pointer text-white text-sm">
                            {{ $t('Change Photo') }}
                        </label>
                        <input id="group-picture-upload" 
                               type="file" 
                               accept="image/*" 
                               class="hidden" 
                               @change="uploadGroupPicture">
                    </div>
                </div>
            </div>
            
            <!-- Group Name -->
            <div class="mt-4 px-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold">{{ contact.first_name }}</h3>
                    <button v-if="isAdmin" 
                            @click="showEditNameModal = true" 
                            class="text-blue-600 hover:text-blue-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                    </button>
                </div>
                
                <!-- Group Info -->
                <div class="mt-2 text-sm text-gray-600">
                    <p>{{ $t('Group') }} · {{ participants.length }} {{ $t('members') }}</p>
                    <p v-if="createdAt" class="mt-1">
                        {{ $t('Created') }} {{ createdAt }}
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Group Description -->
        <div class="bg-white mt-2 py-4 px-6 border-b">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">{{ $t('Description') }}</h4>
                    <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ description }}</p>
                </div>
                <button v-if="isAdmin" 
                        @click="showEditDescModal = true" 
                        class="ml-4 text-blue-600 hover:text-blue-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Media, Links, Docs (Not implemented yet) -->
        <div class="bg-white mt-2 py-4 px-6 border-b">
            <div class="text-sm text-gray-500 text-center py-4">
                {{ $t('Media, links, and docs coming soon') }}
            </div>
        </div>
        
        <!-- Participants -->
        <div class="bg-white mt-2 py-4 border-b">
            <div class="px-6 mb-4 flex items-center justify-between">
                <h4 class="text-sm font-medium text-gray-700">
                    {{ participants.length }} {{ $t('Participants') }}
                </h4>
                <button v-if="isAdmin" 
                        @click="showAddMemberModal = true"
                        class="text-blue-600 hover:text-blue-700 text-sm">
                    {{ $t('Add participant') }}
                </button>
            </div>
            
            <div class="space-y-1">
                <div v-for="participant in participants" 
                     :key="participant.id" 
                     class="px-6 py-2 hover:bg-gray-50 flex items-center justify-between group">
                    <div class="flex items-center flex-1">
                        <div class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center">
                            <span class="text-sm font-medium text-gray-600">
                                {{ participant.name ? participant.name.charAt(0).toUpperCase() : participant.id.substring(0, 2) }}
                            </span>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium">
                                {{ participant.name || participant.id }}
                                <span v-if="participant.isSuperAdmin || participant.isAdmin" 
                                      class="ml-2 text-xs text-gray-500">
                                    {{ getParticipantRoleBadge(participant) }}
                                </span>
                            </p>
                            <p class="text-xs text-gray-500">{{ participant.id }}</p>
                        </div>
                    </div>
                    
                    <!-- Actions (only for admins) -->
                    <Dropdown v-if="isAdmin && participant.id !== owner" 
                              class="opacity-0 group-hover:opacity-100 transition" 
                              align="right">
                        <button class="p-2 hover:bg-gray-100 rounded-full">
                            <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                            </svg>
                        </button>
                        <template #items>
                            <DropdownItemGroup>
                                <DropdownItem v-if="!participant.isAdmin && isSuperAdmin" 
                                             as="button" 
                                             @click="promoteParticipant(participant.id)">
                                    {{ $t('Make group admin') }}
                                </DropdownItem>
                                <DropdownItem v-if="participant.isAdmin && isSuperAdmin" 
                                             as="button" 
                                             @click="demoteParticipant(participant.id)">
                                    {{ $t('Dismiss as admin') }}
                                </DropdownItem>
                                <DropdownItem as="button" 
                                             @click="removeParticipant(participant.id)"
                                             class="text-red-600">
                                    {{ $t('Remove from group') }}
                                </DropdownItem>
                            </DropdownItemGroup>
                        </template>
                    </Dropdown>
                </div>
            </div>
        </div>
        
        <!-- Group Settings (only for admins) -->
        <div v-if="isAdmin" class="bg-white mt-2 py-4 px-6 border-b">
            <button @click="showSettingsModal = true" 
                    class="w-full flex items-center justify-between py-2 hover:bg-gray-50 rounded">
                <span class="text-sm">{{ $t('Group settings') }}</span>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>
        
        <!-- Actions -->
        <div class="bg-white mt-2 py-4 px-6 space-y-2">
            <button @click="getInviteLink" 
                    class="w-full flex items-center py-2 hover:bg-gray-50 rounded text-left">
                <svg class="w-5 h-5 text-gray-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
                <span class="text-sm">{{ $t('Invite via link') }}</span>
            </button>
            
            <button @click="leaveGroup" 
                    class="w-full flex items-center py-2 hover:bg-gray-50 rounded text-left text-red-600">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                <span class="text-sm">{{ $t('Exit group') }}</span>
            </button>
        </div>
        
        <!-- Modals -->
        
        <!-- Edit Name Modal -->
        <Modal :label="$t('Change group subject')" :isOpen="showEditNameModal">
            <form @submit.prevent="updateGroupName" class="mt-4">
                <FormInput v-model="editNameForm.subject" 
                          :name="$t('Group name')" 
                          :error="editNameForm.errors.subject" 
                          type="text" 
                          class="mb-4"/>
                <div class="flex justify-end space-x-2">
                    <button type="button" 
                            @click="showEditNameModal = false" 
                            class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded">
                        {{ $t('Cancel') }}
                    </button>
                    <button type="submit" 
                            :disabled="isLoading"
                            class="px-4 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">
                        {{ $t('Save') }}
                    </button>
                </div>
            </form>
        </Modal>
        
        <!-- Edit Description Modal -->
        <Modal :label="$t('Change group description')" :isOpen="showEditDescModal">
            <form @submit.prevent="updateGroupDescription" class="mt-4">
                <FormTextArea v-model="editDescForm.description" 
                             :name="$t('Description')" 
                             :error="editDescForm.errors.description" 
                             class="mb-4"/>
                <div class="flex justify-end space-x-2">
                    <button type="button" 
                            @click="showEditDescModal = false" 
                            class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded">
                        {{ $t('Cancel') }}
                    </button>
                    <button type="submit" 
                            :disabled="isLoading"
                            class="px-4 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">
                        {{ $t('Save') }}
                    </button>
                </div>
            </form>
        </Modal>
        
        <!-- Settings Modal -->
        <Modal :label="$t('Group settings')" :isOpen="showSettingsModal">
            <form @submit.prevent="updateSettings" class="mt-4 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium">{{ $t('Send messages') }}</p>
                        <p class="text-xs text-gray-500">{{ $t('Who can send messages') }}</p>
                    </div>
                    <select v-model="settingsForm.messages_admins_only" 
                            class="text-sm border-gray-300 rounded">
                        <option :value="false">{{ $t('All participants') }}</option>
                        <option :value="true">{{ $t('Only admins') }}</option>
                    </select>
                </div>
                
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium">{{ $t('Edit group info') }}</p>
                        <p class="text-xs text-gray-500">{{ $t('Who can edit group info') }}</p>
                    </div>
                    <select v-model="settingsForm.info_admins_only" 
                            class="text-sm border-gray-300 rounded">
                        <option :value="false">{{ $t('All participants') }}</option>
                        <option :value="true">{{ $t('Only admins') }}</option>
                    </select>
                </div>
                
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium">{{ $t('Add members') }}</p>
                        <p class="text-xs text-gray-500">{{ $t('Who can add members') }}</p>
                    </div>
                    <select v-model="settingsForm.add_members_admins_only" 
                            class="text-sm border-gray-300 rounded">
                        <option :value="false">{{ $t('All participants') }}</option>
                        <option :value="true">{{ $t('Only admins') }}</option>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-2 pt-4">
                    <button type="button" 
                            @click="showSettingsModal = false" 
                            class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded">
                        {{ $t('Cancel') }}
                    </button>
                    <button type="submit" 
                            :disabled="isLoading"
                            class="px-4 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">
                        {{ $t('Save') }}
                    </button>
                </div>
            </form>
        </Modal>
        
        <!-- Invite Link Modal -->
        <Modal :label="$t('Invite to group via link')" :isOpen="showInviteLinkModal">
            <div class="mt-4">
                <p class="text-sm text-gray-600 mb-4">
                    {{ $t('Share this link with others to let them join your group') }}
                </p>
                <div class="flex items-center space-x-2">
                    <input type="text" 
                           :value="inviteLink" 
                           readonly 
                           class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded bg-gray-50">
                    <button @click="copyInviteLink" 
                            class="px-4 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700">
                        {{ $t('Copy') }}
                    </button>
                </div>
                <div class="flex justify-end mt-4">
                    <button @click="showInviteLinkModal = false" 
                            class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded">
                        {{ $t('Close') }}
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>
```

### 5.2 Update Index.vue

```vue
<!-- resources/js/Pages/User/Chat/Index.vue -->

<script setup>
// Import new component
import GroupProfile from '@/Components/ChatComponents/GroupProfile.vue';

// ...existing code...

// Add condition to show GroupProfile for group chats
const isGroupChat = computed(() => {
    return contact.value && contact.value.type === 'group';
});
</script>

<template>
    <!-- ...existing code... -->
    
    <!-- Right Panel - Contact/Group Info -->
    <div v-if="displayContact" class="md:w-[30%] bg-white border-l overflow-y-auto">
        <!-- Show GroupProfile for groups -->
        <GroupProfile v-if="isGroupChat" :contact="contact" />
        
        <!-- Show ChatContact for individual chats -->
        <ChatContact v-else 
                     :contact="contact" 
                     :fields="props.fields" 
                     :locationSettings="props.settings?.contact_form_field_location"/>
    </div>
</template>
```

---

## 6. Implementation Roadmap

### Phase 1: Basic Group Info (Week 1)
- ✅ Display group name, description, participants
- ✅ Show participant count and roles
- ✅ Basic group avatar display
- ✅ Group creation date

### Phase 2: Admin Features (Week 2)
- ⚠️ Edit group name and description
- ⚠️ Upload/delete group picture
- ⚠️ Add/remove participants
- ⚠️ Promote/demote admins

### Phase 3: Settings & Permissions (Week 3)
- ⚠️ Group settings panel
- ⚠️ Messages permission settings
- ⚠️ Info edit permission settings
- ⚠️ Add members permission settings

### Phase 4: Advanced Features (Week 4)
- ⚠️ Invite link generation & sharing
- ⚠️ Exit group functionality
- ⚠️ Membership requests handling
- ⚠️ Real-time updates for group changes

---

## 7. Testing Checklist

### Unit Tests
- [ ] Group metadata parsing
- [ ] Participant role detection
- [ ] Permission checks (isAdmin, isSuperAdmin)
- [ ] Settings form validation

### Integration Tests
- [ ] Update group name API
- [ ] Update group description API
- [ ] Add/remove participants API
- [ ] Promote/demote participants API
- [ ] Group settings update API
- [ ] Invite link generation API

### E2E Tests
- [ ] View group profile as member
- [ ] View group profile as admin
- [ ] Edit group info as admin
- [ ] Add participant to group
- [ ] Remove participant from group
- [ ] Promote participant to admin
- [ ] Change group settings
- [ ] Share invite link
- [ ] Leave group

---

## 8. Security Considerations

### Permission Checks
1. **Backend validation** untuk setiap aksi admin
2. **Frontend guards** untuk hide/show UI elements
3. **Database validation** untuk group_metadata updates

### Rate Limiting
```php
// Apply rate limiting for sensitive operations
Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/groups/{uuid}/participants', [GroupController::class, 'addParticipants']);
    Route::delete('/groups/{uuid}/participants/{phone}', [GroupController::class, 'removeParticipant']);
});
```

### Audit Log
```php
// Log semua group management actions
AuditLog::create([
    'user_id' => auth()->id(),
    'action' => 'group.participant.removed',
    'target' => $contact->uuid,
    'metadata' => ['phone' => $phone]
]);
```

---

## 9. Performance Optimization

### Caching Strategy
```php
// Cache group metadata untuk 5 menit
$groupData = Cache::remember(
    "group.{$contact->uuid}.metadata",
    300,
    fn() => $this->whatsappService->getGroupInfo($contact->phone)
);
```

### Lazy Loading
```javascript
// Load participants on scroll untuk group besar
const loadMoreParticipants = () => {
    // Implement pagination
};
```

---

## 10. Conclusion

### ✅ Fitur yang BISA Diimplementasi:
1. **Group Info Management** - Edit nama, deskripsi, foto
2. **Participant Management** - Add, remove, promote, demote
3. **Group Settings** - Permissions untuk messages, info, members
4. **Invite Links** - Generate dan share invite link
5. **Admin Controls** - Full admin panel seperti WhatsApp Web

### ❌ Fitur yang TIDAK Bisa:
1. Media/Links/Docs gallery ⚠️ (Bisa workaround dengan database indexing)
2. Search messages dalam group ⚠️ (Bisa workaround dengan local full-text search)
3. Disappearing messages settings (Deteksi saja, tidak bisa set)
4. Group calls (Link ke WhatsApp app)
5. Community linking (Link ke WhatsApp mobile)
6. Business group features (Terbatas, deteksi saja)

---

## 11. Riset Solusi untuk Fitur yang TIDAK Tersedia di API

### 11.1 Research Summary

Berdasarkan riset mendalam ke **GitHub repository `pedroslopez/whatsapp-web.js`** dan community discussions, berikut temuan untuk 6 fitur yang tidak tersedia:

---

### 🔍 Feature 1: Media/Links/Docs Gallery

**Status**: ✅ **BISA DIIMPLEMENTASI** dengan custom database indexing

**Temuan Teknis**:
```javascript
// API yang tersedia di WhatsApp Web.js:
✅ chat.fetchMessages({ limit: Infinity })
✅ message.hasMedia
✅ message.downloadMedia()
✅ message.type // 'image', 'video', 'document', 'audio'
✅ message.links // Array of links in message
```

**Solusi Workaround**:

#### A. Backend Service - Media Indexer
```php
// app/Services/MediaIndexerService.php

class MediaIndexerService
{
    public function indexChatMedia(string $chatId): array
    {
        // 1. Fetch semua pesan dari WhatsApp
        $messages = $this->whatsappService->fetchMessages($chatId, ['limit' => -1]);
        
        $gallery = [
            'media' => [],
            'documents' => [],
            'links' => []
        ];
        
        foreach ($messages as $message) {
            // Index media
            if (in_array($message['type'], ['image', 'video', 'audio'])) {
                $gallery['media'][] = [
                    'message_id' => $message['id'],
                    'type' => $message['type'],
                    'timestamp' => $message['timestamp'],
                    'from' => $message['from'],
                    'caption' => $message['caption'] ?? '',
                    'has_downloaded' => false
                ];
            }
            
            // Index documents
            if ($message['type'] === 'document') {
                $gallery['documents'][] = [
                    'message_id' => $message['id'],
                    'filename' => $message['filename'] ?? 'Unknown',
                    'mimetype' => $message['mimetype'],
                    'filesize' => $message['filesize'],
                    'timestamp' => $message['timestamp'],
                    'from' => $message['from']
                ];
            }
            
            // Index links
            if (!empty($message['links'])) {
                foreach ($message['links'] as $link) {
                    $gallery['links'][] = [
                        'message_id' => $message['id'],
                        'url' => $link['link'],
                        'title' => $this->extractLinkTitle($link['link']),
                        'timestamp' => $message['timestamp'],
                        'from' => $message['from']
                    ];
                }
            }
        }
        
        // Store ke database untuk caching
        DB::table('chat_media_index')->updateOrInsert(
            ['chat_id' => $chatId],
            [
                'gallery_data' => json_encode($gallery),
                'last_indexed' => now(),
                'total_media' => count($gallery['media']),
                'total_docs' => count($gallery['documents']),
                'total_links' => count($gallery['links'])
            ]
        );
        
        return $gallery;
    }
    
    public function downloadAndCacheMedia(string $messageId): string
    {
        // Download media via WhatsApp API
        $media = $this->whatsappService->downloadMedia($messageId);
        
        // Store di local storage
        $filename = uniqid() . '.' . $media['extension'];
        $path = "chat-media/{$filename}";
        
        Storage::disk('public')->put($path, base64_decode($media['data']));
        
        // Update index dengan path lokal
        DB::table('chat_media_index_items')
            ->where('message_id', $messageId)
            ->update(['local_path' => $path]);
        
        return Storage::url($path);
    }
}
```

#### B. Database Schema
```sql
-- Tabel untuk media index
CREATE TABLE chat_media_index (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    chat_id VARCHAR(255) NOT NULL,
    workspace_id INT NOT NULL,
    gallery_data JSON,
    last_indexed TIMESTAMP,
    total_media INT DEFAULT 0,
    total_docs INT DEFAULT 0,
    total_links INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chat_workspace (chat_id, workspace_id),
    INDEX idx_last_indexed (last_indexed)
);

-- Tabel detail per media item untuk query yang lebih cepat
CREATE TABLE chat_media_items (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    chat_id VARCHAR(255) NOT NULL,
    message_id VARCHAR(255) NOT NULL UNIQUE,
    media_type ENUM('image', 'video', 'audio', 'document', 'link'),
    file_url TEXT,
    local_path VARCHAR(500),
    thumbnail_path VARCHAR(500),
    filename VARCHAR(255),
    mimetype VARCHAR(100),
    filesize BIGINT,
    caption TEXT,
    link_url TEXT,
    link_title VARCHAR(500),
    sender_id VARCHAR(255),
    timestamp BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chat_type (chat_id, media_type),
    INDEX idx_timestamp (timestamp),
    FULLTEXT INDEX idx_caption_filename (caption, filename)
);
```

#### C. Frontend Component
```vue
<!-- resources/js/Components/ChatComponents/MediaGallery.vue -->
<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    chatId: String
});

const activeTab = ref('media');
const gallery = ref({
    media: [],
    documents: [],
    links: []
});
const isLoading = ref(false);
const selectedMedia = ref(null);

const filteredItems = computed(() => {
    return gallery.value[activeTab.value] || [];
});

const loadGallery = async (forceRefresh = false) => {
    isLoading.value = true;
    try {
        const response = await axios.get(`/api/chats/${props.chatId}/media-gallery`, {
            params: { refresh: forceRefresh }
        });
        gallery.value = response.data;
    } catch (error) {
        console.error('Error loading gallery:', error);
    } finally {
        isLoading.value = false;
    }
};

const viewMedia = async (item) => {
    // Download jika belum ada local path
    if (!item.local_path) {
        const response = await axios.post(`/api/chats/media/${item.message_id}/download`);
        item.local_path = response.data.url;
    }
    
    selectedMedia.value = item;
};

const downloadMedia = async (item) => {
    const url = item.local_path || await loadMedia(item.message_id);
    const link = document.createElement('a');
    link.href = url;
    link.download = item.filename || 'media';
    link.click();
};

onMounted(() => {
    loadGallery();
});
</script>

<template>
    <div class="h-full flex flex-col bg-white">
        <!-- Header with Tabs -->
        <div class="border-b">
            <div class="flex space-x-4 px-4 py-3">
                <button @click="activeTab = 'media'" 
                        :class="{'border-b-2 border-blue-600 text-blue-600': activeTab === 'media'}"
                        class="pb-2 font-medium">
                    Media ({{ gallery.media.length }})
                </button>
                <button @click="activeTab = 'documents'" 
                        :class="{'border-b-2 border-blue-600 text-blue-600': activeTab === 'documents'}"
                        class="pb-2 font-medium">
                    Docs ({{ gallery.documents.length }})
                </button>
                <button @click="activeTab = 'links'" 
                        :class="{'border-b-2 border-blue-600 text-blue-600': activeTab === 'links'}"
                        class="pb-2 font-medium">
                    Links ({{ gallery.links.length }})
                </button>
            </div>
            <div class="px-4 pb-3">
                <button @click="loadGallery(true)" 
                        :disabled="isLoading"
                        class="text-sm text-blue-600 hover:underline">
                    {{ isLoading ? 'Indexing...' : 'Refresh Index' }}
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-4">
            <!-- Loading State -->
            <div v-if="isLoading" class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="text-sm text-gray-500 mt-2">Loading gallery...</p>
            </div>
            
            <!-- Media Grid -->
            <div v-else-if="activeTab === 'media'" class="grid grid-cols-3 gap-2">
                <div v-for="item in filteredItems" 
                     :key="item.message_id"
                     @click="viewMedia(item)"
                     class="aspect-square bg-gray-200 rounded-lg overflow-hidden cursor-pointer hover:opacity-80">
                    <img v-if="item.type === 'image' && item.local_path" 
                         :src="item.local_path" 
                         class="w-full h-full object-cover">
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <svg v-if="item.type === 'video'" class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        <svg v-else-if="item.type === 'audio'" class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                        </svg>
                    </div>
                </div>
            </div>
            
            <!-- Documents List -->
            <div v-else-if="activeTab === 'documents'" class="space-y-2">
                <div v-for="item in filteredItems" 
                     :key="item.message_id"
                     class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer"
                     @click="downloadMedia(item)">
                    <div class="w-10 h-10 bg-blue-100 rounded flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11z"/>
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium">{{ item.filename }}</p>
                        <p class="text-xs text-gray-500">
                            {{ formatFileSize(item.filesize) }} · 
                            {{ formatDate(item.timestamp) }}
                        </p>
                    </div>
                    <button class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Links List -->
            <div v-else-if="activeTab === 'links'" class="space-y-2">
                <a v-for="item in filteredItems" 
                   :key="item.message_id"
                   :href="item.link_url"
                   target="_blank"
                   class="block p-3 border rounded-lg hover:bg-gray-50">
                    <div class="flex items-start">
                        <div class="w-10 h-10 bg-green-100 rounded flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/>
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-blue-600 hover:underline">
                                {{ item.link_title || item.link_url }}
                            </p>
                            <p class="text-xs text-gray-500 truncate">{{ item.link_url }}</p>
                            <p class="text-xs text-gray-400 mt-1">
                                {{ formatDate(item.timestamp) }}
                            </p>
                        </div>
                    </div>
                </a>
            </div>
            
            <!-- Empty State -->
            <div v-if="!isLoading && filteredItems.length === 0" class="text-center py-12">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <p class="text-gray-500">No {{ activeTab }} found</p>
            </div>
        </div>
    </div>
</template>
```

#### D. API Endpoints
```php
// routes/api.php
Route::get('/chats/{uuid}/media-gallery', [ChatController::class, 'getMediaGallery']);
Route::post('/chats/media/{messageId}/download', [ChatController::class, 'downloadMedia']);

// app/Http/Controllers/Api/v1/ChatController.php
public function getMediaGallery(string $uuid, Request $request): JsonResponse
{
    $chat = Contact::where('uuid', $uuid)->firstOrFail();
    
    // Check cache jika tidak force refresh
    if (!$request->boolean('refresh')) {
        $cached = DB::table('chat_media_index')
            ->where('chat_id', $chat->phone)
            ->where('last_indexed', '>', now()->subHours(24))
            ->first();
        
        if ($cached) {
            return response()->json(json_decode($cached->gallery_data, true));
        }
    }
    
    // Re-index dari WhatsApp
    $gallery = app(MediaIndexerService::class)->indexChatMedia($chat->phone);
    
    return response()->json($gallery);
}

public function downloadMedia(string $messageId): JsonResponse
{
    $url = app(MediaIndexerService::class)->downloadAndCacheMedia($messageId);
    
    return response()->json(['url' => $url]);
}
```

**Keuntungan Solusi**:
- ✅ Tidak perlu fetch ulang dari WhatsApp setiap kali buka gallery
- ✅ Fast loading dengan database index
- ✅ Bisa search caption/filename dengan full-text search
- ✅ Support lazy loading untuk gallery besar
- ✅ Download on-demand untuk hemat storage

**Limitasi**:
- ⚠️ Perlu re-index periodic untuk update data
- ⚠️ Storage requirement untuk cached media
- ⚠️ Initial indexing bisa lambat untuk chat dengan banyak media

---

### 🔍 Feature 2: Search Messages dalam Group

**Status**: ✅ **BISA DIIMPLEMENTASI** dengan local database full-text search

**Temuan Teknis**:
```javascript
// API yang tersedia:
✅ client.searchMessages(query, { chatId: 'xxx' }) // Global search
✅ chat.fetchMessages({ limit: Infinity }) // Fetch semua pesan
❌ Tidak ada native search per-chat dengan filter advanced
```

**Solusi Workaround**:

#### A. Message Indexing Service
```php
// app/Services/MessageSearchService.php

class MessageSearchService
{
    public function indexMessages(string $chatId): int
    {
        $messages = $this->whatsappService->fetchMessages($chatId, ['limit' => -1]);
        
        $indexed = 0;
        foreach ($messages as $message) {
            DB::table('messages_index')->updateOrInsert(
                ['message_id' => $message['id']],
                [
                    'chat_id' => $chatId,
                    'body' => $message['body'],
                    'search_text' => strtolower($message['body']),
                    'from_number' => $message['from'],
                    'from_name' => $this->getContactName($message['from']),
                    'timestamp' => $message['timestamp'],
                    'type' => $message['type'],
                    'has_media' => $message['hasMedia'],
                    'is_forwarded' => $message['isForwarded'] ?? false,
                    'updated_at' => now()
                ]
            );
            $indexed++;
        }
        
        return $indexed;
    }
    
    public function search(string $chatId, string $query, array $filters = []): Collection
    {
        $builder = DB::table('messages_index')
            ->where('chat_id', $chatId);
        
        // Full-text search
        if (!empty($query)) {
            $builder->whereRaw('MATCH(body) AGAINST(? IN BOOLEAN MODE)', [$query]);
        }
        
        // Filters
        if (isset($filters['from'])) {
            $builder->where('from_number', $filters['from']);
        }
        
        if (isset($filters['type'])) {
            $builder->where('type', $filters['type']);
        }
        
        if (isset($filters['has_media'])) {
            $builder->where('has_media', $filters['has_media']);
        }
        
        if (isset($filters['date_from'])) {
            $builder->where('timestamp', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $builder->where('timestamp', '<=', $filters['date_to']);
        }
        
        return $builder
            ->orderBy('timestamp', 'desc')
            ->limit($filters['limit'] ?? 50)
            ->get();
    }
    
    public function autoIndex(): void
    {
        // Hook ke event message_create untuk real-time indexing
        // Dijalankan via queue worker
    }
}
```

#### B. Database Schema
```sql
CREATE TABLE messages_index (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    message_id VARCHAR(255) NOT NULL UNIQUE,
    chat_id VARCHAR(255) NOT NULL,
    workspace_id INT NOT NULL,
    body TEXT,
    search_text TEXT,
    from_number VARCHAR(255),
    from_name VARCHAR(255),
    timestamp BIGINT,
    type VARCHAR(50),
    has_media BOOLEAN DEFAULT FALSE,
    is_forwarded BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chat_timestamp (chat_id, timestamp),
    INDEX idx_from (from_number),
    INDEX idx_type (type),
    FULLTEXT INDEX idx_body_search (body, search_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### C. Frontend Component
```vue
<!-- resources/js/Components/ChatComponents/MessageSearch.vue -->
<script setup>
import { ref, computed } from 'vue';
import { debounce } from 'lodash';
import axios from 'axios';

const props = defineProps({
    chatId: String
});

const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const showFilters = ref(false);
const filters = ref({
    from: null,
    type: null,
    has_media: null,
    date_from: null,
    date_to: null
});

const performSearch = debounce(async () => {
    if (searchQuery.value.length < 2) {
        searchResults.value = [];
        return;
    }
    
    isSearching.value = true;
    try {
        const response = await axios.get(`/api/chats/${props.chatId}/search`, {
            params: {
                query: searchQuery.value,
                ...filters.value
            }
        });
        searchResults.value = response.data;
    } catch (error) {
        console.error('Search error:', error);
    } finally {
        isSearching.value = false;
    }
}, 300);

const clearSearch = () => {
    searchQuery.value = '';
    searchResults.value = [];
    filters.value = {
        from: null,
        type: null,
        has_media: null,
        date_from: null,
        date_to: null
    };
};

const jumpToMessage = (messageId) => {
    // Emit event to parent to scroll to message
    emit('jumpToMessage', messageId);
};
</script>

<template>
    <div class="flex flex-col h-full bg-white">
        <!-- Search Header -->
        <div class="p-4 border-b">
            <div class="flex items-center space-x-2">
                <div class="flex-1 relative">
                    <input type="text"
                           v-model="searchQuery"
                           @input="performSearch"
                           placeholder="Search messages..."
                           class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    <svg class="absolute left-3 top-3 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <button @click="showFilters = !showFilters"
                        :class="{'bg-blue-100 text-blue-600': showFilters}"
                        class="p-2 rounded-lg hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                </button>
                <button v-if="searchQuery" 
                        @click="clearSearch"
                        class="p-2 rounded-lg hover:bg-gray-100">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <!-- Filters Panel -->
            <div v-if="showFilters" class="mt-4 p-3 bg-gray-50 rounded-lg space-y-3">
                <div>
                    <label class="text-xs text-gray-600">Message Type</label>
                    <select v-model="filters.type" @change="performSearch" class="w-full text-sm border-gray-300 rounded mt-1">
                        <option :value="null">All types</option>
                        <option value="chat">Text</option>
                        <option value="image">Images</option>
                        <option value="video">Videos</option>
                        <option value="audio">Audio</option>
                        <option value="document">Documents</option>
                    </select>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" v-model="filters.has_media" @change="performSearch" class="mr-2">
                    <label class="text-sm text-gray-700">Only messages with media</label>
                </div>
            </div>
        </div>
        
        <!-- Search Results -->
        <div class="flex-1 overflow-y-auto">
            <!-- Loading -->
            <div v-if="isSearching" class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p class="text-sm text-gray-500 mt-2">Searching...</p>
            </div>
            
            <!-- Results -->
            <div v-else-if="searchResults.length > 0" class="divide-y">
                <div v-for="result in searchResults"
                     :key="result.message_id"
                     @click="jumpToMessage(result.message_id)"
                     class="p-4 hover:bg-gray-50 cursor-pointer">
                    <div class="flex items-start">
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <span class="text-sm font-medium text-blue-600">
                                {{ result.from_name ? result.from_name.charAt(0) : '?' }}
                            </span>
                        </div>
                        <div class="ml-3 flex-1">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium">{{ result.from_name || result.from_number }}</p>
                                <span class="text-xs text-gray-500">{{ formatDate(result.timestamp) }}</span>
                            </div>
                            <p class="text-sm text-gray-700 mt-1 line-clamp-2">
                                {{ result.body }}
                            </p>
                            <div v-if="result.has_media" class="mt-1">
                                <span class="text-xs bg-gray-200 px-2 py-1 rounded">
                                    {{ result.type }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Empty State -->
            <div v-else-if="searchQuery && !isSearching" class="text-center py-12">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <p class="text-gray-500">No messages found</p>
            </div>
            
            <!-- Initial State -->
            <div v-else class="text-center py-12">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <p class="text-gray-500">Search messages in this chat</p>
            </div>
        </div>
    </div>
</template>
```

#### D. Real-time Indexing dengan Queue
```php
// app/Jobs/IndexNewMessageJob.php

class IndexNewMessageJob implements ShouldQueue
{
    public function __construct(
        private string $chatId,
        private array $messageData
    ) {}
    
    public function handle(): void
    {
        DB::table('messages_index')->updateOrInsert(
            ['message_id' => $this->messageData['id']],
            [
                'chat_id' => $this->chatId,
                'body' => $this->messageData['body'],
                'search_text' => strtolower($this->messageData['body']),
                'from_number' => $this->messageData['from'],
                'timestamp' => $this->messageData['timestamp'],
                'type' => $this->messageData['type'],
                'has_media' => $this->messageData['hasMedia'],
                'updated_at' => now()
            ]
        );
    }
}

// Hook di MessageReceivedEvent
class MessageReceivedEvent
{
    public function handle($event): void
    {
        dispatch(new IndexNewMessageJob(
            $event->chat_id,
            $event->message_data
        ));
    }
}
```

**Keuntungan Solusi**:
- ✅ Fast search dengan MySQL FULLTEXT index
- ✅ Advanced filters (by sender, type, date range, media)
- ✅ Real-time indexing untuk message baru
- ✅ Support search dengan regex/wildcard
- ✅ Pagination untuk hasil banyak

**Limitasi**:
- ⚠️ Perlu storage untuk message index
- ⚠️ Initial indexing bisa lambat untuk chat history panjang
- ⚠️ Perlu periodic re-sync untuk ensure data consistency

---

### 🔍 Feature 3: Disappearing Messages Settings

**Status**: ⚠️ **DETEKSI SAJA** (Tidak bisa set via API)

**Temuan Teknis**:
```javascript
// Yang tersedia:
✅ message.isEphemeral // Cek apakah message ephemeral
✅ window.Store.EphemeralFields // Internal module
❌ Tidak ada API untuk set disappearing messages duration
```

**Solusi Workaround**:

#### A. Detection Service
```php
public function detectEphemeralStatus(string $chatId): array
{
    $messages = $this->whatsappService->fetchMessages($chatId, ['limit' => 20]);
    
    $ephemeralCount = 0;
    foreach ($messages as $msg) {
        if ($msg['isEphemeral'] ?? false) {
            $ephemeralCount++;
        }
    }
    
    return [
        'enabled' => $ephemeralCount > 0,
        'can_modify' => false,
        'notice' => 'Disappearing messages can only be changed in WhatsApp app',
        'detected_count' => $ephemeralCount
    ];
}
```

#### B. UI Warning Component
```vue
<div v-if="chat.isEphemeral" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
    <div class="flex items-start">
        <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
        </svg>
        <div class="ml-3">
            <h4 class="text-sm font-medium text-yellow-800">Disappearing Messages Enabled</h4>
            <p class="text-sm text-yellow-700 mt-1">
                Messages in this chat disappear after they are seen. 
                This setting can only be changed in WhatsApp mobile app.
            </p>
            <button class="text-sm text-yellow-800 font-medium mt-2 hover:underline">
                Learn more
            </button>
        </div>
    </div>
</div>
```

---

### 🔍 Feature 4: Group Calls

**Status**: ❌ **TIDAK TERSEDIA** (No workaround)

**Temuan Teknis**:
```javascript
// Tidak ada di API:
❌ No call initiation APIs
❌ No call management APIs
❌ No WebRTC access
❌ No voice/video streaming

// Hanya bisa deteksi notification:
⚠️ message.type === 'call_log' // Deteksi ada panggilan
```

**Solusi Alternatif**:

#### Link ke WhatsApp App
```vue
<button @click="openWhatsAppForCall" 
        class="w-full flex items-center justify-center py-3 bg-green-600 text-white rounded-lg hover:bg-green-700">
    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
        <path d="M20.52 3.49C18.23 1.2 15.17 0 12.01 0 5.44 0 .11 5.33.11 11.9c0 2.1.55 4.14 1.6 5.95L0 24l6.34-1.66c1.74.95 3.7 1.45 5.67 1.45 6.57 0 11.9-5.33 11.9-11.9 0-3.16-1.2-6.22-3.39-8.4z"/>
    </svg>
    Open WhatsApp to Start Call
</button>
```

**Kesimpulan**: Group calls HARUS menggunakan WhatsApp official (tidak ada workaround)

---

### 🔍 Feature 5: Community Linking

**Status**: ❌ **TIDAK TERSEDIA** (Fitur terlalu baru)

**Temuan Teknis**:
```javascript
// Community adalah fitur 2023+ yang belum support di whatsapp-web.js v1.34.2
❌ No Community APIs
❌ No community detection
❌ No community management

// Hanya ada:
✅ GroupChat (regular groups)
✅ Channel (newsletters)
```

**Kesimpulan**: Community features belum tersedia di API, perlu WhatsApp official

---

### 🔍 Feature 6: Business Group Features

**Status**: ⚠️ **TERBATAS** (Detection only)

**Temuan Teknis**:
```javascript
// Yang tersedia:
✅ client.getBusinessProfile(contactId)
✅ contact.isBusiness
✅ message.orderId
✅ window.Store.QueryProduct

// Yang tidak tersedia:
❌ Business catalog in groups
❌ Product showcase in groups
❌ Shopping cart in groups
❌ Payment in groups
```

**Solusi Workaround**:

#### Business Detection Service
```php
public function detectBusinessMembers(string $groupId): array
{
    $group = $this->whatsappService->getGroupInfo($groupId);
    $businessMembers = [];
    
    foreach ($group['participants'] as $participant) {
        $contact = $this->whatsappService->getContact($participant['id']);
        
        if ($contact['isBusiness'] ?? false) {
            $profile = $this->whatsappService->getBusinessProfile($participant['id']);
            $businessMembers[] = [
                'id' => $participant['id'],
                'name' => $contact['name'],
                'business_name' => $profile['business_name'] ?? null,
                'category' => $profile['category'] ?? null,
                'description' => $profile['description'] ?? null,
                'website' => $profile['website'] ?? null,
                'email' => $profile['email'] ?? null,
                'address' => $profile['address'] ?? null
            ];
        }
    }
    
    return $businessMembers;
}
```

**Kesimpulan**: Business features sangat terbatas, hanya bisa detect & display info

---

## 12. Implementation Priority & Roadmap

### 🎯 PHASE 1: High-Value Implementable Features (Week 1-2)

#### ✅ Priority 1: Media/Links/Docs Gallery
- **Effort**: 5 hari
- **Value**: HIGH - User frequently need to access shared media
- **Tasks**:
  1. Create database schema untuk media index
  2. Build MediaIndexerService dengan background job
  3. Create API endpoints untuk gallery & download
  4. Build MediaGallery.vue component
  5. Integrate dengan GroupProfile component

#### ✅ Priority 2: Message Search
- **Effort**: 4 hari
- **Value**: HIGH - Critical untuk finding information
- **Tasks**:
  1. Create messages_index table dengan FULLTEXT
  2. Build MessageSearchService
  3. Implement real-time indexing dengan queue
  4. Build MessageSearch.vue component
  5. Add search icon di chat header

### 🎯 PHASE 2: Detection & UI Enhancements (Week 3)

#### ⚠️ Priority 3: Disappearing Messages UI
- **Effort**: 1 hari
- **Value**: MEDIUM - Good to inform users
- **Tasks**:
  1. Add detection service
  2. Create warning banner component
  3. Add educational content/link

#### ⚠️ Priority 4: Business Member Detection
- **Effort**: 2 hari
- **Value**: MEDIUM - Nice to have for B2B groups
- **Tasks**:
  1. Build business detection service
  2. Create business card component
  3. Add business section di GroupProfile

### 🎯 PHASE 3: User Education & Alternatives (Week 4)

#### ❌ Priority 5: Feature Limitations Education
- **Effort**: 1 hari
- **Value**: LOW - But important for user expectations
- **Tasks**:
  1. Create help documentation
  2. Add "Open in WhatsApp" buttons untuk unavailable features
  3. Create in-app tooltips/guides

---

## 13. Technical Architecture untuk Workarounds

### 13.1 System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      Frontend (Vue.js)                       │
├─────────────────────────────────────────────────────────────┤
│  • MediaGallery.vue     • MessageSearch.vue                 │
│  • GroupProfile.vue     • BusinessCard.vue                  │
└───────────────────────┬─────────────────────────────────────┘
                        │ Axios HTTP Requests
┌───────────────────────┴─────────────────────────────────────┐
│                 Laravel Backend (PHP 8.x)                    │
├─────────────────────────────────────────────────────────────┤
│  Controllers:                                                │
│  • ChatController                                            │
│  • GroupController                                           │
│                                                               │
│  Services:                                                   │
│  • MediaIndexerService      • MessageSearchService          │
│  • BusinessDetectorService  • EphemeralDetectorService      │
│                                                               │
│  Jobs (Queue):                                               │
│  • IndexNewMessageJob       • IndexChatMediaJob             │
│  • DetectBusinessMembersJob                                 │
└───────────────────────┬─────────────────────────────────────┘
                        │ HTTP API Calls
┌───────────────────────┴─────────────────────────────────────┐
│              WhatsApp Service (Node.js)                      │
├─────────────────────────────────────────────────────────────┤
│  • SessionManager                                            │
│  • whatsapp-web.js Client                                   │
│                                                               │
│  Routes:                                                     │
│  • /messages/fetch      • /media/download                   │
│  • /groups/info         • /contacts/business-profile        │
└───────────────────────┬─────────────────────────────────────┘
                        │ WhatsApp Web Protocol
┌───────────────────────┴─────────────────────────────────────┐
│                    WhatsApp Servers                          │
└─────────────────────────────────────────────────────────────┘

Storage Layer:
┌─────────────────────────────────────────────────────────────┐
│                        MySQL Database                        │
├─────────────────────────────────────────────────────────────┤
│  Tables:                                                     │
│  • chat_media_index      • chat_media_items                 │
│  • messages_index        • contacts                         │
│  • workspace_settings                                        │
└─────────────────────────────────────────────────────────────┘

Cache Layer:
┌─────────────────────────────────────────────────────────────┐
│                      Redis Cache                             │
├─────────────────────────────────────────────────────────────┤
│  • Gallery index cache (TTL: 24h)                           │
│  • Search results cache (TTL: 1h)                           │
│  • Business profile cache (TTL: 7d)                         │
└─────────────────────────────────────────────────────────────┘

Queue System:
┌─────────────────────────────────────────────────────────────┐
│                      Laravel Queue                           │
├─────────────────────────────────────────────────────────────┤
│  • Media indexing jobs (Priority: LOW)                      │
│  • Message indexing jobs (Priority: HIGH)                   │
│  • Business detection jobs (Priority: LOW)                  │
└─────────────────────────────────────────────────────────────┘
```

### 13.2 Data Flow untuk Media Gallery

```
User clicks "Media" tab
         │
         ↓
Check cache (chat_media_index)
         │
    ┌────┴────┐
    │ Cached? │
    └────┬────┘
         │
    Yes  │  No
    ↓    │    ↓
Return   │   Fetch from WhatsApp API
cached   │   (chat.fetchMessages)
data     │         │
         │         ↓
         │   Parse & filter messages
         │   (image/video/doc/links)
         │         │
         │         ↓
         │   Store to database
         │   (chat_media_index)
         │         │
         └─────────┘
               │
               ↓
       Display gallery
               │
               ↓
   User clicks media item
               │
               ↓
     Check if downloaded
         (local_path)
               │
          ┌────┴────┐
          │ Exists? │
          └────┬────┘
               │
          Yes  │  No
          ↓    │    ↓
      Show     │   Download media
      cached   │   (message.downloadMedia)
      media    │         │
               │         ↓
               │   Store to storage
               │   (public/chat-media/)
               │         │
               │         ↓
               │   Update local_path
               │   in database
               │         │
               └─────────┘
                     │
                     ↓
               Display media
```

### 13.3 Performance Optimization

#### Caching Strategy
```php
// Cache gallery index untuk 24 jam
Cache::remember("gallery.{$chatId}", 86400, function() use ($chatId) {
    return app(MediaIndexerService::class)->indexChatMedia($chatId);
});

// Cache search results untuk 1 jam
Cache::remember("search.{$chatId}.{$query}", 3600, function() use ($chatId, $query) {
    return app(MessageSearchService::class)->search($chatId, $query);
});
```

#### Queue Priority
```php
// High priority untuk message indexing (real-time)
IndexNewMessageJob::dispatch($chatId, $messageData)
    ->onQueue('high');

// Low priority untuk media indexing (background)
IndexChatMediaJob::dispatch($chatId)
    ->onQueue('low')
    ->delay(now()->addMinutes(5));
```

#### Database Optimization
```sql
-- Partitioning untuk messages_index by month
ALTER TABLE messages_index
PARTITION BY RANGE (timestamp) (
    PARTITION p_2024_11 VALUES LESS THAN (UNIX_TIMESTAMP('2024-12-01')),
    PARTITION p_2024_12 VALUES LESS THAN (UNIX_TIMESTAMP('2025-01-01')),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- Index optimization
OPTIMIZE TABLE messages_index;
ANALYZE TABLE messages_index;
```

---

## 14. Cost-Benefit Analysis

### 💰 Implementation Costs

| Feature | Development Time | Complexity | Storage Impact | Maintenance |
|---------|-----------------|------------|----------------|-------------|
| Media Gallery | 5 days | Medium | HIGH (cached media files) | Medium (periodic cleanup) |
| Message Search | 4 days | Medium | Medium (text index) | Low (auto-maintained) |
| Ephemeral Detection | 1 day | Low | None | None |
| Business Detection | 2 days | Low | Low (profile cache) | Low |

### 📊 Value Assessment

| Feature | User Demand | Competitive Advantage | Technical Feasibility | ROI Score |
|---------|-------------|----------------------|---------------------|-----------|
| Media Gallery | ⭐⭐⭐⭐⭐ | HIGH | Medium | **9/10** |
| Message Search | ⭐⭐⭐⭐⭐ | HIGH | Medium | **9/10** |
| Ephemeral Detection | ⭐⭐⭐ | Medium | Easy | **6/10** |
| Business Detection | ⭐⭐ | Low | Easy | **4/10** |

### 🎯 Recommendation

**IMPLEMENT NOW**:
1. ✅ Media Gallery - Critical UX feature
2. ✅ Message Search - High user demand

**IMPLEMENT LATER**:
3. ⚠️ Ephemeral Detection - Quick win, low effort
4. ⚠️ Business Detection - Nice to have for B2B

**DO NOT IMPLEMENT**:
5. ❌ Group Calls - Impossible via API
6. ❌ Community Features - Not yet available in library

---

## 15. Risk Mitigation

### Technical Risks

#### Risk 1: Storage Growth
**Mitigation**:
- Implement automatic cleanup untuk media older than 90 days
- Add storage quota per workspace
- Compress media before caching (lossy for images, lossless for docs)

```php
// Auto cleanup job
class CleanupOldMediaJob implements ShouldQueue
{
    public function handle(): void
    {
        $cutoffDate = now()->subDays(90);
        
        $oldMedia = DB::table('chat_media_items')
            ->where('created_at', '<', $cutoffDate)
            ->get();
        
        foreach ($oldMedia as $media) {
            if ($media->local_path) {
                Storage::delete($media->local_path);
            }
        }
        
        DB::table('chat_media_items')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
    }
}
```

#### Risk 2: Index Lag (Real-time vs Batch)
**Mitigation**:
- Use queue dengan priority tinggi untuk new messages
- Batch index untuk historical messages during off-peak hours
- Show "Indexing..." indicator di UI

#### Risk 3: API Rate Limiting
**Mitigation**:
- Implement exponential backoff untuk failed requests
- Cache aggressively untuk reduce API calls
- Add circuit breaker pattern

```php
class WhatsAppCircuitBreaker
{
    public function call(callable $callback)
    {
        if ($this->isOpen()) {
            throw new ServiceUnavailableException('Circuit breaker is open');
        }
        
        try {
            $result = $callback();
            $this->recordSuccess();
            return $result;
        } catch (Exception $e) {
            $this->recordFailure();
            throw $e;
        }
    }
}
```

---

## 16. Monitoring & Analytics

### Metrics to Track

```php
// Track indexing performance
event(new MediaIndexingCompleted([
    'chat_id' => $chatId,
    'items_indexed' => $count,
    'duration_ms' => $duration,
    'storage_used_mb' => $storageSize
]));

// Track search performance
event(new SearchPerformed([
    'chat_id' => $chatId,
    'query' => $query,
    'results_count' => $resultsCount,
    'response_time_ms' => $responseTime
]));

// Track feature usage
event(new FeatureUsed([
    'feature' => 'media_gallery',
    'user_id' => $userId,
    'workspace_id' => $workspaceId
]));
```

### Dashboard Metrics

1. **Storage Metrics**
   - Total media storage used
   - Storage per workspace
   - Growth rate

2. **Performance Metrics**
   - Average search response time
   - Index lag time
   - Cache hit rate

3. **Usage Metrics**
   - Daily active users of search
   - Daily active users of gallery
   - Most searched terms

---

## 17. Kesimpulan & Next Actions

### 📋 Summary of Research

Dari riset mendalam ke **GitHub `pedroslopez/whatsapp-web.js`** repository, Stack Overflow, dan community discussions, kami menemukan:

#### ✅ FULLY IMPLEMENTABLE (with workarounds):
1. **Media/Links/Docs Gallery** - Via database indexing & caching
2. **Message Search** - Via local full-text search with MySQL

#### ⚠️ PARTIALLY IMPLEMENTABLE (detection only):
3. **Disappearing Messages** - Can detect, cannot set
4. **Business Features** - Can detect members, limited functionality

#### ❌ NOT IMPLEMENTABLE:
5. **Group Calls** - No API access, requires WhatsApp app
6. **Community Linking** - Feature too new, not in library yet

### 🎯 Recommended Implementation Plan

**PHASE 1** (Week 1-2): Core Features
- Implement Media Gallery dengan full database indexing
- Implement Message Search dengan FULLTEXT search
- **Expected ROI**: HIGH

**PHASE 2** (Week 3): Enhancements
- Add Ephemeral Messages detection & UI warning
- Add Business Member detection & display
- **Expected ROI**: MEDIUM

**PHASE 3** (Week 4): Polish & Education
- Add user education untuk unavailable features
- Create "Open in WhatsApp" deep links
- Optimize performance & caching
- **Expected ROI**: LOW but important for UX

### 📊 Total Effort Estimation

- **Development**: 12-15 hari
- **Testing**: 3-4 hari
- **Documentation**: 2 hari
- **Total**: ~17-21 hari kerja (3-4 minggu)

### 📊 Effort Estimation:
- **Backend**: 3-4 hari
- **Node.js Service**: 2-3 hari
- **Frontend Component**: 4-5 hari
- **Testing**: 2-3 hari
- **Total**: ~12-15 hari kerja

### 🎯 Next Steps:
1. Review dan approval design
2. Create API endpoints
3. Implement Node.js routes
4. Build frontend component
5. Testing dan bug fixes
6. Documentation update

---

**Document Version**: 1.0  
**Last Updated**: 19 November 2025  
**Status**: ✅ Ready for Implementation
