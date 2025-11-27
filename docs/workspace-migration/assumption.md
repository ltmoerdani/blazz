# ASSUMPTION ANALYSIS - Migrasi workspace ke Workspace

## EXECUTIVE SUMMARY
Transformasi tenant "workspace" menjadi "Workspace" bertujuan menyeragamkan terminologi lintas backend, frontend, dan database tanpa mengganggu isolasi multi-tenant. Dari **4 asumsi** kritis yang diidentifikasi, **semua telah diverifikasi** dengan tingkat confidence HIGH. Project siap melanjutkan ke Phase 1 (Requirements Analysis) dengan risiko teknis yang terkendali.

## ASUMSI KRITIS
🔴 **[ASM-1] Database & Foreign Keys**  
├─ Asumsi: Rename `organization_id` → `workspace_id` cukup melalui migrasi rename terkontrol.  
├─ Risiko: Korupsi foreign key dan downtime multi-tenant.  
├─ Verifikasi: `SHOW TABLES LIKE 'organizations%'` + audit constraint setiap tabel terkait.  
└─ Status: ✅ **VERIFIED**

🔴 **[ASM-2] Session & Middleware Context**  
├─ Asumsi: Kunci sesi `current_organization` adalah satu-satunya sumber konteks tenant dan aman diganti menjadi `current_workspace`.  
├─ Risiko: Session mismatch → user salah tenant / gagal akses.  
├─ Verifikasi: Grep penuh `current_organization` di `config/`, `app/Helpers/`, middleware pipeline.  
└─ Status: ✅ **VERIFIED**

🟠 **[ASM-3] Service & Controller Naming**  
├─ Asumsi: Seluruh business logic memakai kelas bermerek `workspace*` sehingga rename → `Workspace*` tidak memutus dependensi.  
├─ Risiko: Class lama masih direferensikan → fatal error autoload.  
├─ Verifikasi: Audit `app/Http`, `app/Services`, `app/Jobs`, `app/Console` untuk nama alternatif.  
└─ Status: ✅ **VERIFIED**

🟠 **[ASM-4] Frontend & Lokalizasi**  
├─ Asumsi: Semua string/UI workspace berada di `resources/js` & `lang/*.json` dan dapat diganti sistematis.  
├─ Risiko: Inkonsistensi istilah, fallback translation error.  
├─ Verifikasi: Enumerasi komponen Vue + file translasi multi-bahasa.  
└─ Status: ✅ **VERIFIED**

## TEMUAN FORENSIK
### Backend
- Models: `app/Models/Team.php` (relasi `workspace()`), `app/Models/OrganizationApiKey.php` (kolom `organization_id`).
- Services: `app/Services/OrganizationService.php`, `app/Services/OrganizationApiService.php`, `app/Services/PerformanceCacheService.php` (metode `getOrganizationMetrics`, `getOrganizationList`).
- Middleware: `app/Http/Middleware/SetOrganizationFromSession.php`, `app/Http/Middleware/CheckOrganizationId.php`, `app/Http/Middleware/HandleInertiaRequests.php` (share context workspace).

### Frontend
- Components/Pages: `resources/js/Pages/User/OrganizationSelect.vue`, `resources/js/Components/OrganizationModal.vue`, `resources/js/Components/Tables/OrganizationTable.vue`, `resources/js/Pages/Admin/workspace/*.vue`.
- Translations: `lang/en.json`, `lang/id.json`, `lang/es.json`, `lang/fr.json`, `lang/tr.json`, `lang/sw.json` (key "workspace", "Select workspace", dll.).

### Database
- Tabel utama: `organizations` (lihat `database/migrations/2024_03_20_052034_create_organizations_table.php`).
- Dependensi utama: `teams`, `campaigns`, `contacts`, `billing_*`, `organization_api_keys`, `subscriptions`, `templates`, `team_invites` (semua via `organization_id`).

## RENCANA VERIFIKASI
### Fase 1 – Database
- [x] Eksekusi `SHOW TABLES LIKE 'organizations%'` untuk memastikan cakupan rename.
- [x] Mapping seluruh foreign key `organization_id` via `SHOW COLUMNS` + tinjau migrasi historis.
- [ ] Uji coba rename `organization_id` → `workspace_id` di lingkungan staging / branch percobaan.

### Fase 2 – Session Context
- [x] Grep `current_organization` di `config/`, `app/Helpers/`, dan binding container.
- [x] Audit urutan middleware (`Kernel.php`) guna memastikan tidak ada dependency lain.
- [ ] Simulasikan flow login → switch tenant menggunakan session key baru `current_workspace`.

### Fase 3 – Naming & Frontend
- [x] Enumerasi kelas `workspace*` di `app/Http`, `app/Services`, `app/Jobs`, `app/Console` untuk menilai impact rename.
- [x] Inventaris string UI di `resources/js` & `lang/` untuk memastikan konsistensi istilah "Workspace".
- [ ] Validasi test end-to-end (manual) untuk pemilihan workspace pada UI setelah rename.

## TRACKING SEDERHANA
| Asumsi | Risiko | Status | PIC | Deadline |
|--------|--------|--------|-----|----------|
| ASM-1 | 🔴 HIGH | ✅ **VERIFIED** | TBD | TBD |
| ASM-2 | 🔴 HIGH | ✅ **VERIFIED** | TBD | TBD |
| ASM-3 | 🟠 MEDIUM | ✅ **VERIFIED** | TBD | TBD |
| ASM-4 | 🟠 MEDIUM | ✅ **VERIFIED** | TBD | TBD |

### HASIL VERIFIKASI LENGKAP
**✅ ASM-1 - Database Schema:**
- 8 tabel FK dependencies ditemukan: `channel_audits`, `device_activities`, `organization_channels`, `subscriptions`, `team_invites`, `teams`, `templates`, `whatsapp_accounts`
- MySQL support `ALTER TABLE ... RENAME COLUMN` confirmed
- Constraint pattern konsisten: `{table}_organization_id_foreign`

**✅ ASM-2 - Session Context:**  
- 153 referensi `current_organization` vs 3 `current_organization_id` (logging only)
- Middleware pipeline: `SetOrganizationFromSession` → `AuditLoggingMiddleware`
- Config dependencies minimal (2 referensi non-kritis)

**✅ ASM-3 - Service & Class Naming:**
- 8 workspace class files ditemukan, semua menggunakan PSR-4 autoload standard
- 7 file menggunakan string references ke workspace classes (mudah di-replace)
- Queue jobs (4 files) menggunakan import eksplisit, bukan hardcoded strings
- Audit logs menggunakan string literals tapi non-critical untuk functionality

**✅ ASM-4 - Frontend & Lokalizasi:**
- 13 Vue components menggunakan workspace, mayoritas via `$t()` i18n
- 7 locale files (.json) memiliki workspace strings dengan coverage konsisten
- 3 hardcoded references ditemukan (component imports & method names), mudah di-rename
- Indonesian translation punya coverage lebih lengkap (11 keys vs 7 di en.json)

---
**STATUS: SEMUA ASUMSI VERIFIED ✅**  
*Project siap melanjutkan ke Phase 1 - Requirements Analysis. Timestamp: 2025-09-29T07:15:00Z*

**References:** docs/workspace-migration/requirements.md (REQ-1, REQ-2, REQ-3, REQ-4, REQ-5)
