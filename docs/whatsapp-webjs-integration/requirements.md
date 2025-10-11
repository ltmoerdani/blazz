# REQUIREMENTS SPECIFICATION - WhatsApp Web JS Integration

**Document Version:** 1.0  
**Date:** 2025-10-08  
**Status:** VERIFIED & CONFIRMED  
**Author:** System Analysis Based on User Specifications  
**References:** assumption.md (Phase 1 Verified)

---

## EXECUTIVE SUMMARY

### Project Objective
Mengganti core WhatsApp Business API (Meta) dengan WhatsApp Web JS untuk memungkinkan:
1. Multi-number support dalam satu workspace
2. Setup via QR code tanpa approval Meta
3. Plan-based limit untuk jumlah WhatsApp numbers
4. Full compatibility dengan existing features (chats, contacts, campaigns, templates, automation)
5. Single-command startup untuk Laravel + Node.js services

### Critical Success Factors
- ✅ Zero data loss dari existing Meta API integration
- ✅ Backward compatibility dengan fitur existing
- ✅ Gradual migration path (Meta API + Web JS coexist)
- ✅ Production-ready dengan monitoring dan health checks
- ✅ Minimal database schema changes

---

## FUNCTIONAL REQUIREMENTS

### FR-1: Multi-Number Management

#### FR-1.1: WhatsApp Number Setup via QR Code
**Priority:** CRITICAL  
**User Story:** Sebagai workspace owner, saya ingin menambah WhatsApp number baru dengan scan QR code tanpa perlu Meta API approval.

**Acceptance Criteria:**
- [ ] Tombol "Tambah WhatsApp Number" tersedia di `/settings/whatsapp`
- [ ] Clicking button membuka modal dengan QR code
- [ ] QR code auto-regenerate setiap 5 menit (300 detik) - expired dan generate QR baru
- [ ] Timer countdown displayed with MM:SS format: "QR Code expires in: 4:32..."
- [ ] Real-time status update via broadcast driver (Socket.IO atau Pusher):
  - `qr-code-generated` → Display QR dengan countdown timer
  - `session-status-changed` (status: 'qr_scanning') → Show loading state "Scanning..."
  - `session-status-changed` (status: 'connected') → Show success message, close modal, refresh session list
  - `session-status-changed` (status: 'disconnected') → Show error, allow retry
- [ ] Broadcast driver auto-detected dari workspace/admin settings
- [ ] Setelah connected, phone number otomatis terdeteksi dan disimpan
- [ ] Session data ter-encrypted dan tersimpan ke database
- [ ] Loading state saat waiting for QR generation (< 3 seconds)

**Business Rules:**
- Maximum WhatsApp numbers per workspace = plan limit (`subscription_plans.metadata.max_whatsapp_numbers`)
- Jika limit reached, show error: "Anda telah mencapai batas maksimal nomor WhatsApp"
- Default limit untuk trial plan = 1 number
- Admin dapat set limit via Admin Panel

**Technical Specifications:**
```javascript
**Socket.IO Server Setup (Node.js Service) - OPTIONAL Driver:**
```javascript
const express = require('express');
const { Server } = require('socket.io');
const http = require('http');

const app = express();
const server = http.createServer(app);

// Socket.IO server only if enabled
let io = null;
if (process.env.BROADCAST_DRIVER === 'socketio') {
    io = new Server(server, {
        cors: {
            origin: process.env.LARAVEL_URL || 'http://127.0.0.1:8000',
            credentials: true
        }
    });

    // Workspace-specific rooms
    io.on('connection', (socket) => {
        console.log('Socket.IO client connected:', socket.id);
        
        socket.on('join-workspace', (workspaceId) => {
            socket.join(`workspace-${workspaceId}`);
            console.log(`Socket ${socket.id} joined workspace-${workspaceId}`);
        });
        
        socket.on('disconnect', () => {
            console.log('Socket.IO client disconnected:', socket.id);
        });
    });
}

// Broadcast abstraction layer (works with both drivers)
function broadcastToWorkspace(workspaceId, event, data) {
    const driver = process.env.BROADCAST_DRIVER || 'pusher';
    
    if (driver === 'socketio' && io) {
        io.to(`workspace-${workspaceId}`).emit(event, data);
    } else if (driver === 'pusher') {
        // Use Pusher client (existing implementation)
        const Pusher = require('pusher');
        const pusher = new Pusher({
            appId: process.env.PUSHER_APP_ID,
            key: process.env.PUSHER_APP_KEY,
            secret: process.env.PUSHER_APP_SECRET,
            cluster: process.env.PUSHER_APP_CLUSTER
        });
        pusher.trigger(`workspace-${workspaceId}`, event, data);
    }
}

server.listen(3001, () => {
    console.log(`REST API on port 3000, Broadcast: ${process.env.BROADCAST_DRIVER || 'pusher'}`);
});
```
```

**Frontend Broadcast Driver Integration (Auto-detect):**
```vue
<script setup>
import { io } from 'socket.io-client';
import Pusher from 'pusher-js';
import { onMounted, ref } from 'vue';

const qrCode = ref(null);
const expiresIn = ref(300); // 5 minutes = 300 seconds
const countdownDisplay = ref('5:00');
const status = ref('initializing');

// Broadcast driver dari workspace settings
const broadcastDriver = workspaceSettings.broadcast_driver; // 'socketio' or 'pusher'
let connection = null;

onMounted(() => {
    if (broadcastDriver === 'socketio') {
        // Socket.IO setup
        connection = io('http://localhost:3001', {
            auth: { workspace_id: workspaceId }
        });
        
        connection.emit('join-workspace', workspaceId);
        
        connection.on('qr-code-generated', handleQRGenerated);
        connection.on('session-status-changed', handleSessionStatusChanged);
        
    } else if (broadcastDriver === 'pusher') {
        // Pusher setup (existing implementation)
        const pusher = new Pusher(import.meta.env.VITE_PUSHER_APP_KEY, {
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER
        });
        
        const channel = pusher.subscribe(`workspace-${workspaceId}`);
        channel.bind('qr-code-generated', handleQRGenerated);
        channel.bind('session-status-changed', handleSessionStatusChanged);
    }
});

function handleQRGenerated(data) {
    qrCode.value = data.qr_code_base64;
    expiresIn.value = data.expires_in_seconds || 300; // Default 5 minutes
    startCountdown();
}

function handleSessionStatusChanged(data) {
    if (data.status === 'connected') {
        status.value = 'connected';
        clearInterval(countdownInterval);
        setTimeout(() => closeModal(), 2000);
    }
}

let countdownInterval = null;
function startCountdown() {
    clearInterval(countdownInterval);
    
    countdownInterval = setInterval(() => {
        expiresIn.value--;
        
        // Format MM:SS
        const minutes = Math.floor(expiresIn.value / 60);
        const seconds = expiresIn.value % 60;
        countdownDisplay.value = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        if (expiresIn.value <= 0) {
            clearInterval(countdownInterval);
            qrCode.value = null; // Wait for new QR
            countdownDisplay.value = 'Generating new QR...';
        }
    }, 1000);
}
</script>
```

**Database Impact:**
```sql
INSERT INTO whatsapp_sessions (
    workspace_id, session_id, phone_number, 
    status, qr_code, session_data, is_primary, is_active
) VALUES (?, ?, ?, 'connected', NULL, ?, true, true);
```

---

#### FR-1.2: WhatsApp Number List Display
**Priority:** CRITICAL  
**User Story:** Sebagai workspace owner, saya ingin melihat semua WhatsApp numbers yang terhubung dengan status masing-masing.

**Acceptance Criteria:**
- [ ] List semua WhatsApp numbers ditampilkan di `/settings/whatsapp`
- [ ] Setiap item menampilkan:
  - Phone number (formatted: +62 812-3456-7890)
  - Display name (verified name dari WhatsApp)
  - Status badge (Connected / Disconnected / Initializing)
  - Primary badge (jika is_primary = true)
  - Last connected timestamp
  - Action buttons: Set as Primary, Disconnect, Delete
- [ ] Real-time status update via broadcast driver (connection status changes)
- [ ] Sorting by: primary first, then by created_at DESC
- [ ] Empty state: "Belum ada nomor WhatsApp terhubung"

**UI/UX Mockup:**
```vue
<div class="whatsapp-numbers-list">
  <div v-for="session in sessions" class="number-card">
    <div class="number-info">
      <span class="phone">{{ session.phone_number }}</span>
      <span class="name">{{ session.name }}</span>
      <span v-if="session.is_primary" class="badge primary">Primary</span>
      <span :class="['badge', session.status]">{{ session.status }}</span>
    </div>
    <div class="actions">
      <button @click="setPrimary(session.id)">Set as Primary</button>
      <button @click="disconnect(session.id)">Disconnect</button>
      <button @click="deleteSession(session.id)" class="danger">Delete</button>
    </div>
  </div>
</div>
```

---

#### FR-1.3: Plan-Based Number Limit
**Priority:** HIGH  
**User Story:** Sebagai admin, saya ingin mengatur berapa banyak WhatsApp numbers yang bisa digunakan per plan.

**Acceptance Criteria:**
- [ ] Field `max_whatsapp_numbers` di Admin Panel → Plans Management
- [ ] Default values:
  - Free/Trial: 1 number
  - Basic: 2 numbers
  - Professional: 5 numbers
  - Enterprise: 10 numbers (atau unlimited)
- [ ] Validation saat create/update subscription plan
- [ ] Frontend check sebelum show "Tambah WhatsApp Number" button
- [ ] Backend validation di API endpoint `/api/whatsapp/sessions` POST

**Database Schema:**
```json
// subscription_plans.metadata structure
{
    "max_whatsapp_numbers": 5,
    "message_limit": 10000,
    "other_features": {}
}
```

**API Response:**
```json
{
    "can_add_number": false,
    "current_count": 5,
    "max_allowed": 5,
    "message": "Anda telah mencapai batas maksimal nomor WhatsApp. Upgrade plan untuk menambah lebih banyak nomor."
}
```

---

#### FR-1.4: Session Actions & Management ⚠️ **GAP #1 - P0 CRITICAL**
**Priority:** P0 CRITICAL  
**User Story:** Sebagai workspace user, saya ingin melakukan reconnect WhatsApp session yang disconnected dan regenerate QR code jika expired tanpa menghapus session.

**Referencing:** 
- CRITICAL-GAPS-AUDIT-REPORT.md (GAP #1)
- tasks.md (TASK-BE-008)
- design.md (DES-9 - Session Lifecycle Management)

**Discovery Date:** 2025-01-XX (Frontend Implementation Phase)  
**User Report:** Frontend built UI buttons for "Reconnect" and "Regenerate QR" but backend controller methods missing

**Current State - BROKEN:**
```php
// File: app/Http/Controllers/User/WhatsAppSessionController.php
// ❌ MISSING: reconnect() method
// ❌ MISSING: regenerateQR() method

// Frontend calls these routes but 404 error:
POST /settings/whatsapp/sessions/{uuid}/reconnect      → 404
POST /settings/whatsapp/sessions/{uuid}/regenerate-qr  → 404
```

**Problem Analysis:**
1. ❌ **Disconnected Session Recovery**: Users cannot reconnect after WhatsApp logout/ban
2. ❌ **QR Code Expiration**: When QR expires (5 min timeout), users must delete + recreate session
3. ❌ **Data Loss Risk**: Delete session loses statistics (messages_sent, chats_count, etc.)
4. ❌ **Poor UX**: "Add New Number" instead of "Reconnect" confusing for users

**Expected State - FIXED:**

**Acceptance Criteria:**
- [x] ✅ Controller method `reconnect()` implemented
- [x] ✅ Controller method `regenerateQR()` implemented
- [x] ✅ Service method `WhatsAppWebJSProvider::reconnectSession()` implemented
- [x] ✅ Service method `WhatsAppWebJSProvider::regenerateQR()` implemented
- [x] ✅ Routes registered in `routes/web.php`
- [x] ✅ Policy authorization checks workspace ownership
- [x] ✅ Real-time QR broadcast via Socket.IO/Pusher
- [x] ✅ Session status transition: disconnected → qr_scanning → connected
- [x] ✅ Statistics preserved (messages_sent, etc. NOT reset)
- [x] ✅ Error handling: session not found, already connected, API failure

**User Flow - Reconnect:**
```
User sees "Disconnected" session
    ↓
Clicks "Reconnect" button
    ↓
POST /settings/whatsapp/sessions/{uuid}/reconnect
    ↓
Controller validates: workspace ownership, session exists, status = disconnected
    ↓
Service calls Node.js API: POST /api/sessions/{sessionId}/reconnect
    ↓
Node.js generates new QR → Broadcast via Socket.IO/Pusher
    ↓
Frontend receives qr-generated event → Display QR in modal
    ↓
User scans QR with WhatsApp mobile
    ↓
WhatsApp authenticated → status = connected
    ↓
Broadcast session-status-changed event
    ↓
Frontend receives event → Close modal, update session card status
    ↓
Success: Session reconnected without data loss
```

**User Flow - Regenerate QR:**
```
User in QR modal, timer shows "00:00 Expired"
    ↓
Clicks "Regenerate QR Code" button
    ↓
POST /settings/whatsapp/sessions/{uuid}/regenerate-qr
    ↓
Controller validates: workspace ownership, session exists, status = qr_scanning
    ↓
Service calls Node.js API: POST /api/sessions/{sessionId}/regenerate-qr
    ↓
Node.js generates fresh QR → Broadcast via Socket.IO/Pusher
    ↓
Frontend receives qr-generated event → Display new QR, reset timer to 5:00
    ↓
User scans new QR within 5 minutes
    ↓
Success: Session connected
```

**Technical Specifications:**

**1. Controller Methods:**
```php
// app/Http/Controllers/User/WhatsAppSessionController.php

/**
 * Reconnect a disconnected WhatsApp session
 * 
 * @param string $uuid Session UUID
 * @return \Illuminate\Http\RedirectResponse
 */
public function reconnect($uuid)
{
    try {
        $session = WhatsAppSession::where('uuid', $uuid)
            ->where('workspace_id', session('current_workspace'))
            ->firstOrFail();
        
        // Validate session can be reconnected
        if ($session->status === 'connected') {
            return redirect()->back()->with('status', [
                'type' => 'warning',
                'message' => 'Session already connected',
            ]);
        }
        
        // Call service to reconnect
        $provider = WhatsAppManager::provider($session->provider_type, $session->workspace_id);
        $result = $provider->reconnectSession($session);
        
        if ($result->success) {
            return redirect()->back()->with('status', [
                'type' => 'success',
                'message' => 'Reconnection initiated. Please scan QR code.',
            ]);
        }
        
        return redirect()->back()->with('status', [
            'type' => 'error',
            'message' => $result->message,
        ]);
        
    } catch (\Exception $e) {
        Log::error('Session reconnect failed', [
            'uuid' => $uuid,
            'error' => $e->getMessage(),
        ]);
        
        return redirect()->back()->with('status', [
            'type' => 'error',
            'message' => 'Failed to reconnect session',
        ]);
    }
}

/**
 * Regenerate QR code for a session
 * 
 * @param string $uuid Session UUID
 * @return \Illuminate\Http\RedirectResponse
 */
public function regenerateQR($uuid)
{
    try {
        $session = WhatsAppSession::where('uuid', $uuid)
            ->where('workspace_id', session('current_workspace'))
            ->firstOrFail();
        
        // Validate session is in QR scanning state
        if (!in_array($session->status, ['qr_scanning', 'disconnected'])) {
            return redirect()->back()->with('status', [
                'type' => 'warning',
                'message' => 'Cannot regenerate QR for this session status',
            ]);
        }
        
        // Call service to regenerate QR
        $provider = WhatsAppManager::provider($session->provider_type, $session->workspace_id);
        $result = $provider->regenerateQR($session);
        
        if ($result->success) {
            return redirect()->back()->with('status', [
                'type' => 'success',
                'message' => 'QR code regenerated successfully',
            ]);
        }
        
        return redirect()->back()->with('status', [
            'type' => 'error',
            'message' => $result->message,
        ]);
        
    } catch (\Exception $e) {
        Log::error('QR regeneration failed', [
            'uuid' => $uuid,
            'error' => $e->getMessage(),
        ]);
        
        return redirect()->back()->with('status', [
            'type' => 'error',
            'message' => 'Failed to regenerate QR code',
        ]);
    }
}
```

**2. Service Methods:**
```php
// app/Services/WhatsAppWebJSProvider.php

/**
 * Reconnect a disconnected WhatsApp session
 * 
 * @param WhatsAppSession $session
 * @return object {success: bool, message: string}
 */
public function reconnectSession(WhatsAppSession $session)
{
    try {
        // Call Node.js service
        $response = Http::timeout(30)->post("{$this->nodeServiceUrl}/api/sessions/{$session->id}/reconnect", [
            'workspace_id' => $session->workspace_id,
            'api_key' => config('whatsapp.node_api_key'),
        ]);
        
        if ($response->successful()) {
            $data = $response->json();
            
            // Update session status
            $session->update([
                'status' => 'qr_scanning',
                'last_activity_at' => now(),
            ]);
            
            Log::info('Session reconnection initiated', [
                'session_id' => $session->id,
                'workspace_id' => $session->workspace_id,
            ]);
            
            return (object) [
                'success' => true,
                'message' => 'Reconnection initiated. QR code will be displayed.',
                'data' => $data,
            ];
        }
        
        return (object) [
            'success' => false,
            'message' => 'Node.js service returned error: ' . $response->body(),
        ];
        
    } catch (\Exception $e) {
        Log::error('Reconnect session failed', [
            'session_id' => $session->id,
            'error' => $e->getMessage(),
        ]);
        
        return (object) [
            'success' => false,
            'message' => 'Failed to reconnect: ' . $e->getMessage(),
        ];
    }
}

/**
 * Regenerate QR code for a session
 * 
 * @param WhatsAppSession $session
 * @return object {success: bool, message: string}
 */
public function regenerateQR(WhatsAppSession $session)
{
    try {
        // Call Node.js service
        $response = Http::timeout(30)->post("{$this->nodeServiceUrl}/api/sessions/{$session->id}/regenerate-qr", [
            'workspace_id' => $session->workspace_id,
            'api_key' => config('whatsapp.node_api_key'),
        ]);
        
        if ($response->successful()) {
            $data = $response->json();
            
            Log::info('QR code regenerated', [
                'session_id' => $session->id,
                'workspace_id' => $session->workspace_id,
            ]);
            
            return (object) [
                'success' => true,
                'message' => 'QR code regenerated successfully',
                'data' => $data,
            ];
        }
        
        return (object) [
            'success' => false,
            'message' => 'Node.js service returned error: ' . $response->body(),
        ];
        
    } catch (\Exception $e) {
        Log::error('Regenerate QR failed', [
            'session_id' => $session->id,
            'error' => $e->getMessage(),
        ]);
        
        return (object) [
            'success' => false,
            'message' => 'Failed to regenerate QR: ' . $e->getMessage(),
        ];
    }
}
```

**3. Routes:**
```php
// routes/web.php
Route::post('/settings/whatsapp/sessions/{uuid}/reconnect', [WhatsAppSessionController::class, 'reconnect'])
    ->name('whatsapp.sessions.reconnect');

Route::post('/settings/whatsapp/sessions/{uuid}/regenerate-qr', [WhatsAppSessionController::class, 'regenerateQR'])
    ->name('whatsapp.sessions.regenerate-qr');
```

**4. Node.js API Endpoints (Already Implemented in TASK-NODE-002):**
```javascript
// whatsapp-service/src/api/routes.js
app.post('/api/sessions/:sessionId/reconnect', async (req, res) => {
    const { sessionId } = req.params;
    const client = sessionManager.getClient(sessionId);
    
    if (!client) {
        return res.status(404).json({ error: 'Session not found' });
    }
    
    // Destroy old client
    await client.destroy();
    
    // Initialize new client (will trigger QR generation)
    await sessionManager.initializeSession(sessionId, req.body.workspace_id);
    
    res.json({ message: 'Reconnection initiated' });
});

app.post('/api/sessions/:sessionId/regenerate-qr', async (req, res) => {
    const qr = await sessionManager.regenerateQR(sessionId);
    
    if (!qr) {
        return res.status(404).json({ error: 'Cannot generate QR' });
    }
    
    res.json({ qr_code: qr, expires_in: 300 });
});
```

**Business Rules:**
- **Reconnect Conditions:**
  - Session must exist and belong to workspace
  - Session status = disconnected OR qr_scanning
  - Cannot reconnect if already connected
  - Statistics preserved (messages_sent, chats_count, etc.)

- **Regenerate QR Conditions:**
  - Session must exist and belong to workspace
  - Session status = qr_scanning OR disconnected
  - QR expires after 5 minutes (300 seconds)
  - Can regenerate unlimited times
  - New QR invalidates old QR

- **Security:**
  - Workspace ownership validated via session()->get('current_workspace')
  - Policy check: $this->authorize('update', $session)
  - API key required for Node.js communication
  - HMAC signature validation (from TASK-BE-003)

**Error Scenarios:**
1. **Session Not Found:** 404 redirect with error message
2. **Already Connected:** Warning message "Session already connected"
3. **Node.js Service Down:** Error "Failed to connect to WhatsApp service"
4. **QR Timeout:** Auto-transition to expired state, show "Regenerate" button
5. **WhatsApp Authentication Failed:** Display error from WhatsApp API

**Implementation Impact:**
- **Files Modified:** 2 (Controller, Provider)
- **Lines Added:** ~200 lines (100 controller + 100 provider)
- **Routes Added:** 2 routes
- **Fix Time:** 3.5 hours (including testing)
- **Priority:** P0 CRITICAL (Feature non-functional without this)

**Dependencies:**
- TASK-BE-002 (Controller foundation) ✅ COMPLETED
- TASK-BE-001 (Provider pattern) ✅ COMPLETED
- TASK-NODE-002 (Session Manager) ✅ COMPLETED
- TASK-FE-001 (Frontend UI) ✅ COMPLETED

**Cross-References:**
- CRITICAL-GAPS-AUDIT-REPORT.md (GAP #1)
- design.md (DES-9 - Session Lifecycle Management)
- tasks.md (TASK-BE-008)

**Success Metrics:**
- 100% disconnected sessions can reconnect without data loss
- QR regeneration success rate > 95%
- Average reconnection time < 30 seconds
- Zero session deletions due to QR expiration

---

### FR-2: Chat Management dengan Multi-Number

#### FR-2.1: Chat Inbox Grouping & WhatsApp Web Sync
**Priority:** HIGH  
**User Story:** Sebagai agent, saya ingin melihat chat dari semua WhatsApp numbers dengan opsi filter by number.

**PENTING - Chat Synchronization Behavior:**
> ✅ **Anda BENAR!** - Ketika WhatsApp Web JS connected, sistem akan otomatis sync SEMUA chat yang ada di WhatsApp (seperti WhatsApp Web):
> - Saat pertama kali connect → WhatsApp Web JS akan fetch existing chats dari WhatsApp server
> - Chat history yang sudah ada di WhatsApp akan ter-sync ke database
> - New incoming messages akan real-time masuk ke sistem
> - System akan auto-create contact jika belum ada di database
> - Chat yang sudah di-archive di WhatsApp juga bisa di-sync (optional)
> 
> **Catatan:** Ini berbeda dengan Meta API yang hanya receive new incoming messages setelah webhook configured.

**Acceptance Criteria:**
- [ ] **Initial Sync:** Saat WhatsApp session connected, trigger chat synchronization:
  - Fetch recent chats (last 30 days atau configurable)
  - Import chat history dengan pagination (avoid timeout)
  - Create contacts automatically jika belum exist
  - Link chats ke correct `whatsapp_session_id`
  - Show sync progress: "Syncing chats... 45/120"
- [ ] Sidebar chat inbox memiliki dropdown filter:
  - "All Conversations" (default - dari semua numbers)
  - "By WhatsApp Number" → Show number list dengan unread count
  - "Unassigned"
- [ ] Jika filter by number, tampilkan hanya chat dari number tersebut
- [ ] Setiap chat item menampilkan indicator WhatsApp number (jika mode "All")
- [ ] Badge unread count per WhatsApp number
- [ ] Chat sorting tetap by `last_message_at` DESC
- [ ] Real-time incoming chat dari multiple numbers via Socket.IO

**Technical Implementation:**
```php
// ChatController@index with session filter
$query = Chat::with(['contact', 'whatsappSession'])
    ->where('workspace_id', $workspaceId)
    ->when($sessionId, function ($q) use ($sessionId) {
        $q->where('whatsapp_session_id', $sessionId);
    })
    ->orderBy('created_at', 'desc')
    ->paginate(50);
```

**Chat Synchronization Implementation:**
```javascript
// Node.js - Chat Sync on Session Connected
client.on('ready', async () => {
    console.log('WhatsApp session ready, starting chat sync...');
    
    // Get all chats from WhatsApp
    const chats = await client.getChats();
    
    // Send to Laravel for processing
    for (const chat of chats) {
        const messages = await chat.fetchMessages({ limit: 50 }); // Last 50 messages
        
        await axios.post('http://localhost:8000/api/whatsapp/sync-chat', {
            session_id: sessionId,
            workspace_id: workspaceId,
            chat_id: chat.id._serialized,
            contact_number: chat.id.user,
            contact_name: chat.name,
            messages: messages.map(msg => ({
                id: msg.id._serialized,
                body: msg.body,
                timestamp: msg.timestamp,
                from_me: msg.fromMe,
                type: msg.type,
                has_media: msg.hasMedia
            })),
            unread_count: chat.unreadCount,
            last_message_at: chat.timestamp
        });
    }
    
    console.log(`Synced ${chats.length} chats successfully`);
});
```

```php
// Laravel - Process Chat Sync
// app/Http/Controllers/Api/WhatsAppSyncController.php
public function syncChat(Request $request) {
    $session = WhatsAppSession::where('session_id', $request->session_id)->first();
    
    // Create or update contact
    $contact = Contact::firstOrCreate(
        [
            'workspace_id' => $request->workspace_id,
            'phone' => $this->formatPhone($request->contact_number)
        ],
        [
            'first_name' => $request->contact_name,
            'source_session_id' => $session->id,
            'source_type' => 'webjs',
            'created_by' => 0
        ]
    );
    
    // Import messages
    foreach ($request->messages as $message) {
        $chat = Chat::updateOrCreate(
            ['wam_id' => $message['id']],
            [
                'workspace_id' => $request->workspace_id,
                'whatsapp_session_id' => $session->id,
                'contact_id' => $contact->id,
                'type' => $message['from_me'] ? 'outbound' : 'inbound',
                'metadata' => json_encode($message),
                'status' => 'delivered',
                'provider_type' => 'webjs',
                'created_at' => Carbon::createFromTimestamp($message['timestamp'])
            ]
        );
    }
    
    return response()->json(['status' => 'synced', 'contact_id' => $contact->id]);
}
```

**Business Rules - Chat Sync:**
- Initial sync triggered automatically on `client.on('ready')` event
- Sync configurable: Last N days (default: 30 days) atau Last N chats (default: 100)
- Media files NOT synced initially (too large), only on-demand
- Sync runs in background, non-blocking
- Progress tracked: `chat_sync_status` enum ('pending', 'syncing', 'completed', 'failed')
- Re-sync available manually from UI: "Sync Chat History" button
- Sync interval: Only once per session connection, not on every restart

**Performance Considerations:**
- Batch insert: 50 chats per batch untuk avoid memory issues
- Rate limit: Max 10 chats/second untuk avoid WhatsApp throttling
- Timeout: Max 5 minutes untuk complete sync, lalu continue in background
- Error handling: Failed chats logged, tidak block entire sync process

---

#### FR-2.2: Reply from Same Number
**Priority:** CRITICAL  
**User Story:** Sebagai agent, ketika saya reply chat, pesan harus dikirim dari WhatsApp number yang sama dengan penerima pesan awal.

**Acceptance Criteria:**
- [ ] System auto-detect WhatsApp session dari chat
- [ ] Reply message menggunakan `chat.whatsapp_session_id`
- [ ] Jika session disconnected, show warning: "WhatsApp number tidak aktif, hubungkan kembali"
- [ ] Fallback to primary number jika original session tidak tersedia
- [ ] Chat metadata menyimpan session_id yang digunakan

**Business Rules:**
- Maintain conversation context per WhatsApp number
- Contact mungkin chat ke multiple numbers → separate conversation threads
- Junction table `contact_sessions` track all interactions

---

### FR-3: Campaign Multi-Number Distribution

#### FR-3.1: Round-Robin Campaign Distribution
**Priority:** HIGH  
**User Story:** Sebagai marketer, saya ingin campaign messages didistribusi across multiple WhatsApp numbers untuk avoid rate limit.

**Acceptance Criteria:**
- [ ] Campaign creation flow sama seperti sekarang
- [ ] System auto-distribute recipients across available sessions
- [ ] Distribution algorithm: Round-robin by default
- [ ] Message delay: 3-5 seconds random antar message untuk avoid spam detection
- [ ] Campaign logs mencatat `whatsapp_session_id` yang digunakan
- [ ] UI menampilkan distribution summary sebelum send:
  ```
  Total Recipients: 1000
  Available Numbers: 3
  Distribution:
    - +62 812-XXXX (334 messages)
    - +62 813-YYYY (333 messages)
    - +62 821-ZZZZ (333 messages)
  Estimated Time: ~83 minutes
  ```

**Technical Implementation:**
```php
// app/Services/Campaign/MultiNumberCampaignService.php
public function distributeCampaign(Campaign $campaign) {
    $sessions = $campaign->workspace->whatsappSessions()
        ->where('status', 'connected')
        ->where('is_active', true)
        ->get();
    
    if ($sessions->isEmpty()) {
        throw new NoActiveSessionException('Tidak ada WhatsApp number yang aktif');
    }
    
    $recipients = $campaign->recipients;
    $distribution = [];
    
    foreach ($recipients as $index => $recipient) {
        $session = $sessions[$index % $sessions->count()];
        $delay = rand(3, 5) * $index; // Random 3-5 sec delay
        
        $distribution[] = [
            'campaign_log_id' => $recipient->id,
            'whatsapp_session_id' => $session->id,
            'scheduled_at' => now()->addSeconds($delay)
        ];
    }
    
    DB::table('campaign_logs')->upsert($distribution, ['id'], ['whatsapp_session_id', 'scheduled_at']);
}
```

**Business Rules:**
- Only use sessions with `status = 'connected'` and `is_active = true`
- Jika semua sessions disconnected, campaign status = 'paused'
- Auto-resume campaign ketika session reconnected
- Track message count per session untuk fairness

---

#### FR-3.2: Campaign Performance by Number
**Priority:** MEDIUM  
**User Story:** Sebagai marketer, saya ingin lihat campaign performance breakdown by WhatsApp number.

**Acceptance Criteria:**
- [ ] Campaign detail page menampilkan tab "Performance by Number"
- [ ] Metrics per number:
  - Messages Sent
  - Delivered Rate
  - Read Rate
  - Failed Rate
  - Response Rate
- [ ] Chart visualization (bar chart)
- [ ] Export to CSV with session breakdown

---

### FR-4: Contact Multi-Session Management

#### FR-4.1: Contact Source Tracking
**Priority:** MEDIUM  
**User Story:** Sebagai agent, saya ingin tahu dari WhatsApp number mana contact ini pertama kali interact.

**Acceptance Criteria:**
- [ ] Contact detail page menampilkan "Source WhatsApp Number"
- [ ] Field `contacts.source_session_id` terisi saat first contact
- [ ] Field `contacts.source_type` = 'webjs' atau 'meta'
- [ ] Contact profile menampilkan list of all WhatsApp numbers yang pernah interact

**UI Display:**
```
Contact Information:
  Name: John Doe
  Phone: +62 812-3456-7890
  Source: WhatsApp Number +62 813-XXXX (Primary)
  
  Interaction History:
    - +62 813-XXXX (10 conversations)
    - +62 821-YYYY (3 conversations)
```

---

#### FR-4.2: Contact Session Association
**Priority:** MEDIUM  
**User Story:** System harus track semua WhatsApp numbers yang pernah interact dengan contact.

**Acceptance Criteria:**
- [ ] Junction table `contact_sessions` auto-populate on new chat
- [ ] Track `last_interaction_at` per session
- [ ] API endpoint `/api/contacts/{uuid}/sessions` return list sessions
- [ ] Contact merge functionality considers all sessions

**Database Updates:**
```sql
-- On new inbound message
INSERT INTO contact_sessions (contact_id, whatsapp_session_id, last_interaction_at)
VALUES (?, ?, NOW())
ON DUPLICATE KEY UPDATE last_interaction_at = NOW();
```

---

### FR-5: Template Compatibility

#### FR-5.1: Dual Template System
**Priority:** HIGH  
**User Story:** Sebagai content creator, saya ingin gunakan Meta API templates untuk official messages dan internal templates untuk Web JS.

**Acceptance Criteria:**
- [ ] Template creation form memiliki field "Provider Type":
  - Meta API (requires approval)
  - WhatsApp Web JS (instant, no approval)
- [ ] Meta API templates tetap sync dengan Meta dashboard
- [ ] Web JS templates disimpan di database saja
- [ ] Template list filter by provider type
- [ ] Campaign template selector show compatible templates based on workspace provider

**Business Rules:**
- Meta API templates: Status = pending → approved → rejected
- Web JS templates: Status = active (instant)
- Meta API templates can use buttons, lists, call-to-action
- Web JS templates limited to text, media, simple buttons (based on whatsapp-web.js capabilities)

**Database Schema:**
```sql
ALTER TABLE templates ADD COLUMN provider_type VARCHAR(20) DEFAULT 'meta';
ALTER TABLE templates ADD COLUMN capabilities JSON; -- Store supported features

-- Example capabilities
{
    "buttons": true,
    "list": false,
    "cta_url": true,
    "header_media": true
}
```

---

#### FR-5.2: Template Variable Replacement
**Priority:** HIGH  
**User Story:** Template variables harus work untuk both Meta API dan Web JS templates.

**Acceptance Criteria:**
- [ ] Variable syntax: `{{contact_name}}`, `{{phone}}`, `{{custom_field_1}}`
- [ ] Variable replacement logic sama untuk both providers
- [ ] Preview template with sample data
- [ ] Validation error jika variable tidak tersedia di contact data

---

### FR-6: Automation Multi-Session Support

#### FR-6.1: Session-Aware Automation
**Priority:** HIGH  
**User Story:** Automation rules harus trigger correctly untuk messages dari any WhatsApp number.

**Acceptance Criteria:**
- [ ] Automation rules memiliki field "Apply to":
  - All WhatsApp Numbers (default)
  - Specific Number (dropdown)
- [ ] Auto-reply sent from same number yang terima message
- [ ] Workflow automation track session_id in execution context
- [ ] Automation logs mencatat session_id

**Technical Implementation:**
```php
// app/Services/Automation/MultiSessionAutomationService.php
public function processAutomation(Chat $chat) {
    $session = $chat->whatsappSession;
    $workspace = $session->workspace;
    
    $rules = $workspace->automationRules()
        ->where('is_active', true)
        ->where(function ($query) use ($session) {
            $query->whereNull('whatsapp_session_id') // Global rules
                  ->orWhere('whatsapp_session_id', $session->id); // Session-specific
        })
        ->orderBy('priority', 'desc')
        ->get();
    
    foreach ($rules as $rule) {
        if ($this->matchRule($rule, $chat)) {
            $this->executeAction($rule, $chat, $session);
            if ($rule->stop_on_match) break;
        }
    }
}
```

---

#### FR-6.2: Welcome Message per Number
**Priority:** MEDIUM  
**User Story:** Sebagai workspace owner, saya ingin set different welcome message untuk each WhatsApp number.

**Acceptance Criteria:**
- [ ] Session settings memiliki field "Welcome Message"
- [ ] Welcome message dikirim saat first contact dengan number tersebut
- [ ] Support template variables
- [ ] Can be disabled per session

---

### FR-7: Node.js Service Integration

### FR-7: Node.js Service Integration

#### FR-7.0: Real-time Communication Architecture (Dual Broadcast Driver Model)
**Priority:** CRITICAL  
**Decision:** Add Socket.IO as alternative broadcast driver, coexist with Pusher

**Rationale:**
| Aspect | Socket.IO | Pusher |
|--------|-----------|---------|
| **Cost** | ✅ FREE (self-hosted) | ⚠️ Paid ($49+/month) |
| **Connections** | ✅ Unlimited | ❌ Limited by plan |
| **Control** | ✅ Full control | ❌ Third-party dependency |
| **Latency** | ✅ Lower (same server) | ⚠️ Higher (external) |
| **Installation** | ✅ Already in package.json v4.8.0 | ✅ Already available (pusher-js v8.3.0) |
| **Setup** | Simple (same Node.js server) | Requires external account |
| **Reliability** | Self-managed | Depends on Pusher uptime |
| **Admin Config** | ✅ Via Settings dropdown | ✅ Via Settings dropdown (existing) |

**Evidence:**
```json
// package.json - Both available
{
  "dependencies": {
    "socket.io-client": "^4.8.0",  // ✅ RECOMMENDED
    "pusher-js": "^8.3.0"          // Available but paid
  }
}
```

**Technical Benefits Socket.IO:**
- ✅ WebSocket dengan auto-fallback ke long-polling
- ✅ Room-based broadcasting (workspace isolation)
- ✅ Reconnection logic built-in
- ✅ Binary data support (untuk QR code image)
- ✅ Acknowledgments untuk guaranteed delivery

**Dual Driver Implementation Strategy:**
- Admin dapat pilih broadcast driver via `/admin/settings/broadcast-drivers`
- Socket.IO server optional, hanya start jika `BROADCAST_DRIVER=socketio`
- Pusher tetap available, existing users tidak perlu migrasi
- Frontend auto-detect driver dari workspace settings
- Broadcast abstraction layer untuk unified event handling
- Events: `qr-code-generated`, `session-status-changed`, `message-received`, `health-update`
- Rooms/Channels: `workspace-{id}` untuk isolation
- Auth: Workspace ID verification on connection

**Configuration in Admin Settings:**
```
Settings > Broadcast Drivers
┌─────────────────────────────────────┐
│ Select Broadcast Driver:            │
│ ○ Pusher (Managed, paid service)    │
│ ● Socket.IO (Self-hosted, free)     │
│                                      │
│ [Save Configuration]                 │
└─────────────────────────────────────┘
```

---

#### FR-7.1: WhatsApp Web JS Service Architecture
**Priority:** CRITICAL  
**User Story:** System harus menjalankan Node.js service terpisah untuk handle WhatsApp Web JS connections.

**Acceptance Criteria:**
- [ ] Node.js Express server di `/whatsapp-service/` (port 3000)
- [ ] REST API endpoints:
  - `POST /api/sessions` - Create new session
  - `DELETE /api/sessions/:id` - Disconnect session
  - `POST /api/messages/send` - Send message
  - `GET /api/sessions/:id/status` - Get session status
  - `GET /health` - Health check
- [ ] Socket.IO server integrated (port 3001, same process)
- [ ] Redis for async queue communication (optional, for high load)
- [ ] Graceful shutdown handling (cleanup sessions before exit)

**Technical Stack:**
```json
{
  "dependencies": {
    "express": "^4.18.2",
    "whatsapp-web.js": "^1.23.0",
    "socket.io": "^4.6.1",     // Real-time WebSocket server
    "ioredis": "^5.3.2",       // Optional: Redis for queue
    "axios": "^1.6.2",         // HTTP client for Laravel webhook
    "qrcode": "^1.5.3",        // QR code generation utilities
    "dotenv": "^16.0.3"        // Environment variables
  }
}
```

**Broadcast Abstraction Layer (Node.js):**
```javascript
// whatsapp-service/server.js
const express = require('express');
const { Server } = require('socket.io');
const http = require('http');
const Pusher = require('pusher');

const app = express();
const server = http.createServer(app);

// Initialize broadcast drivers
const BROADCAST_DRIVER = process.env.BROADCAST_DRIVER || 'pusher';
let io = null;
let pusher = null;

// Socket.IO setup (optional, only if driver=socketio)
if (BROADCAST_DRIVER === 'socketio') {
    io = new Server(server, {
        cors: {
            origin: process.env.LARAVEL_URL || 'http://localhost:8000',
            credentials: true
        }
    });
    
    io.on('connection', (socket) => {
        console.log('Socket.IO client connected:', socket.id);
        
        socket.on('join-workspace', (workspaceId) => {
            socket.join(`workspace-${workspaceId}`);
            console.log(`Socket ${socket.id} joined workspace ${workspaceId}`);
        });
        
        socket.on('disconnect', () => {
            console.log('Socket.IO client disconnected:', socket.id);
        });
    });
}

// Pusher setup (if driver=pusher)
if (BROADCAST_DRIVER === 'pusher') {
    pusher = new Pusher({
        appId: process.env.PUSHER_APP_ID,
        key: process.env.PUSHER_APP_KEY,
        secret: process.env.PUSHER_APP_SECRET,
        cluster: process.env.PUSHER_APP_CLUSTER
    });
}

// Unified broadcast function
function broadcastToWorkspace(workspaceId, event, data) {
    if (BROADCAST_DRIVER === 'socketio' && io) {
        io.to(`workspace-${workspaceId}`).emit(event, data);
    } else if (BROADCAST_DRIVER === 'pusher' && pusher) {
        pusher.trigger(`workspace-${workspaceId}`, event, data);
    }
}

// Start servers
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`REST API running on port ${PORT}`);
    console.log(`Broadcast driver: ${BROADCAST_DRIVER}`);
});

if (BROADCAST_DRIVER === 'socketio') {
    const WS_PORT = process.env.WS_PORT || 3001;
    server.listen(WS_PORT, () => console.log(`Socket.IO running on port ${WS_PORT}`));
}

module.exports = { app, io, broadcastToWorkspace };
```

**Directory Structure:**
```
whatsapp-service/
├── server.js
├── package.json
├── .env
├── controllers/
│   ├── sessionController.js
│   └── messageController.js
├── services/
│   ├── whatsappService.js
│   ├── sessionManager.js
│   └── queueService.js
├── middleware/
│   └── authMiddleware.js
├── sessions/ (runtime data)
└── logs/
```

---

#### FR-7.2: Laravel-Node.js Communication
**Priority:** CRITICAL  
**User Story:** Laravel dan Node.js harus berkomunikasi secara reliable dan efficient.

**Acceptance Criteria:**
- [ ] **Synchronous (HTTP):** Laravel → Node.js untuk immediate operations
  - Create session (with timeout 30s)
  - Get session status
  - Force disconnect
- [ ] **Asynchronous (Redis Queue):** Untuk bulk operations
  - Send campaign messages
  - Broadcast messages
- [ ] **Callback (Webhook):** Node.js → Laravel untuk events
  - Message received
  - Session status change
  - QR code generated
- [ ] Authentication via API key (header: `X-API-Key`)
- [ ] Retry logic dengan exponential backoff
- [ ] Circuit breaker pattern untuk handle Node.js downtime

**Communication Flow:**
```
User Action → Laravel Controller → HTTP/Redis → Node.js Service
                                                      ↓
                                            WhatsApp Web JS Client
                                                      ↓
Laravel Webhook ← Callback HTTP ← Event Emitter ← Client Events
```

---

#### FR-7.3: Session Isolation & Security
**Priority:** CRITICAL  
**User Story:** Setiap workspace session harus isolated dan secure dari cross-workspace access.

**Acceptance Criteria:**
- [ ] Session data storage per workspace: `./sessions/{workspace_id}/{session_id}/`
- [ ] Chromium user data dir isolated per session
- [ ] Workspace ID validation pada setiap API call
- [ ] Session ID format: `{workspace_id}-{uuid}`
- [ ] Encrypted session data storage
- [ ] Rate limiting per workspace (prevent abuse)

**Security Measures:**
```javascript
class SessionManager {
    validateAccess(sessionId, requestWorkspaceId) {
        const [workspaceId, uuid] = sessionId.split('-');
        if (parseInt(workspaceId) !== parseInt(requestWorkspaceId)) {
            throw new UnauthorizedError('Workspace access violation');
        }
    }
}
```

---

### FR-8: Operational Requirements

#### FR-8.1: Single Command Startup
**Priority:** HIGH  
**User Story:** Sebagai developer, saya ingin start semua services dengan satu command.

**Acceptance Criteria:**
- [ ] **Development:** `npm run dev` starts:
  - Laravel dev server (port 8000)
  - Node.js WhatsApp service (port 3000)
  - Vite dev server (port 5173)
  - Laravel queue worker (optional)
- [ ] **Production:** `pm2 start ecosystem.config.js` starts all services
- [ ] Logs accessible via `pm2 logs`
- [ ] Process monitoring via `pm2 monit`
- [ ] Auto-restart on crash
- [ ] Graceful shutdown on `pm2 stop`

**Implementation:**
```json
// package.json
{
  "scripts": {
    "dev": "concurrently \"npm:dev:*\"",
    "dev:laravel": "php artisan serve",
    "dev:node": "cd whatsapp-service && npm run dev",
    "dev:vite": "vite",
    "dev:queue": "php artisan queue:work",
    
    "prod:start": "pm2 start ecosystem.config.js",
    "prod:stop": "pm2 stop all",
    "prod:restart": "pm2 restart all",
    "prod:logs": "pm2 logs"
  }
}
```

---

#### FR-8.2: Health Monitoring & Alerts
**Priority:** HIGH  
**User Story:** Sebagai sysadmin, saya ingin monitor health status dari all WhatsApp sessions dan services.

**Acceptance Criteria:**
- [ ] Health dashboard di `/admin/health`
- [ ] Metrics displayed:
  - Total sessions: Active / Disconnected / Failed
  - Messages sent today (per session & total)
  - Error rate (last 24h)
  - Service uptime (Laravel, Node.js, Redis, Socket.IO)
  - Memory usage (Node.js process)
- [ ] Real-time updates via Socket.IO (health metrics broadcast every 10s)
- [ ] Alert system:
  - Email alert jika session disconnected > 5 minutes
  - Slack/Discord webhook integration
  - Browser notification untuk admin users
- [ ] API endpoint `/api/health` return JSON status

**Health Score Algorithm:**
```php
public function calculateHealthScore($session) {
    $score = 100;
    
    // Connection status
    if ($session->status !== 'connected') $score -= 50;
    
    // Disconnection frequency (last 24h)
    $disconnections = $session->events()
        ->where('event', 'disconnected')
        ->where('created_at', '>=', now()->subDay())
        ->count();
    $score -= ($disconnections * 10);
    
    // Message failure rate
    $failureRate = $session->messageFailureRate();
    $score -= ($failureRate * 100);
    
    // Last activity (session inactive > 1 hour)
    if ($session->last_connected_at < now()->subHour()) {
        $score -= 20;
    }
    
    return max(0, $score);
}
```

---

#### FR-8.3: Session Auto-Recovery
**Priority:** MEDIUM  
**User Story:** System harus auto-reconnect WhatsApp sessions yang disconnected.

**Acceptance Criteria:**
- [ ] Monitor session status setiap 30 seconds
- [ ] Jika status = 'disconnected', attempt reconnect
- [ ] Retry strategy: 3 attempts dengan delay 10s, 30s, 60s
- [ ] Jika reconnect failed setelah 3 attempts, mark as 'failed' dan notify admin
- [ ] Success reconnect → broadcast event ke frontend
- [ ] Logs reconnection attempts dengan timestamp

**Technical Implementation:**
```javascript
class SessionMonitor {
    async monitorSessions() {
        setInterval(async () => {
            for (const [sessionId, client] of this.sessions) {
                const state = await client.getState();
                
                if (state === 'DISCONNECTED') {
                    await this.attemptReconnect(sessionId);
                }
            }
        }, 30000); // Every 30 seconds
    }
    
    async attemptReconnect(sessionId, attempt = 1) {
        const delays = [10000, 30000, 60000]; // 10s, 30s, 60s
        
        try {
            await this.clients.get(sessionId).initialize();
            this.notifySuccess(sessionId);
        } catch (error) {
            if (attempt < 3) {
                setTimeout(() => {
                    this.attemptReconnect(sessionId, attempt + 1);
                }, delays[attempt]);
            } else {
                this.notifyFailure(sessionId);
            }
        }
    }
}
```

---

### FR-8: Security & Data Protection

#### FR-8.1: Session Data Encryption
**Priority:** CRITICAL  
**User Story:** Sebagai security officer, saya ingin memastikan session data WhatsApp ter-encrypt untuk compliance (GDPR, PCI-DSS).

**Acceptance Criteria:**
- [ ] Session data di database encrypted menggunakan AES-256-CBC
- [ ] Per-record initialization vector (IV) untuk enhanced security
- [ ] Encryption key management:
  - Stored di environment variable (`.env`)
  - Rotatable tanpa data loss (migration utility)
  - Separate key untuk dev/staging/production
- [ ] Encrypted fields:
  - `whatsapp_sessions.session_data` (5-10MB data)
  - `whatsapp_sessions.auth_credentials`
  - Sensitive metadata fields
- [ ] Decryption performance < 100ms per record

**Technical Implementation:**
```php
// app/Models/WhatsAppSession.php
protected $casts = [
    'session_data' => 'encrypted:json',
    'auth_credentials' => 'encrypted:array',
    'metadata' => 'json'
];

// Custom accessor dengan backward compatibility
public function getDecryptedSessionAttribute()
{
    try {
        return decrypt($this->session_data);
    } catch (\Exception $e) {
        // Log decryption failure
        Log::error('Session decryption failed', [
            'session_id' => $this->id,
            'error' => $e->getMessage()
        ]);
        return null;
    }
}

// Key rotation utility
php artisan whatsapp:rotate-encryption-key --old-key=xxx --new-key=yyy
```

**Security Measures:**
- Database backups also encrypted (AES-256)
- Encryption keys never committed to git (use `.env.example` template)
- Access logs untuk decryption operations
- Quarterly key rotation policy

---

#### FR-8.2: Inter-Service API Authentication
**Priority:** CRITICAL  
**User Story:** Sebagai security engineer, saya ingin prevent unauthorized access ke Node.js WhatsApp service.

**Acceptance Criteria:**
- [ ] HMAC-SHA256 signature validation untuk Laravel → Node.js requests
- [ ] Timestamp validation untuk prevent replay attacks (5-minute window)
- [ ] Request payload signing:
  ```
  signature = HMAC-SHA256(method + path + timestamp + body, API_SECRET)
  ```
- [ ] HTTP headers required:
  - `X-API-Key`: Static API key
  - `X-Timestamp`: Unix timestamp milliseconds
  - `X-Signature`: HMAC signature
- [ ] Rate limiting per API key: 100 requests/minute
- [ ] IP whitelist untuk production (Laravel IP only)
- [ ] Failed authentication logging with alerting

**Technical Implementation:**
```javascript
// whatsapp-service/middleware/authMiddleware.js
const crypto = require('crypto');

class AuthMiddleware {
    static async validateRequest(req, res, next) {
        const apiKey = req.headers['x-api-key'];
        const timestamp = req.headers['x-timestamp'];
        const signature = req.headers['x-signature'];
        
        // Validate API key
        if (apiKey !== process.env.API_SECRET) {
            return res.status(401).json({ error: 'Invalid API key' });
        }
        
        // Validate timestamp (prevent replay attacks)
        const now = Date.now();
        const requestTime = parseInt(timestamp);
        if (Math.abs(now - requestTime) > 300000) { // 5 minutes
            return res.status(401).json({ error: 'Request expired' });
        }
        
        // Verify HMAC signature
        const payload = `${req.method}:${req.path}:${timestamp}:${JSON.stringify(req.body)}`;
        const expectedSignature = crypto
            .createHmac('sha256', process.env.API_SECRET)
            .update(payload)
            .digest('hex');
        
        if (signature !== expectedSignature) {
            this.logFailedAuth(req);
            return res.status(401).json({ error: 'Invalid signature' });
        }
        
        // Rate limiting
        const rateLimitKey = `rate_limit:${apiKey}`;
        const requests = await redis.incr(rateLimitKey);
        if (requests === 1) {
            await redis.expire(rateLimitKey, 60);
        }
        if (requests > 100) {
            return res.status(429).json({ error: 'Rate limit exceeded' });
        }
        
        next();
    }
    
    static logFailedAuth(req) {
        console.error('Failed authentication attempt', {
            ip: req.ip,
            path: req.path,
            headers: req.headers,
            timestamp: new Date().toISOString()
        });
    }
}
```

**Laravel Client Implementation:**
```php
// app/Services/NodeWhatsAppClient.php
class NodeWhatsAppClient
{
    public function sendRequest(string $endpoint, array $data)
    {
        $timestamp = now()->valueOf();
        $method = 'POST';
        $payload = $method . ':' . $endpoint . ':' . $timestamp . ':' . json_encode($data);
        $signature = hash_hmac('sha256', $payload, config('services.whatsapp_node.api_secret'));
        
        return Http::withHeaders([
            'X-API-Key' => config('services.whatsapp_node.api_key'),
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ])->post(config('services.whatsapp_node.base_url') . $endpoint, $data);
    }
}
```

---

#### FR-8.3: Session Isolation & Access Control
**Priority:** CRITICAL  
**User Story:** Sebagai workspace owner, saya ingin ensure session saya tidak bisa diakses oleh workspace lain.

**Acceptance Criteria:**
- [ ] File-based session storage isolated per workspace:
  ```
  /sessions/{workspace_id}/{session_id}/
  ```
- [ ] Directory permissions: `chmod 700` (owner only)
- [ ] Workspace ID validation di semua session operations
- [ ] Database queries include `workspace_id` constraint
- [ ] Audit trail untuk session access attempts
- [ ] Automatic session cleanup on workspace deletion

**Technical Implementation:**
```php
// app/Services/SessionValidator.php
class SessionValidator
{
    public static function validateSessionAccess($sessionId, $workspaceId)
    {
        $session = WhatsAppSession::where('session_id', $sessionId)
            ->where('workspace_id', $workspaceId)
            ->first();
        
        if (!$session) {
            throw new UnauthorizedAccessException(
                'Session not found or unauthorized'
            );
        }
        
        // Additional security checks
        if ($session->status === 'banned') {
            throw new SessionBannedException(
                'This WhatsApp number has been banned'
            );
        }
        
        if ($session->last_activity_at < now()->subHours(24)) {
            throw new SessionExpiredException(
                'Session expired due to inactivity'
            );
        }
        
        // Update last activity
        $session->touch('last_activity_at');
        
        // Log access
        SessionAccessLog::create([
            'session_id' => $session->id,
            'workspace_id' => $workspaceId,
            'action' => 'access',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
        
        return $session;
    }
}
```

**File System Security:**
```bash
# Session directory initialization
mkdir -p /var/www/whatsapp-service/sessions/{workspace_id}
chmod 700 /var/www/whatsapp-service/sessions/{workspace_id}
chown node:node /var/www/whatsapp-service/sessions/{workspace_id}

# Prevent path traversal
# Validate workspace_id format: alphanumeric + hyphen only
if ! [[ "$WORKSPACE_ID" =~ ^[a-zA-Z0-9-]+$ ]]; then
    echo "Invalid workspace ID"
    exit 1
fi
```

**Database Schema:**
```sql
-- Audit trail table
CREATE TABLE whatsapp_session_access_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    action ENUM('access', 'create', 'disconnect', 'delete', 'unauthorized_attempt'),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_workspace (session_id, workspace_id, created_at),
    INDEX idx_workspace_action (workspace_id, action, created_at)
);
```

---

#### FR-8.4: WhatsApp Ban Prevention
**Priority:** HIGH  
**User Story:** Sebagai workspace owner, saya tidak ingin WhatsApp number saya ke-ban karena excessive messaging.

**Acceptance Criteria:**
- [ ] Rate limiting configuration per session:
  - Max 30 messages/minute
  - Max 1000 messages/hour
  - Max 500 unique contacts/day
  - Max 256 broadcast size (WhatsApp limit)
- [ ] Progressive delay implementation:
  - 0-5 msg/min: No delay
  - 5-10 msg/min: 1s delay
  - 10-15 msg/min: 2s delay
  - 15-20 msg/min: 3s delay
  - 20+ msg/min: 5s delay
- [ ] Ban risk scoring system (0-100):
  - Score calculation based on:
    * Message volume trends
    * Spam reports (if available from WhatsApp API)
    * Rapid contact additions
    * Broadcast frequency
  - Auto-pause session if score > 80
- [ ] Warning notifications:
  - Alert at 70% of rate limit
  - Critical alert at 90% of rate limit
- [ ] Automatic session pause if limit exceeded
- [ ] Manual override untuk trusted workspaces

**Technical Implementation:**
```javascript
// whatsapp-service/services/banPreventionService.js
class BanPreventionService {
    constructor() {
        this.messageCounters = new Map();
        this.warningThresholds = {
            messages_per_minute: 30,
            messages_per_hour: 1000,
            unique_contacts_per_day: 500,
            broadcast_size: 256,
            media_per_hour: 100
        };
    }
    
    async checkAndThrottle(sessionId, recipientCount = 1) {
        const counter = this.getOrCreateCounter(sessionId);
        
        // Check broadcast size
        if (recipientCount > this.warningThresholds.broadcast_size) {
            throw new ValidationException(
                `Broadcast size ${recipientCount} exceeds WhatsApp limit of 256`
            );
        }
        
        // Check messages per minute
        if (counter.messagesLastMinute >= this.warningThresholds.messages_per_minute) {
            throw new ThrottleException(
                'Rate limit: Too many messages per minute. Please wait.'
            );
        }
        
        // Check messages per hour
        if (counter.messagesLastHour >= this.warningThresholds.messages_per_hour) {
            await this.pauseSession(sessionId, 'Hourly limit reached');
            await this.notifyWorkspace(sessionId, 'Session auto-paused to prevent ban');
            return false;
        }
        
        // Update counters
        counter.messagesLastMinute++;
        counter.messagesLastHour++;
        counter.totalToday++;
        
        // Calculate dynamic delay
        const delay = this.calculateDelay(counter);
        if (delay > 0) {
            await this.delay(delay);
        }
        
        // Check if warning threshold reached
        if (counter.messagesLastMinute >= this.warningThresholds.messages_per_minute * 0.7) {
            await this.sendWarning(sessionId, 'Approaching rate limit');
        }
        
        // Calculate ban risk score
        const banRiskScore = this.calculateBanRisk(counter);
        if (banRiskScore > 80) {
            await this.pauseSession(sessionId, 'High ban risk detected');
        }
        
        return true;
    }
    
    calculateDelay(counter) {
        const msgPerMin = counter.messagesLastMinute;
        if (msgPerMin > 20) return 5000;
        if (msgPerMin > 15) return 3000;
        if (msgPerMin > 10) return 2000;
        if (msgPerMin > 5) return 1000;
        return 0;
    }
    
    calculateBanRisk(counter) {
        let score = 0;
        
        // Volume factor (40 points max)
        const hourlyPercentage = (counter.messagesLastHour / this.warningThresholds.messages_per_hour) * 100;
        score += (hourlyPercentage / 100) * 40;
        
        // Burst factor (30 points max)
        const minutePercentage = (counter.messagesLastMinute / this.warningThresholds.messages_per_minute) * 100;
        score += (minutePercentage / 100) * 30;
        
        // Unique contacts factor (20 points max)
        const contactPercentage = (counter.uniqueContactsToday / this.warningThresholds.unique_contacts_per_day) * 100;
        score += (contactPercentage / 100) * 20;
        
        // Broadcast frequency factor (10 points max)
        if (counter.broadcastsToday > 5) {
            score += 10;
        } else if (counter.broadcastsToday > 3) {
            score += 5;
        }
        
        return Math.min(Math.round(score), 100);
    }
}
```

**Monitoring Dashboard:**
- Real-time ban risk score per session
- Message volume charts (last 24 hours)
- Rate limit usage percentage
- Historical ban incidents log

---

### FR-9: Migration & Backward Compatibility

#### FR-9.1: Gradual Migration Strategy
**Priority:** CRITICAL  
**User Story:** Existing workspaces menggunakan Meta API harus tetap berfungsi normal selama transisi.

**Acceptance Criteria:**
- [ ] Workspace dapat pilih provider: Meta API atau Web JS (atau both)
- [ ] Provider detection automatic berdasarkan available sessions
- [ ] Existing Meta API config di `workspaces.metadata.whatsapp` tidak dihapus
- [ ] Jika workspace memiliki Meta API config DAN Web JS sessions:
  - Prioritas: Web JS sessions (jika connected)
  - Fallback: Meta API (jika Web JS disconnected)
- [ ] Admin dapat force switch provider per workspace
- [ ] Migration wizard di Admin Panel:
  ```
  Step 1: Pilih workspaces untuk migrate
  Step 2: Setup WhatsApp Web JS sessions
  Step 3: Test connectivity
  Step 4: Switch provider
  Step 5: Remove Meta API config (optional)
  ```

**Business Rules:**
- New workspaces default to Web JS
- Existing workspaces keep Meta API until manual migration
- During transition, both providers can coexist
- Zero downtime migration

---

#### FR-9.2: Data Preservation
**Priority:** CRITICAL  
**User Story:** Semua existing data (chats, contacts, campaigns) harus tetap intact setelah migration.

**Acceptance Criteria:**
- [ ] Existing chats tetap accessible
- [ ] Chat history tidak hilang
- [ ] Contact associations preserved
- [ ] Campaign statistics tidak berubah
- [ ] Template remains compatible
- [ ] Automation rules tetap active

**Migration Checklist:**
- [x] Backup database before migration
- [ ] Run migration scripts di staging first
- [ ] Verify data integrity dengan automated tests
- [ ] Rollback plan jika migration failed
- [ ] Post-migration validation report

---

### FR-10: Broadcast Driver Configuration (SOCKET.IO + PUSHER)

#### FR-10.1: Admin Broadcast Driver Selection ⚠️ **GAP #5 - CRITICAL**
**Priority:** P0-CRITICAL  
**User Story:** Sebagai super admin, saya ingin configure broadcast driver (Socket.IO atau Pusher) yang tersedia untuk semua workspace.  
**Referencing:** CRITICAL-GAPS-AUDIT-REPORT.md (GAP #5), assumption.md (ASM-11 line 5, 12, 807)

**Acceptance Criteria:**
- [ ] Admin Panel → Settings → Broadcast Drivers (`/admin/settings/broadcast-drivers`)
- [ ] Dropdown "Broadcast Driver" dengan options:
  - **Pusher** (existing, cloud-based, paid)
  - **Socket.IO** (new, self-hosted, FREE) ⚠️ **MISSING**
- [ ] Form fields conditional based on selection:
  
  **Pusher Fields** (existing):
  - Pusher App ID
  - Pusher App Key
  - Pusher App Secret
  - Pusher App Cluster
  
  **Socket.IO Fields** (new):
  - Socket.IO URL (default: `http://localhost`)
  - Socket.IO Port (default: `3000`)
  - Enable/Disable toggle
  
- [ ] Settings saved to `settings` table:
  - `broadcast_driver` = 'pusher' | 'socketio'
  - `pusher_*` fields (existing)
  - `socketio_url`, `socketio_port`, `socketio_enabled` (new)
- [ ] Validation:
  - Test connection before save
  - Show error jika service not reachable
- [ ] Help text: "Socket.IO adalah alternatif GRATIS dari Pusher untuk real-time notifications"

**Current Implementation Status:**
- ❌ `resources/js/Pages/Admin/Setting/Broadcast.vue` only has Pusher option
- ❌ Socket.IO option MISSING from dropdown (line 60)
- ❌ Socket.IO form fields NOT implemented
- ❌ Settings table missing `socketio_*` keys

**Technical Implementation:**
```vue
<!-- resources/js/Pages/Admin/Setting/Broadcast.vue -->
<script setup>
const methods = [
    { label: 'Pusher', value: 'pusher' },
    { label: 'Socket.IO (Free)', value: 'socketio' }, // ADD THIS
]

const form = useForm({
    broadcast_driver: getValueByKey('broadcast_driver'),
    // Pusher fields (existing)
    pusher_app_key: getValueByKey('pusher_app_key'),
    pusher_app_id: getValueByKey('pusher_app_id'),
    pusher_app_secret: getValueByKey('pusher_app_secret'),
    pusher_app_cluster: getValueByKey('pusher_app_cluster'),
    // Socket.IO fields (NEW)
    socketio_url: getValueByKey('socketio_url'),
    socketio_port: getValueByKey('socketio_port'),
    socketio_enabled: getValueByKey('socketio_enabled'),
})
</script>

<template>
    <!-- Pusher fields (existing) -->
    <div v-if="form.broadcast_driver === 'pusher'" class="grid gap-6 grid-cols-2">
        <!-- existing Pusher fields -->
    </div>
    
    <!-- Socket.IO fields (NEW) -->
    <div v-if="form.broadcast_driver === 'socketio'" class="grid gap-6 grid-cols-2">
        <FormInput v-model="form.socketio_url" :name="$t('Socket.IO URL')" 
                   :type="'text'" :error="form.errors.socketio_url" 
                   :class="'col-span-2'"/>
        <FormInput v-model="form.socketio_port" :name="$t('Socket.IO Port')" 
                   :type="'number'" :error="form.errors.socketio_port" 
                   :class="'col-span-1'"/>
        <FormToggle v-model="form.socketio_enabled" :name="$t('Enable Socket.IO')" 
                    :class="'col-span-1'"/>
    </div>
</template>
```

---

#### FR-10.2: Workspace Broadcast Driver Selection ⚠️ **GAP #6 & #7 - CRITICAL**
**Priority:** P0-CRITICAL  
**User Story:** Sebagai workspace owner, saya ingin memilih broadcast driver (Socket.IO atau Pusher) untuk workspace saya.  
**Referencing:** CRITICAL-GAPS-AUDIT-REPORT.md (GAP #6, #7), tasks.md (line 1419)

**Acceptance Criteria:**
- [ ] Database field `workspaces.broadcast_driver` exists ⚠️ **MISSING**
  - Type: VARCHAR(50)
  - Default: 'pusher'
  - Values: 'pusher' | 'socketio'
  - Indexed for performance
  
- [ ] Workspace Settings → Broadcast Driver (`/settings/general` or `/settings/broadcast`)
- [ ] Dropdown "Real-time Communication Driver" dengan options:
  - Options determined by admin settings (only show enabled drivers)
  - If Socket.IO enabled by admin → show "Socket.IO (Free)"
  - Always show Pusher if configured
  
- [ ] UI Display:
  ```
  Real-time Communication Settings
  
  Broadcast Driver: [Dropdown]
    - Pusher (Cloud-based, paid service)
    - Socket.IO (Self-hosted, FREE) ← Recommended
  
  [Help icon] Socket.IO provides free real-time updates without monthly costs.
              Pusher offers higher reliability but requires subscription.
  
  [Save Changes]
  ```

- [ ] Backend endpoint: `PUT /api/workspace/broadcast-driver`
  ```php
  public function updateBroadcastDriver(Request $request)
  {
      $validated = $request->validate([
          'broadcast_driver' => 'required|in:pusher,socketio',
      ]);
      
      $workspace = Workspace::find(session('current_workspace'));
      $workspace->update($validated);
      
      return redirect()->back()->with('status', [
          'type' => 'success',
          'message' => 'Broadcast driver updated successfully',
      ]);
  }
  ```

- [ ] Inertia page props include `workspace.broadcast_driver`:
  ```php
  // app/Http/Middleware/HandleInertiaRequests.php
  public function share(Request $request): array
  {
      $workspace = Workspace::find(session('current_workspace'));
      
      return [
          'workspace' => $workspace ? [
              'id' => $workspace->id,
              'name' => $workspace->name,
              'broadcast_driver' => $workspace->broadcast_driver, // ADD THIS
          ] : null,
      ];
  }
  ```

**Current Implementation Status:**
- ❌ `workspaces` table DOES NOT have `broadcast_driver` column
- ❌ Workspace model DOES NOT expose broadcast_driver field
- ❌ No UI for workspace owner to select driver
- ❌ `HandleInertiaRequests` middleware NOT passing broadcast_driver to frontend
- ✅ Frontend composable `useWhatsAppSocket.js` ALREADY BUILT to read `workspace.broadcast_driver`

**Impact of Missing Implementation:**
- Frontend composable cannot auto-detect broadcast driver
- All workspaces forced to use global admin driver
- Socket.IO cannot be used even if available
- `workspace.broadcast_driver` prop undefined in Inertia pages

**Database Migration Required:**
```php
// database/migrations/YYYY_MM_DD_HHMMSS_add_broadcast_driver_to_workspaces_table.php
public function up()
{
    Schema::table('workspaces', function (Blueprint $table) {
        $table->string('broadcast_driver', 50)
            ->default('pusher')
            ->after('timezone')
            ->comment('Broadcast driver: pusher or socketio');
        
        $table->index('broadcast_driver');
    });
}

public function down()
{
    Schema::table('workspaces', function (Blueprint $table) {
        $table->dropColumn('broadcast_driver');
    });
}
```

---

#### FR-10.3: Plan Management - WhatsApp Limits
**Priority:** HIGH  
**User Story:** Sebagai super admin, saya ingin set max WhatsApp numbers per subscription plan.

**Acceptance Criteria:**
- [ ] Admin Panel → Plans → Edit/Create Plan form
- [ ] New field: "Maximum WhatsApp Numbers" (integer, min: 1, max: 100)
- [ ] Validation: Cannot set lower than currently used by workspaces
- [ ] Bulk update existing subscriptions (optional)
- [ ] Audit log untuk plan changes

---

#### FR-10.4: Workspace Session Management
**Priority:** MEDIUM  
**User Story:** Sebagai super admin, saya ingin manage WhatsApp sessions dari all workspaces.

**Acceptance Criteria:**
- [ ] Admin Panel → WhatsApp Sessions
- [ ] Table columns:
  - Workspace Name
  - Phone Number
  - Status
  - Health Score
  - Last Connected
  - Actions (View, Disconnect, Delete)
- [ ] Filter by: Workspace, Status, Date Range
- [ ] Bulk actions: Disconnect selected, Delete selected
- [ ] Export to CSV

---

#### FR-10.5: System Health Dashboard
**Priority:** MEDIUM  
**User Story:** Sebagai super admin, saya ingin overview system health untuk WhatsApp services.

**Acceptance Criteria:**
- [ ] Admin Panel → Dashboard → WhatsApp Health widget
- [ ] Metrics:
  - Total Active Sessions: X
  - Total Messages Today: Y
  - Average Health Score: Z%
  - Node.js Service Status: Online/Offline
  - Redis Status: Online/Offline
- [ ] Chart: Message volume (last 7 days)
- [ ] Alert notifications (red badge jika ada issues)

---

#### FR-10.6: Navigation Menu - WhatsApp Sessions Discovery ⚠️ **GAP #2 - P0 CRITICAL**
**Priority:** P0 CRITICAL  
**User Story:** Sebagai workspace user, saya ingin menemukan fitur WhatsApp Sessions dengan mudah melalui navigation menu di Settings page.

**Current State - BROKEN UX:**
- ✅ WhatsApp Sessions page exists at `/settings/whatsapp/sessions`
- ✅ Page fully functional (list sessions, add number, QR modal, real-time updates)
- ❌ **CRITICAL GAP:** No navigation menu link in `/settings` sidebar
- ❌ Users CANNOT discover feature without manually typing URL
- ❌ Confusing with existing "Whatsapp settings" menu (Meta API configuration)

**File:** `resources/js/Pages/User/Settings/Layout.vue`

**Existing Navigation (Line 17-47):**
```vue
<ul class="space-y-1">
    <!-- General settings -->
    <li :class="$page.url === '/settings' ? 'bg-slate-50 text-black' : ''">
        <Link href="/settings">General</Link>
    </li>
    
    <!-- Whatsapp settings (Meta API) - CONFUSING! -->
    <li :class="$page.url.startsWith('/settings/whatsapp') ? 'bg-slate-50 text-black' : ''">
        <Link href="/settings/whatsapp">Whatsapp settings</Link>
    </li>
    
    <!-- Contact fields, Ticket settings, Automation, Plugins -->
    <!-- ... -->
</ul>
```

**Problem Analysis:**
1. **Zero Discoverability**: New feature hidden from users
2. **URL Conflict**: `/settings/whatsapp` matches `/settings/whatsapp/sessions` causing wrong menu highlight
3. **User Confusion**: Two WhatsApp-related pages without clear distinction
4. **Onboarding Failure**: New workspaces can't find how to add WhatsApp numbers

**Expected State - FIXED UX:**
```vue
<ul class="space-y-1">
    <!-- General settings -->
    <li :class="$page.url === '/settings' ? 'bg-slate-50 text-black' : ''">
        <Link href="/settings">General</Link>
    </li>
    
    <!-- WhatsApp Numbers (Web.JS Multi-Session) - NEW! -->
    <li :class="$page.url.startsWith('/settings/whatsapp/sessions') ? 'bg-slate-50 text-black' : ''">
        <Link href="/settings/whatsapp/sessions" class="flex items-center gap-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
            </svg>
            <span>{{ $t('WhatsApp Numbers') }}</span>
        </Link>
    </li>
    
    <!-- Whatsapp settings (Meta API Configuration) - CLARIFIED! -->
    <li :class="$page.url === '/settings/whatsapp' ? 'bg-slate-50 text-black' : ''">
        <Link href="/settings/whatsapp">{{ $t('Meta API Settings') }}</Link>
    </li>
    
    <!-- Contact fields, Ticket settings, etc... -->
</ul>
```

**Acceptance Criteria:**
- [x] ✅ "WhatsApp Numbers" menu item added to Settings sidebar
- [x] ✅ Menu positioned BEFORE "Meta API Settings" (logical grouping)
- [x] ✅ WhatsApp icon (SVG) displayed next to label
- [x] ✅ Active state highlighting works correctly (bg-slate-50)
- [x] ✅ URL matching precise: `/settings/whatsapp/sessions` only (not `/settings/whatsapp`)
- [x] ✅ Translation key added: `'WhatsApp Numbers'` (EN), `'Nomor WhatsApp'` (ID)
- [x] ✅ Navigation tested: Click → Page loads correctly
- [x] ✅ Visual consistency: Same styling as other menu items
- [x] ✅ Mobile responsive: Icon + text readable on small screens

**Business Rules:**
- Menu visible to all workspace members (Admin, Agent, User)
- No permission check required (same as "Whatsapp settings")
- Menu order: General → **WhatsApp Numbers** → Meta API Settings → Contacts → Tickets → Automation → Plugins

**Technical Specifications:**
```vue
<!-- File: resources/js/Pages/User/Settings/Layout.vue -->

<!-- Add after General settings (line 21) -->
<li class="mb-2" :class="$page.url.startsWith('/settings/whatsapp/sessions') ? 'bg-slate-50 text-black' : ''">
    <Link 
        href="/settings/whatsapp/sessions" 
        class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-slate-100 rounded transition-colors"
    >
        <!-- WhatsApp Icon -->
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
        </svg>
        
        <!-- Label with translation -->
        <span>{{ $t('WhatsApp Numbers') }}</span>
    </Link>
</li>
```

**Translation Keys (lang/en.json & lang/id.json):**
```json
{
    "WhatsApp Numbers": "WhatsApp Numbers",
    "Meta API Settings": "Meta API Settings"
}
```

```json
{
    "WhatsApp Numbers": "Nomor WhatsApp",
    "Meta API Settings": "Pengaturan Meta API"
}
```

**Implementation Impact:**
- **Files Modified:** 1 (Layout.vue)
- **Lines Added:** ~15 lines
- **Fix Time:** 15 minutes
- **Testing Time:** 5 minutes
- **Priority:** P0 CRITICAL (Feature undiscoverable)

**Dependencies:**
- TASK-FE-001 (WhatsApp Sessions page already complete ✅)
- No backend changes required

**Cross-References:**
- CRITICAL-GAPS-AUDIT-REPORT.md (GAP #2)
- design.md (DES-8 - Navigation UX Design)
- tasks.md (TASK-FE-008)

**Verification Steps:**
1. Navigate to http://127.0.0.1:8000/settings
2. ✅ "WhatsApp Numbers" menu visible in sidebar
3. ✅ Click menu → Navigate to `/settings/whatsapp/sessions`
4. ✅ Active state (bg-slate-50) applied correctly
5. ✅ WhatsApp icon displayed properly
6. ✅ No URL conflict with "Meta API Settings"
7. ✅ Mobile view: Icon + text readable

**Success Metrics:**
- 100% users can discover WhatsApp Sessions feature
- Zero confusion between Meta API vs Web.JS settings
- Navigation flow: Settings → WhatsApp Numbers → Add Number → QR Scan → Success

---

#### FR-10.7: Page Disambiguation - Meta API vs Web.JS ⚠️ **GAP #3 - P1 HIGH**
**Priority:** P1 HIGH  
**User Story:** Sebagai workspace user, saya ingin understand perbedaan antara Meta API WhatsApp settings dan Web.JS WhatsApp Numbers agar tidak confused.

**Referencing:** 
- CRITICAL-GAPS-AUDIT-REPORT.md (GAP #3)
- design.md (DES-10 - Page Disambiguation UX)
- tasks.md (TASK-FE-009)

**Discovery Date:** 2025-01-XX (User Testing Phase)  
**User Report:** "Ada 2 halaman WhatsApp settings, mana yang harus saya gunakan?"

**Current State - CONFUSING:**
- `/settings/whatsapp` → "Whatsapp settings" (Meta API Cloud-based)
- `/settings/whatsapp/sessions` → "WhatsApp Numbers" (Web.JS On-premise)
- No visual indication which is which
- No explanation of differences
- Users try to configure wrong page

**Problem Analysis:**
1. ❌ **Page Purpose Unclear**: Both pages have "WhatsApp" in name
2. ❌ **No Guidance**: Users don't know when to use Meta API vs Web.JS
3. ❌ **Configuration Errors**: Users configure Meta API credentials then expect Web.JS to work
4. ❌ **Support Tickets**: "Why my WhatsApp not working after setup?"

**Expected State - CLEAR:**

**Solution 1: Warning Banner on Meta API Page**
```vue
<!-- File: resources/js/Pages/User/Settings/Whatsapp.vue -->
<template>
    <div class="mb-6">
        <!-- Info Banner: Page Purpose -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">
                        {{ $t('WhatsApp Business API (Cloud-based)') }}
                    </h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>
                            {{ $t('This page is for configuring WhatsApp Business API credentials from Meta. Requires business verification and approval.') }}
                        </p>
                        <p class="mt-2">
                            {{ $t('Looking for on-premise WhatsApp multi-number management?') }}
                            <Link 
                                href="/settings/whatsapp/sessions" 
                                class="font-medium underline hover:text-blue-900"
                            >
                                {{ $t('Go to WhatsApp Numbers →') }}
                            </Link>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Existing Meta API Form -->
        <div class="mt-6">
            <!-- ... existing form fields ... -->
        </div>
    </div>
</template>
```

**Solution 2: Info Banner on Web.JS Page**
```vue
<!-- File: resources/js/Pages/User/Settings/WhatsAppSessions.vue (TASK-FE-001) -->
<template>
    <div class="mb-6">
        <!-- Info Banner: Page Purpose -->
        <div class="bg-green-50 border-l-4 border-green-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-green-800">
                        {{ $t('WhatsApp Numbers (On-premise Multi-Session)') }}
                    </h3>
                    <div class="mt-2 text-sm text-green-700">
                        <p>
                            {{ $t('Connect multiple personal/business WhatsApp numbers via QR code. No Meta approval required. Runs on your server.') }}
                        </p>
                        <p class="mt-2">
                            {{ $t('Need official WhatsApp Business API (Cloud)?') }}
                            <Link 
                                href="/settings/whatsapp" 
                                class="font-medium underline hover:text-green-900"
                            >
                                {{ $t('Go to Meta API Settings →') }}
                            </Link>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Existing Sessions List -->
        <div class="mt-6">
            <!-- ... existing session cards ... -->
        </div>
    </div>
</template>
```

**Solution 3: Comparison Table (Optional)**
```vue
<!-- Add collapsible section "Compare Options" -->
<div class="mb-6">
    <button 
        @click="showComparison = !showComparison"
        class="text-sm text-gray-600 hover:text-gray-900 underline"
    >
        {{ showComparison ? $t('Hide comparison') : $t('Compare WhatsApp options →') }}
    </button>
    
    <div v-if="showComparison" class="mt-4 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Feature</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Meta API (Cloud)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Web.JS (On-premise)</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Setup Method</td>
                    <td class="px-6 py-4 text-sm text-gray-500">API Credentials + Business Verification</td>
                    <td class="px-6 py-4 text-sm text-gray-500">QR Code Scan (Instant)</td>
                </tr>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Approval Required</td>
                    <td class="px-6 py-4 text-sm text-gray-500">Yes (Meta Review 1-7 days)</td>
                    <td class="px-6 py-4 text-sm text-gray-500">No (Instant)</td>
                </tr>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Multi-Number Support</td>
                    <td class="px-6 py-4 text-sm text-gray-500">1 Number per Workspace</td>
                    <td class="px-6 py-4 text-sm text-gray-500">Multiple Numbers (Plan-based)</td>
                </tr>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Hosting</td>
                    <td class="px-6 py-4 text-sm text-gray-500">Cloud (Meta Servers)</td>
                    <td class="px-6 py-4 text-sm text-gray-500">On-premise (Your Server)</td>
                </tr>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Cost</td>
                    <td class="px-6 py-4 text-sm text-gray-500">Conversation-based Pricing</td>
                    <td class="px-6 py-4 text-sm text-gray-500">Free (Server costs only)</td>
                </tr>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Best For</td>
                    <td class="px-6 py-4 text-sm text-gray-500">Official Business Communication</td>
                    <td class="px-6 py-4 text-sm text-gray-500">Multi-agent Teams, Personal Numbers</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
```

**Acceptance Criteria:**
- [x] ✅ Info banner added to `/settings/whatsapp` (Meta API page)
- [x] ✅ Info banner added to `/settings/whatsapp/sessions` (Web.JS page)
- [x] ✅ Cross-link between both pages (easy navigation)
- [x] ✅ Clear explanation of each page purpose
- [x] ✅ Visual distinction (blue banner for Meta API, green for Web.JS)
- [x] ✅ Translation keys added for all banner text
- [x] ✅ Mobile responsive design
- [x] ✅ Optional comparison table (collapsible)

**Translation Keys:**
```json
// lang/en.json
{
    "WhatsApp Business API (Cloud-based)": "WhatsApp Business API (Cloud-based)",
    "This page is for configuring WhatsApp Business API credentials from Meta. Requires business verification and approval.": "This page is for configuring WhatsApp Business API credentials from Meta. Requires business verification and approval.",
    "Looking for on-premise WhatsApp multi-number management?": "Looking for on-premise WhatsApp multi-number management?",
    "Go to WhatsApp Numbers →": "Go to WhatsApp Numbers →",
    
    "WhatsApp Numbers (On-premise Multi-Session)": "WhatsApp Numbers (On-premise Multi-Session)",
    "Connect multiple personal/business WhatsApp numbers via QR code. No Meta approval required. Runs on your server.": "Connect multiple personal/business WhatsApp numbers via QR code. No Meta approval required. Runs on your server.",
    "Need official WhatsApp Business API (Cloud)?": "Need official WhatsApp Business API (Cloud)?",
    "Go to Meta API Settings →": "Go to Meta API Settings →",
    "Compare WhatsApp options →": "Compare WhatsApp options →"
}

// lang/id.json
{
    "WhatsApp Business API (Cloud-based)": "WhatsApp Business API (Berbasis Cloud)",
    "This page is for configuring WhatsApp Business API credentials from Meta. Requires business verification and approval.": "Halaman ini untuk konfigurasi kredensial WhatsApp Business API dari Meta. Memerlukan verifikasi bisnis dan persetujuan.",
    "Looking for on-premise WhatsApp multi-number management?": "Mencari pengelolaan multi-nomor WhatsApp on-premise?",
    "Go to WhatsApp Numbers →": "Ke Nomor WhatsApp →",
    
    "WhatsApp Numbers (On-premise Multi-Session)": "Nomor WhatsApp (Multi-Sesi On-premise)",
    "Connect multiple personal/business WhatsApp numbers via QR code. No Meta approval required. Runs on your server.": "Hubungkan beberapa nomor WhatsApp pribadi/bisnis via kode QR. Tidak perlu persetujuan Meta. Berjalan di server Anda.",
    "Need official WhatsApp Business API (Cloud)?": "Butuh WhatsApp Business API resmi (Cloud)?",
    "Go to Meta API Settings →": "Ke Pengaturan Meta API →",
    "Compare WhatsApp options →": "Bandingkan opsi WhatsApp →"
}
```

**Implementation Impact:**
- **Files Modified:** 2 (Whatsapp.vue, WhatsAppSessions.vue from TASK-FE-001)
- **Lines Added:** ~60 lines (30 per page)
- **Translation Keys:** 9 keys × 2 languages = 18 entries
- **Fix Time:** 2 hours (1 hour implementation + 1 hour testing)
- **Priority:** P1 HIGH (Reduces user confusion and support tickets)

**Business Rules:**
- Banner always visible (not dismissible) - Permanent guidance
- Cross-links work both directions
- Comparison table optional (controlled by showComparison state)
- Visual consistency with Blazz design system (Tailwind colors)

**Success Metrics:**
- 80% reduction in support tickets about "wrong WhatsApp page"
- 100% users understand difference between Meta API vs Web.JS
- Zero configuration errors (credentials in wrong page)
- Average time to correct page < 10 seconds

**Cross-References:**
- CRITICAL-GAPS-AUDIT-REPORT.md (GAP #3)
- design.md (DES-10 - Page Disambiguation UX)
- tasks.md (TASK-FE-009)

---

#### FR-10.8: Settings Table Seeder - Socket.IO Configuration ⚠️ **GAP #8 - P0 CRITICAL**
**Priority:** P0 CRITICAL  
**User Story:** Sebagai system installer, saya ingin Socket.IO configuration automatically seeded ke settings table saat fresh installation.

**Referencing:** 
- CRITICAL-GAPS-AUDIT-REPORT.md (GAP #8)
- design.md (DES-11 - Configuration Management)
- tasks.md (TASK-BE-010)

**Discovery Date:** 2025-01-XX (Gap Audit Phase)  
**Root Cause:** Admin broadcast settings page expects `socketio_url` and `socketio_port` in settings table but seeder never created

**Current State - BROKEN:**
```php
// Admin visits /admin/settings/broadcast-drivers
// Form tries to load:
getValueByKey('socketio_url')    → NULL (not in settings table)
getValueByKey('socketio_port')   → NULL (not in settings table)

// Result: Empty form fields, no default values, confusing for admin
```

**Problem Analysis:**
1. ❌ **Missing Default Configuration**: Socket.IO settings not in database
2. ❌ **Fresh Installation Broken**: Admin cannot configure Socket.IO without manual SQL
3. ❌ **Inconsistent with Pusher**: Pusher has default entries, Socket.IO doesn't
4. ❌ **Migration Gap**: Existing installations missing Socket.IO settings

**Expected State - FIXED:**

**Acceptance Criteria:**
- [x] ✅ Seeder created for Socket.IO settings
- [x] ✅ Default values: `socketio_url` = http://127.0.0.1:3000, `socketio_port` = 3000
- [x] ✅ Seeder runs automatically on `php artisan db:seed`
- [x] ✅ Seeder idempotent (safe to run multiple times)
- [x] ✅ Migration for existing installations (add missing entries)
- [x] ✅ Admin form displays default values correctly
- [x] ✅ Broadcast driver dropdown includes Socket.IO option

**Implementation:**

**1. Create Seeder:**
```php
// database/seeders/SocketIOSettingsSeeder.php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;

class SocketIOSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds for Socket.IO configuration
     *
     * @return void
     */
    public function run()
    {
        $socketIOSettings = [
            [
                'key' => 'socketio_url',
                'value' => env('SOCKETIO_URL', 'http://127.0.0.1:3000'),
                'type' => 'text',
                'description' => 'Socket.IO server URL for real-time broadcasting',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'socketio_port',
                'value' => env('SOCKETIO_PORT', '3000'),
                'type' => 'number',
                'description' => 'Socket.IO server port',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'socketio_enabled',
                'value' => env('BROADCAST_DRIVER', 'pusher') === 'socketio' ? '1' : '0',
                'type' => 'boolean',
                'description' => 'Enable Socket.IO broadcast driver',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($socketIOSettings as $setting) {
            // Check if setting already exists (idempotency)
            $exists = Setting::where('key', $setting['key'])->exists();
            
            if (!$exists) {
                Setting::create($setting);
                $this->command->info("✓ Created setting: {$setting['key']}");
            } else {
                $this->command->info("⊙ Setting already exists: {$setting['key']}");
            }
        }
        
        $this->command->info('Socket.IO settings seeded successfully!');
    }
}
```

**2. Add to DatabaseSeeder:**
```php
// database/seeders/DatabaseSeeder.php
public function run()
{
    $this->call([
        // ... existing seeders ...
        SocketIOSettingsSeeder::class,  // Add this line
    ]);
}
```

**3. Create Migration for Existing Installations:**
```php
// database/migrations/2025_01_XX_add_socketio_settings.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add Socket.IO settings if not exists
        $socketIOSettings = [
            ['key' => 'socketio_url', 'value' => 'http://127.0.0.1:3000', 'type' => 'text'],
            ['key' => 'socketio_port', 'value' => '3000', 'type' => 'number'],
            ['key' => 'socketio_enabled', 'value' => '0', 'type' => 'boolean'],
        ];

        foreach ($socketIOSettings as $setting) {
            if (!Setting::where('key', $setting['key'])->exists()) {
                Setting::create([
                    'key' => $setting['key'],
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'description' => "Socket.IO {$setting['key']}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Setting::whereIn('key', ['socketio_url', 'socketio_port', 'socketio_enabled'])->delete();
    }
};
```

**4. Update .env.example:**
```bash
# Socket.IO Broadcast Driver (FREE alternative to Pusher)
BROADCAST_DRIVER=pusher
SOCKETIO_URL=http://127.0.0.1:3000
SOCKETIO_PORT=3000
```

**5. Verification Command:**
```bash
# Fresh installation
php artisan db:seed --class=SocketIOSettingsSeeder

# Check settings table
php artisan tinker
>>> Setting::whereIn('key', ['socketio_url', 'socketio_port', 'socketio_enabled'])->get()

# Expected output:
# [
#   { "key": "socketio_url", "value": "http://127.0.0.1:3000" },
#   { "key": "socketio_port", "value": "3000" },
#   { "key": "socketio_enabled", "value": "0" }
# ]

# Existing installation migration
php artisan migrate
```

**Business Rules:**
- **Default Values:**
  - `socketio_url`: http://127.0.0.1:3000 (local development)
  - `socketio_port`: 3000 (default Node.js service port)
  - `socketio_enabled`: 0 (Pusher by default, admin can enable)

- **Environment Variables:**
  - Read from .env if available (override defaults)
  - Fallback to hardcoded defaults if .env not set

- **Idempotency:**
  - Seeder checks if key exists before insert
  - Migration checks if key exists before insert
  - Safe to run multiple times
  - No duplicate entries

**Implementation Impact:**
- **Files Created:** 2 (Seeder, Migration)
- **Files Modified:** 2 (DatabaseSeeder.php, .env.example)
- **Lines Added:** ~120 lines total
- **Fix Time:** 20 minutes
- **Priority:** P0 CRITICAL (Blocks admin Socket.IO configuration)

**Dependencies:**
- TASK-FE-006 (Admin Broadcast Settings UI) - Depends on this
- GAP #5 (Socket.IO dropdown) - Depends on this
- Settings table must exist (existing Blazz installation)

**Cross-References:**
- CRITICAL-GAPS-AUDIT-REPORT.md (GAP #8)
- design.md (DES-11 - Configuration Management)
- tasks.md (TASK-BE-010)

**Success Metrics:**
- 100% fresh installations have Socket.IO settings
- 100% existing installations migrated successfully
- Zero "setting not found" errors in admin panel
- Admin can configure Socket.IO immediately after installation

---

### FR-11: Performance Optimization

#### FR-11.1: Chat Sync Progressive Loading
**Priority:** HIGH  
**User Story:** Sebagai agent, saya ingin chat sync tidak overwhelm system when connecting WhatsApp dengan ribuan chat history.

**Acceptance Criteria:**
- [ ] Prioritized sync strategy:
  1. Unread chats first (highest priority)
  2. Recent chats (last 30 days)
  3. Archived chats (last, background job)
- [ ] Batch processing: 50 chats per batch
- [ ] Rate limiting: Max 10 chats/second sync rate
- [ ] Progress indicator di UI:
  ```
  Syncing chats... 45/120 (38%)
  [=========>           ] 
  Estimated time: 2 minutes
  ```
- [ ] Timeout handling: Max 5 minutes for initial sync
- [ ] Background continuation: Remaining chats synced via queue job
- [ ] Sync resumption: Can continue from last synced position after interruption

**Technical Implementation:**
```javascript
// whatsapp-service/services/chatSyncService.js
class ChatSyncService {
    async syncChats(client, sessionId, workspaceId) {
        const BATCH_SIZE = 50;
        const MAX_SYNC_TIME = 300000; // 5 minutes
        const startTime = Date.now();
        
        try {
            const chats = await client.getChats();
            const totalChats = chats.length;
            
            // Prioritize: unread first, then recent
            const prioritizedChats = chats.sort((a, b) => {
                if (a.unreadCount && !b.unreadCount) return -1;
                if (!a.unreadCount && b.unreadCount) return 1;
                return b.timestamp - a.timestamp;
            });
            
            for (let i = 0; i < totalChats; i += BATCH_SIZE) {
                // Check timeout
                if (Date.now() - startTime > MAX_SYNC_TIME) {
                    await this.scheduleBackgroundSync(sessionId, i, totalChats);
                    break;
                }
                
                const batch = prioritizedChats.slice(i, i + BATCH_SIZE);
                await this.syncBatch(batch, sessionId, workspaceId);
                
                // Broadcast progress
                this.broadcastProgress(workspaceId, {
                    synced: Math.min(i + BATCH_SIZE, totalChats),
                    total: totalChats,
                    percentage: Math.round((i + BATCH_SIZE) / totalChats * 100)
                });
                
                // Rate limiting delay
                await this.delay(5000); // 50 chats per 5 seconds = 10 chats/sec
            }
        } catch (error) {
            await this.handleSyncError(sessionId, error);
        }
    }
    
    async syncBatch(chats, sessionId, workspaceId) {
        const messages = [];
        
        for (const chat of chats) {
            // Only sync last 20 messages initially
            const recentMessages = await chat.fetchMessages({ limit: 20 });
            messages.push({
                chat_id: chat.id._serialized,
                name: chat.name,
                unread_count: chat.unreadCount,
                messages: recentMessages.map(m => ({
                    id: m.id._serialized,
                    body: m.body,
                    timestamp: m.timestamp,
                    from: m.from,
                    to: m.to,
                    type: m.type
                }))
            });
        }
        
        // Bulk insert via Laravel API
        await axios.post(`${process.env.LARAVEL_URL}/api/whatsapp/sync-batch`, {
            session_id: sessionId,
            workspace_id: workspaceId,
            chats: messages
        });
    }
}
```

**Performance Targets:**
- Sync 100 chats in < 1 minute (initial unread/recent only)
- Full sync 1000 chats in < 15 minutes (background)
- Memory usage < 200MB during sync
- No UI blocking during sync process

---

#### FR-11.2: Queue Priority System
**Priority:** HIGH  
**User Story:** Sebagai agent, saya ingin real-time chat replies prioritized over bulk campaign messages.

**Acceptance Criteria:**
- [ ] Multiple queue channels:
  - `whatsapp-urgent`: Real-time chat replies (priority 1)
  - `whatsapp-high`: Automation responses (priority 2)
  - `whatsapp-normal`: Regular messages (priority 3)
  - `whatsapp-campaign`: Campaign messages (priority 4)
- [ ] Queue workers configuration:
  ```bash
  php artisan queue:work --queue=whatsapp-urgent,whatsapp-high,whatsapp-normal,whatsapp-campaign
  ```
- [ ] Worker allocation:
  - 5 workers for urgent queue
  - 10 workers for high queue
  - 10 workers for normal queue
  - 5 workers for campaign queue
- [ ] Automatic priority assignment based on message context
- [ ] Queue depth monitoring with alerts

**Technical Implementation:**
```php
// app/Jobs/SendWhatsAppMessage.php
class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public $sessionId,
        public $recipient,
        public $message,
        public $priority = 'normal'
    ) {
        // Set queue based on priority
        $this->onQueue($this->getPriorityQueue());
        
        // Set delay based on priority
        $this->delay($this->getDelay());
    }
    
    private function getPriorityQueue(): string
    {
        return match($this->priority) {
            'urgent' => 'whatsapp-urgent',
            'high' => 'whatsapp-high',
            'normal' => 'whatsapp-normal',
            'low', 'campaign' => 'whatsapp-campaign',
            default => 'whatsapp-normal'
        };
    }
    
    private function getDelay(): int
    {
        return match($this->priority) {
            'urgent' => 0,                    // No delay
            'high' => 1,                      // 1 second
            'normal' => 3,                    // 3 seconds
            'low', 'campaign' => rand(3, 5)  // 3-5 seconds random
        };
    }
    
    public function handle()
    {
        // Message sending implementation
        $service = app(WhatsAppService::class);
        $service->sendMessage($this->sessionId, $this->recipient, $this->message);
    }
}
```

**Monitoring:**
- Queue depth per priority level
- Average processing time per queue
- Alert if urgent queue depth > 100
- Alert if campaign queue depth > 10,000

---

#### FR-11.3: Database Query Optimization
**Priority:** MEDIUM  
**User Story:** Sebagai developer, saya ingin dashboard loading cepat bahkan dengan ribuan chats dan contacts.

**Acceptance Criteria:**
- [ ] Composite indexes untuk common queries:
  ```sql
  CREATE INDEX idx_chats_workspace_session_created 
      ON chats(workspace_id, whatsapp_session_id, created_at);
  
  CREATE INDEX idx_contacts_workspace_phone 
      ON contacts(workspace_id, phone);
  
  CREATE INDEX idx_campaign_logs_campaign_status 
      ON campaign_logs(campaign_id, status, created_at);
  ```
- [ ] Query caching strategy:
  - Session list cached for 60 seconds
  - Chat list cached for 30 seconds
  - Contact list cached for 120 seconds
- [ ] Eager loading prevention of N+1 queries:
  ```php
  Chat::with(['contact', 'whatsappSession', 'latestMessage'])->get();
  ```
- [ ] Pagination for large datasets: Max 50 items per page
- [ ] Query performance monitoring: Alert if query > 1 second

**Implementation:**
```php
// app/Http/Controllers/ChatController.php
public function index(Request $request)
{
    $workspaceId = session('current_workspace');
    $sessionId = $request->input('session_id');
    
    // Cache key
    $cacheKey = "chats:{$workspaceId}:{$sessionId}:page:{$request->page}";
    
    // Try cache first
    return Cache::remember($cacheKey, 30, function () use ($workspaceId, $sessionId) {
        $query = Chat::with([
            'contact:id,first_name,last_name,phone',
            'whatsappSession:id,phone_number,name',
            'latestMessage:id,chat_id,body,created_at'
        ])
        ->where('workspace_id', $workspaceId);
        
        if ($sessionId) {
            $query->where('whatsapp_session_id', $sessionId);
        }
        
        return $query
            ->orderBy('last_message_at', 'desc')
            ->paginate(50);
    });
}
```

---

### FR-12: Operations & Monitoring

#### FR-12.1: Comprehensive Monitoring Metrics
**Priority:** CRITICAL  
**User Story:** Sebagai DevOps engineer, saya ingin real-time visibility into system health dan performance.

**Acceptance Criteria:**
- [ ] Prometheus metrics exported dari Node.js service:
  - `whatsapp_session_status{workspace_id, session_id, phone_number}` - Gauge (1=connected, 0=disconnected)
  - `whatsapp_messages_sent_total{workspace_id, session_id, type, status}` - Counter
  - `whatsapp_qr_generation_duration_seconds` - Histogram
  - `whatsapp_session_health_score{workspace_id, session_id}` - Gauge (0-100)
  - `whatsapp_ban_risk_score{workspace_id, session_id}` - Gauge (0-100)
  - `whatsapp_chat_sync_duration_seconds` - Histogram
  - `whatsapp_message_delivery_duration_seconds` - Histogram
- [ ] Grafana dashboard dengan panels:
  - Active sessions count
  - Message volume (last 24h)
  - Average health score
  - Ban risk distribution
  - Queue depth by priority
  - API response times
  - Error rate
- [ ] Alert rules (Prometheus AlertManager):
  ```yaml
  groups:
    - name: whatsapp_alerts
      rules:
        - alert: SessionDisconnected
          expr: whatsapp_session_status == 0
          for: 5m
          labels:
            severity: warning
          annotations:
            summary: "WhatsApp session disconnected"
            
        - alert: HighBanRisk
          expr: whatsapp_ban_risk_score > 80
          for: 1m
          labels:
            severity: critical
          annotations:
            summary: "High ban risk detected"
            
        - alert: LowHealthScore
          expr: whatsapp_session_health_score < 50
          for: 10m
          labels:
            severity: warning
  ```

**Technical Implementation:**
```javascript
// whatsapp-service/metrics/prometheus.js
const promClient = require('prom-client');

class WhatsAppMetrics {
    constructor() {
        this.register = new promClient.Registry();
        
        // Session status gauge
        this.sessionStatus = new promClient.Gauge({
            name: 'whatsapp_session_status',
            help: 'WhatsApp session connection status',
            labelNames: ['workspace_id', 'session_id', 'phone_number'],
            registers: [this.register]
        });
        
        // Messages sent counter
        this.messagesSent = new promClient.Counter({
            name: 'whatsapp_messages_sent_total',
            help: 'Total WhatsApp messages sent',
            labelNames: ['workspace_id', 'session_id', 'type', 'status'],
            registers: [this.register]
        });
        
        // QR generation duration
        this.qrGeneration = new promClient.Histogram({
            name: 'whatsapp_qr_generation_duration_seconds',
            help: 'QR code generation duration',
            buckets: [0.5, 1, 2, 3, 5, 10],
            registers: [this.register]
        });
        
        // Health score gauge
        this.healthScore = new promClient.Gauge({
            name: 'whatsapp_session_health_score',
            help: 'WhatsApp session health score (0-100)',
            labelNames: ['workspace_id', 'session_id'],
            registers: [this.register]
        });
        
        // Ban risk gauge
        this.banRiskScore = new promClient.Gauge({
            name: 'whatsapp_ban_risk_score',
            help: 'WhatsApp session ban risk score (0-100)',
            labelNames: ['workspace_id', 'session_id'],
            registers: [this.register]
        });
    }
    
    updateSessionStatus(workspaceId, sessionId, phoneNumber, isConnected) {
        this.sessionStatus.labels(workspaceId, sessionId, phoneNumber)
            .set(isConnected ? 1 : 0);
    }
    
    recordMessageSent(workspaceId, sessionId, type, status) {
        this.messagesSent.labels(workspaceId, sessionId, type, status).inc();
    }
    
    getMetrics() {
        return this.register.metrics();
    }
}

// Expose metrics endpoint
app.get('/metrics', async (req, res) => {
    res.set('Content-Type', promClient.register.contentType);
    res.end(await metrics.getMetrics());
});
```

---

#### FR-12.2: Disaster Recovery & Backup
**Priority:** HIGH  
**User Story:** Sebagai system admin, saya ingin automated backup dan disaster recovery procedures.

**Acceptance Criteria:**
- [ ] Automated daily backups:
  - Database: Full mysqldump
  - Session files: Tar + gzip
  - Configuration files: .env, configs
- [ ] Encrypted backup storage: AES-256-CBC
- [ ] Backup retention policy: Keep 30 days, rotate to S3/cloud
- [ ] Backup verification: Daily integrity checks
- [ ] Restore procedures documented dan tested monthly
- [ ] RPO (Recovery Point Objective): 24 hours
- [ ] RTO (Recovery Time Objective): 2 hours

**Technical Implementation:**
```bash
#!/bin/bash
# filepath: scripts/backup-whatsapp-sessions.sh

BACKUP_DIR="/backups/whatsapp/$(date +%Y%m%d_%H%M%S)"
BACKUP_KEY=$(cat /etc/whatsapp/backup.key)
S3_BUCKET="s3://myapp-backups/whatsapp"

# Create backup directory
mkdir -p $BACKUP_DIR

# 1. Backup database
echo "Backing up database..."
mysqldump -u $DB_USER -p$DB_PASSWORD $DB_NAME \
    whatsapp_sessions \
    whatsapp_session_events \
    whatsapp_session_access_logs \
    contact_sessions \
    > $BACKUP_DIR/whatsapp_database.sql

# 2. Backup session files
echo "Backing up session files..."
tar -czf $BACKUP_DIR/session_data.tar.gz \
    /var/www/whatsapp-service/sessions/

# 3. Backup configuration
echo "Backing up configuration..."
cp /var/www/whatsapp-service/.env $BACKUP_DIR/env_backup.txt
cp /var/www/html/.env $BACKUP_DIR/laravel_env_backup.txt

# 4. Encrypt backups
echo "Encrypting backups..."
openssl enc -aes-256-cbc -salt \
    -in $BACKUP_DIR/whatsapp_database.sql \
    -out $BACKUP_DIR/whatsapp_database.sql.enc \
    -k $BACKUP_KEY

openssl enc -aes-256-cbc -salt \
    -in $BACKUP_DIR/session_data.tar.gz \
    -out $BACKUP_DIR/session_data.tar.gz.enc \
    -k $BACKUP_KEY

# Remove unencrypted files
rm $BACKUP_DIR/whatsapp_database.sql
rm $BACKUP_DIR/session_data.tar.gz

# 5. Upload to S3
echo "Uploading to S3..."
aws s3 cp $BACKUP_DIR $S3_BUCKET/$(basename $BACKUP_DIR)/ --recursive

# 6. Verify backup integrity
echo "Verifying backup integrity..."
for file in $BACKUP_DIR/*.enc; do
    openssl enc -aes-256-cbc -d -in $file -k $BACKUP_KEY > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "✓ $(basename $file) verified"
    else
        echo "✗ $(basename $file) FAILED verification"
        exit 1
    fi
done

# 7. Cleanup old backups (keep 30 days)
echo "Cleaning up old backups..."
find /backups/whatsapp -type d -mtime +30 -exec rm -rf {} \;

echo "Backup completed successfully: $BACKUP_DIR"

# Log to monitoring
curl -X POST http://localhost:8000/api/admin/backup-logs \
    -H "Content-Type: application/json" \
    -d "{\"type\":\"whatsapp_sessions\",\"status\":\"success\",\"backup_dir\":\"$BACKUP_DIR\"}"
```

**Restore Procedures:**
```bash
#!/bin/bash
# filepath: scripts/restore-whatsapp-sessions.sh

BACKUP_DIR=$1
BACKUP_KEY=$(cat /etc/whatsapp/backup.key)

if [ -z "$BACKUP_DIR" ]; then
    echo "Usage: $0 <backup_directory>"
    exit 1
fi

# 1. Stop services
echo "Stopping services..."
pm2 stop whatsapp-service
sudo systemctl stop nginx

# 2. Decrypt and restore database
echo "Restoring database..."
openssl enc -aes-256-cbc -d \
    -in $BACKUP_DIR/whatsapp_database.sql.enc \
    -out /tmp/whatsapp_database.sql \
    -k $BACKUP_KEY

mysql -u $DB_USER -p$DB_PASSWORD $DB_NAME < /tmp/whatsapp_database.sql
rm /tmp/whatsapp_database.sql

# 3. Decrypt and restore session files
echo "Restoring session files..."
openssl enc -aes-256-cbc -d \
    -in $BACKUP_DIR/session_data.tar.gz.enc \
    -out /tmp/session_data.tar.gz \
    -k $BACKUP_KEY

tar -xzf /tmp/session_data.tar.gz -C /var/www/whatsapp-service/
rm /tmp/session_data.tar.gz

# 4. Restore configuration (manual review required)
echo "Configuration files available in $BACKUP_DIR/env_backup.txt"
echo "Please review and apply manually"

# 5. Restart services
echo "Restarting services..."
pm2 start whatsapp-service
sudo systemctl start nginx

echo "Restore completed. Please verify system functionality."
```

---

#### FR-12.3: Zero-Downtime Deployment
**Priority:** MEDIUM  
**User Story:** Sebagai DevOps engineer, saya ingin deploy updates tanpa disconnect active WhatsApp sessions.

**Acceptance Criteria:**
- [ ] Blue-green deployment strategy:
  1. Start new Node.js instance on different port
  2. Migrate active sessions via Redis state sync
  3. Switch load balancer/nginx upstream
  4. Graceful shutdown old instance
- [ ] Session migration without disconnection
- [ ] Rollback capability within 5 minutes
- [ ] Deployment success rate > 95%
- [ ] Deployment window: Off-peak hours (2-4 AM) preferred
- [ ] Automated health checks before/after deployment

**Technical Implementation:**
```yaml
# filepath: .github/workflows/deploy-zero-downtime.yml
name: Zero Downtime Deployment

on:
  push:
    branches: [production]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v2
        
      - name: Health Check Before
        run: |
          curl -f http://production-server:3000/health || exit 1
          
      - name: Start New Node.js Instance
        run: |
          ssh deploy@production-server << 'EOF'
            cd /var/www/whatsapp-service
            git pull origin production
            npm install
            PORT=3001 pm2 start server.js --name whatsapp-service-new
            sleep 10
          EOF
          
      - name: Migrate Active Sessions
        run: |
          ssh deploy@production-server << 'EOF'
            node /var/www/scripts/migrate-sessions.js \
              --from-port=3000 \
              --to-port=3001
          EOF
          
      - name: Switch Load Balancer
        run: |
          ssh deploy@production-server << 'EOF'
            # Update nginx upstream
            sudo sed -i 's/localhost:3000/localhost:3001/g' /etc/nginx/sites-available/whatsapp
            sudo nginx -t && sudo nginx -s reload
          EOF
          
      - name: Health Check After
        run: |
          curl -f http://production-server:3001/health || exit 1
          
      - name: Graceful Shutdown Old Instance
        run: |
          ssh deploy@production-server << 'EOF'
            # Wait for active operations to complete
            curl -X POST http://localhost:3000/shutdown/graceful
            sleep 30
            pm2 delete whatsapp-service
          EOF
          
      - name: Rename New Instance
        run: |
          ssh deploy@production-server << 'EOF'
            pm2 delete whatsapp-service-new
            PORT=3000 pm2 start server.js --name whatsapp-service
            sudo sed -i 's/localhost:3001/localhost:3000/g' /etc/nginx/sites-available/whatsapp
            sudo nginx -s reload
          EOF
```

---

## NON-FUNCTIONAL REQUIREMENTS

### NFR-1: Performance
- **Response Time:** API response < 200ms (p95)
- **Message Delivery:** < 2 seconds end-to-end
- **QR Code Generation:** < 3 seconds
- **Session Initialization:** < 10 seconds
- **Campaign Processing:** 1000 messages in < 1 hour (with rate limiting)
- **Chat Load Time:** < 500ms untuk 50 chats

### NFR-2: Scalability
- **Concurrent Sessions:** Support 100+ sessions per Node.js instance
- **Horizontal Scaling:** Node.js service can be load-balanced
- **Database:** Handle 1M+ chats, 100K+ contacts per workspace
- **Redis Queue:** Process 10K messages/hour

### NFR-3: Reliability
- **Uptime:** 99.5% (excluding maintenance windows)
- **Session Persistence:** 99% success rate after restart
- **Message Delivery:** 95% success rate
- **Auto-Recovery:** Reconnect within 2 minutes of disconnection

### NFR-4: Security
- **Session Data:** Encrypted at rest (AES-256)
- **API Communication:** HTTPS only
- **Authentication:** JWT tokens with 1-hour expiry
- **Rate Limiting:** 
  - API: 100 requests/minute per workspace
  - Messages: 30 messages/minute per session
- **Access Control:** Role-based permissions

### NFR-5: Maintainability
- **Code Coverage:** Minimum 70% for critical services
- **Documentation:** All API endpoints documented (OpenAPI/Swagger)
- **Logging:** Structured logs (JSON format) untuk easy parsing
- **Monitoring:** APM integration (New Relic / Datadog compatible)

### NFR-6: Usability
- **Setup Time:** < 2 minutes untuk add new WhatsApp number
- **Learning Curve:** Existing users dapat adopt within 5 minutes
- **Error Messages:** Clear, actionable error messages
- **Mobile Responsive:** All UI components work on mobile

---

## ACCEPTANCE CRITERIA SUMMARY

### Must Have (P0 - Critical)
- ✅ Multi-number setup via QR code
- ✅ Chat routing per WhatsApp session
- ✅ Campaign multi-session distribution
- ✅ Node.js service architecture
- ✅ Laravel-Node communication
- ✅ Session persistence & recovery
- ✅ Backward compatibility dengan Meta API
- ✅ Single command startup
- ✅ Database migrations

### Should Have (P1 - High)
- ✅ Template dual system (Meta + Web JS)
- ✅ Automation session-aware
- ✅ Health monitoring dashboard
- ✅ Plan-based limits
- ✅ Admin panel enhancements
- ✅ Rate limiting implementation

### Could Have (P2 - Medium)
- ✅ Campaign performance by number
- ✅ Contact session history
- ✅ Welcome message per number
- ✅ Session auto-recovery
- ✅ Export health reports

### Won't Have (Out of Scope)
- ❌ AI-powered message routing
- ❌ WhatsApp Business API migration tool
- ❌ Multi-device support (beyond web session)
- ❌ Voice/Video call features
- ❌ WhatsApp Status/Stories

---

## TESTING REQUIREMENTS

### Unit Tests
- [ ] WhatsApp session CRUD operations
- [ ] Message routing logic
- [ ] Campaign distribution algorithm
- [ ] Rate limiting mechanism
- [ ] Template variable replacement

### Integration Tests
- [ ] Laravel ↔ Node.js communication
- [ ] Redis queue processing
- [ ] Webhook callbacks
- [ ] Database transactions

### End-to-End Tests
- [ ] Complete QR setup flow
- [ ] Send message from specific session
- [ ] Campaign with multi-session distribution
- [ ] Session disconnect & recovery
- [ ] Migration from Meta API to Web JS

### Load Tests
- [ ] 100 concurrent sessions
- [ ] 1000 messages/minute throughput
- [ ] 10K recipients campaign
- [ ] 50 users accessing chat simultaneously

---

## DEPLOYMENT REQUIREMENTS

### Prerequisites
- [x] PHP 8.1+
- [x] MySQL 8.0+
- [x] Redis 6.0+
- [x] Node.js 18+
- [x] Chromium (for puppeteer)
- [x] PM2 (production)
- [x] Supervisor (alternative)

### Environment Variables
```env
# Laravel .env additions
WHATSAPP_NODE_SERVICE_URL=http://localhost:3000
WHATSAPP_NODE_SOCKETIO_URL=http://localhost:3001
WHATSAPP_NODE_API_KEY=your-secret-api-key
WHATSAPP_SESSION_ENCRYPTION_KEY=32-char-key
REDIS_QUEUE_CONNECTION=redis

# Node.js .env (whatsapp-service/.env)
LARAVEL_URL=http://localhost:8000
LARAVEL_API_URL=http://localhost:8000/api
LARAVEL_WEBHOOK_URL=http://localhost:8000/api/whatsapp/webhook
API_KEY=your-secret-api-key
REDIS_URL=redis://localhost:6379
PORT=3000
WEBSOCKET_PORT=3001
SESSION_STORAGE_PATH=./sessions
NODE_ENV=development
```

### Migration Steps
```bash
# 1. Backup database
php artisan db:backup

# 2. Run migrations
php artisan migrate

# 3. Install Node.js service
cd whatsapp-service && npm install

# 4. Setup PM2
pm2 start ecosystem.config.js

# 5. Verify health
curl http://localhost:8000/api/health
curl http://localhost:3000/health
```

---

## SUCCESS METRICS

### Adoption Metrics
- **Target:** 80% of workspaces using Web JS within 3 months
- **Current Meta API users:** Smoothly migrated without incidents

### Performance Metrics
- **Message Delivery Rate:** > 95%
- **Session Uptime:** > 99%
- **QR Setup Success Rate:** > 98%
- **Average Messages per Session:** 500+ per day

### Business Metrics
- **Plan Upgrades:** 20% increase (due to multi-number feature)
- **Customer Satisfaction:** > 4.5/5
- **Support Tickets:** < 5% increase (documentation effective)

---

## RISK ASSESSMENT

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| WhatsApp ban due to rate limit | HIGH | MEDIUM | Implement strict rate limiting, delays |
| Node.js service crashes | HIGH | LOW | PM2 auto-restart, health monitoring |
| Session data loss | HIGH | LOW | Encrypted backup, Redis persistence |
| Migration data loss | CRITICAL | LOW | Comprehensive backup, staging tests |
| Performance degradation | MEDIUM | MEDIUM | Load testing, optimization |
| Security breach | CRITICAL | LOW | Encryption, auth, rate limiting |

---

## GLOSSARY

- **Session:** WhatsApp Web JS client instance connected to a phone number
- **Provider:** WhatsApp service provider (Meta API or Web JS)
- **Workspace:** Tenant/organization using the platform
- **Campaign:** Bulk message sending feature
- **Automation:** Auto-reply and workflow rules
- **Health Score:** Numeric indicator (0-100) of session reliability

---

## APPENDIX

### A. User-Provided Solutions Reference
Lihat user response untuk detailed technical solutions:
- Database architecture (whatsapp_sessions table structure)
- Laravel-Node.js communication patterns
- Campaign distribution algorithm
- Automation enhancement strategy
- Health monitoring implementation
- Single command startup configuration

### B. Related Documents
- `assumption.md` - Initial assumptions and Phase 1 verification
- `design.md` (to be created) - Detailed architecture design
- `tasks.md` (to be created) - Implementation task breakdown
- API documentation (to be created) - REST API specifications

---

**Document Status:** APPROVED FOR IMPLEMENTATION  
**Next Step:** Create `design.md` with detailed architecture and technical specifications  
**Estimated Implementation Time:** 4-6 weeks (with testing)
