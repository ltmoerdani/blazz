# 🎨 Arsitektur Visual Diagram

## Overview

Document ini menyediakan **visual representation** dari arsitektur Blazz untuk memudahkan pemahaman sistem secara keseluruhan.

---

## 🏗️ High-Level Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           CLIENT LAYER                                   │
│                                                                           │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐│
│  │  Web Browser │  │ Mobile App   │  │ External API │  │   Webhooks   ││
│  │   (Vue.js)   │  │  (Future)    │  │  Consumers   │  │  (WhatsApp)  ││
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘│
│         │                 │                  │                  │        │
└─────────┼─────────────────┼──────────────────┼──────────────────┼────────┘
          │                 │                  │                  │
          ▼                 ▼                  ▼                  ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                      APPLICATION LAYER (Laravel)                         │
│                                                                           │
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │                    PRESENTATION LAYER                               │ │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐             │ │
│  │  │  Inertia.js  │  │ REST API     │  │  Webhook     │             │ │
│  │  │  Responses   │  │  Endpoints   │  │  Handlers    │             │ │
│  │  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘             │ │
│  └─────────┼──────────────────┼──────────────────┼────────────────────┘ │
│            │                  │                  │                       │
│  ┌─────────▼──────────────────▼──────────────────▼────────────────────┐ │
│  │                    CONTROLLER LAYER                                 │ │
│  │                                                                      │ │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌────────────┐  │ │
│  │  │   User     │  │   Admin    │  │    API     │  │  Webhook   │  │ │
│  │  │Controllers │  │Controllers │  │Controller  │  │ Controller │  │ │
│  │  └─────┬──────┘  └─────┬──────┘  └─────┬──────┘  └─────┬──────┘  │ │
│  │        │               │               │               │          │ │
│  │        │  ┌────────────┴───────────────┴───────────────┘          │ │
│  └────────┼──┼───────────────────────────────────────────────────────┘ │
│           │  │                                                           │
│  ┌────────▼──▼──────────────────────────────────────────────────────┐  │
│  │              🔥 SERVICE LAYER (Business Logic Hub)                │  │
│  │                                                                    │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │  │
│  │  │  WhatsApp   │  │  Campaign   │  │    Chat     │              │  │
│  │  │   Service   │  │   Service   │  │   Service   │              │  │
│  │  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘              │  │
│  │         │                │                │                       │  │
│  │  ┌──────▼──────┐  ┌──────▼──────┐  ┌──────▼──────┐              │  │
│  │  │  Template   │  │   Billing   │  │  Workspace  │              │  │
│  │  │   Service   │  │   Service   │  │   Service   │              │  │
│  │  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘              │  │
│  │         │                │                │                       │  │
│  │         └────────────────┼────────────────┘                       │  │
│  └──────────────────────────┼──────────────────────────────────────┘  │
│                             │                                           │
│  ┌──────────────────────────▼──────────────────────────────────────┐  │
│  │            BACKGROUND PROCESSING LAYER                           │  │
│  │                                                                   │  │
│  │  ┌───────────────────┐  ┌───────────────────┐                   │  │
│  │  │   Queue Jobs      │  │   Event System    │                   │  │
│  │  │                   │  │                   │                   │  │
│  │  │ - SendCampaign    │  │ - NewChatEvent    │                   │  │
│  │  │ - ProcessMessages │  │ - StatusUpdate    │                   │  │
│  │  │ - ImportContacts  │  │ - Broadcast       │                   │  │
│  │  └─────────┬─────────┘  └─────────┬─────────┘                   │  │
│  │            │                      │                             │  │
│  │            └──────────┬───────────┘                             │  │
│  └───────────────────────┼─────────────────────────────────────────┘  │
│                          │                                             │
│  ┌───────────────────────▼─────────────────────────────────────────┐  │
│  │                    DATA LAYER                                    │  │
│  │                                                                   │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │  │
│  │  │  Eloquent   │  │   MySQL     │  │    Redis    │             │  │
│  │  │   Models    │  │  Database   │  │    Cache    │             │  │
│  │  └─────────────┘  └─────────────┘  └─────────────┘             │  │
│  │                                                                   │  │
│  └───────────────────────────────────────────────────────────────────┘  │
│                                                                           │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │              MODULE SYSTEM (Extension Layer)                      │  │
│  │                                                                    │  │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐           │  │
│  │  │ Embedded     │  │   Razorpay   │  │   Custom     │           │  │
│  │  │   Signup     │  │   Payment    │  │   Modules    │           │  │
│  │  └──────────────┘  └──────────────┘  └──────────────┘           │  │
│  │                                                                    │  │
│  └────────────────────────────────────────────────────────────────────┘  │
└───────────────────────────┬───────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    EXTERNAL INTEGRATIONS                                 │
│                                                                           │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐│
│  │  WhatsApp    │  │   Payment    │  │   Storage    │  │   Pusher     ││
│  │  Cloud API   │  │   Gateways   │  │  (S3/Local)  │  │  Real-time   ││
│  └──────────────┘  └──────────────┘  └──────────────┘  └──────────────┘│
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 🔄 Data Flow Diagram: Chat Message

```
┌─────────────────────────────────────────────────────────────────────────┐
│                 OUTGOING MESSAGE FLOW (User → Contact)                   │
└─────────────────────────────────────────────────────────────────────────┘

1. USER ACTION
   │
   └─→ Vue Component (ChatComposer.vue)
       │ - User types message
       │ - Clicks send button
       │
       ▼
2. HTTP REQUEST
   │
   └─→ POST /user/chats/send
       │ - Contact UUID
       │ - Message content
       │ - Message type
       │
       ▼
3. CONTROLLER LAYER
   │
   └─→ ChatController@sendMessage
       │ - Validates request
       │ - Authenticates user
       │ - Gets workspace context
       │
       ▼
4. SERVICE LAYER
   │
   └─→ ChatService@sendMessage
       │ ├─→ Find contact by UUID
       │ ├─→ Validate workspace access
       │ ├─→ Call WhatsappService
       │ │   │
       │ │   └─→ WhatsappService@sendMessage
       │ │       │ - Build API payload
       │ │       │ - Set headers
       │ │       │ - HTTP POST to WhatsApp API
       │ │       │
       │ │       ▼
       │ │   ┌────────────────────────────┐
       │ │   │   WhatsApp Cloud API       │
       │ │   │  (External Service)        │
       │ │   └────────┬───────────────────┘
       │ │            │
       │ │            └─→ Returns message_id
       │ │
       │ ├─→ Create Chat record
       │ │   └─→ Model: Chat::create()
       │ │       │ - workspace_id
       │ │       │ - contact_id
       │ │       │ - message
       │ │       │ - whatsapp_message_id
       │ │       │ - status: 'pending'
       │ │
       │ └─→ Broadcast event
       │     └─→ broadcast(new NewChatEvent($chat))
       │
       ▼
5. REAL-TIME BROADCAST
   │
   └─→ Pusher Broadcasting
       │ - Channel: workspace.{id}
       │ - Event: chat.new
       │ - Payload: chat data
       │
       ▼
6. CLIENT UPDATE
   │
   └─→ Vue Component (ChatList.vue)
       │ - Echo listener receives event
       │ - Adds message to chat list
       │ - Updates UI reactively
       │
       ▼
7. WEBHOOK CALLBACK (Async)
   │
   └─→ WhatsApp sends status updates
       │ ├─→ Message sent
       │ ├─→ Message delivered
       │ └─→ Message read
       │
       ▼
   └─→ WebhookController@handle
       │ - Verifies signature
       │ - Updates Chat status
       │ - Broadcasts status change
       │
       ▼
   └─→ UI updates message status icon
       └─→ ✓ (sent) → ✓✓ (delivered) → ✓✓ (read, blue)
```

---

## 🚀 Campaign Processing Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│              CAMPAIGN CREATION & PROCESSING FLOW                         │
└─────────────────────────────────────────────────────────────────────────┘

1. USER CREATES CAMPAIGN
   │
   └─→ POST /user/campaigns
       │ - template_id
       │ - contact_group_id
       │ - metadata (variables)
       │ - scheduled_at
       │
       ▼
2. CONTROLLER
   │
   └─→ CampaignController@store
       │ - Validates data
       │ - Checks permissions
       │
       ▼
3. SERVICE LAYER
   │
   └─→ CampaignService@store
       │ ├─→ DB::beginTransaction()
       │ ├─→ Create Campaign record
       │ ├─→ Upload media files (if any)
       │ ├─→ Store metadata
       │ ├─→ DB::commit()
       │ │
       │ └─→ Dispatch CreateCampaignLogsJob
       │     └─→ Queue: 'campaigns'
       │
       ▼
4. BACKGROUND PROCESSING - STEP 1
   │
   └─→ CreateCampaignLogsJob::handle
       │ ├─→ Fetch ContactGroup contacts
       │ │   └─→ Example: 10,000 contacts
       │ │
       │ ├─→ Bulk create CampaignLog records
       │ │   └─→ CampaignLog::insert([...])
       │ │       │ - campaign_id
       │ │       │ - contact_id
       │ │       │ - status: 'pending'
       │ │
       │ └─→ Dispatch ProcessCampaignMessagesJob
       │     └─→ delay(10 seconds)
       │
       ▼
5. BACKGROUND PROCESSING - STEP 2
   │
   └─→ ProcessCampaignMessagesJob::handle
       │ ├─→ Fetch pending logs (chunked: 100)
       │ │
       │ ├─→ For each log:
       │ │   └─→ Dispatch SendCampaignJob
       │ │       └─→ delay(calculated based on rate limit)
       │ │
       │ └─→ If more logs remain:
       │     └─→ Re-dispatch self (1 minute delay)
       │
       ▼
6. BACKGROUND PROCESSING - STEP 3
   │
   └─→ SendCampaignJob::handle (x100 parallel)
       │ ├─→ Fetch CampaignLog
       │ ├─→ Build template with contact variables
       │ │   └─→ TemplateTrait::buildTemplate()
       │ │       │ - Replace {{name}} → "John Doe"
       │ │       │ - Replace {{company}} → "Acme Corp"
       │ │
       │ ├─→ Call WhatsappService@sendTemplateMessage
       │ │   └─→ POST to WhatsApp API
       │ │       │
       │ │       ▼
       │ │   ┌────────────────────────────┐
       │ │   │   WhatsApp Cloud API       │
       │ │   │  (Rate Limited: 80/sec)    │
       │ │   └────────┬───────────────────┘
       │ │            │
       │ │            └─→ Returns message_id or error
       │ │
       │ ├─→ Create Chat record
       │ │   └─→ Chat::create()
       │ │       │ - whatsapp_message_id
       │ │       │ - status: 'pending'
       │ │
       │ ├─→ Update CampaignLog status
       │ │   └─→ 'success' or 'failed'
       │ │
       │ └─→ Broadcast NewChatEvent (if success)
       │
       ▼
7. REAL-TIME UPDATES
   │
   └─→ Dashboard shows live statistics
       │ - Total sent: 8,542 / 10,000
       │ - Success rate: 97.3%
       │ - Failed: 230
       │ - Pending: 1,228
       │
       ▼
8. WEBHOOK CALLBACKS (Async)
   │
   └─→ WhatsApp sends delivery status
       │ ├─→ 8,312 delivered
       │ ├─→ 4,521 read
       │ └─→ 230 failed
       │
       └─→ Campaign analytics updated
```

---

## 🔐 Multi-Tenancy Data Isolation

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    WORKSPACE ISOLATION PATTERN                           │
└─────────────────────────────────────────────────────────────────────────┘

┌────────────────┐
│  User Login    │
└────────┬───────┘
         │
         ▼
┌────────────────────────────────────────────────────┐
│  Session: current_workspace = 123                  │
└────────────────┬───────────────────────────────────┘
                 │
                 ▼
┌────────────────────────────────────────────────────┐
│  Controller:                                       │
│  $workspaceId = session()->get('current_workspace')│
└────────────────┬───────────────────────────────────┘
                 │
                 ▼
┌────────────────────────────────────────────────────┐
│  Service instantiation:                            │
│  new ContactService($workspaceId)                  │
└────────────────┬───────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│  Service queries (ALWAYS scoped):                           │
│                                                              │
│  Contact::where('workspace_id', $this->workspaceId)        │
│         ->where('status', 'active')                         │
│         ->get()                                             │
│                                                              │
│  Result: Only contacts from workspace 123                   │
└─────────────────────────────────────────────────────────────┘

DATABASE STRUCTURE:
┌──────────────────────────────────────────────────────────────┐
│  contacts table                                              │
├────┬──────────────┬────────────┬────────────┬───────────────┤
│ id │ workspace_id │    name    │   phone    │     email     │
├────┼──────────────┼────────────┼────────────┼───────────────┤
│  1 │     123      │ John Doe   │ +1234567   │ john@acme.com │  ← User sees this
│  2 │     123      │ Jane Smith │ +1234568   │ jane@acme.com │  ← User sees this
│  3 │     456      │ Bob Wilson │ +1234569   │ bob@corp.com  │  ← User does NOT see
│  4 │     789      │ Alice Lee  │ +1234570   │ alice@co.com  │  ← User does NOT see
└────┴──────────────┴────────────┴────────────┴───────────────┘
          │                                            ▲
          │                                            │
          └────────── Automatic filtering ─────────────┘

API TOKEN SCOPING:
┌──────────────────────────────────────────────────────────────┐
│  workspace_api_keys table                                    │
├────┬──────────────┬─────────────────────┬──────────┬─────────┤
│ id │ workspace_id │       token         │  status  │ created │
├────┼──────────────┼─────────────────────┼──────────┼─────────┤
│  1 │     123      │ sk_live_abc123...   │  active  │ 2024... │
│  2 │     456      │ sk_live_def456...   │  active  │ 2024... │
└────┴──────────────┴─────────────────────┴──────────┴─────────┘

API Request Flow:
Bearer Token: sk_live_abc123...
    │
    └─→ Lookup token → workspace_id = 123
        │
        └─→ All operations automatically scoped to workspace 123
```

---

## 📊 Service Layer Interaction Patterns

```
┌─────────────────────────────────────────────────────────────────────────┐
│                  SERVICE-TO-SERVICE COMMUNICATION                        │
└─────────────────────────────────────────────────────────────────────────┘

Example: Sending a campaign message with media

CampaignService
    │
    ├─→ buildCampaignMetadata()
    │
    ├─→ calls MediaService
    │   │ └─→ uploadMedia($file)
    │   │     │ - Validates file type
    │   │     │ - Uploads to S3 or local storage
    │   │     │ - Returns media URL
    │   │
    │   └─→ Returns media_id
    │
    ├─→ calls TemplateService
    │   │ └─→ buildTemplate($templateName, $metadata, $contact)
    │   │     │ - Loads template structure
    │   │     │ - Replaces variables
    │   │     │ - Validates format
    │   │
    │   └─→ Returns built template payload
    │
    └─→ calls WhatsappService
        └─→ sendTemplateMessage($contact, $template)
            │ - Calls WhatsApp API
            │ - Creates Chat record
            │ - Broadcasts event
            │
            └─→ Returns result object

┌─────────────────────────────────────────────────────────────────────────┐
│                      SERVICE DEPENDENCY MAP                              │
└─────────────────────────────────────────────────────────────────────────┘

                    ┌──────────────────┐
                    │ CampaignService  │
                    └────────┬─────────┘
                             │
                 ┌───────────┼───────────┐
                 │           │           │
                 ▼           ▼           ▼
        ┌────────────┐  ┌────────┐  ┌─────────────┐
        │ Template   │  │ Media  │  │  WhatsApp   │
        │  Service   │  │Service │  │   Service   │
        └────────────┘  └────────┘  └──────┬──────┘
                                           │
                                           ▼
                                    ┌──────────────┐
                                    │ ChatService  │
                                    └──────────────┘

Independent Services (No Dependencies):
- SettingService
- WorkspaceService
- BillingService
- NotificationService
```

---

## 🎯 Request Lifecycle Visualization

```
┌─────────────────────────────────────────────────────────────────────────┐
│              TYPICAL WEB REQUEST LIFECYCLE                               │
└─────────────────────────────────────────────────────────────────────────┘

1. Browser Request
   │ POST /user/contacts
   │ Content-Type: application/json
   │ Cookie: session_id=xyz...
   │
   ▼
2. Laravel Entry Point
   │ public/index.php
   │
   ▼
3. HTTP Kernel
   │ app/Http/Kernel.php
   │
   ▼
4. Middleware Stack (In Order)
   │ ├─→ TrustProxies
   │ ├─→ ValidateCsrfToken
   │ ├─→ StartSession
   │ ├─→ Authenticate (check if logged in)
   │ ├─→ WorkspaceContext (set workspace)
   │ └─→ RateLimit (throttle requests)
   │
   ▼
5. Router
   │ routes/web.php
   │ Matches: Route::post('/user/contacts', [ContactController::class, 'store'])
   │
   ▼
6. Controller
   │ ContactController@store(StoreContactRequest $request)
   │ - Request auto-validated by FormRequest
   │ - $validated = $request->validated()
   │
   ▼
7. Service Layer
   │ $contactService->create($validated)
   │ - Business logic execution
   │ - Database operations
   │ - External API calls
   │
   ▼
8. Model/Database
   │ Contact::create([...])
   │ - Eloquent ORM
   │ - MySQL query execution
   │ - Model events fired
   │
   ▼
9. Service Returns Result
   │ return (object) [
   │     'success' => true,
   │     'data' => $contact,
   │     'message' => 'Contact created!'
   │ ]
   │
   ▼
10. Controller Returns Response
    │ return redirect()->back()->with('status', [...])
    │
    ▼
11. Inertia.js Response
    │ - Page props updated
    │ - No full page reload
    │ - Vue components re-render
    │
    ▼
12. Browser
    │ - UI updated
    │ - Success message shown
    │ - Contact appears in list

Total Time: 50-200ms (typical)
```

---

## 🔄 Background Job Processing Visualization

```
┌─────────────────────────────────────────────────────────────────────────┐
│                  QUEUE WORKER PROCESSING FLOW                            │
└─────────────────────────────────────────────────────────────────────────┘

Queue Table (Redis or Database):
┌────┬──────────┬────────────────────┬──────────┬─────────┬──────────────┐
│ id │  queue   │      payload       │ attempts │  status │  available   │
├────┼──────────┼────────────────────┼──────────┼─────────┼──────────────┤
│ 1  │campaigns │SendCampaignJob:101 │    0     │ pending │ 2024-10-06...│
│ 2  │campaigns │SendCampaignJob:102 │    0     │ pending │ 2024-10-06...│
│ 3  │default   │SendEmailJob:55     │    1     │ pending │ 2024-10-06...│
│ 4  │campaigns │SendCampaignJob:103 │    2     │ failed  │ 2024-10-06...│
└────┴──────────┴────────────────────┴──────────┴─────────┴──────────────┘

Queue Worker Process:
php artisan queue:work --queue=campaigns,default

┌──────────────────────────────────────┐
│      Queue Worker Loop               │
│                                      │
│  while (true) {                      │
│      1. Fetch next job               │
│      2. Unserialize payload          │
│      3. Call job->handle()           │
│      4. If success: delete job       │
│      5. If fail: increment attempts  │
│      6. If max attempts: move to     │
│         failed_jobs table            │
│      7. Sleep 3 seconds              │
│  }                                   │
└──────────────────────────────────────┘

Job Execution Timeline:
┌─────────────────────────────────────────────────────────────────┐
│ SendCampaignJob #101                                            │
├─────────────────────────────────────────────────────────────────┤
│  00:00.000  Job fetched from queue                              │
│  00:00.050  Job handle() method starts                          │
│  00:00.100  Fetch campaign log from database                    │
│  00:00.200  Build template with contact variables               │
│  00:00.300  Call WhatsApp API                                   │
│  00:01.500  ← WhatsApp API responds (1.2s network latency)      │
│  00:01.550  Create Chat record                                  │
│  00:01.600  Update CampaignLog status                           │
│  00:01.650  Broadcast NewChatEvent                              │
│  00:01.700  Job completed successfully                          │
│  00:01.750  Job deleted from queue                              │
└─────────────────────────────────────────────────────────────────┘
Total: 1.75 seconds

Multiple Workers (Parallel Processing):
Worker 1: ████████ SendCampaignJob #101 (1.7s)
Worker 2:  ████████ SendCampaignJob #102 (1.8s)
Worker 3:   ████████ SendCampaignJob #103 (1.6s)
Worker 4:    ████████ SendCampaignJob #104 (1.9s)

Throughput: ~2.3 jobs/second per worker
With 4 workers: ~9.2 jobs/second
Daily capacity: ~795,000 messages
```

---

## 🌐 Module System Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    MODULE PLUGIN ARCHITECTURE                            │
└─────────────────────────────────────────────────────────────────────────┘

Core Application
┌─────────────────────────────────────────────────────────────────────┐
│  Laravel Application                                                 │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │  Core Services                                                 │ │
│  │  - ContactService                                              │ │
│  │  - CampaignService                                             │ │
│  │  - ChatService                                                 │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                      │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │  Module Loading System                                         │ │
│  │  - Autoloader: "Modules\\": "modules/"                         │ │
│  │  - Service Provider Registration                               │ │
│  └───────────────────────────────────────────────────────────────┘ │
└──────────────────────────┬───────────────────────────────────────────┘
                           │
           ┌───────────────┼───────────────┐
           │               │               │
           ▼               ▼               ▼
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│  Module 1    │  │  Module 2    │  │  Module 3    │
│ Embedded     │  │  Razorpay    │  │  Custom      │
│  Signup      │  │  Payment     │  │  Module      │
├──────────────┤  ├──────────────┤  ├──────────────┤
│ Controllers/ │  │ Controllers/ │  │ Controllers/ │
│ Services/    │  │ Services/    │  │ Services/    │
│ Models/      │  │ Migrations/  │  │ Views/       │
│ Providers/   │  │ Providers/   │  │ Providers/   │
│ routes.php   │  │ routes.php   │  │ routes.php   │
└──────────────┘  └──────────────┘  └──────────────┘

Module Registration Flow:
1. Composer autoload discovers modules
2. ModuleServiceProvider boots
3. Module routes registered
4. Module views/assets published
5. Module available in app

Module Integration Example:
┌──────────────────────────────────────────────────────────────┐
│  Razorpay Module Integration                                 │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  Core App                          Module                   │
│  ┌─────────────┐                  ┌──────────────┐         │
│  │  Billing    │──calls──────────→│  Razorpay    │         │
│  │  Service    │                  │  Service     │         │
│  └─────────────┘                  └──────┬───────┘         │
│                                           │                  │
│                                           ▼                  │
│                                    ┌────────────────┐       │
│                                    │ Razorpay API   │       │
│                                    │ (External)     │       │
│                                    └────────────────┘       │
└──────────────────────────────────────────────────────────────┘
```

---

## 📱 Frontend Architecture (Vue.js + Inertia.js)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                  FRONTEND COMPONENT HIERARCHY                            │
└─────────────────────────────────────────────────────────────────────────┘

resources/js/
│
├── app.js (Entry Point)
│   │
│   ├─→ Initializes Vue 3
│   ├─→ Registers Inertia plugin
│   ├─→ Configures Laravel Echo
│   └─→ Mounts root component
│
├── Pages/ (Inertia Page Components)
│   │
│   ├── Auth/
│   │   ├── Login.vue
│   │   └── Register.vue
│   │
│   └── User/
│       ├── Dashboard.vue
│       │   │
│       │   └─→ Uses Components:
│       │       ├── StatCard.vue
│       │       ├── RecentChats.vue
│       │       └── CampaignStats.vue
│       │
│       ├── Chat/
│       │   └── Index.vue
│       │       │
│       │       └─→ Uses Components:
│       │           ├── ContactList.vue
│       │           ├── ChatWindow.vue
│       │           │   ├── MessageList.vue
│       │           │   └── MessageComposer.vue
│       │           └── ContactInfo.vue
│       │
│       └── Campaigns/
│           ├── Index.vue
│           └── Create.vue
│               │
│               └─→ Uses Components:
│                   ├── TemplateSelector.vue
│                   ├── ContactGroupSelector.vue
│                   └── ScheduleSelector.vue
│
├── Components/ (Reusable Components)
│   ├── UI/
│   │   ├── Button.vue
│   │   ├── Modal.vue
│   │   ├── Dropdown.vue
│   │   └── Pagination.vue
│   │
│   ├── Chat/
│   │   ├── ChatMessage.vue
│   │   ├── MediaPreview.vue
│   │   └── EmojiPicker.vue
│   │
│   └── Campaign/
│       ├── CampaignCard.vue
│       └── CampaignStats.vue
│
├── Composables/ (Vue Composition API)
│   ├── useChat.js
│   │   └─→ provides: {sendMessage, loadMessages, markAsRead}
│   │
│   ├── useCampaign.js
│   │   └─→ provides: {createCampaign, getCampaigns, analytics}
│   │
│   └── useNotification.js
│       └─→ provides: {showSuccess, showError, showInfo}
│
└── Layouts/
    ├── GuestLayout.vue
    └── AuthenticatedLayout.vue
        │
        └─→ Uses Components:
            ├── Sidebar.vue
            ├── Navbar.vue
            └── Footer.vue

Data Flow (Example: Sending Chat Message):

User types message in MessageComposer.vue
    │
    ├─→ Component emits 'send' event with message data
    │
    └─→ Parent ChatWindow.vue catches event
        │
        └─→ Calls composable: useChat().sendMessage()
            │
            └─→ Makes Inertia POST request
                │
                └─→ Backend processes (Controller → Service → API)
                    │
                    └─→ Inertia returns updated props
                        │
                        └─→ Vue component re-renders
                            │
                            └─→ Message appears in chat
                                │
                                └─→ Laravel Echo broadcasts event
                                    │
                                    └─→ Other users receive real-time update
```

---

## 🎯 Key Takeaways

1. **Layered Architecture** - Clear separation between presentation, business logic, and data
2. **Service-Oriented** - Business logic centralized dalam service layer
3. **Async Processing** - Heavy operations di-handle via queue system
4. **Multi-Tenancy** - Workspace isolation di setiap layer
5. **Real-time** - Event broadcasting untuk instant updates
6. **Modular** - Plugin system untuk extensibility
7. **Scalable** - Designed untuk horizontal scaling

---

**Untuk deep dive ke specific patterns, refer to:**
- [01-arsitektur-overview.md](./01-arsitektur-overview.md)
- [02-component-connections.md](./02-component-connections.md)
- [03-folder-structure.md](./03-folder-structure.md)
- [04-feature-development-guide.md](./04-feature-development-guide.md)
