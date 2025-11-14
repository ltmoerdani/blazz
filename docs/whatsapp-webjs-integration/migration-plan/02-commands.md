# Renaming Commands

**UPDATE: Commands berdasarkan comprehensive scanning 161 files**

## Database Migration

### Create Simple Migration
```bash
php artisan make:migration rename_whatsapp_sessions_to_accounts
```

### Migration Content (simple):
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename tables
        Schema::rename('whatsapp_sessions', 'whatsapp_accounts');
        Schema::rename('contact_sessions', 'contact_accounts');

        // Rename columns in whatsapp_accounts table
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->renameColumn('session_name', 'account_name');
            // Consider renaming 'status' to 'connection_status' if needed
        });

        // Update foreign keys in related tables
        Schema::table('contact_accounts', function (Blueprint $table) {
            $table->renameColumn('whatsapp_session_id', 'whatsapp_account_id');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->renameColumn('whatsapp_session_id', 'whatsapp_account_id');
        });

        Schema::table('campaign_logs', function (Blueprint $table) {
            $table->renameColumn('whatsapp_session_id', 'whatsapp_account_id');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->renameColumn('source_session_id', 'source_account_id');
        });
    }

    public function down(): void
    {
        // Reverse all renames
        Schema::rename('whatsapp_accounts', 'whatsapp_sessions');
        Schema::rename('contact_accounts', 'contact_sessions');
        // ... reverse all column renames
    }
};
```

## File Renaming Commands

### Backend PHP Files (Core Files)
```bash
# Models
mv app/Models/WhatsAppSession.php app/Models/WhatsAppAccount.php
mv app/Models/ContactSession.php app/Models/ContactAccount.php

# Services
mv app/Services/WhatsApp/WhatsAppSessionService.php app/Services/WhatsApp/WhatsAppAccountService.php

# Controllers (User)
mv app/Http/Controllers/User/WhatsAppSessionController.php app/Http/Controllers/User/WhatsAppAccountController.php
mv app/Http/Controllers/User/WhatsAppSessionManagementController.php app/Http/Controllers/User/WhatsAppAccountManagementController.php
mv app/Http/Controllers/User/WhatsAppSessionStatusController.php app/Http/Controllers/User/WhatsAppAccountStatusController.php

# API Controllers
mv app/Http/Controllers/Api/v1/WhatsApp/SessionController.php app/Http/Controllers/Api/v1/WhatsApp/AccountController.php

# Events & Exceptions
mv app/Events/WhatsAppSessionStatusChangedEvent.php app/Events/WhatsAppAccountStatusChangedEvent.php
mv app/Exceptions/WhatsAppSessionNotFoundException.php app/Exceptions/WhatsAppAccountNotFoundException.php
```

### Frontend Vue Files
```bash
# Vue Components
mv resources/js/Pages/User/Settings/WhatsAppSessions.vue resources/js/Pages/User/Settings/WhatsAppAccounts.vue
```

### Node.js Service Files (WhatsApp Web.js Service)
```bash
cd whatsapp-service/src/services/
mv SessionRestoration.js AccountRestoration.js
mv SessionHealthMonitor.js AccountHealthMonitor.js
mv SessionStorageOptimizer.js AccountStorageOptimizer.js
mv SessionPool.js AccountPool.js
```

### Shell Scripts
```bash
# Maintenance scripts
mv scripts/maintenance/cleanup-whatsapp-sessions.sh scripts/maintenance/cleanup-whatsapp-accounts.sh

# Setup scripts
mv scripts/setup/initialize-session.php scripts/setup/initialize-account.php
```

## Find & Replace Commands (After Renaming Files)

### Using sed (Unix/Mac/Linux) - BE CAREFUL!
```bash
# ⚠️ BACKUP FIRST: cp -r app/ app_backup/

# In PHP files - Core class replacements
find app/ -name "*.php" -type f -exec sed -i '' 's/WhatsAppSession/WhatsAppAccount/g' {} \;
find app/ -name "*.php" -type f -exec sed -i '' 's/ContactSession/ContactAccount/g' {} \;

# In PHP files - Variable references
find app/ -name "*.php" -type f -exec sed -i '' 's/whatsapp_session/whatsapp_account/g' {} \;
find app/ -name "*.php" -type f -exec sed -i '' 's/whatsapp_sessions/whatsapp_accounts/g' {} \;

# In Vue/JS files
find resources/js/ -name "*.vue" -o -name "*.js" | xargs sed -i '' 's/sessionsList/accountsList/g'
find resources/js/ -name "*.vue" -o -name "*.js" | xargs sed -i '' 's/WhatsAppSessions/WhatsAppAccounts/g'

# In routes
sed -i '' 's/whatsapp-sessions/whatsapp-accounts/g' routes/web.php
sed -i '' 's/whatsapp\.sessions\./whatsapp.accounts./g' routes/web.php
```

### Manual Find & Replace (SAFER OPTION)
**Gunakan IDE/Editor Find & Replace:**

#### Pattern 1: Class Names
- Find: `WhatsAppSession`
- Replace: `WhatsAppAccount`

#### Pattern 2: Model References
- Find: `ContactSession`
- Replace: `ContactAccount`

#### Pattern 3: Database References
- Find: `whatsapp_session_id`
- Replace: `whatsapp_account_id`

#### Pattern 4: URL/Route References
- Find: `whatsapp-sessions`
- Replace: `whatsapp-accounts`

## Important Routes to Update

### In `routes/web.php`:
```php
// Find these patterns:
Route::prefix('settings/whatsapp-sessions')
Route::name('whatsapp.sessions.')

// Replace with:
Route::prefix('settings/whatsapp-accounts')
Route::name('whatsapp.accounts.')
```

### In `routes/api.php`:
```php
// Find:
Route::prefix('whatsapp/sessions')

// Replace:
Route::prefix('whatsapp/accounts')
```

## Configuration Updates

### In `config/whatsapp.php`:
```php
// Find sections that reference:
'sessions' => [...]

// Replace with:
'accounts' => [...]
```

## Clear All Caches After Changes
```bash
# Laravel caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

# Frontend build
npm run build

# Restart services if needed
php artisan queue:restart
```

## Test Everything
```bash
# Laravel tests
php artisan test

# Frontend tests
npm run test

# Manual test WhatsApp functionality
```

## Git Commands
```bash
# Create feature branch
git checkout -b feature/whatsapp-sessions-to-accounts

# Add changes
git add .

# Commit changes
git commit -m "Refactor: Rename WhatsApp Sessions to WhatsApp Accounts

- Renamed core model files
- Updated database schema references
- Updated routes and URLs
- Find & replace session references

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

## Rollback Plan (If Needed)
```bash
# Git rollback
git reset --hard HEAD~1

# Database rollback (if migration already run)
php artisan migrate:rollback
```

---

**⚠️ IMPORTANT:**
1. **BACKUP** sebelum menjalankan commands
2. **TEST** di staging environment terlebih dahulu
3. **INCREMENTAL** changes, tidak semua sekaligus
4. **REVIEW** changes sebelum commit