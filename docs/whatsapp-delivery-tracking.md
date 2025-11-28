# WhatsApp Message Delivery Tracking - Research Document

## 📌 Overview

Dokumen ini berisi hasil riset tentang bagaimana mendapatkan informasi status pengiriman pesan WhatsApp (Sent, Delivered, Read, Failed) menggunakan WhatsApp Web JS.

---

## 🎯 Message ACK (Acknowledgment) System

### MessageAck Enum dari WhatsApp Web JS

WhatsApp menggunakan sistem ACK (Acknowledgment) untuk melacak status pesan:

| ACK Value | Constant | Status | Keterangan |
|-----------|----------|--------|------------|
| -1 | `ACK_ERROR` | Error | Pesan gagal dikirim |
| 0 | `ACK_PENDING` | Pending | Menunggu dikirim |
| 1 | `ACK_SERVER` | Sent | Pesan sudah sampai di server WhatsApp |
| 2 | `ACK_DEVICE` | Sent | Pesan sudah dikirim ke device penerima |
| 3 | `ACK_READ` | Delivered | Pesan sudah diterima oleh penerima |
| 4 | `ACK_PLAYED` | Read/Played | Pesan sudah dibaca (atau media sudah diputar) |

### Event Handler di Node.js

```javascript
// File: whatsapp-service/src/managers/SessionManager.js

client.on('message_ack', async (message, ack) => {
    // Convert WhatsApp ACK to our status format
    let status;
    switch (ack) {
        case 1:
            status = 'pending'; // Message sent to WhatsApp server
            break;
        case 2:
            status = 'sent'; // Message delivered to recipient's phone
            break;
        case 3:
            status = 'delivered'; // Message delivered to recipient
            break;
        case 4:
            status = 'read'; // Message read by recipient
            break;
        default:
            status = 'pending';
    }

    // Broadcast status update to Laravel
    await this.sendToLaravel('message_status_updated', {
        workspace_id: workspaceId,
        session_id: sessionId,
        message_id: message.id._serialized,
        status: status,
        ack_level: ack,
        timestamp: message.timestamp
    });
});
```

---

## 📊 Data Flow Architecture

```
┌─────────────────────┐
│   WhatsApp Server   │
└─────────┬───────────┘
          │ ACK Events
          ▼
┌─────────────────────┐
│  WhatsApp Web JS    │
│  (Node.js Service)  │
└─────────┬───────────┘
          │ message_ack event
          ▼
┌─────────────────────┐
│  SessionManager.js  │  → Translates ACK to status
└─────────┬───────────┘
          │ HTTP POST to Laravel
          ▼
┌─────────────────────────────────────┐
│  WebhookController.php              │
│  - handleMessageStatusUpdated()     │
│  - handleMessageDelivered()         │
│  - handleMessageRead()              │
└─────────┬───────────────────────────┘
          │ Dispatch Job
          ▼
┌─────────────────────────────────────┐
│  UpdateMessageStatusJob.php         │
│  - Update chats table               │
│  - Update campaign_logs metadata    │
│  - Trigger UpdateCampaignStatisticsJob │
└─────────┬───────────────────────────┘
          │
          ▼
┌─────────────────────────────────────┐
│  UpdateCampaignStatisticsJob.php    │
│  - Recalculate campaign stats       │
│  - Update Campaign model            │
│  - Broadcast real-time event        │
└─────────────────────────────────────┘
```

---

## 📁 File Locations

### Node.js (WhatsApp Service)

| File | Purpose |
|------|---------|
| `whatsapp-service/src/managers/SessionManager.js` | Event handler untuk `message_ack` |

### Laravel (Backend)

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Api/v1/WhatsApp/WebhookController.php` | Receive webhook events |
| `app/Jobs/UpdateMessageStatusJob.php` | Update message status in DB |
| `app/Jobs/UpdateCampaignStatisticsJob.php` | Recalculate campaign stats |
| `app/Models/Campaign.php` | `getCounts()` and `updatePerformanceCounters()` |
| `app/Models/Chat.php` | Store message status |

---

## 📊 Database Schema

### chats Table
```sql
ALTER TABLE chats ADD COLUMN message_status ENUM('pending', 'sent', 'delivered', 'read', 'failed') DEFAULT 'pending';
ALTER TABLE chats ADD COLUMN ack_level TINYINT DEFAULT NULL;
ALTER TABLE chats ADD COLUMN delivered_at TIMESTAMP NULL;
ALTER TABLE chats ADD COLUMN read_at TIMESTAMP NULL;
```

### campaign_logs Table
```sql
-- metadata JSON field stores:
{
    "message_status": "delivered",
    "ack_level": 3,
    "status_updated_at": "2025-11-28T10:30:00Z",
    "delivered_at": "2025-11-28T10:30:05Z",
    "read_at": "2025-11-28T10:35:00Z"
}
```

### campaigns Table
```sql
-- Performance counters
messages_sent INT DEFAULT 0
messages_delivered INT DEFAULT 0
messages_read INT DEFAULT 0
messages_failed INT DEFAULT 0
```

---

## 🔍 Campaign Statistics Query

```php
// File: app/Models/Campaign.php - getCounts()

public function getCounts(){
    return $this->campaignLogs()
        ->selectRaw('
            COUNT(*) as total_message_count,
            SUM(CASE WHEN campaign_logs.status = "success" 
                AND chat.status IN ("accepted", "sent", "delivered", "read") THEN 1 ELSE 0 END) as total_sent_count,
            SUM(CASE WHEN campaign_logs.status = "success" 
                AND chat.status IN ("delivered", "read") THEN 1 ELSE 0 END) as total_delivered_count,
            SUM(CASE WHEN campaign_logs.status = "failed" THEN 1 ELSE 0 END) +
                SUM(CASE WHEN campaign_logs.status = "success" 
                    AND chat.status = "failed" THEN 1 ELSE 0 END) as total_failed_count,
            SUM(CASE WHEN campaign_logs.status = "success" 
                AND chat.status = "read" THEN 1 ELSE 0 END) as total_read_count
        ')
        ->leftJoin('chats as chat', 'chat.id', '=', 'campaign_logs.chat_id')
        ->where('campaign_logs.campaign_id', $this->id)
        ->first();
}
```

---

## ⚠️ Current Issue: chat_id is NULL

### Problem
Campaign logs tidak memiliki `chat_id`, sehingga tidak bisa di-join dengan tabel `chats` untuk mendapatkan status delivery.

### Root Cause Analysis
Dari database check:
```
Log ID: 9 - Contact: Laksmana Tri Moerdani
  Log Status: success
  Chat exists: No (chat_id is NULL)
```

### Solution Required
Saat mengirim pesan campaign, kita perlu:
1. Simpan `chat_id` ke `campaign_logs` setelah pesan berhasil dikirim
2. Atau gunakan `whatsapp_message_id` untuk tracking

---

## 🔧 Implementation Checklist

### ✅ Already Implemented
- [x] `message_ack` event handler di Node.js
- [x] `WebhookController` menerima event
- [x] `UpdateMessageStatusJob` untuk update Chat
- [x] `UpdateCampaignStatisticsJob` untuk update Campaign stats
- [x] Real-time broadcast via Laravel Echo
- [x] Campaign model dengan `getCounts()` dan `updatePerformanceCounters()`

### ❌ Needs Fix
- [ ] **Link campaign_logs to chats**: Simpan `chat_id` saat kirim campaign
- [ ] **Frontend display**: Update Campaign Details page untuk show real-time stats
- [ ] **Alternative tracking via whatsapp_message_id** jika chat_id tidak tersedia

---

## 🚀 Recommended Fix

### Option 1: Link via chat_id (Preferred)
Saat ProcessCampaignMessagesJob mengirim pesan, simpan chat_id ke campaign_log:

```php
// In ProcessCampaignMessagesJob
$chat = Chat::create([...]);
$campaignLog->update(['chat_id' => $chat->id]);
```

### Option 2: Link via whatsapp_message_id
Update query getCounts() untuk join via whatsapp_message_id:

```php
->leftJoin('chats as chat', function($join) {
    $join->on('chat.whatsapp_message_id', '=', 
        DB::raw("JSON_EXTRACT(campaign_logs.metadata, '$.data.wam_id')"));
})
```

---

## 📈 Rate Calculations

```php
// Delivery Rate = (Delivered / Sent) × 100%
$deliveryRate = $sent > 0 ? ($delivered / $sent) * 100 : 0;

// Read Rate = (Read / Delivered) × 100%  
$readRate = $delivered > 0 ? ($read / $delivered) * 100 : 0;

// Success Rate = (Sent / Total) × 100%
$successRate = $total > 0 ? ($sent / $total) * 100 : 0;
```

---

## 📚 References

- [WhatsApp Web JS Documentation](https://docs.wwebjs.dev/)
- [MessageAck Enum](https://docs.wwebjs.dev/global.html#MessageAck)
- [Client Events - message_ack](https://docs.wwebjs.dev/Client.html#event:message_ack)
- [Message.ack Property](https://docs.wwebjs.dev/Message.html#ack)
- [Message.getInfo()](https://docs.wwebjs.dev/Message.html#getInfo) - Returns MessageInfo with delivery details

---

## 📝 MessageInfo Structure

Method `Message.getInfo()` returns:
```javascript
{
    delivery: [
        { id: ContactId, t: timestamp },  // Delivered to these contacts
        ...
    ],
    deliveryRemaining: number,  // Count of not yet delivered
    played: [
        { id: ContactId, t: timestamp },  // Voice message played by
        ...
    ],
    playedRemaining: number,
    read: [
        { id: ContactId, t: timestamp },  // Read by these contacts
        ...
    ],
    readRemaining: number
}
```

This is useful for group messages to track individual read receipts.

---

## 🐛 Bug Fix: chat_id Null Issue (Fixed)

### Problem

Campaign logs tidak memiliki `chat_id` yang menyebabkan `message_ack` events tidak dapat memperbarui status delivery untuk campaigns.

**Root Cause:**
- `MessageService.php` (untuk WebJS) mengembalikan response format yang berbeda dari `MessageSendingService.php` (untuk Meta API)
- `MessageService` mengembalikan: `{ success: true, data: $chat, ... }`
- `MessageSendingService` mengembalikan: `{ success: true, data: { chat: $chat }, ... }`
- `ProcessSingleCampaignLogJob` dan `SendCampaignJob` mengakses `$responseObject->data->chat->id`

### Evidence

```bash
# Data check menunjukkan semua campaign_logs memiliki chat_id = NULL
php artisan tinker --execute="echo App\Models\CampaignLog::whereNull('chat_id')->count();"
# Output: 10

# Tetapi metadata menunjukkan ada Chat ID tersimpan
{
    "data": {
        "id": 524,  // <-- Chat ID ada di data.id, bukan data.chat.id!
        ...
    }
}
```

### Solution Applied

**File: `app/Services/WhatsApp/MessageService.php`**

Fixed 3 methods to return consistent response format:

1. **sendMessage()** - Line ~88-95
2. **sendTemplateMessage()** - Line ~641-649  
3. **sendBulkMessages()** - Line ~155

```php
// BEFORE (wrong format)
return (object) [
    'success' => true,
    'data' => $chat,  // Chat object directly
    ...
];

// AFTER (consistent with MessageSendingService)
$responseData = (object) ['chat' => $chat];
return (object) [
    'success' => true,
    'data' => $responseData,  // Chat wrapped in data->chat
    ...
];
```

### Impact

After this fix:
- `campaign_logs.chat_id` will be properly populated
- `message_ack` events can link to correct Chat records
- Delivery rate and read rate can be accurately calculated
- CampaignLogTable.vue can display correct status from linked Chat

### Files Changed

| File | Changes |
|------|---------|
| `app/Services/WhatsApp/MessageService.php` | Fixed response format in sendMessage(), sendTemplateMessage(), sendBulkMessages() |

---

*Document created: November 28, 2025*
*Last updated: November 28, 2025*
