# 🧪 Real-Time WebSocket Testing Guide

**Date:** November 18, 2025  
**Branch:** staging-chats-fix-notif  
**Purpose:** Comprehensive testing untuk verify real-time notification fixes

---

## 📋 Pre-requisites

### Backend Setup
```bash
# 1. Start Reverb server
php artisan reverb:start --debug

# 2. Start queue worker (for background jobs)
php artisan queue:work

# 3. Start Laravel server
php artisan serve
```

### Browser Setup
```javascript
// Open browser console and enable verbose logging
localStorage.setItem('debug', '*');

// Check Echo initialization
console.log('Echo instance:', window.Echo);
console.log('Active channels:', Object.keys(window.Echo.connector.channels));
```

---

## 🎯 Test Scenarios

### ✅ Scenario 1: Badge Update for Non-Active Contact

**Objective:** Verify badge increments when message arrives for contact that's NOT currently open.

**Steps:**
1. Open chat page di browser
2. Buka chat dengan Contact A
3. Kirim WhatsApp message dari Contact B (bukan Contact A)
4. **Expected Results:**
   - ✅ Badge Contact B bertambah +1
   - ✅ Contact B pindah ke top of list
   - ✅ Chat thread Contact A tidak berubah
   - ✅ Sound notification terdengar

**Verification:**
```javascript
// Check console logs
// ✅ Should see: "📊 [App.vue] User on chat page, Index.vue will handle badge update"
// ✅ Should see: "🔔 Badge updated locally: { contactId: X, unreadCount: Y }"
```

---

### ✅ Scenario 2: Real-Time Message Display in Active Chat

**Objective:** Verify message muncul langsung di chat thread tanpa refresh.

**Steps:**
1. Open chat page di browser
2. Buka chat dengan Contact A
3. Kirim WhatsApp message dari Contact A
4. **Expected Results:**
   - ✅ Message langsung muncul di chat thread
   - ✅ Badge Contact A tetap 0 (tidak bertambah)
   - ✅ Tidak ada counter increment
   - ✅ Auto-scroll ke bottom thread

**Verification:**
```javascript
// Check console logs
// ✅ Should see: "✅ Scenario 2: Message for current chat - updating thread only"
// ✅ Should see: "✅ Message added to current chat thread"
// ❌ Should NOT see: "Badge updated locally"
```

---

### ✅ Scenario 3: Badge Update When on Different Page

**Objective:** Verify badge increments di sidebar saat user tidak di chat page.

**Steps:**
1. Navigate ke page lain (Dashboard, Campaigns, dll)
2. Kirim WhatsApp message dari Contact A
3. Check sidebar counter
4. **Expected Results:**
   - ✅ Global unread counter di sidebar bertambah +1
   - ✅ Sound notification terdengar
   - ✅ Badge tetap persisten saat navigate antar pages

**Verification:**
```javascript
// Check console logs
// ✅ Should see: "📊 [App.vue] User not on chat page, incrementing global counter"
// ✅ Global counter in sidebar should increase
```

---

### ✅ Scenario 4: Badge Reset When Opening Chat

**Objective:** Verify badge reset to 0 saat user membuka chat.

**Steps:**
1. Pastikan Contact A memiliki unread messages (badge > 0)
2. Click Contact A di sidebar
3. **Expected Results:**
   - ✅ Badge Contact A berubah jadi 0
   - ✅ Chat thread ter-load dengan semua messages
   - ✅ Global counter di sidebar berkurang sesuai unread count Contact A
   - ✅ Backend mark messages as read

**Verification:**
```javascript
// Check network tab
// ✅ Should see: GET /chats/{uuid} with status 200
// ✅ Response should have: contact.unread_messages = 0

// Check console logs
// ✅ Should see: "📭 Sidebar updated: unread reset for [contact name]"
```

---

### ✅ Scenario 5: Multiple Contacts Simultaneous Updates

**Objective:** Verify system handle multiple incoming messages dari berbagai contacts.

**Steps:**
1. Open chat page dengan Contact A active
2. Kirim messages bersamaan dari Contact B, C, D
3. **Expected Results:**
   - ✅ All badges update correctly
   - ✅ Contacts reorder di sidebar (latest first)
   - ✅ Active chat (Contact A) tetap tidak terganggu
   - ✅ Sound hanya play once per message

**Verification:**
```javascript
// Check console logs
// ✅ Should see multiple: "🔔 Badge updated locally"
// ✅ Each with different contactId
// ✅ No duplicate increments for same message
```

---

## 🔍 Advanced Debugging

### Check WebSocket Connection
```javascript
// Verify connection state
const pusher = window.Echo.connector.pusher;
console.log('Connection state:', pusher.connection.state);
// Expected: "connected"

// Check subscribed channels
console.log('Channels:', Object.keys(pusher.channels.channels));
// Expected: ["chats.ch1"] atau workspace ID lain

// Listen to all Pusher events
pusher.connection.bind_global((eventName, data) => {
    console.log('Pusher event:', eventName, data);
});
```

### Monitor Event Flow
```javascript
// Add listener untuk raw WebSocket messages
pusher.connection.bind('message', (event) => {
    console.log('📨 Raw WebSocket message:', event);
});

// Monitor subscription status
const channel = pusher.channels.channels['chats.ch1'];
if (channel) {
    channel.bind('pusher:subscription_succeeded', () => {
        console.log('✅ Subscribed successfully');
    });
    
    channel.bind('pusher:subscription_error', (err) => {
        console.error('❌ Subscription failed:', err);
    });
}
```

### Test Manual Event Broadcast
```bash
# From tinker
php artisan tinker

# Broadcast test event
>>> $workspace = App\Models\Workspace::first();
>>> event(new \App\Events\NewChatEvent([
...     'type' => 'test',
...     'message' => 'Hello from tinker',
...     'contact_id' => 1
... ], $workspace->id));
```

---

## 🐛 Common Issues & Solutions

### Issue 1: Events Not Received
**Symptoms:** Console shows subscription success tapi tidak ada events

**Debug Steps:**
```bash
# 1. Check Reverb logs
php artisan reverb:start --debug
# Look for: "[✓] Message published to channel: chats.ch1"

# 2. Check broadcaster config
php artisan config:cache
php artisan config:clear

# 3. Verify .env settings
BROADCAST_DRIVER=reverb
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
```

**Solution:**
- Restart Reverb server
- Clear browser cache
- Check firewall/proxy settings

---

### Issue 2: Double Counting
**Symptoms:** Badge increment 2x untuk single message

**Debug Steps:**
```javascript
// Add breakpoint di App.vue line ~90
echo.channel(channelName).listen('NewChatEvent', (event) => {
    debugger; // Check if called multiple times
    ...
});

// Check for duplicate subscriptions
console.log('Active channels:', Object.keys(window.Echo.connector.channels));
// Should only have ONE chats.ch{workspaceId}
```

**Solution:**
- Verify route detection works: `window.location.pathname.includes('/user/chat')`
- Remove any duplicate Echo subscriptions
- Check for competing composables

---

### Issue 3: Badge Not Updating
**Symptoms:** Message received tapi badge tidak berubah

**Debug Steps:**
```javascript
// Check if contact found in list
const contactIndex = rows.value.data.findIndex(c => c.id == incomingContactId);
console.log('Contact index:', contactIndex);
// Should be >= 0 if contact exists

// Check reactive updates
console.log('Contact unread before:', targetContact.unread_messages);
targetContact.unread_messages += 1;
console.log('Contact unread after:', targetContact.unread_messages);
```

**Solution:**
- Verify contact exists in `rows.value.data`
- Check type coercion: use `==` instead of `===`
- Ensure Vue reactivity with direct property assignment

---

### Issue 4: Connection Drops
**Symptoms:** Initially works then stops receiving events

**Debug Steps:**
```javascript
// Monitor connection state changes
pusher.connection.bind('state_change', (states) => {
    console.log('Connection state change:', states);
    // from: "connected", to: "disconnected"
});

// Check ping interval
console.log('Ping interval:', pusher.config.activityTimeout);
```

**Solution:**
```bash
# Increase activity timeout in .env
REVERB_APP_ACTIVITY_TIMEOUT=120

# Restart Reverb
php artisan reverb:restart
```

---

## 📊 Performance Benchmarks

### Expected Latency
- **Event broadcast** (backend): < 10ms
- **WebSocket delivery**: < 50ms
- **UI update** (frontend): < 100ms
- **Total end-to-end**: < 200ms

### Test Commands
```bash
# Monitor Reverb performance
php artisan reverb:start --debug | grep "published"

# Check message queue
php artisan queue:failed
```

---

## ✅ Acceptance Criteria

### Must Pass All:
- [ ] Scenario 1: Badge update untuk non-active contact
- [ ] Scenario 2: Real-time message display di active chat
- [ ] Scenario 3: Badge update when on different page
- [ ] Scenario 4: Badge reset when opening chat
- [ ] Scenario 5: Multiple contacts simultaneous updates

### Performance:
- [ ] End-to-end latency < 500ms
- [ ] No memory leaks after 1000 messages
- [ ] Sound notification tidak overlap
- [ ] UI tetap responsive during updates

### Edge Cases:
- [ ] Handle connection loss gracefully
- [ ] No double counting
- [ ] No phantom badges
- [ ] Works across multiple browser tabs
- [ ] Works on mobile browsers

---

## 🚀 Load Testing

### Stress Test Script
```javascript
// Send 100 messages rapidly
for (let i = 0; i < 100; i++) {
    axios.post('/api/v1/test/send-message', {
        workspace_id: 1,
        contact_id: 1,
        message: `Test message ${i}`
    });
}

// Monitor for:
// - Badge accuracy
// - UI responsiveness
// - Memory usage
// - Event queue buildup
```

---

## 📝 Test Report Template

```markdown
## Test Execution Report

**Date:** [DATE]
**Tester:** [NAME]
**Environment:** [LOCAL/STAGING/PRODUCTION]

### Scenario Results
- [ ] Scenario 1: ✅ PASS / ❌ FAIL
- [ ] Scenario 2: ✅ PASS / ❌ FAIL
- [ ] Scenario 3: ✅ PASS / ❌ FAIL
- [ ] Scenario 4: ✅ PASS / ❌ FAIL
- [ ] Scenario 5: ✅ PASS / ❌ FAIL

### Issues Found
1. [Issue description]
   - Severity: HIGH/MEDIUM/LOW
   - Steps to reproduce:
   - Screenshots:

### Performance Metrics
- Average latency: [X]ms
- Peak latency: [X]ms
- Messages tested: [X]
- Errors: [X]

### Browser Compatibility
- [ ] Chrome ✅
- [ ] Firefox ✅
- [ ] Safari ✅
- [ ] Edge ✅
- [ ] Mobile Chrome ✅
- [ ] Mobile Safari ✅

### Recommendation
[ ] APPROVED FOR DEPLOYMENT
[ ] NEEDS FIXES BEFORE DEPLOYMENT
```

---

## 🔗 Related Documents

- [25-realtime-reverb-critical-fixes-report.md](./25-realtime-reverb-critical-fixes-report.md)
- [24-realtime-notification-gap-analysis.md](./24-realtime-notification-gap-analysis.md)
- [20-realtime-websocket-fix-report.md](./20-realtime-websocket-fix-report.md)

---

**Status:** Ready for Testing  
**Last Updated:** November 18, 2025
