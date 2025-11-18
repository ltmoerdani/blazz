# 🔧 Quick Reference: Real-Time WebSocket Fixes

**Quick access guide untuk developer yang handle real-time notifications.**

---

## 🎯 ROOT CAUSES (sudah di-fix)

1. **Event Name Mismatch** → `.NewChatEvent` vs `NewChatEvent`
2. **Parameter Swapping** → 7 lokasi tukar parameter constructor
3. **Double Counting** → App.vue dan Index.vue increment bersamaan

---

## ✅ FIXES APPLIED

### Frontend Event Listeners

```javascript
// ❌ WRONG (Pusher format)
chatChannel.listen('.NewChatEvent', (event) => { ... })

// ✅ CORRECT (Reverb format)
chatChannel.listen('NewChatEvent', (event) => { ... })
```

**Location:** 
- `resources/js/Pages/User/Chat/Index.vue` (line 549)
- `resources/js/Pages/User/Layout/App.vue` (line 83)

---

### Backend Event Broadcasting

```php
// ❌ WRONG - Parameters swapped
event(new NewChatEvent($contact->id, [
    'type' => 'text',
    'message' => $message['text']['body'],
]));

// ✅ CORRECT - Proper order: ($data, $workspaceId)
event(new NewChatEvent([
    'type' => 'text',
    'message' => $message['text']['body'],
    'contact_id' => $contact->id,
], $workspace->id));
```

**Fixed in:**
- `app/Services/ChatService.php` (6 methods)
- `app/Http/Controllers/Api/v1/WhatsAppWebhookController.php` (1 method)

---

### Double Counting Prevention

```javascript
// ✅ FIXED - Route detection to prevent double counting
echo.channel(channelName).listen('NewChatEvent', (event) => {
    const isOnChatPage = window.location.pathname.includes('/user/chat');
    
    if (!isOnChatPage) {
        unreadMessages.value += 1; // Only if NOT on chat page
    }
});
```

**Location:** `resources/js/Pages/User/Layout/App.vue`

---

## 🧪 QUICK TEST

### 1. Check WebSocket Connection
```javascript
// Browser console
console.log('Echo:', window.Echo);
console.log('State:', window.Echo.connector.pusher.connection.state);
// Expected: "connected"
```

### 2. Test Event Reception
```bash
# Terminal 1: Start Reverb with debug
php artisan reverb:start --debug

# Terminal 2: Broadcast test event
php artisan tinker
>>> event(new \App\Events\NewChatEvent(['type' => 'test'], 1));
```

### 3. Verify Badge Update
1. Open chat page
2. Send WhatsApp message
3. Check console logs for: `"🔔 Badge updated locally"`

---

## 🔍 DEBUGGING COMMANDS

### Backend
```bash
# Check Reverb logs
php artisan reverb:start --debug | grep "published"

# Test event manually
php artisan tinker
>>> event(new \App\Events\NewChatEvent(['test' => true], 1));

# Clear config cache
php artisan config:clear && php artisan cache:clear
```

### Frontend
```javascript
// Monitor all WebSocket events
window.Echo.connector.pusher.connection.bind_global((event, data) => {
    console.log('WebSocket event:', event, data);
});

// Check active subscriptions
console.log('Channels:', Object.keys(window.Echo.connector.channels));

// Test subscription
window.Echo.channel('chats.ch1').listen('NewChatEvent', console.log);
```

---

## 📊 EXPECTED BEHAVIOR

| Scenario | Expected Result |
|----------|----------------|
| Message to non-active contact | Badge +1, contact moves to top |
| Message to active contact | Message appears in thread, badge stays 0 |
| User on different page | Global counter +1, sound plays |
| Open chat with unread | Badge resets to 0 |
| Multiple contacts | All badges update independently |

---

## 🚨 COMMON ISSUES

### Events Not Received
```bash
# 1. Restart Reverb
php artisan reverb:restart

# 2. Check .env
BROADCAST_DRIVER=reverb
REVERB_APP_KEY=...
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
```

### Double Counting
```javascript
// Check route detection
console.log('Is on chat page:', window.location.pathname.includes('/user/chat'));
```

### Badge Not Updating
```javascript
// Verify contact found
const found = rows.value.data.find(c => c.id == contactId);
console.log('Contact found:', found);
```

---

## 📁 FILES MODIFIED

```
app/
├── Services/
│   └── ChatService.php (6 fixes)
└── Http/Controllers/Api/v1/
    └── WhatsAppWebhookController.php (1 fix)

resources/js/
└── Pages/User/
    ├── Chat/
    │   └── Index.vue (event name fix)
    └── Layout/
        └── App.vue (event name + double counting fix)
```

---

## 🔗 REFERENCES

- **Comprehensive Report:** `25-realtime-reverb-critical-fixes-report.md`
- **Testing Guide:** `26-realtime-testing-guide.md`
- **Executive Summary:** `27-executive-summary-realtime-fixes.md`

---

## 💡 KEY TAKEAWAYS

1. **Reverb ≠ Pusher**: Event format berbeda, jangan pakai dot prefix
2. **Parameter Order Matters**: Always `(data, workspaceId)`, not `(id, data)`
3. **Single Source of Truth**: Prevent double counting dengan route detection
4. **Test with Browser**: Node.js test tidak cukup, test dengan actual browser

---

**Last Updated:** November 18, 2025  
**Status:** ✅ Production Ready
