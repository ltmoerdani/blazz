# DESIGN - Organization → Workspace Migration

## AS-IS BASELINE (FORENSIC ANALYSIS & SCAN SUMMARY)

### **Database Schema Evidence (Phase 1 Verified):**
**Organizations Table Structure:**
```sql
-- File: database/migrations/*_create_organizations_table.php
CREATE TABLE organizations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    uuid CHAR(50) UNIQUE,
    identifier VARCHAR(128),
    name VARCHAR(255) NULL,
    address TEXT NULL, 
    metadata TEXT NULL,
    timezone VARCHAR(128) NULL,
    created_by BIGINT UNSIGNED,
    timestamps
);
```

**FK Dependency Pattern Evidence:**
```sql
-- 24+ migration files dengan organization_id FK constraints:
$table->unsignedBigInteger('organization_id');
$table->foreign('organization_id')->references('id')->on('organizations');

// CASCADE DELETE example (subscriptions table):
$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');

// AFFECTED TABLES dengan FK constraints:
// chats, contacts, contact_groups, campaigns, templates, teams, team_invites
// subscriptions, billing_credits, billing_debits, billing_invoices, billing_payments
// billing_transactions, documents, organization_api_keys, auto_replies, contact_fields
// tickets, audit_logs (24+ tables total)
```

### **Service Layer Evidence (Phase 1 Verified):**
**OrganizationService Pattern:**
```php
// File: app/Services/OrganizationService.php
// DEPENDENCIES (15+ imports required for WorkspaceService):
use App\Http\Resources\OrganizationsResource;
use App\Http\Resources\BillingResource;
use App\Http\Resources\UserResource;
use App\Models\BillingCredit, BillingDebit, BillingInvoice, BillingPayment;
use App\Models\BillingTransaction, Organization, Setting, Subscription;
use App\Models\SubscriptionPlan, Team, Template, User;
use DB, Str, Propaganistas\LaravelPhone\PhoneNumber;

// METHOD SIGNATURES:
public function get(object $request, $userId = null)           // Line 32
public function getByUuid($request, $uuid = null)              // Line 47  
public function store(Object $request)                         // Line 75
public function update($request, $uuid)                        // Line 148
public function storeTransaction($request, $uuid)              // Line 186
public function destroy($uuid)                                 // Line 224
```

### **Middleware Evidence (Phase 1 Verified):**
**SetOrganizationFromSession Pattern:**
```php
// File: app/Http/Middleware/SetOrganizationFromSession.php
public function handle($request, Closure $next)
{
    if ($request->session()->has('current_organization')) {
        $request->merge(['organization' => $request->session()->get('current_organization')]);
    }
    return $next($request);
}

// KERNEL REGISTRATION (app/Http/Kernel.php):
// Global web middleware stack position:
'web' => [
    \App\Http\Middleware\SetOrganizationFromSession::class, // FIRST: Set organization
    \App\Http\Middleware\AuditLoggingMiddleware::class,    // SECOND: Log dengan context  
    \App\Http\Middleware\Localization::class,
],

// Named middleware alias:
'setOrganization' => \App\Http\Middleware\SetOrganizationFromSession::class,
'check.organization' => \App\Http\Middleware\CheckOrganizationId::class,
```

### **Organization Model Evidence (Phase 1 Verified):**
**Model Relationships:**
```php
// File: app/Models/Organization.php
public function teams() {
    return $this->hasMany(Team::class, 'organization_id');
}

public function owner() {
    return $this->belongsTo(Team::class, 'id', 'organization_id')->where('role', 'owner');
}

public function subscription() {
    return $this->belongsTo(Subscription::class, 'id', 'organization_id');
}

// Query method dengan complex relationships:
public function listAll($searchTerm, $userId = null) {
    return $this->with(['teams.user', 'owner.user', 'subscription.plan'])
        ->withCount('teams')->latest()->paginate(10);
}
```

### **Frontend Pattern Evidence (Phase 1 Verified):**
**Vue Component Organization Props:**
```javascript
// Pattern in resources/js/Pages/Auth/Invite.vue line 78
const props = defineProps(['flash', 'config', 'organization', 'companyConfig', 'invite', 'user']);

// Pattern in resources/js/Components/ProfileModal.vue lines 43-48
organization_name: props.organization?.name,
address: getAddressDetail(props.organization?.address, 'street'),
city: getAddressDetail(props.organization?.address, 'city'),
```

**Route Pattern Evidence:**
```php
// File: routes/web.php - Organization route mappings:
// User Panel Routes:
Route::get('/select-organization', [OrganizationController::class, 'index']);
Route::post('/select-organization', [OrganizationController::class, 'selectOrganization']);
Route::post('/organization', [User\OrganizationController::class, 'store']);
Route::put('/profile/organization', [ProfileController::class, 'updateOrganization']);

// Admin Panel Routes:
Route::resource('organizations', Admin\OrganizationController::class);

// CONTROLLER FILES:
// app/Http/Controllers/OrganizationController.php (base)
// app/Http/Controllers/User/OrganizationController.php (user panel)
// app/Http/Controllers/Admin/OrganizationController.php (admin panel)
```

**Vue Route Pattern:**
```javascript
// Pattern in OrganizationModal.vue
form.post('/organization', { preserveScroll: true })
```

---

## TARGET/DELTA DESIGN (EVIDENCE-BASED ADAPTATION)

### DES-1: Database Migration Strategy (Zero-Downtime)
**Current State:** organizations table dengan 8 fields + 24 dependent migrations  
**Target State:** workspaces table dengan identical schema + updated FK references  
**Delta:** Table rename + FK cascade updates

**Implementation Strategy:**
```sql
-- Step 1: Create workspaces table (exact copy)
CREATE TABLE workspaces (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    uuid CHAR(50) UNIQUE, 
    identifier VARCHAR(128),
    name VARCHAR(255) NULL,
    address TEXT NULL,
    metadata TEXT NULL,
    timezone VARCHAR(128) NULL,
    created_by BIGINT UNSIGNED,
    timestamps
);

-- Step 2: Dual-write setup untuk data consistency
-- Step 3: FK migration: organization_id → workspace_id (24 tables)
-- Step 4: Application read switch
-- Step 5: Drop organizations table
```

### DES-2: Service Layer Migration (Duplication Strategy)
**Current State:** OrganizationService dengan 6 public methods  
**Target State:** WorkspaceService dengan identical method signatures  
**Delta:** Class duplication + method parameter consistency

**Implementation Strategy:**
```php
// EXACT copy of OrganizationService → WorkspaceService
// File: app/Services/WorkspaceService.php

// CRITICAL: Copy ALL 15+ dependencies:
use App\Http\Resources\WorkspacesResource;        // OrganizationsResource → WorkspacesResource
use App\Http\Resources\BillingResource;         // Keep same
use App\Http\Resources\UserResource;            // Keep same
use App\Models\BillingCredit, BillingDebit, BillingInvoice, BillingPayment;
use App\Models\BillingTransaction, Workspace, Setting, Subscription;  // Organization → Workspace
use App\Models\SubscriptionPlan, Team, Template, User;
use DB, Str, Propaganistas\LaravelPhone\PhoneNumber;

// METHOD SIGNATURES (identical):
public function get(object $request, $userId = null)           // Identical signature
public function getByUuid($request, $uuid = null)              // Identical signature  
public function store(Object $request)                         // Identical signature
public function update($request, $uuid)                        // Identical signature
public function storeTransaction($request, $uuid)              // Identical signature
public function destroy($uuid)                                 // Identical signature

// INTERNAL CHANGES:
// Organization::class → Workspace::class
// OrganizationsResource::class → WorkspacesResource::class
// All 'organization' strings → 'workspace' (internal logic only)
```

### DES-3: Session Context Migration (Request Merge Pattern)
**Current State:** SetOrganizationFromSession dengan current_organization session key  
**Target State:** SetWorkspaceFromSession dengan current_workspace session key  
**Delta:** Session key rename + request merge parameter update

**Implementation Strategy:**
```php
// DUPLICATE exact pattern dari SetOrganizationFromSession
// File: app/Http/Middleware/SetWorkspaceFromSession.php
public function handle($request, Closure $next)
{
    if ($request->session()->has('current_workspace')) {
        $request->merge(['workspace' => $request->session()->get('current_workspace')]);
    }
    return $next($request);
}

// KERNEL REGISTRATION UPDATES (app/Http/Kernel.php):
// Replace in global web middleware:
'web' => [
    \App\Http\Middleware\SetWorkspaceFromSession::class, // RENAME dari SetOrganization
    \App\Http\Middleware\AuditLoggingMiddleware::class,  // Update to use workspace context
    \App\Http\Middleware\Localization::class,
],

// Update named middleware aliases:
'setWorkspace' => \App\Http\Middleware\SetWorkspaceFromSession::class,
'check.workspace' => \App\Http\Middleware\CheckWorkspaceId::class,  // Rename dari CheckOrganizationId

// DEPENDENT MIDDLEWARE updates:
// AuditLoggingMiddleware: Update to use workspace context
// AdvancedRateLimitMiddleware: Update rate limiting keys
```

### DES-4: Frontend Vue Component Migration (Props Mapping)
**Current State:** 26 Vue components dengan organization props dan data  
**Target State:** Same components dengan workspace props dan data  
**Delta:** Props renaming + API response field mapping

**Implementation Strategy:**
```javascript
// DUPLICATE exact pattern dari Organization props
// Before: defineProps(['organization', 'config', ...])
// After:  defineProps(['workspace', 'config', ...])

// EXACT same data access patterns:
// Before: props.organization?.name
// After:  props.workspace?.name

// Route duplication:
// Before: form.post('/organization', ...)  
// After:  form.post('/workspace', ...)
```

---

## RISK MITIGATION STRATEGIES

### From Phase 0 Risk Analysis:
**Risk:** FK constraint violations during table rename  
**Mitigation:** Staged migration dengan dual-write period  
**Validation:** Test dengan database backup + rollback procedures

**Risk:** Session corruption during middleware transition  
**Mitigation:** Backward compatibility layer untuk session keys  
**Validation:** Session testing dengan multiple organization contexts

**Risk:** Frontend component breaking during props rename  
**Mitigation:** Feature flag deployment + component-by-component updates  
**Validation:** E2E testing untuk complete user workflows

---

## IMPLEMENTATION COMPLEXITY ANALYSIS

### DES-5: Migration Phases dengan Evidence-Based Sequencing

**Phase 1 - Frontend/UI (26 components, 7 locales, 7 routes):**
- **Complexity:** MEDIUM - Props renaming pattern consistent
- **Evidence:** Vue component patterns show consistent organization prop usage
- **Strategy:** Component-by-component duplication with backward compatibility

**Phase 2 - Database (1 table, 24 FK dependencies):**
- **Complexity:** HIGH - FK constraint dependencies require precise ordering  
- **Evidence:** organization_id pattern across 24 migrations confirmed
- **Strategy:** Zero-downtime dengan dual-write approach

**Phase 3 - Backend (13 Organization classes, 6 service methods, 5+ middleware files):**
- **Complexity:** HIGH - Service has 15+ model dependencies, global middleware pipeline changes
- **Evidence:** OrganizationService complex dependencies, middleware registered globally
- **Strategy:** Class duplication + dependency mapping + kernel middleware updates + cascade changes

---

## SELF-VERIFICATION CHECKPOINT - DESIGN

**Verification Actions Performed:**
1. Cross-check design vs Phase 1 forensic findings ✅
2. Validate service integration matches exact method signatures ✅  
3. Verify frontend patterns align with existing implementations ✅
4. Check design traceability to requirements ✅

**Major Corrections Applied (Deep Scan Results):**
- 🔄 **Service Dependencies:** Added 15+ model/resource imports critical for WorkspaceService
- 🔄 **Middleware Pipeline:** SetOrganizationFromSession is GLOBAL web middleware (not optional)
- 🔄 **Route Structure:** 3 separate controllers (User, Admin, base) + 7 route definitions
- 🔄 **FK Constraints:** 24+ tables dengan actual foreign key constraints + cascade deletes
- 🔄 **Model Relationships:** Complex relationships (teams.user, owner.user, subscription.plan)
- 🔄 **Middleware Dependencies:** AuditLoggingMiddleware + AdvancedRateLimitMiddleware affected
- 🔄 **Backend Complexity:** Upgraded dari MEDIUM → HIGH due to global middleware changes

**Phase 1 Forensics Performed:** ✅ YES  
**Confidence Level:** 🔥 HIGH  
**Ready for User Confirmation:** ✅ YES

**References:** docs/workspace-migration/assumption.md (ASM-1, ASM-2, ASM-3, ASM-4), docs/workspace-migration/requirements.md (REQ-1, REQ-2, REQ-3, REQ-4, REQ-5), docs/workspace-migration/tasks.md (akan dibuat setelah confirmation)