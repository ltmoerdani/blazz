# Realtime Notification Gap Analysis & Remediation Plan

**Date:** 17 November 2025  
**Status:** 🚧 IN PROGRESS  
**Owner:** Chat Platform Team  
**Branch:** `staging-chats-fix-notif`

---

## 1. Executive Summary

Seluruh skenario realtime yang diwajibkan (badge saat idle, update thread saat kontak aktif, badge saat berada di kontak lain) gagal karena rantai broadcast → listener → UI cache tidak sinkron. Akar masalah terbesar adalah parameter `NewChatEvent` yang tertukar (payload dikirim sebagai workspaceId) sehingga event tidak pernah sampai ke channel `chats.ch{workspaceId}`. Bahkan jika event tiba, struktur data yang digunakan UI (`contact.last_chat`) tidak pernah diperbarui secara reaktif, menyebabkan badge/preview/macOS order tidak berubah. Dokumen ini memetakan kondisi eksisting, gap teknis, serta rencana remediasi bertahap.

---

## 2. Scope & Goals

| Item | Detail |
|------|--------|
| Target Fitur | Realtime chat list (badge, preview, reorder) & chat thread update |
| Skenario Uji | 1) Idle di chat list, 2) Sedang membuka kontak yang sama, 3) Membuka kontak lain |
| Out-of-scope | Pengiriman pesan outbound, template campaign, modul tiket |
| Deliverables | Fix plan backend, patch frontend, SOP testing, referensi best practice |

---

## 3. Reference Materials

- `docs/chats/20-realtime-websocket-fix-report.md`: baseline integrasi Reverb & Echo
- `docs/chats/22-realtime-badge-update-complete-fix.md`: percobaan patch optimistik sebelumnya
- Riset eksternal:
  - Scaledrone Vue Chat Tutorial – pattern satu sumber data untuk contact list + presence indicator
  - Ably Pub/Sub Presence Docs – pentingnya payload standar & mekanisme resync setelah reconnect

---

## 4. Current Architecture Snapshot

### 4.1 Server → Broadcast
- Sumber data: `app/Services/ChatService.php` (`processTextMessage`, `processMediaMessage`, dll.)
- Event: `App\Events\NewChatEvent` yang mengimplementasi `ShouldBroadcastNow`
- channel: `chats.ch{$workspaceId}` (public channel untuk kompatibilitas Reverb)
- Payload: `$chat` (array `[ { type: 'chat', value: {...} } ]`)

### 4.2 Frontend Listener
- File: `resources/js/Pages/User/Chat/Index.vue`
- Echo instance diambil dari `resources/js/echo.js` → fallback ke `window.Echo` (bootstrap)
- Listener: `echo.channel('chats.ch' + workspaceId).listen('.NewChatEvent', handler)`
- Handler memanggil `updateSidePanel(event.chat)` dan optional `updateChatThread`

### 4.3 UI State & Cache
- `rows`: daftar kontak (Inertia props) → dipakai oleh `ChatTable.vue`
- `chatThread`: shallowRef of messages (masuk ke `ChatThread.vue`)
- Cache lokal: `chatCache` (per contact uuid)

---

## 5. Failure Mapping per Scenario

| Scenario | Ekspektasi | Realita | Root Cause |
|----------|------------|---------|------------|
| 1. Idle di chat list | Badge & preview update instant, reorder list | Tidak ada perubahan sampai refresh manual | Broadcast tidak masuk channel karena parameter tertukar + `rows.data` dimutasi tanpa mengganti referensi + `last_chat` tidak pernah diupdate |
| 2. Kontak aktif | Bubble baru muncul tanpa badge | Thread tetap diam, harus reload | Event tidak diterima; fallback fetch tidak berjalan otomatis; `updateChatThread` hanya bekerja jika event valid |
| 3. Kontak lain | Badge naik pada kontak terkait, preview update | Tidak ada badge, list tidak bergerak | Sama dengan scenario 1, plus `last_chat` tidak diubah sehingga `ChatTable` tak punya data untuk preview |

---

## 6. Detailed Root Cause Analysis

1. **Payload vs Workspace Parameter Swapped**  
   - Kode saat ini: `event(new NewChatEvent($contact->id, [...]))`  
   - Konstruktor: `__construct($chat, $workspaceId)`  
   - Dampak: channel dihitung sebagai `chats.chArray` (string literal "Array"), bukan `chats.ch1`. Semua subscriber melewatkan event.

2. **Non-reactive Sidebar Updates**  
   - `updateSidePanel` memodifikasi `rows.value.data[index].last_message` dsb tetapi `ChatTable` membaca `contact.last_chat.metadata`. Tanpa assignment baru ke `last_chat` dan tanpa membuat salinan array, Vue tidak memicu rerender.

3. **Thread Listener Reliance**  
   - `ChatThread.vue` tidak memiliki fallback fetch saat event tak masuk. Ketika event gagal, thread tidak pernah melihat pesan masuk sampai user men-trigger `selectContact` (yang memanggil axios GET).

4. **Inconsistent Data Contract**  
   - Payload event bentuknya raw array `[ { type, value } ]` tanpa meta ringkas (unreadCount, preview text). UI harus menebak `message`, `body`, atau `metadata`, membuat patch raw rentan salah.

5. **Redundant Server Sync**  
   - Debounced fetch `/chats` dilakukan setelah perubahan lokal, namun response kembali ke bentuk awal (tanpa patch `last_chat`). Jika backend belum memperbarui kolom `contacts.unread_messages`, badge bisa kembali `0` meskipun seharusnya `1`.

---

## 7. Remediation Plan

### 7.1 Backend Actions
1. **Perbaiki konstruktor event** – cari semua `new NewChatEvent` dan pastikan pemanggilan menjadi `new NewChatEvent($chatPayload, $this->workspaceId)`.
2. **Standardisasi payload** – bungkus data ke struktur:
   ```php
   [
     'chat' => $chatData,
     'contact_id' => $contact->id,
     'workspace_id' => $this->workspaceId,
     'summary' => [
         'text' => $normalizedText,
         'created_at' => now(),
         'unread_delta' => 1
     ]
   ]
   ```
3. **Update `ContactResource`** agar `last_chat` selalu memiliki `metadata`, `message_preview`, dan `latest_chat_created_at`.
4. **Tambahkan Feature Test**: `tests/Feature/NewChatEventTest.php` memastikan channel name valid & payload berformat benar.

### 7.2 Frontend Actions
1. **Reaktifkan Sidebar:**
   - Saat `updateSidePanel`, set `rows.value.data[index] = { ...rows.value.data[index], last_chat: updatedLastChat, unread_messages: count }`.
   - Reassign container: `rows.value = { ...rows.value, data: [...rows.value.data] }`.
2. **Thread Fallback:** jika `event.chat` kosong atau `contact.id` tidak match, panggil `fetchChatDataInBackground(contact.uuid, cacheKey, true)`.
3. **Unified Preview Extractor:** buat helper `extractPreview(chatValue)` yang memeriksa `message`, `body`, `metadata`, `template`, dsb agar tidak duplikasi.
4. **Group vs Private Handling:** satukan format payload `event.group` untuk group metadata sehingga `ChatTable` dapat menampilkan label peserta.
5. **Connectivity Guards:** hanya jalankan `/chats` sync jika `document.hidden === false` & `navigator.onLine === true` untuk menghindari spam request.

### 7.3 Testing & Validation
- Jalankan `./test-realtime-scenarios.sh` untuk regression manual.
- Tambah cypress spec `cypress/e2e/chat-realtime.cy.js` dengan tiga scenario.
- Observasi log Reverb (`reverb-verbose.log`) memastikan event masuk channel yang benar.

---

## 8. Implementation Checklist

| Task | Owner | ETA | Status |
|------|-------|-----|--------|
| Refactor NewChatEvent invocations | Backend | 18 Nov | ⏳ |
| Normalized payload builder | Backend | 18 Nov | ⏳ |
| Sidebar reactive patch | Frontend | 19 Nov | ⏳ |
| ChatThread fallback fetch | Frontend | 19 Nov | ⏳ |
| Automated tests (PHPUnit + Cypress) | QA | 20 Nov | ⏳ |

---

## 9. Risk Assessment

| Risk | Mitigation |
|------|------------|
| Event payload berubah menyebabkan klien lama gagal parsing | Tambahkan version flag `event_version`. Klien lama fallback ke format lama hingga upgrade |
| Badge double increment | Normalize `unread_delta` dari backend; hindari auto ++ jika server mengembalikan nilai final |
| Performance regression akibat frequent `/chats` fetch | Gunakan TTL & only fetch ketika window aktif |

---

## 10. Follow-up Items

1. Dokumentasikan kontrak event baru di `docs/chats/ANALISIS-IMPLEMENTASI-LENGKAP.md`.
2. Refactor `ChatTable.vue` supaya memakai composable `useChatList()` sehingga logic side panel terpusat.
3. Evaluasi opsi presence/per-contact channel (private) setelah channel public kembali stabil.

---

## 11. Appendix

### 11.1 Key File References
- `app/Events/NewChatEvent.php`
- `app/Services/ChatService.php`
- `resources/js/Pages/User/Chat/Index.vue`
- `resources/js/Components/ChatComponents/ChatThread.vue`
- `resources/js/Components/ChatComponents/ChatTable.vue`
- `resources/js/echo.js`, `resources/js/bootstrap.js`

### 11.2 Command Cheatsheet
```bash
# Jalankan dev stack
./start-dev.sh

# Bangun asset (opsional sebelum staging)
npm run build

# Manual scenario tester
./test-realtime-scenarios.sh
```

---

**Kesimpulan:** Fix utama adalah memastikan event benar-benar terkirim ke channel yang di-subscribe dan memastikan UI state diperbarui secara reaktif. Setelah dua titik ini stabil, patch optimistik yang sudah ada di `22-realtime-badge-update-complete-fix.md` dapat bekerja penuh tanpa memerlukan refresh manual.
