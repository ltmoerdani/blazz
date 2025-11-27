# Manual Testing Instructions

## ✅ QUICK TEST UNTUK VERIFY SYSTEM

Sekarang session sudah connected, mari test manual:

### **Step 1: Kirim WhatsApp Message**

Dari HP Anda, kirim pesan WhatsApp **KE** nomor: **+62 811-801-641**

Contoh pesan: `"Test dari [nama anda]"`

### **Step 2: Check Logs (Real-time)**

```bash
# Terminal 1: Monitor Laravel logs
tail -f storage/logs/laravel.log | grep "WhatsApp"

# Terminal 2: Monitor Node.js logs
tail -f whatsapp-service/logs/whatsapp-service.log | grep "message"
```

### **Step 3: Check Database**

```bash
php artisan tinker --execute="
echo 'Contacts: ' . \App\Models\Contact::count() . PHP_EOL;
echo 'Chats: ' . \App\Models\Chat::count() . PHP_EOL;

\$chat = \App\Models\Chat::latest()->first();
if (\$chat) {
    echo 'Latest chat: ' . \$chat->contact_name . PHP_EOL;
    echo 'Provider: ' . \$chat->provider_type . PHP_EOL;
}
"
```

### **Step 4: Check UI**

1. Refresh browser: http://127.0.0.1:8000/chats
2. Should see chat count updated
3. Should see chat item in list

---

## 🔍 EXPECTED FLOW:

```
WhatsApp Message Sent
    ↓
Node.js receives via whatsapp-web.js
    ↓
Node.js sends webhook to Laravel
    ↓
Laravel processes message:
    - Creates Contact (if new)
    - Creates Chat (if new)
    - Creates Message
    ↓
Laravel broadcasts to frontend (WebSocket)
    ↓
UI updates automatically
```

---

## ❌ IF IT FAILS:

Check these in order:

1. **Session connected?**
   ```bash
   curl http://localhost:3001/health
   ```
   Should show: `"connected": 1`

2. **Webhook receiving?**
   ```bash
   tail -f whatsapp-service/logs/whatsapp-service.log | grep "message received"
   ```

3. **Laravel processing?**
   ```bash
   tail -f storage/logs/laravel.log | grep "WhatsApp message received"
   ```

4. **Database updated?**
   ```bash
   php artisan tinker --execute="echo \App\Models\Contact::count()"
   ```

5. **Check for errors:**
   ```bash
   tail -100 storage/logs/laravel.log | grep ERROR
   ```

---

## 📝 TROUBLESHOOTING

### Issue: "Session not connected"
```bash
# Check session status
cd whatsapp-service
tail -f logs/whatsapp-service.log | grep "session"

# Look for: "WhatsApp account ready"
```

### Issue: "Webhook timeout"
```bash
# Check Laravel is running
curl http://127.0.0.1:8000/health

# Check MAMP/Laravel logs
tail -f storage/logs/laravel.log
```

### Issue: "No chats in UI"
```bash
# 1. Clear cache
php artisan cache:clear
php artisan config:clear

# 2. Rebuild frontend
npm run build

# 3. Hard refresh browser (Cmd+Shift+R)
```

---

**Created:** 2025-10-23
**Purpose:** Manual testing after auto-reconnect implementation
