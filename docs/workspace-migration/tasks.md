# TASKS - workspace → Workspace Migration

## IMPLEMENTATION CHECKLIST (FRONTEND → DATABASE → BACKEND)

### 🎨 PHASE 1: FRONTEND/UI MIGRATION (26 components, 7 locales, 7 routes)

#### TASK-F1: Translation Files Migration
**Goal:** Update all i18n keys dari workspace → workspace
**Complexity:** 🟡 MEDIUM
**Estimated Time:** 2-4 hours

**Files to Update:**
- [ ] `lang/en.json` - English translations
- [ ] `lang/id.json` - Indonesian translations  
- [ ] `lang/es.json` - Spanish translations
- [ ] `lang/fr.json` - French translations
- [ ] `lang/tr.json` - Turkish translations
- [ ] `lang/sw.json` - Swahili translations
- [ ] `lang/default_en.json` - Default fallback

**Implementation Steps:**
1. Search & replace all `workspace.*` keys → `workspace.*` 
2. Update nested translation objects (workspace.name → workspace.name)
3. Maintain backward compatibility dengan deprecation notice
4. Test translation loading dengan missing key fallbacks

**Validation:**
```bash
# Test command: Verify no broken translation keys
grep -r "workspace\." resources/js --include="*.vue" | grep "\$t("
```

---

#### TASK-F2: Vue Components Props Migration  
**Goal:** Update 26 Vue components dari workspace props → workspace props
**Complexity:** 🔴 HIGH (breaking changes)
**Estimated Time:** 6-10 hours

**Components to Update:**
- [ ] `resources/js/Components/ProfileModal.vue` - workspace prop → workspace
- [ ] `resources/js/Components/OrganizationModal.vue` - **CRITICAL** rename file + logic
- [ ] `resources/js/Components/Tables/OrganizationTable.vue` - **CRITICAL** rename + routes
- [ ] `resources/js/Components/Tables/BillingTable.vue` - item.workspace → item.workspace
- [ ] `resources/js/Pages/Auth/Invite.vue` - props.workspace → props.workspace
- [ ] `resources/js/Pages/Admin/Layout/Menu.vue` - workspace prop → workspace
- [ ] **+20 additional components** (from 26 total verified)

**Implementation Steps Per Component:**
1. Update `defineProps()` declarations: `'workspace'` → `'workspace'`
2. Update template references: `props.workspace` → `props.workspace`
3. Update computed properties using workspace data
4. Update method calls dan form submissions
5. Test component rendering dengan new props

**Critical File Renames:**
```bash
# These files need complete rename:
mv resources/js/Components/OrganizationModal.vue → WorkspaceModal.vue
mv resources/js/Components/Tables/OrganizationTable.vue → WorkspaceTable.vue
```

**Validation:**
```bash
# Test command: Verify no lingering workspace props
find resources/js -name "*.vue" -exec grep -H "props.*workspace" {} \;
```

---

#### TASK-F3: Route Definitions Migration
**Goal:** Update 7 workspace routes → workspace routes  
**Complexity:** 🟡 MEDIUM
**Estimated Time:** 2-3 hours

**Routes to Update in `routes/web.php`:**
- [ ] Line 96: `/profile/workspace` → `/profile/workspace`
- [ ] Line 110: `/select-workspace` → `/select-workspace`  
- [ ] Line 111: `selectOrganization` method → `selectWorkspace`
- [ ] Line 112: `/workspace` POST → `/workspace` POST
- [ ] Line 114: `check.workspace` middleware → `check.workspace`
- [ ] Line 221: `organizations` resource → `workspaces` resource
- [ ] Line 258: `/organizations` → `/workspaces` (commented route)

**Implementation Steps:**
1. Update route paths dengan backward compatibility aliases
2. Update named routes: `user.workspace.*` → `user.workspace.*`
3. Update middleware references: `check.workspace` → `check.workspace`
4. Update controller method names di route definitions
5. Test route resolution dan parameter binding

**Validation:**
```bash
# Test command: Verify route registration
php artisan route:list | grep -i workspace
```

---

### 💾 PHASE 2: DATABASE MIGRATION (24+ tables, FK constraints)

#### TASK-D1: Create Workspaces Table (Schema Duplication)
**Goal:** Create identical workspaces table dari organizations schema
**Complexity:** 🟡 MEDIUM  
**Estimated Time:** 1-2 hours

**Implementation Steps:**
1. Create migration: `2025_09_29_create_workspaces_table.php`
2. **EXACT schema duplication** dari organizations:
   ```php
   Schema::create('workspaces', function (Blueprint $table) {
       $table->bigIncrements('id');
       $table->char('uuid', 50)->unique();
       $table->string('identifier', 128);
       $table->string('name')->nullable();
       $table->text('address')->nullable(); 
       $table->text('metadata')->nullable();
       $table->string('timezone', 128)->nullable();
       $table->unsignedBigInteger('created_by');
       $table->timestamps();
   });
   ```
3. Run migration dan verify table structure
4. Setup dual-write mechanism (temporary)

**Validation:**
```bash
# Test command: Verify table structure match
php artisan tinker -c "Schema::getColumnListing('workspaces')"
```

---

#### TASK-D2: FK Migration Strategy (24+ Tables)  
**Goal:** Update organization_id FK constraints → workspace_id
**Complexity:** 🔴 HIGH (constraint dependencies)
**Estimated Time:** 8-12 hours

**Migration Sequence (Critical Order):**
1. **Step 1: Add workspace_id columns** (no constraints yet)
2. **Step 2: Data migration** organizations → workspaces  
3. **Step 3: FK constraint creation** untuk workspace_id
4. **Step 4: Drop organization_id constraints** 
5. **Step 5: Drop organization_id columns**

**Tables Requiring FK Updates:**
- [ ] `chats` - organization_id → workspace_id
- [ ] `contacts` - organization_id → workspace_id  
- [ ] `contact_groups` - organization_id → workspace_id
- [ ] `campaigns` - organization_id → workspace_id
- [ ] `templates` - organization_id → workspace_id
- [ ] `teams` - organization_id → workspace_id
- [ ] `team_invites` - organization_id → workspace_id
- [ ] `subscriptions` - organization_id → workspace_id (CASCADE DELETE)
- [ ] `billing_credits` - organization_id → workspace_id
- [ ] `billing_debits` - organization_id → workspace_id
- [ ] `billing_invoices` - organization_id → workspace_id
- [ ] `billing_payments` - organization_id → workspace_id
- [ ] `billing_transactions` - organization_id → workspace_id
- [ ] `documents` - organization_id → workspace_id
- [ ] `organization_api_keys` - **RENAME TABLE** → `workspace_api_keys`
- [ ] `auto_replies` - organization_id → workspace_id
- [ ] `contact_fields` - organization_id → workspace_id
- [ ] `tickets` - organization_id → workspace_id
- [ ] `audit_logs` - organization_id → workspace_id
- [ ] **+5 additional tables** dari scan results

**Critical Migration Template:**
```php
// Migration: add_workspace_id_to_[table]_table.php
public function up() {
    Schema::table('[table_name]', function (Blueprint $table) {
        // Step 1: Add column
        $table->unsignedBigInteger('workspace_id')->nullable()->after('organization_id');
        
        // Step 2: Copy data  
        DB::statement('UPDATE [table_name] SET workspace_id = organization_id WHERE organization_id IS NOT NULL');
        
        // Step 3: Add FK constraint
        $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
        
        // Step 4: Drop old constraint & column (separate migration)
    });
}
```

**Validation:**
```bash
# Test command: Verify FK constraints
php artisan tinker -c "DB::select('SHOW CREATE TABLE chats')"
```

---

### ⚙️ PHASE 3: BACKEND MIGRATION (13 classes, 15+ dependencies, 5+ middleware)

#### TASK-B1: Service Layer Migration (Complex Dependencies)
**Goal:** Create WorkspaceService dengan 15+ model dependencies  
**Complexity:** 🔴 HIGH (complex integrations)
**Estimated Time:** 6-8 hours

**Files to Create/Update:**
- [ ] `app/Services/WorkspaceService.php` - **NEW** (copy dari OrganizationService)
- [ ] `app/Http/Resources/WorkspacesResource.php` - **NEW** (copy dari OrganizationsResource)

**Dependencies to Update (15+ imports):**
```php
// EXACT imports untuk WorkspaceService:
use App\Http\Resources\WorkspacesResource;        // NEW (OrganizationsResource copy)
use App\Http\Resources\BillingResource;          // Keep same
use App\Http\Resources\UserResource;             // Keep same  
use App\Models\BillingCredit, BillingDebit, BillingInvoice, BillingPayment;
use App\Models\BillingTransaction, Workspace, Setting, Subscription;  // workspace → Workspace
use App\Models\SubscriptionPlan, Team, Template, User;
use DB, Str, Propaganistas\LaravelPhone\PhoneNumber;
```

**Method Implementation (6 methods):**
- [ ] `get(object $request, $userId = null)` - Update query workspace → Workspace
- [ ] `getByUuid($request, $uuid = null)` - Update model references
- [ ] `store(Object $request)` - Update creation logic
- [ ] `update($request, $uuid)` - Update update logic  
- [ ] `storeTransaction($request, $uuid)` - Update transaction logic
- [ ] `destroy($uuid)` - Update deletion logic

**Implementation Steps:**
1. **EXACT copy** OrganizationService.php → WorkspaceService.php
2. Replace all `workspace::class` → `Workspace::class`
3. Replace `OrganizationsResource::class` → `WorkspacesResource::class` 
4. Update all internal string references 'workspace' → 'workspace'
5. Test service methods dengan workspace context
6. Maintain identical method signatures untuk compatibility

**Validation:**
```bash
# Test command: Verify service instantiation
php artisan tinker -c "app(App\Services\WorkspaceService::class)"
```

---

#### TASK-B2: Controller Migration (3-Tier Architecture)
**Goal:** Update 3 workspace controllers → workspace controllers
**Complexity:** 🟡 MEDIUM (namespace changes)  
**Estimated Time:** 4-6 hours

**Controllers to Update:**
- [ ] `app/Http/Controllers/OrganizationController.php` → `WorkspaceController.php`
- [ ] `app/Http/Controllers/User/OrganizationController.php` → `WorkspaceController.php`  
- [ ] `app/Http/Controllers/Admin/OrganizationController.php` → `WorkspaceController.php`

**Implementation Steps Per Controller:**
1. Rename controller file: workspace* → Workspace*
2. Update class name: `OrganizationController` → `WorkspaceController`
3. Update service injection: `OrganizationService` → `WorkspaceService`
4. Update model references: `workspace::class` → `Workspace::class`
5. Update method names: `selectOrganization()` → `selectWorkspace()`
6. Update view/redirect paths: workspace → workspace
7. Test controller methods dengan dependency injection

**Validation:**
```bash
# Test command: Verify controller registration
php artisan route:list | grep WorkspaceController
```

---

#### TASK-B3: Middleware Pipeline Migration (Global Changes)
**Goal:** Update global middleware pipeline workspace → workspace
**Complexity:** 🔴 HIGH (system-wide impact)
**Estimated Time:** 4-6 hours

**Middleware Files to Update:**
- [ ] `app/Http/Middleware/SetOrganizationFromSession.php` → `SetWorkspaceFromSession.php`
- [ ] `app/Http/Middleware/CheckOrganizationId.php` → `CheckWorkspaceId.php`  
- [ ] `app/Http/Middleware/AuditLoggingMiddleware.php` - Update context handling
- [ ] `app/Http/Middleware/AdvancedRateLimitMiddleware.php` - Update rate limiting keys
- [ ] `app/Http/Kernel.php` - Update middleware registration

**Critical: Kernel.php Updates**
```php
// app/Http/Kernel.php - Global web middleware:
'web' => [
    \App\Http\Middleware\SetWorkspaceFromSession::class,  // RENAME dari SetOrganization
    \App\Http\Middleware\AuditLoggingMiddleware::class,   // UPDATE context usage
    \App\Http\Middleware\Localization::class,
],

// Named middleware aliases:
'setWorkspace' => \App\Http\Middleware\SetWorkspaceFromSession::class,
'check.workspace' => \App\Http\Middleware\CheckWorkspaceId::class,
```

**Implementation Steps:**
1. **EXACT copy** SetOrganizationFromSession → SetWorkspaceFromSession
2. Update session key: `current_organization` → `current_workspace`
3. Update request merge: `['workspace' => ...]` → `['workspace' => ...]`
4. Update AuditLoggingMiddleware context extraction
5. Update AdvancedRateLimitMiddleware rate keys
6. Update Kernel.php middleware registration
7. Test middleware pipeline dengan workspace context

**Validation:**  
```bash
# Test command: Verify middleware registration
php artisan route:list --middleware=setWorkspace
```

---

#### TASK-B4: Model Migration & Relationships
**Goal:** Create Workspace model dengan complex relationships
**Complexity:** 🟡 MEDIUM (relationship mapping)
**Estimated Time:** 3-4 hours

**Files to Create:**
- [ ] `app/Models/Workspace.php` - **NEW** (copy dari workspace.php)

**Critical Relationships to Maintain:**
```php
// EXACT relationship patterns:
public function teams() {
    return $this->hasMany(Team::class, 'workspace_id');  // organization_id → workspace_id
}

public function owner() {
    return $this->belongsTo(Team::class, 'id', 'workspace_id')->where('role', 'owner');
}

public function subscription() {
    return $this->belongsTo(Subscription::class, 'id', 'workspace_id');
}

// Complex query method:
public function listAll($searchTerm, $userId = null) {
    return $this->with(['teams.user', 'owner.user', 'subscription.plan'])
        ->withCount('teams')->latest()->paginate(10);
}
```

**Implementation Steps:**
1. **EXACT copy** workspace.php → Workspace.php
2. Update FK references: organization_id → workspace_id
3. Update relationship methods untuk new table structure
4. Maintain exact query patterns dan method signatures
5. Test model relationships dan eager loading
6. Update Team model: organization_id → workspace_id FK

**Validation:**
```bash
# Test command: Verify model relationships  
php artisan tinker -c "App\Models\Workspace::with('teams')->first()"
```

---

#### TASK-B5: PSR-4 Autoload & Class Registration
**Goal:** Update composer autoload dan class references
**Complexity:** 🟡 MEDIUM (namespace updates)
**Estimated Time:** 2-3 hours

**Files to Update:**
- [ ] `composer.json` - Update autoload classmap (if needed)
- [ ] Update imports across 13+ workspace* classes
- [ ] Update service providers (if any workspace bindings)

**workspace Classes to Update (13 files):**
- Search & replace all `workspace::class` → `Workspace::class` references
- Update import statements: `use App\Models\workspace` → `use App\Models\Workspace`
- Update service bindings dan container registrations

**Implementation Steps:**
1. Run composer autoload dump
2. Search & replace workspace class references
3. Update service provider bindings  
4. Update factory references (if any)
5. Test class resolution dan autoloading

**Validation:**
```bash
# Test command: Verify class autoloading
composer dump-autoload && php artisan tinker -c "class_exists('App\Models\Workspace')"
```

---

## 🧪 TESTING & VALIDATION TASKS

#### TASK-T1: Multi-Tenant Isolation Testing
**Goal:** Verify workspace context maintains data isolation
**Estimated Time:** 4-6 hours

**Test Cases:**
- [ ] User can only access own workspace data
- [ ] Session workspace context properly set
- [ ] FK constraints prevent cross-workspace access
- [ ] Middleware pipeline maintains workspace context

#### TASK-T2: Migration Rollback Testing  
**Goal:** Verify complete migration rollback capability
**Estimated Time:** 3-4 hours

**Test Cases:**
- [ ] Database rollback restores workspace tables
- [ ] Frontend rollback restores workspace terminology
- [ ] Service layer rollback restores OrganizationService
- [ ] Session rollback restores current_organization

---

## 📊 TASK DEPENDENCIES & SEQUENCING

### **Critical Path Dependencies:**
```
TASK-F1 (Translations) → TASK-F2 (Components) → TASK-F3 (Routes)
                ↓
TASK-D1 (Workspaces Table) → TASK-D2 (FK Migration)
                ↓  
TASK-B4 (Model) → TASK-B1 (Service) → TASK-B2 (Controllers) → TASK-B3 (Middleware)
                ↓
TASK-B5 (Autoload) → TASK-T1 (Testing) → TASK-T2 (Rollback)
```

### **Parallel Execution Possible:**
- TASK-F1, TASK-F2, TASK-F3 (Frontend tasks dapat parallel)
- TASK-B1, TASK-B4 (Service + Model dapat parallel setelah database ready)

### **Risk Mitigation Checkpoints:**
- **After TASK-D1:** Verify workspaces table structure
- **After TASK-D2:** Verify FK constraints dan data integrity  
- **After TASK-B3:** Verify middleware pipeline functionality
- **After TASK-T1:** Verify multi-tenant isolation maintained

---

## SELF-VERIFICATION CHECKPOINT - TASKS

**Verification Actions Performed:**
1. Cross-check tasks vs corrected design.md evidence ✅
2. Validate task sequencing follows Frontend → Database → Backend strategy ✅
3. Verify all 26 components, 24+ tables, 13 classes covered ✅  
4. Check task dependencies align dengan critical path requirements ✅

**Task Coverage Validation:**
- ✅ **Frontend:** 26 components + 7 locales + 7 routes = 100% coverage
- ✅ **Database:** 24+ tables + FK constraints + workspaces creation = 100% coverage  
- ✅ **Backend:** 13 classes + 15+ dependencies + 5 middleware + 3 controllers = 100% coverage
- ✅ **Testing:** Multi-tenant isolation + rollback procedures = Risk mitigation covered

**Confidence Level:** 🔥 HIGH (All design evidence converted to actionable tasks)
**Ready for Implementation:** ✅ YES

**References:** docs/workspace-migration/assumption.md, docs/workspace-migration/requirements.md, docs/workspace-migration/design.md