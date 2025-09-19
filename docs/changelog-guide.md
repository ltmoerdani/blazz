# Panduan Penulisan & Update Changelog

Dokumen ini berisi aturan, format, dan langkah-langkah pembuatan serta pembaruan changelog untuk project Blazz. Tujuannya agar seluruh tim dapat mencatat perubahan secara konsisten, mudah dibaca, dan dapat ditelusuri oleh semua stakeholder.

---

## Tujuan Changelog
- Menjadi catatan resmi seluruh perubahan, penambahan fitur, perbaikan bug, peningkatan keamanan, dan optimasi penting.
- Memudahkan developer, QA, product manager, dan stakeholder menelusuri evolusi aplikasi chat.
- Menyediakan transparansi dan referensi historis untuk semua perubahan sistem chat dan komunikasi.
- Dokumentasi khusus untuk fitur real-time messaging, integrasi WhatsApp, dan sistem keamanan.

## Struktur & Format Changelog
1. **Judul Utama**: Gunakan `# 📝 CHANGELOG` di bagian paling atas.
2. **Paragraf Pembuka**: Jelaskan tujuan changelog secara singkat.
3. **Setiap Rilis/Versi**:
   - Gunakan heading `### Versi x.x.x` (misal: `### Versi 0.13.0`).
   - Judul fitur/inti rilis ditebalkan (**bold**).
   - Tanggal rilis dicetak miring (_italic_) di bawah judul fitur.
   - Deskripsi perubahan dalam paragraf terpisah, jelas, dan ringkas.
   - Gunakan garis pemisah `---` antar versi.
4. **Status Pembaruan Changelog**:
   - Tambahkan di bagian bawah dokumen.
   - Format: `- **v0.x.x — yyyy-mm-dd** — Ringkasan update.`

## Aturan Penulisan
- Satu versi hanya untuk satu batch perubahan besar (fitur utama, security update, integrasi baru, dsb).
- Hindari menulis "Added/Changed/Fixed" secara terpisah, gunakan narasi yang menggambarkan dampak bisnis.
- Tulis dalam bahasa Indonesia yang jelas dan profesional.
- Untuk fitur chat/messaging, sertakan konteks penggunaan dan manfaat bagi end-user.
- Setiap security update atau perubahan API harus dijelaskan dengan detail.
- Update sistem WhatsApp integration atau real-time features wajib didokumentasikan lengkap.
- Setiap update harus menyertakan tanggal rilis dan impact level (low/medium/high/critical).
- Jangan menghapus riwayat versi lama.
- Update bagian "Status Pembaruan Changelog" setiap kali ada rilis baru.

## Langkah Membuat/Update Changelog
1. Setelah fitur/bugfix/security update/integrasi selesai dan siap rilis:
   - Tentukan nomor versi baru (ikuti semver: MAJOR.MINOR.PATCH).
   - Untuk security updates gunakan PATCH increment.
   - Untuk fitur chat/messaging baru gunakan MINOR increment.
   - Untuk breaking changes atau major WhatsApp API updates gunakan MAJOR increment.
   - Tambahkan entri baru di bagian atas changelog sesuai format.
   - Sertakan impact level dan daftar fitur yang terpengaruh.
   - Update bagian "Status Pembaruan Changelog" di bawah dokumen.
2. Lakukan review internal dengan tim QA dan product manager sebelum merge ke branch utama.
3. Untuk security-related changes, pastikan koordinasi dengan security team.
4. Pastikan changelog selalu up-to-date sebelum deployment/production.
5. Notifikasi stakeholder terkait untuk major releases atau breaking changes.

## Contoh Entri

```
### Versi 2.1.0
**Fitur Real-time Message Status & WhatsApp Business API Integration**
_18 September 2025 — Impact: Medium_

Sistem chat kini mendukung real-time message status (delivered, read, failed) dengan indikator visual yang lebih jelas. Penambahan integrasi WhatsApp Business API untuk multi-device support dan perbaikan performance pada chat loading untuk conversation dengan 1000+ messages. Update keamanan untuk enkripsi message metadata.

**Breaking Changes**: 
- API endpoint `/api/v1/chats` sekarang memerlukan organization_id parameter
- WebSocket connection protocol updated ke v2.0

**Migration Required**: 
- Jalankan `php artisan migrate` untuk update chat_status_logs table
- Update environment variables untuk WhatsApp Business API credentials
---

### Versi 2.0.5  
**Security Patch - Message Encryption Enhancement**
_15 September 2025 — Impact: Critical_

Perbaikan critical security vulnerability pada message encryption system. Semua messages sekarang menggunakan AES-256 encryption dengan rotating keys. Mandatory update untuk semua installations.

**Security Impact**: 
- CVE-2025-XXXX addressed
- Enhanced message privacy protection
- Audit trail untuk semua message access
---
```

## Kategori Perubahan Khusus Blazz

### 🔒 Security Updates
- **Critical**: Vulnerability fixes, encryption updates, auth system changes
- **High**: API security enhancements, data protection improvements  
- **Medium**: Access control updates, audit logging enhancements
- **Low**: Security-related UI improvements, warning messages

### 💬 Messaging Features
- **Core Chat**: Real-time messaging, message status, threading
- **WhatsApp Integration**: Business API updates, webhook changes, multi-device
- **Media Handling**: File uploads, image/video processing, storage optimization
- **Templates**: Message templates, quick replies, automated responses

### 🏢 Organization & Teams
- **Multi-tenancy**: Organization isolation, data separation
- **User Management**: Roles, permissions, team collaboration
- **Billing & Subscriptions**: Payment processing, plan management, usage tracking
- **API Access**: Organization API keys, rate limiting, usage analytics

### 🔧 Infrastructure & Performance  
- **Database**: Migration scripts, indexing, query optimization
- **Caching**: Redis integration, session management, real-time updates
- **Queue System**: Background jobs, message processing, notification delivery
- **Monitoring**: Logging, metrics, health checks, error tracking

### 📱 UI/UX Improvements
- **Chat Interface**: Message bubbles, typing indicators, emoji support
- **Dashboard**: Analytics, reporting, user management interfaces  
- **Mobile Responsiveness**: Touch interactions, mobile-first design
- **Accessibility**: Screen reader support, keyboard navigation, contrast improvements
## Referensi
- [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
- [Semantic Versioning](https://semver.org/)
- [WhatsApp Business API Changelog](https://developers.facebook.com/docs/whatsapp/changelog)
- [Laravel Security Advisories](https://laravel.com/docs/releases#security-updates)

---

_Dokumen ini wajib diikuti oleh seluruh kontributor project Blazz._
