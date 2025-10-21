# Entity Relationship Design - Blazz Database

> **Complete ERD Documentation dengan Mermaid Diagrams**  
> **Database Version:** MySQL 8.0+  
> **Total Tables:** 60+ tables

---

## 📋 TABLE OF CONTENTS

1. [Entity Overview](#entity-overview)
2. [Core Domain - User & Workspace Management](#core-domain)
3. [Messaging Domain - Chats & Campaigns](#messaging-domain)
4. [Contact Management Domain](#contact-management-domain)
5. [Billing & Subscription Domain](#billing-domain)
6. [Support & CMS Domain](#support-domain)
7. [Security & Audit Domain](#security-domain)
8. [Complete ERD Diagram](#complete-erd)

---

## 📊 ENTITY OVERVIEW

### Table Classification by Domain

```
CORE DOMAIN (User & Workspace Management)
├── users (Central user authentication & profile)
├── workspaces (Tenant/organization isolation)
├── teams (User-workspace membership with roles)
├── team_invites (Pending workspace invitations)
└── roles (System-wide role definitions)

MESSAGING DOMAIN (WhatsApp Communication)
├── chats (Individual messages - 10M+ records)
├── chat_media (Media attachments - images/videos/documents)
├── chat_notes (Internal agent notes on conversations)
├── chat_tickets (Chat-to-support ticket conversion)
├── chat_ticket_logs (Ticket activity history)
├── templates (WhatsApp approved message templates)
├── campaigns (Bulk messaging campaigns)
├── campaign_logs (Individual campaign message delivery - 25M+ records)
├── campaign_log_retries (Failed message retry tracking)
└── auto_replies (Automated response rules)

CONTACT MANAGEMENT DOMAIN
├── contacts (Customer/contact database - 1.5M+ records)
├── contact_groups (Segmentation groups)
├── contact_contact_group (Many-to-many junction table)
└── contact_fields (Custom field definitions)

BILLING & SUBSCRIPTION DOMAIN
├── subscription_plans (Available pricing plans)
├── subscriptions (Workspace subscription status)
├── billing_invoices (Invoice generation)
├── billing_items (Invoice line items)
├── billing_payments (Payment records)
├── billing_credits (Account credits)
├── billing_debits (Account debits)
├── billing_transactions (Transaction ledger)
├── billing_tax_rates (Tax calculation rules)
├── coupons (Discount codes)
└── payment_gateways (Payment processor config)

SUPPORT & CMS DOMAIN
├── tickets (Customer support tickets)
├── ticket_comments (Ticket conversation threads)
├── ticket_categories (Support categorization)
├── faqs (Knowledge base articles)
├── pages (CMS pages - About, Terms, etc.)
├── blog_posts (Blog content)
├── blog_authors (Blog author profiles)
├── blog_categories (Blog organization)
├── blog_tags (Blog taxonomy)
└── reviews (Customer testimonials)

SECURITY & AUDIT DOMAIN
├── audit_logs (Comprehensive activity tracking)
├── security_incidents (Security event monitoring)
├── authentication_events (Login/logout/failure tracking)
├── rate_limit_violations (API throttling violations)
├── data_access_logs (GDPR compliance tracking)
└── workspace_api_keys (API authentication tokens)

SYSTEM DOMAIN
├── jobs (Background queue jobs)
├── failed_jobs (Failed job records)
├── job_batches (Batch job tracking)
├── notifications (User notification queue)
├── email_logs (Email sending history)
├── email_templates (Transactional email templates)
├── modules (Plugin/extension registry)
├── addons (Marketplace addons)
├── languages (Multi-language support)
├── settings (System configuration)
├── documents (File storage metadata)
├── password_reset_tokens (Password reset verification)
└── seeder_histories (Migration seeder tracking)
```

---

## 🔐 CORE DOMAIN - User & Workspace Management

### Entity Relationship Summary

```
users (1) ──────< (N) teams (N) >────── (1) workspaces
  │                                           │
  │ created_by                     created_by │
  └──────< (N) workspaces ────────────────────┘
  
  │                                           
  └──────< (N) team_invites
```

**Relationships:**
- **users → workspaces**: 1:N (One user can CREATE many workspaces)
- **users ↔ workspaces**: N:M through `teams` (Users can be members of multiple workspaces)
- **workspaces → teams**: 1:N (One workspace has many team members)
- **users → team_invites**: 1:N (One user can invite many people)

### ERD Diagram: Core Domain

```mermaid
erDiagram
    users ||--o{ workspaces : "created_by"
    users ||--o{ teams : "member_of"
    workspaces ||--o{ teams : "has_members"
    users ||--o{ team_invites : "invited_by"
    workspaces ||--o{ team_invites : "invite_to"
    workspaces ||--|| subscriptions : "has_subscription"
    subscription_plans ||--o{ subscriptions : "plan_type"
    
    users {
        bigint id PK
        char uuid UK "Unique 50 chars"
        string email UK "Unique email"
        string first_name
        string last_name
        string role "user/admin"
        timestamp email_verified_at
        string password
        timestamp created_at
        timestamp deleted_at "Soft delete"
    }
    
    workspaces {
        bigint id PK
        char uuid UK
        string identifier "URL-safe slug"
        string name
        text metadata "JSON config"
        string timezone
        bigint created_by FK
        timestamp created_at
        timestamp deleted_at
    }
    
    teams {
        bigint id PK
        char uuid UK
        bigint workspace_id FK
        bigint user_id FK
        enum role "owner/manager"
        enum status "active/suspended"
        bigint created_by FK
        timestamp created_at
    }
    
    team_invites {
        bigint id PK
        char uuid UK
        bigint workspace_id FK
        string email
        enum role "owner/manager"
        enum status "pending/accepted/declined"
        bigint invited_by FK
        timestamp expires_at
        timestamp created_at
    }
    
    subscriptions {
        bigint id PK
        char uuid UK
        bigint workspace_id FK
        bigint plan_id FK
        text payment_details "JSON"
        timestamp start_date
        datetime valid_until
        enum status "trial/active"
        timestamp created_at
    }
    
    subscription_plans {
        bigint id PK
        char uuid UK
        string name
        decimal price
        enum period "monthly/yearly"
        text metadata "JSON features"
        enum status "active/inactive"
        timestamp created_at
    }
```

### Key Relationships Explained

#### 1. **User-Workspace Relationship (Creator)**

```sql
-- One user can CREATE multiple workspaces
SELECT w.* FROM workspaces w
WHERE w.created_by = 42;  -- All workspaces created by user #42
```

**Cardinality:** 1:N (One-to-Many)  
**Foreign Key:** `workspaces.created_by → users.id`

#### 2. **User-Workspace Relationship (Membership)**

```sql
-- One user can be MEMBER of multiple workspaces through teams table
SELECT w.* FROM workspaces w
INNER JOIN teams t ON t.workspace_id = w.id
WHERE t.user_id = 42 AND t.status = 'active';
```

**Cardinality:** N:M (Many-to-Many)  
**Junction Table:** `teams`  
**Additional Attributes:** role (owner/manager), status (active/suspended)

#### 3. **Workspace-Subscription Relationship**

```sql
-- One workspace has ONE active subscription
SELECT s.* FROM subscriptions s
WHERE s.workspace_id = 123
ORDER BY s.created_at DESC
LIMIT 1;  -- Latest subscription
```

**Cardinality:** 1:1 (One-to-One - logically, though physically 1:N for history)  
**Foreign Key:** `subscriptions.workspace_id → workspaces.id`

---

## 💬 MESSAGING DOMAIN - Chats & Campaigns

### Entity Relationship Summary

```
workspaces (1) ──────< (N) chats
                             │
                             ├──────< (N) chat_media
                             ├──────< (N) chat_notes
                             └──────< (N) chat_tickets
                             
workspaces (1) ──────< (N) campaigns
                             │
                             └──────< (N) campaign_logs
                                          │
                                          └──────< (N) campaign_log_retries

workspaces (1) ──────< (N) templates
workspaces (1) ──────< (N) auto_replies
```

**Relationships:**
- **workspaces → chats**: 1:N (Workspace-scoped chat messages)
- **chats → chat_media**: 1:1 (One chat can have one media attachment)
- **chats → contacts**: N:1 (Many chats belong to one contact)
- **campaigns → campaign_logs**: 1:N (One campaign generates many message logs)
- **campaign_logs → campaign_log_retries**: 1:N (Failed logs can have retry attempts)

### ERD Diagram: Messaging Domain

```mermaid
erDiagram
    workspaces ||--o{ chats : "workspace_scoped"
    contacts ||--o{ chats : "chat_participant"
    chats ||--o| chat_media : "has_media"
    chats ||--o{ chat_notes : "internal_notes"
    chats ||--o| chat_tickets : "converted_to"
    users ||--o{ chat_notes : "created_by"
    
    workspaces ||--o{ campaigns : "workspace_scoped"
    templates ||--o{ campaigns : "uses_template"
    contact_groups ||--o{ campaigns : "target_group"
    campaigns ||--o{ campaign_logs : "message_delivery"
    contacts ||--o{ campaign_logs : "recipient"
    campaign_logs ||--o{ campaign_log_retries : "retry_attempts"
    
    workspaces ||--o{ templates : "workspace_scoped"
    workspaces ||--o{ auto_replies : "workspace_scoped"
    
    chats {
        bigint id PK
        char uuid UK
        int workspace_id FK
        string wam_id "WhatsApp Message ID"
        int contact_id FK
        enum type "inbound/outbound"
        text metadata "JSON message content"
        int media_id FK
        string status "sent/delivered/read/failed"
        int deleted_by FK
        timestamp created_at
        timestamp deleted_at
    }
    
    chat_media {
        bigint id PK
        char uuid UK
        int workspace_id FK
        int chat_id FK
        string type "image/video/audio/document"
        string media_id "WhatsApp media ID"
        string mime_type
        string filename
        bigint file_size
        string local_path
        text metadata "JSON"
        timestamp created_at
    }
    
    chat_notes {
        bigint id PK
        char uuid UK
        int chat_id FK
        int user_id FK "Agent who added note"
        text note
        timestamp created_at
    }
    
    chat_tickets {
        bigint id PK
        char uuid UK
        int chat_id FK
        int ticket_id FK
        int workspace_id FK
        int created_by FK
        timestamp created_at
    }
    
    campaigns {
        bigint id PK
        char uuid UK
        int workspace_id FK
        string name
        int template_id FK
        int contact_group_id FK
        text metadata "JSON variables"
        string status "pending/processing/completed/failed"
        datetime scheduled_at
        int created_by FK
        timestamp created_at
    }
    
    campaign_logs {
        bigint id PK
        int campaign_id FK
        int contact_id FK
        int chat_id FK "Resulting chat message"
        text metadata "JSON delivery details"
        enum status "pending/success/failed/ongoing"
        int retry_count
        timestamp created_at
    }
    
    campaign_log_retries {
        bigint id PK
        bigint campaign_log_id FK
        int retry_attempt
        string failure_reason
        text error_details "JSON"
        timestamp attempted_at
        timestamp created_at
    }
    
    templates {
        bigint id PK
        char uuid UK
        bigint workspace_id FK
        string meta_id "WhatsApp template ID"
        string name
        string category "MARKETING/UTILITY"
        string language "en_US/id_ID"
        text metadata "JSON template structure"
        string status "APPROVED/PENDING/REJECTED"
        bigint created_by FK
        timestamp created_at
    }
    
    auto_replies {
        bigint id PK
        char uuid UK
        int workspace_id FK
        string name
        text trigger "Keyword atau pattern"
        string match_criteria "exact/contains/regex"
        text metadata "JSON reply content"
        int created_by FK
        timestamp created_at
        timestamp deleted_at
    }
```

### Key Relationships Explained

#### 1. **Chat-Contact Relationship**

```sql
-- Get all chats for a specific contact
SELECT c.* FROM chats c
WHERE c.contact_id = 789
  AND c.workspace_id = 123
ORDER BY c.created_at DESC;
```

**Cardinality:** N:1 (Many-to-One)  
**Logic:** One contact can have many chat messages over time.

#### 2. **Campaign-Template-ContactGroup Relationship**

```sql
-- Campaign references both template and target contact group
SELECT 
    c.name AS campaign_name,
    t.name AS template_name,
    cg.name AS target_group
FROM campaigns c
INNER JOIN templates t ON c.template_id = t.id
INNER JOIN contact_groups cg ON c.contact_group_id = cg.id
WHERE c.workspace_id = 123;
```

**Cardinality:** Campaign N:1 Template, Campaign N:1 ContactGroup

#### 3. **Campaign Execution Flow**

```sql
-- Campaign creates multiple campaign_logs (one per contact)
SELECT 
    cl.contact_id,
    cl.status,
    cl.retry_count,
    COUNT(clr.id) AS total_retries
FROM campaign_logs cl
LEFT JOIN campaign_log_retries clr ON clr.campaign_log_id = cl.id
WHERE cl.campaign_id = 456
GROUP BY cl.id;
```

**Flow:**
1. Campaign created with template + contact_group
2. Job generates campaign_logs for each contact in group
3. Each log tracks delivery status
4. Failed logs spawn campaign_log_retries

---

## 👥 CONTACT MANAGEMENT DOMAIN

### Entity Relationship Summary

```
workspaces (1) ──────< (N) contacts
workspaces (1) ──────< (N) contact_groups
contacts (N) ──────< (M) contact_groups  [via contact_contact_group]
workspaces (1) ──────< (N) contact_fields
```

**Relationships:**
- **workspaces → contacts**: 1:N (Workspace-scoped contacts)
- **workspaces → contact_groups**: 1:N (Workspace-scoped groups)
- **contacts ↔ contact_groups**: N:M (Contacts can be in multiple groups)

### ERD Diagram: Contact Management

```mermaid
erDiagram
    workspaces ||--o{ contacts : "workspace_scoped"
    workspaces ||--o{ contact_groups : "workspace_scoped"
    contacts }o--o{ contact_groups : "membership"
    contact_contact_group }o--|| contacts : "contact_id"
    contact_contact_group }o--|| contact_groups : "group_id"
    workspaces ||--o{ contact_fields : "custom_fields"
    
    contacts {
        bigint id PK
        char uuid UK
        int workspace_id FK
        string first_name
        string last_name
        string phone "E.164 format"
        string email
        string avatar "Profile image URL"
        text address
        text metadata "JSON custom fields"
        tinyint is_favorite
        timestamp latest_chat_created_at "Denormalized"
        int created_by FK
        timestamp created_at
        timestamp deleted_at
        
        INDEX idx_workspace_search "workspace_id, first_name"
        FULLTEXT idx_contact_fulltext "first_name, last_name, phone, email"
    }
    
    contact_groups {
        bigint id PK
        char uuid UK
        int workspace_id FK
        string name
        int created_by FK
        timestamp created_at
        timestamp deleted_at
    }
    
    contact_contact_group {
        bigint id PK
        bigint contact_id FK
        bigint contact_group_id FK
        timestamp created_at
        
        UNIQUE unique_membership "contact_id, contact_group_id"
        INDEX idx_group_members "contact_group_id"
    }
    
    contact_fields {
        bigint id PK
        char uuid UK
        int workspace_id FK
        string name "Field label"
        string field_type "text/number/date/select"
        text options "JSON for select fields"
        boolean is_required
        int created_by FK
        timestamp created_at
    }
```

### Key Relationships Explained

#### 1. **Many-to-Many Contact-Group Relationship**

```sql
-- Get all contacts in a specific group
SELECT c.* FROM contacts c
INNER JOIN contact_contact_group ccg ON ccg.contact_id = c.id
WHERE ccg.contact_group_id = 10
  AND c.workspace_id = 123
  AND c.deleted_at IS NULL;

-- Get all groups a contact belongs to
SELECT cg.* FROM contact_groups cg
INNER JOIN contact_contact_group ccg ON ccg.contact_group_id = cg.id
WHERE ccg.contact_id = 500
  AND cg.workspace_id = 123;
```

**Junction Table:** `contact_contact_group`  
**Constraints:** UNIQUE (contact_id, contact_group_id) - prevents duplicate memberships

#### 2. **Contact Search with Full-Text Index**

```sql
-- Full-text search across multiple fields
SELECT * FROM contacts
WHERE workspace_id = 123
  AND MATCH(first_name, last_name, phone, email) 
      AGAINST('john +62812' IN BOOLEAN MODE)
  AND deleted_at IS NULL;
```

**Index:** FULLTEXT `idx_contact_fulltext` on (first_name, last_name, phone, email)

---

## 💳 BILLING & SUBSCRIPTION DOMAIN

### Entity Relationship Summary

```
workspaces (1) ──────< (N) subscriptions ────── (N:1) subscription_plans
workspaces (1) ──────< (N) billing_invoices
billing_invoices (1) ──────< (N) billing_items
billing_invoices (1) ──────< (N) billing_payments
workspaces (1) ──────< (N) billing_credits
workspaces (1) ──────< (N) billing_debits
workspaces (1) ──────< (N) billing_transactions
```

### ERD Diagram: Billing Domain

```mermaid
erDiagram
    workspaces ||--o{ subscriptions : "has_subscription"
    subscription_plans ||--o{ subscriptions : "plan_type"
    workspaces ||--o{ billing_invoices : "billed_to"
    subscription_plans ||--o{ billing_invoices : "invoice_plan"
    billing_invoices ||--o{ billing_items : "line_items"
    billing_invoices ||--o{ billing_payments : "payments"
    coupons ||--o{ billing_invoices : "discount_applied"
    workspaces ||--o{ billing_credits : "account_credits"
    workspaces ||--o{ billing_debits : "account_debits"
    workspaces ||--o{ billing_transactions : "ledger"
    
    subscriptions {
        bigint id PK
        char uuid UK
        bigint workspace_id FK
        bigint plan_id FK
        text payment_details "JSON"
        timestamp start_date
        datetime valid_until
        enum status "trial/active/expired/cancelled"
        timestamp created_at
    }
    
    subscription_plans {
        bigint id PK
        char uuid UK
        string name
        decimal price "23,2 precision"
        enum period "monthly/yearly"
        text metadata "JSON features/limits"
        enum status "active/inactive"
        timestamp created_at
    }
    
    billing_invoices {
        bigint id PK
        char uuid UK
        int workspace_id FK
        int plan_id FK
        decimal subtotal "13,2"
        int coupon_id FK
        decimal coupon_amount "13,10"
        decimal tax "23,10"
        enum tax_type "inclusive/exclusive"
        decimal total "13,10"
        timestamp created_at
    }
    
    billing_items {
        bigint id PK
        char uuid UK
        int invoice_id FK
        string description
        int quantity
        decimal unit_price "13,10"
        decimal amount "13,10"
        timestamp created_at
    }
    
    billing_payments {
        bigint id PK
        char uuid UK
        int workspace_id FK
        int invoice_id FK
        string payment_method "stripe/paypal/razorpay"
        string transaction_id "Payment gateway ID"
        decimal amount "13,10"
        string currency "USD/IDR"
        enum status "pending/success/failed"
        text metadata "JSON gateway response"
        timestamp paid_at
        timestamp created_at
    }
    
    billing_credits {
        bigint id PK
        char uuid UK
        int workspace_id FK
        decimal amount "13,10"
        string source "payment/refund/bonus"
        text description
        timestamp created_at
    }
    
    billing_debits {
        bigint id PK
        char uuid UK
        int workspace_id FK
        decimal amount "13,10"
        string purpose "subscription/overage/addon"
        text description
        timestamp created_at
    }
    
    billing_transactions {
        bigint id PK
        char uuid UK
        int workspace_id FK
        enum type "credit/debit"
        decimal amount "13,10"
        decimal balance "Running balance"
        text description
        timestamp created_at
    }
    
    coupons {
        bigint id PK
        char uuid UK
        string code UK "Unique coupon code"
        enum type "percentage/fixed"
        decimal value "Discount amount"
        int usage_limit
        int usage_count
        timestamp valid_from
        timestamp valid_until
        enum status "active/inactive"
        timestamp created_at
    }
```

### Key Relationships Explained

#### 1. **Subscription Lifecycle**

```sql
-- Current active subscription
SELECT s.*, sp.name AS plan_name
FROM subscriptions s
INNER JOIN subscription_plans sp ON s.plan_id = sp.id
WHERE s.workspace_id = 123
  AND s.status = 'active'
  AND s.valid_until > NOW()
ORDER BY s.created_at DESC
LIMIT 1;
```

#### 2. **Invoice Generation Flow**

```sql
-- Create invoice with items and payments
INSERT INTO billing_invoices (uuid, workspace_id, plan_id, subtotal, total, ...)
VALUES (...);

INSERT INTO billing_items (invoice_id, description, quantity, unit_price, amount)
VALUES (LAST_INSERT_ID(), 'Professional Plan - Monthly', 1, 49.00, 49.00);

-- Payment processing
INSERT INTO billing_payments (invoice_id, payment_method, amount, status, ...)
VALUES (LAST_INSERT_ID(), 'stripe', 49.00, 'success', ...);
```

#### 3. **Account Balance Tracking**

```sql
-- Credit/Debit tracking dengan running balance
SELECT 
    bt.type,
    bt.amount,
    bt.balance AS running_balance,
    bt.description,
    bt.created_at
FROM billing_transactions bt
WHERE bt.workspace_id = 123
ORDER BY bt.created_at DESC;

-- Current balance
SELECT balance FROM billing_transactions
WHERE workspace_id = 123
ORDER BY created_at DESC
LIMIT 1;
```

---

## 🎫 SUPPORT & CMS DOMAIN

### Entity Relationship Summary

```
workspaces (1) ──────< (N) tickets
tickets (1) ──────< (N) ticket_comments
ticket_categories (1) ──────< (N) tickets

System-wide (no workspace_id):
├── faqs
├── pages
├── blog_posts ────── blog_authors
├── blog_posts ────<> blog_categories
└── blog_posts ────<> blog_tags
```

### ERD Diagram: Support & CMS

```mermaid
erDiagram
    workspaces ||--o{ tickets : "workspace_scoped"
    users ||--o{ tickets : "created_by"
    ticket_categories ||--o{ tickets : "categorized_as"
    tickets ||--o{ ticket_comments : "conversation"
    users ||--o{ ticket_comments : "commented_by"
    
    blog_authors ||--o{ blog_posts : "authored_by"
    blog_categories ||--o{ blog_posts : "categorized_in"
    blog_tags }o--o{ blog_posts : "tagged_with"
    
    tickets {
        bigint id PK
        char uuid UK
        int workspace_id FK
        string subject
        text description
        int category_id FK
        enum priority "low/medium/high"
        enum status "open/in_progress/resolved/closed"
        int assigned_to FK
        int created_by FK
        timestamp created_at
    }
    
    ticket_comments {
        bigint id PK
        char uuid UK
        int ticket_id FK
        int user_id FK
        text comment
        boolean is_internal "Internal note vs customer reply"
        timestamp created_at
    }
    
    ticket_categories {
        bigint id PK
        char uuid UK
        string name
        text description
        timestamp created_at
    }
    
    faqs {
        bigint id PK
        char uuid UK
        string question
        text answer
        int order_index
        enum status "active/inactive"
        timestamp created_at
    }
    
    pages {
        bigint id PK
        char uuid UK
        string title
        string slug UK "URL-safe identifier"
        text content "HTML content"
        enum status "published/draft"
        timestamp created_at
    }
    
    blog_posts {
        bigint id PK
        char uuid UK
        bigint author_id FK
        bigint category_id FK
        string title
        string slug UK
        text content
        text excerpt
        string featured_image
        enum status "published/draft"
        timestamp published_at
        timestamp created_at
    }
    
    blog_authors {
        bigint id PK
        char uuid UK
        string name
        string bio
        string avatar
        timestamp created_at
    }
    
    blog_categories {
        bigint id PK
        char uuid UK
        string name
        string slug UK
        timestamp created_at
    }
    
    blog_tags {
        bigint id PK
        char uuid UK
        string name
        string slug UK
        timestamp created_at
    }
```

---

## 🔒 SECURITY & AUDIT DOMAIN

### Entity Relationship Summary

```
audit_logs (1) ──────< (N) security_incidents
audit_logs (1) ──────< (N) authentication_events
audit_logs (1) ──────< (N) data_access_logs

workspaces (1) ──────< (N) workspace_api_keys
```

### ERD Diagram: Security & Audit

```mermaid
erDiagram
    users ||--o{ audit_logs : "performed_by"
    workspaces ||--o{ audit_logs : "workspace_context"
    audit_logs ||--o{ security_incidents : "spawns_incident"
    audit_logs ||--o| authentication_events : "auth_event"
    audit_logs ||--o| data_access_logs : "data_access"
    
    users ||--o{ authentication_events : "login_attempt"
    users ||--o{ rate_limit_violations : "violated_by"
    users ||--o{ data_access_logs : "accessor"
    users ||--o{ data_access_logs : "target_user"
    
    workspaces ||--o{ workspace_api_keys : "api_credentials"
    
    audit_logs {
        string id PK "Request ID - 100 chars"
        string event_type "50 chars"
        string endpoint "Route name - 100 chars"
        string method "HTTP method"
        text url
        string ip_address "45 chars - IPv4/IPv6"
        text user_agent
        bigint user_id FK
        bigint workspace_id FK
        string session_id "100 chars"
        json request_data
        int status_code
        bigint response_size
        decimal execution_time "10,3 milliseconds"
        bigint memory_usage
        boolean success
        string event_result "success/client_error/server_error"
        timestamp created_at
        
        INDEX idx_user_activity "user_id, created_at"
        INDEX idx_workspace_activity "workspace_id, created_at"
    }
    
    security_incidents {
        bigint id PK
        string audit_id FK "Reference to audit_logs"
        string incident_type "unauthorized_access/rate_limit/sql_injection"
        enum severity "low/medium/high/critical"
        string ip_address
        bigint user_id FK
        string endpoint
        json details
        boolean resolved
        text resolution_notes
        timestamp resolved_at
        timestamp created_at
        
        INDEX idx_severity "severity, resolved, created_at"
    }
    
    authentication_events {
        bigint id PK
        string audit_id FK
        enum event_type "login_attempt/login_success/login_failure/logout"
        string email
        bigint user_id FK
        string ip_address
        text user_agent
        string failure_reason
        boolean suspicious
        json additional_data "2FA/device info"
        timestamp created_at
        
        INDEX idx_email_events "email, event_type, created_at"
        INDEX idx_suspicious "suspicious, created_at"
    }
    
    rate_limit_violations {
        bigint id PK
        string ip_address
        bigint user_id FK
        string rate_limit_type "ip/user/endpoint/workspace"
        string endpoint
        int attempts
        int limit_threshold
        int window_duration "Seconds"
        timestamp first_violation
        timestamp last_violation
        boolean blocked
        timestamp block_expires_at
        timestamp created_at
        
        INDEX idx_ip_violations "ip_address, created_at"
        INDEX idx_blocked "blocked, block_expires_at"
    }
    
    data_access_logs {
        bigint id PK
        string audit_id FK
        bigint user_id FK "Accessor"
        bigint target_user_id FK "Target"
        bigint workspace_id FK
        string data_type "user_profile/chat_history/contact_info"
        string access_type "read/export/modify/delete"
        string data_source "Table/endpoint accessed"
        json accessed_fields
        string purpose "Business purpose"
        boolean consent_given "GDPR"
        timestamp created_at
        
        INDEX idx_target_audit "target_user_id, data_type, created_at"
    }
    
    workspace_api_keys {
        bigint id PK
        char uuid UK
        bigint workspace_id FK
        string name "Key label"
        string key_hash "Hashed API key"
        text permissions "JSON capabilities"
        timestamp last_used_at
        timestamp expires_at
        bigint created_by FK
        bigint deleted_by FK
        timestamp created_at
        timestamp deleted_at
    }
```

### Key Relationships Explained

#### 1. **Audit Log as Central Event Source**

```sql
-- Comprehensive activity tracking
SELECT 
    al.event_type,
    u.email,
    w.name AS workspace_name,
    al.endpoint,
    al.execution_time,
    al.created_at
FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
LEFT JOIN workspaces w ON al.workspace_id = w.id
WHERE al.created_at >= NOW() - INTERVAL 1 DAY
ORDER BY al.created_at DESC;
```

#### 2. **Security Incident Detection**

```sql
-- High severity unresolved incidents
SELECT 
    si.*,
    al.endpoint,
    al.ip_address,
    u.email
FROM security_incidents si
INNER JOIN audit_logs al ON si.audit_id = al.id
LEFT JOIN users u ON si.user_id = u.id
WHERE si.severity IN ('high', 'critical')
  AND si.resolved = FALSE
ORDER BY si.created_at DESC;
```

#### 3. **GDPR Compliance - Data Access Tracking**

```sql
-- Who accessed what data about a specific user
SELECT 
    dal.access_type,
    dal.data_type,
    accessor.email AS accessed_by,
    dal.purpose,
    dal.consent_given,
    dal.created_at
FROM data_access_logs dal
INNER JOIN users accessor ON dal.user_id = accessor.id
WHERE dal.target_user_id = 789  -- Target user whose data was accessed
ORDER BY dal.created_at DESC;
```

---

## 📐 COMPLETE ERD DIAGRAM

### Full System ERD (Simplified View)

```mermaid
erDiagram
    %% CORE RELATIONSHIPS
    users ||--o{ workspaces : creates
    users }o--o{ workspaces : member_via_teams
    workspaces ||--|| subscriptions : has
    subscription_plans ||--o{ subscriptions : type
    
    %% MESSAGING RELATIONSHIPS
    workspaces ||--o{ chats : scoped
    workspaces ||--o{ campaigns : scoped
    workspaces ||--o{ templates : scoped
    contacts ||--o{ chats : participant
    templates ||--o{ campaigns : uses
    campaigns ||--o{ campaign_logs : generates
    
    %% CONTACT RELATIONSHIPS
    workspaces ||--o{ contacts : scoped
    workspaces ||--o{ contact_groups : scoped
    contacts }o--o{ contact_groups : membership
    
    %% BILLING RELATIONSHIPS
    workspaces ||--o{ billing_invoices : billed
    billing_invoices ||--o{ billing_items : items
    billing_invoices ||--o{ billing_payments : paid
    
    %% SUPPORT RELATIONSHIPS
    workspaces ||--o{ tickets : support
    tickets ||--o{ ticket_comments : thread
    
    %% SECURITY RELATIONSHIPS
    workspaces ||--o{ audit_logs : activity
    audit_logs ||--o{ security_incidents : spawns
    audit_logs ||--o{ authentication_events : tracks
    
    users {
        bigint id PK
        char uuid UK
        string email UK
    }
    
    workspaces {
        bigint id PK
        char uuid UK
        string name
        bigint created_by FK
    }
    
    contacts {
        bigint id PK
        char uuid UK
        int workspace_id FK
        string phone
    }
    
    chats {
        bigint id PK
        char uuid UK
        int workspace_id FK
        int contact_id FK
    }
    
    campaigns {
        bigint id PK
        char uuid UK
        int workspace_id FK
        int template_id FK
    }
    
    templates {
        bigint id PK
        char uuid UK
        bigint workspace_id FK
    }
    
    subscriptions {
        bigint id PK
        char uuid UK
        bigint workspace_id FK
        bigint plan_id FK
    }
```

---

## 📊 RELATIONSHIP SUMMARY TABLE

| Entity 1 | Relationship | Entity 2 | Type | Junction Table | Notes |
|----------|--------------|----------|------|----------------|-------|
| users | creates | workspaces | 1:N | - | One user creates many workspaces |
| users | member_of | workspaces | N:M | teams | User can be member of multiple workspaces |
| workspaces | has | subscriptions | 1:1 | - | One active subscription per workspace |
| workspaces | contains | contacts | 1:N | - | Workspace-scoped contacts |
| workspaces | contains | chats | 1:N | - | Workspace-scoped chats |
| workspaces | contains | campaigns | 1:N | - | Workspace-scoped campaigns |
| contacts | participates_in | chats | 1:N | - | One contact has many chats |
| contacts | belongs_to | contact_groups | N:M | contact_contact_group | Contacts in multiple groups |
| campaigns | uses | templates | N:1 | - | Campaign references one template |
| campaigns | generates | campaign_logs | 1:N | - | One campaign creates many logs |
| campaign_logs | retries | campaign_log_retries | 1:N | - | Failed logs have retry attempts |
| chats | has | chat_media | 1:1 | - | One chat can have one media |
| chats | converts_to | chat_tickets | 1:1 | - | Chat can be converted to ticket |
| tickets | has | ticket_comments | 1:N | - | Ticket conversation thread |
| billing_invoices | contains | billing_items | 1:N | - | Invoice line items |
| billing_invoices | paid_by | billing_payments | 1:N | - | Multiple payments for one invoice |
| audit_logs | spawns | security_incidents | 1:N | - | Audit event creates incidents |
| blog_posts | authored_by | blog_authors | N:1 | - | Posts have one author |
| blog_posts | tagged_with | blog_tags | N:M | blog_post_tags | Posts have multiple tags |

---

## 🔑 FOREIGN KEY CONSTRAINTS

### Critical Foreign Keys with ON DELETE Behavior

```sql
-- CASCADE DELETE (Parent delete removes children)
ALTER TABLE subscriptions 
    ADD FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE;

ALTER TABLE contact_contact_group 
    ADD FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE;

-- RESTRICT (Prevent parent delete if children exist)
ALTER TABLE campaigns 
    ADD FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE RESTRICT;

-- SET NULL (Set to NULL on parent delete)
ALTER TABLE chats 
    ADD FOREIGN KEY (media_id) REFERENCES chat_media(id) ON DELETE SET NULL;
```

### Foreign Key Index Strategy

```sql
-- Every foreign key column has an index for performance
CREATE INDEX idx_chats_workspace ON chats(workspace_id);
CREATE INDEX idx_chats_contact ON chats(contact_id);
CREATE INDEX idx_campaigns_workspace ON campaigns(workspace_id);
CREATE INDEX idx_campaigns_template ON campaigns(template_id);
```

---

**Next Document:** [Complete Schema Structure →](./03-complete-schema-structure.md)
