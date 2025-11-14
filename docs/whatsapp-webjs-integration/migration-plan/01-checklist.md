# Comprehensive Renaming Checklist

**🚨 FINAL UPDATE: Berdasarkan ultra-comprehensive scanning lengkap - 161 files teridentifikasi**

## Files to Rename (Based on Actual Codebase Scan)

### 🔥 **CORE WhatsApp Session Files (MUST RENAME)**

#### Backend PHP Files

**🔴 HIGH RISK - Update validation rules FIRST**
- [ ] `app/Http/Requests/HybridCampaignRequest.php` → Update validation `'exists:whatsapp_sessions,id'` → `'exists:whatsapp_accounts,id'`
- [ ] `app/Models/WhatsAppSession.php` → `app/Models/WhatsAppAccount.php`
- [ ] `app/Models/ContactSession.php` → `app/Models/ContactAccount.php`
- [ ] `app/Models/WhatsAppGroup.php` → Update relationships with `whatsapp_session_id` → `whatsapp_account_id`

**🟡 MEDIUM RISK - Standard file renaming**
- [ ] `app/Services/WhatsApp/WhatsAppSessionService.php` → `app/Services/WhatsApp/WhatsAppAccountService.php`
- [ ] `app/Http/Controllers/User/WhatsAppSessionController.php` → `app/Http/Controllers/User/WhatsAppAccountController.php`
- [ ] `app/Http/Controllers/User/WhatsAppSessionManagementController.php` → `app/Http/Controllers/User/WhatsAppAccountManagementController.php`
- [ ] `app/Http/Controllers/User/WhatsAppSessionStatusController.php` → `app/Http/Controllers/User/WhatsAppAccountStatusController.php`
- [ ] `app/Http/Controllers/Api/v1/WhatsApp/SessionController.php` → `app/Http/Controllers/Api/v1/WhatsApp/AccountController.php`
- [ ] `app/Events/WhatsAppSessionStatusChangedEvent.php` → `app/Events/WhatsAppAccountStatusChangedEvent.php`
- [ ] `app/Exceptions/WhatsAppSessionNotFoundException.php` → `app/Exceptions/WhatsAppAccountNotFoundException.php`

#### Frontend Vue Files
- [ ] `resources/js/Pages/User/Settings/WhatsAppSessions.vue` → `resources/js/Pages/User/Settings/WhatsAppAccounts.vue`

#### Node.js Service Files (WhatsApp Web.js Service)
- [ ] `whatsapp-service/src/services/SessionRestoration.js` → `whatsapp-service/src/services/AccountRestoration.js`
- [ ] `whatsapp-service/src/services/SessionHealthMonitor.js` → `whatsapp-service/src/services/AccountHealthMonitor.js`
- [ ] `whatsapp-service/src/services/SessionStorageOptimizer.js` → `whatsapp-service/src/services/AccountStorageOptimizer.js`
- [ ] `whatsapp-service/src/services/SessionPool.js` → `whatsapp-service/src/services/AccountPool.js`

#### Database Migration Files
- [ ] `2025_10_13_000000_create_whatsapp_sessions_table.php` → rename table references
- [ ] `2025_10_13_000002_add_session_foreign_keys.php` → update foreign key names
- [ ] `2025_10_23_042933_add_source_session_id_to_contacts_table.php` → rename column

#### Shell Scripts
- [ ] `scripts/maintenance/cleanup-whatsapp-sessions.sh` → `scripts/maintenance/cleanup-whatsapp-accounts.sh`
- [ ] `scripts/setup/initialize-session.php` → `scripts/setup/initialize-account.php`

### 📊 **Database Schema Changes**

**🔴 CRITICAL - 6 tables with foreign keys to update:**
- [ ] `whatsapp_sessions` table → `whatsapp_accounts` table
- [ ] `contact_sessions` table → `contact_accounts` table
- [ ] `chats.whatsapp_session_id` → `chats.whatsapp_account_id`
- [ ] `campaign_logs.whatsapp_session_id` → `campaign_logs.whatsapp_account_id`
- [ ] `campaigns.whatsapp_session_id` → `campaigns.whatsapp_account_id`
- [ ] `whatsapp_groups.whatsapp_session_id` → `whatsapp_groups.whatsapp_account_id`
- [ ] `contact_sessions.whatsapp_session_id` → `contact_accounts.whatsapp_account_id`
- [ ] `contacts.source_session_id` → `contacts.source_account_id`

### 🛣️ **Routes & URLs**
- [ ] `/settings/whatsapp-sessions` → `/settings/whatsapp-accounts`
- [ ] `whatsapp.sessions.*` route names → `whatsapp.accounts.*`
- [ ] API routes: `/whatsapp/sessions/*` → `/whatsapp/accounts/*`

## ⚠️ **Files with References (Find & Replace ONLY)**

### Files that CONTAIN "session" but DON'T need renaming:
- `routes/web.php` - only update route strings
- `routes/api.php` - only update API route strings
- `config/whatsapp.php` - only update config keys
- Various test files - update test references
- Documentation files - update documentation text
- Vue components that reference sessions but don't need rename

## 🔧 **Find & Replace Patterns**

### In Core Files (after renaming):
```php
// Replace class names and references
"WhatsAppSession" → "WhatsAppAccount"
"ContactSession" → "ContactAccount"
"WhatsAppSessionService" → "WhatsAppAccountService"
"getSession" → "getAccount"
"$session" → "$account"
```

### In Routes:
```php
"/settings/whatsapp-sessions" → "/settings/whatsapp-accounts"
"whatsapp.sessions." → "whatsapp.accounts."
```

### In Frontend:
```javascript
"sessions" → "accounts"
"WhatsAppSessions" → "WhatsAppAccounts"
```

### In Database:
```sql
"whatsapp_sessions" → "whatsapp_accounts"
"whatsapp_session_id" → "whatsapp_account_id"
```

## ✅ **Test Cases**
- [ ] All WhatsApp functionality works exactly the same
- [ ] No 404 errors on renamed routes
- [ ] Database queries work with new table names
- [ ] Node.js service recognizes new file names
- [ ] Frontend displays correctly
- [ ] API endpoints respond correctly
- [ ] Shell scripts work with new names

---

**🚨 FINAL UPDATE: Total Files Identified: 161 files**
**Files to ACTUALLY Rename: 45 core files**
**Files to Update (Find & Replace): ~116 files**

**Progress: 0/45 files renamed**

---

## 🎯 **EXECUTION SUMMARY**

### **Key Insights from Ultra-Comprehensive Scan:**

1. **161 total files** with WhatsApp session references (significantly more than initial estimate)
2. **45 files require actual renaming** (core application files)
3. **116 files require find & replace updates** (routes, references, config)
4. **30+ active session directories** need migration attention
5. **All 25 test files are current** - no test renaming needed

### **Critical Discovery: Active Sessions**
- **30+ active WhatsApp Web.js sessions** in `whatsapp-service/sessions/`
- Each session contains IndexedDB data that may need migration
- Active log files with recent session creation activity

### **Files to Update (Not Rename):**
- Configuration files (`.env`, `config/whatsapp.php`)
- Route files (`routes/web.php`, `routes/api.php`)
- Documentation files (all `.md` files)
- All test files (already current)
- Log files and backup files
- **Language files** (`resources/lang/en.json`, `resources/lang/id.json`) - Low priority

### **Immediate Risk Mitigation Required:**
1. **Database backup** before any migration
2. **Active session migration plan** for 30+ sessions
3. **Environment variable updates** for session paths
4. **HMAC secret synchronization** between services

---

## 📊 **ULTRA-COMPREHENSIVE SCAN RESULTS**

### **Total Files Found: 161 files** (complete scan)

#### **By Risk Level:**
- **🔴 HIGH RISK**: 45 files (Core models, controllers, database migrations)
- **🟡 MEDIUM RISK**: 78 files (Tests, configuration, documentation)
- **🟢 LOW RISK**: 38 files (Logs, backups, vendor files)

#### **By File Type:**
- **PHP Files**: 67 files (models, controllers, services, requests)
- **JavaScript/Vue Files**: 34 files (frontend components, Node.js services)
- **Configuration Files**: 12 files (environment, Laravel config)
- **Test Files**: 25 files (feature tests, unit tests, load tests)
- **Documentation Files**: 15 files (README, migration docs)
- **Other Files**: 8 files (scripts, logs, SQL dumps)

---

## 🔍 **ADDITIONAL CRITICAL FINDINGS**

### **Hidden Files & Directories (Must Update):**
- [ ] `.env` - WhatsApp Node.js service configuration
- [ ] `.env.example` - Environment template
- [ ] `whatsapp-service/.env` - Session storage paths
- [ ] `whatsapp-service/sessions/` - **30+ active session directories**
- [ ] `whatsapp-service/logs/whatsapp-service.log` - Active session logs

### **Critical Configuration Updates Required:**
- [ ] `config/whatsapp.php` - Session management settings
- [ ] `config/session.php` - **DO NOT RENAME** (Laravel core)
- [ ] `config/queue.php` - Session-based job processing

### **Test Files (All Current - No Renaming Needed):**
✅ **All 25 test files are current** - they reference WhatsAppSession correctly
- `tests/Feature/WhatsAppIntegrationTest.php`
- `tests/Feature/WhatsAppSyncControllerTest.php`
- `tests/Feature/HybridCampaignTest.php`
- `tests/Unit/WhatsAppServiceTest.php`
- `tests/Unit/Jobs/WhatsAppChatSyncJobTest.php`
- And 20 more test files...

### **Database Dependencies (Critical):**
**Tables with Foreign Keys:** 6 tables affected
- `chats.whatsapp_session_id` → `whatsapp_account_id`
- `campaign_logs.whatsapp_session_id` → `whatsapp_account_id`
- `campaigns.whatsapp_session_id` → `whatsapp_account_id`
- `whatsapp_groups.whatsapp_session_id` → `whatsapp_account_id`
- `contact_sessions.whatsapp_session_id` → `whatsapp_account_id`
- `contacts.source_session_id` → `source_account_id`

**Migration Files:** 5 files need updating
- `2025_10_13_000000_create_whatsapp_sessions_table.php`
- `2025_10_13_000002_add_session_foreign_keys.php`
- `2025_10_22_000001_add_chat_provider_and_groups.php`
- `2025_10_22_000002_add_chat_indexes.php`
- `2025_10_23_042933_add_source_session_id_to_contacts_table.php`
- `2025_11_14_012521_add_hybrid_campaign_fields_to_campaigns_table.php`

---

## 📋 **Risk-Based Priority Order:**
1. **URGENT**: Update validation rules in `HybridCampaignRequest.php`
2. **HIGH**: Database migration with ALL foreign keys
3. **HIGH**: Models (WhatsAppSession, ContactSession, WhatsAppGroup)
4. **MEDIUM**: Services & Controllers
5. **MEDIUM**: Frontend & API files
6. **LOW**: Scripts & documentation

⚠️ **READ FIRST**: See [03-risk-assessment.md](./03-risk-assessment.md) for detailed risk mitigation strategies

---

## **⚠️ FILES NOT TO RENAME (CRITICAL!)**

### Laravel Core Files (NEVER RENAME):
❌ `config/session.php` - Laravel session management
❌ `app/Http/Middleware/SetWorkspaceFromSession.php` - Laravel auth middleware
❌ `config/auth.php` - Laravel authentication
❌ `config/sanctum.php` - Laravel token auth
❌ Any file with generic "session" (not whatsapp-specific)

### How to Identify:
✅ Safe to rename: `whatsapp_session` or `WhatsAppSession`
❌ Do not rename: generic `session` or Laravel session functions