# 🏗️ Hybrid Service-Oriented Architecture - Complete Technical Implementation

## 📋 Architecture Overview

**Blazz Platform** mengimplementasikan **Hybrid Service-Oriented Architecture (SOA)** yang menggabungkan kekuatan dari beberapa architectural patterns: MVC foundation, Service Layer Pattern, Event-Driven Architecture, dan Microservices-inspired design. Arsitektur ini dirancang untuk mensupport enterprise-grade scalability dengan maintainable codebase dan clear separation of concerns.

## 🎯 Core Architectural Principles

### Design Philosophy

1. **Single Responsibility Principle** - Setiap service memiliki satu tugas yang jelas
2. **Dependency Injection** - Loose coupling melalui constructor injection
3. **Interface Segregation** - Specific interfaces untuk specific needs
4. **Open/Closed Principle** - Open for extension, closed for modification
5. **Don't Repeat Yourself (DRY)** - Code reuse melalui abstraksi

### Architecture Layers

```
┌─────────────────────────────────────────────────────────────┐
│                    PRESENTATION LAYER                       │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐│
│  │   Vue.js SPA    │  │   REST API      │  │   WebSocket     ││
│  │   Components    │  │   Endpoints     │  │   Events        ││
│  └─────────────────┘  └─────────────────┘  └─────────────────┘│
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                  APPLICATION LAYER                         │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐│
│  │   Controllers   │  │   Service Layer │  │   Event System  ││
│  │   (Thin)        │  │   (Business)    │  │   (Real-time)   ││
│  └─────────────────┘  └─────────────────┘  └─────────────────┘│
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                    DOMAIN LAYER                            │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐│
│  │   Models        │  │   Value Objects │  │   Domain Events ││
│  │   (Entities)    │  │   (Data)        │  │   (Business)    ││
│  └─────────────────┘  └─────────────────┘  └─────────────────┘│
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                 INFRASTRUCTURE LAYER                        │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐│
│  │   Repositories  │  │   External APIs │  │   Queue System  ││
│  │   (Data Access) │  │   (Integrations)│  │   (Async)       ││
│  └─────────────────┘  └─────────────────┘  └─────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

## 🔧 Service Layer Architecture

### Core Service Structure

**Abstract Base Service**
```php
// app/Services/BaseService.php
abstract class BaseService {
    protected $workspaceId;
    protected $errors = [];

    public function __construct($workspaceId) {
        $this->workspaceId = $workspaceId;
    }

    abstract protected function getModel();

    protected function validateWorkspaceAccess() {
        if (!$this->workspaceId) {
            throw new \InvalidArgumentException('Workspace ID is required');
        }

        $workspace = Workspace::find($this->workspaceId);
        if (!$workspace) {
            throw new ModelNotFoundException('Workspace not found');
        }

        return $workspace;
    }

    protected function validateUserPermission($permission) {
        $user = auth()->user();
        if (!$user || !PermissionService::hasPermission($user, $permission)) {
            throw new UnauthorizedException('Insufficient permissions');
        }
    }

    protected function handleServiceError(\Exception $e, $context = []) {
        Log::error(get_class($this) . ' error', [
            'error' => $e->getMessage(),
            'workspace_id' => $this->workspaceId,
            'context' => $context
        ]);

        $this->errors[] = $e->getMessage();
        throw $e;
    }

    protected function createSuccessResponse($data, $message = null) {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'workspace_id' => $this->workspaceId
        ];
    }

    protected function createErrorResponse($message, $code = 400) {
        return [
            'success' => false,
            'message' => $message,
            'errors' => $this->errors,
            'workspace_id' => $this->workspaceId
        ];
    }

    public function getErrors() {
        return $this->errors;
    }

    public function hasErrors() {
        return !empty($this->errors);
    }
}
```

**Service Interface Contracts**
```php
// app/Contracts/Services/ContactServiceInterface.php
interface ContactServiceInterface {
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
    public function find($id);
    public function getAll($filters = []);
    public function search($term, $filters = []);
}

// app/Contracts/Services/MessageServiceInterface.php
interface MessageServiceInterface {
    public function sendMessage($contactId, $message, $options = []);
    public function sendMedia($contactId, $media, $caption = null);
    public function sendTemplate($contactId, $template, $parameters = []);
    public function getMessageHistory($contactId, $pagination = []);
    public function markAsRead($messageId);
    public function deleteMessage($messageId);
}
```

### Business Service Implementation

**Contact Service with Complete CRUD Operations**
```php
// app/Services/ContactService.php
class ContactService extends BaseService implements ContactServiceInterface {
    private ContactFieldService $fieldService;
    private ContactGroupService $groupService;

    public function __construct($workspaceId, ContactFieldService $fieldService = null, ContactGroupService $groupService = null) {
        parent::__construct($workspaceId);
        $this->fieldService = $fieldService ?: app(ContactFieldService::class);
        $this->groupService = $groupService ?: app(ContactGroupService::class);
    }

    public function create(array $data) {
        try {
            $this->validateWorkspaceAccess();
            $this->validateUserPermission('create_contacts');

            DB::beginTransaction();

            // Validate required fields
            $this->validateContactData($data);

            // Check for duplicates
            if ($this->isDuplicateContact($data)) {
                throw new ValidationException('Contact with this email or phone already exists');
            }

            // Process phone number formatting
            if (isset($data['phone'])) {
                $data['phone'] = $this->formatPhoneNumber($data['phone']);
            }

            // Create contact
            $contact = Contact::create([
                'workspace_id' => $this->workspaceId,
                'uuid' => Str::uuid(),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? '',
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'company' => $data['company'] ?? null,
                'position' => $data['position'] ?? null,
                'is_favorite' => $data['is_favorite'] ?? false,
                'metadata' => $data['metadata'] ?? [],
                'created_at' => now()
            ]);

            // Handle custom fields
            if (isset($data['custom_fields'])) {
                $this->saveCustomFields($contact, $data['custom_fields']);
            }

            // Handle group assignments
            if (isset($data['groups'])) {
                $this->assignToGroups($contact, $data['groups']);
            }

            // Handle initial note
            if (isset($data['initial_note'])) {
                $this->addInitialNote($contact, $data['initial_note']);
            }

            // Update usage metrics
            UsageTrackingService::trackContactCreated($this->workspaceId);

            // Fire domain event
            event(new ContactCreatedEvent($contact));

            DB::commit();

            return $this->createSuccessResponse($contact, 'Contact created successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleServiceError($e, ['data' => $data]);
        }
    }

    public function update($id, array $data) {
        try {
            $this->validateWorkspaceAccess();
            $this->validateUserPermission('edit_contacts');

            $contact = Contact::where('workspace_id', $this->workspaceId)
                             ->where('id', $id)
                             ->firstOrFail();

            DB::beginTransaction();

            // Validate update data
            $this->validateContactData($data, $contact);

            // Process phone number if updated
            if (isset($data['phone'])) {
                $data['phone'] = $this->formatPhoneNumber($data['phone']);
            }

            // Update contact
            $contact->update(array_merge($data, ['updated_at' => now()]));

            // Handle custom fields updates
            if (isset($data['custom_fields'])) {
                $this->saveCustomFields($contact, $data['custom_fields']);
            }

            // Handle group assignments
            if (isset($data['groups'])) {
                $this->assignToGroups($contact, $data['groups']);
            }

            // Fire domain event
            event(new ContactUpdatedEvent($contact, $data));

            DB::commit();

            return $this->createSuccessResponse($contact, 'Contact updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleServiceError($e, ['contact_id' => $id, 'data' => $data]);
        }
    }

    public function delete($id) {
        try {
            $this->validateWorkspaceAccess();
            $this->validateUserPermission('delete_contacts');

            $contact = Contact::where('workspace_id', $this->workspaceId)
                             ->where('id', $id)
                             ->firstOrFail();

            DB::beginTransaction();

            // Soft delete contact
            $contact->update([
                'deleted_at' => now(),
                'deleted_by' => auth()->id()
            ]);

            // Archive related data
            $this->archiveRelatedData($contact);

            // Fire domain event
            event(new ContactDeletedEvent($contact));

            DB::commit();

            return $this->createSuccessResponse(null, 'Contact deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleServiceError($e, ['contact_id' => $id]);
        }
    }

    public function find($id) {
        try {
            $this->validateWorkspaceAccess();
            $this->validateUserPermission('view_contacts');

            $contact = Contact::with(['contactGroups', 'customFields', 'lastChat'])
                             ->where('workspace_id', $this->workspaceId)
                             ->where('id', $id)
                             ->firstOrFail();

            return $this->createSuccessResponse($contact);

        } catch (\Exception $e) {
            return $this->handleServiceError($e, ['contact_id' => $id]);
        }
    }

    public function getAll($filters = []) {
        try {
            $this->validateWorkspaceAccess();
            $this->validateUserPermission('view_contacts');

            $query = Contact::with(['contactGroups', 'lastChat'])
                            ->where('workspace_id', $this->workspaceId)
                            ->whereNull('deleted_at');

            // Apply filters
            $this->applyFilters($query, $filters);

            // Apply sorting
            $this->applySorting($query, $filters);

            // Pagination
            $perPage = $filters['per_page'] ?? 20;
            $page = $filters['page'] ?? 1;

            $contacts = $query->paginate($perPage, ['*'], 'page', $page);

            return $this->createSuccessResponse($contacts);

        } catch (\Exception $e) {
            return $this->handleServiceError($e, ['filters' => $filters]);
        }
    }

    public function search($term, $filters = []) {
        try {
            $this->validateWorkspaceAccess();
            $this->validateUserPermission('view_contacts');

            if (empty($term)) {
                return $this->getAll($filters);
            }

            $query = Contact::with(['contactGroups', 'lastChat'])
                            ->where('workspace_id', $this->workspaceId)
                            ->whereNull('deleted_at')
                            ->where(function ($q) use ($term) {
                                $q->where('first_name', 'like', "%{$term}%")
                                  ->orWhere('last_name', 'like', "%{$term}%")
                                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"])
                                  ->orWhere('email', 'like', "%{$term}%")
                                  ->orWhere('phone', 'like', "%{$term}%")
                                  ->orWhere('company', 'like', "%{$term}%");
                            });

            // Apply additional filters
            $this->applyFilters($query, $filters);

            // Pagination
            $perPage = $filters['per_page'] ?? 20;
            $page = $filters['page'] ?? 1;

            $contacts = $query->paginate($perPage, ['*'], 'page', $page);

            return $this->createSuccessResponse($contacts);

        } catch (\Exception $e) {
            return $this->handleServiceError($e, ['search_term' => $term, 'filters' => $filters]);
        }
    }

    // Private helper methods
    private function validateContactData($data, $contact = null) {
        $rules = [
            'first_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:contacts,email',
            'phone' => 'nullable|string',
            'company' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000'
        ];

        // For updates, exclude current record from unique validation
        if ($contact) {
            $rules['email'] = 'nullable|email|unique:contacts,email,' . $contact->id;
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors()->first());
        }

        // Validate phone number format
        if (isset($data['phone'])) {
            if (!$this->isValidPhoneNumber($data['phone'])) {
                throw new ValidationException('Invalid phone number format');
            }
        }
    }

    private function isDuplicateContact($data) {
        $query = Contact::where('workspace_id', $this->workspaceId)
                        ->whereNull('deleted_at');

        if (isset($data['email']) && !empty($data['email'])) {
            $query->orWhere('email', $data['email']);
        }

        if (isset($data['phone']) && !empty($data['phone'])) {
            $query->orWhere('phone', $data['phone']);
        }

        return $query->exists();
    }

    private function formatPhoneNumber($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add country code if missing (assuming US for example)
        if (strlen($phone) === 10) {
            $phone = '1' . $phone;
        }

        return '+' . $phone;
    }

    private function isValidPhoneNumber($phone) {
        try {
            $phoneNumber = PhoneNumber::make($phone);
            return $phoneNumber->isValid();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function saveCustomFields($contact, $customFields) {
        foreach ($customFields as $fieldId => $value) {
            if (!empty($value)) {
                ContactFieldValue::updateOrCreate(
                    [
                        'contact_id' => $contact->id,
                        'field_id' => $fieldId
                    ],
                    [
                        'value' => $value,
                        'updated_at' => now()
                    ]
                );
            }
        }
    }

    private function assignToGroups($contact, $groupIds) {
        $contact->contactGroups()->sync($groupIds);
    }

    private function addInitialNote($contact, $noteText) {
        ChatNote::create([
            'contact_id' => $contact->id,
            'user_id' => auth()->id(),
            'note' => $noteText,
            'workspace_id' => $this->workspaceId,
            'created_at' => now()
        ]);
    }

    private function archiveRelatedData($contact) {
        // Archive chats
        $contact->chats()->update(['deleted_at' => now()]);

        // Archive notes
        $contact->notes()->update(['deleted_at' => now()]);

        // Archive group memberships
        $contact->contactGroups()->detach();
    }

    private function applyFilters($query, $filters) {
        // Filter by groups
        if (isset($filters['groups']) && !empty($filters['groups'])) {
            $query->whereHas('contactGroups', function ($q) use ($filters) {
                $q->whereIn('contact_groups.id', $filters['groups']);
            });
        }

        // Filter by favorite status
        if (isset($filters['is_favorite'])) {
            $query->where('is_favorite', $filters['is_favorite']);
        }

        // Filter by company
        if (isset($filters['company']) && !empty($filters['company'])) {
            $query->where('company', 'like', "%{$filters['company']}%");
        }

        // Filter by date range
        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Filter by last activity
        if (isset($filters['has_recent_activity'])) {
            $query->whereNotNull('latest_chat_created_at')
                  ->where('latest_chat_created_at', '>=', now()->subDays(7));
        }
    }

    private function applySorting($query, $filters) {
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $allowedSortFields = [
            'first_name', 'last_name', 'email', 'phone', 'company',
            'created_at', 'updated_at', 'latest_chat_created_at'
        ];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }
    }

    protected function getModel() {
        return Contact::class;
    }
}
```

## 🎮 Controller Layer (Thin Controllers)

### Service-Integrated Controllers

**Contact Controller Implementation**
```php
// app/Http/Controllers/ContactController.php
class ContactController extends Controller {
    private ContactService $contactService;
    private ContactFieldService $fieldService;
    private ContactGroupService $groupService;

    public function __construct(
        ContactService $contactService,
        ContactFieldService $fieldService,
        ContactGroupService $groupService
    ) {
        $this->contactService = $contactService;
        $this->fieldService = $fieldService;
        $this->groupService = $groupService;
    }

    public function index(Request $request, Workspace $workspace) {
        $filters = $request->all();
        $filters['workspace_id'] = $workspace->id;

        $response = $this->contactService
            ->setWorkspaceId($workspace->id)
            ->getAll($filters);

        if ($request->expectsJson()) {
            return response()->json($response);
        }

        return Inertia::render('User/Contacts/Index', [
            'contacts' => $response['data'],
            'filters' => $filters,
            'fields' => $this->fieldService->getAll($workspace->id)['data'],
            'groups' => $this->groupService->getAll($workspace->id)['data']
        ]);
    }

    public function store(StoreContactRequest $request, Workspace $workspace) {
        $data = $request->validated();
        $data['workspace_id'] = $workspace->id;

        $response = $this->contactService
            ->setWorkspaceId($workspace->id)
            ->create($data);

        if ($request->expectsJson()) {
            return response()->json($response, $response['success'] ? 201 : 422);
        }

        if ($response['success']) {
            return redirect()
                ->route('contacts.index', ['workspace' => $workspace->id])
                ->with('success', $response['message']);
        }

        return redirect()
            ->back()
            ->withErrors(['error' => $response['message']])
            ->withInput();
    }

    public function show(Request $request, Workspace $workspace, Contact $contact) {
        // Verify contact belongs to workspace
        if ($contact->workspace_id !== $workspace->id) {
            abort(404);
        }

        $response = $this->contactService
            ->setWorkspaceId($workspace->id)
            ->find($contact->id);

        if ($request->expectsJson()) {
            return response()->json($response);
        }

        return Inertia::render('User/Contacts/Show', [
            'contact' => $response['data'],
            'workspace' => $workspace
        ]);
    }

    public function update(UpdateContactRequest $request, Workspace $workspace, Contact $contact) {
        // Verify contact belongs to workspace
        if ($contact->workspace_id !== $workspace->id) {
            abort(404);
        }

        $data = $request->validated();

        $response = $this->contactService
            ->setWorkspaceId($workspace->id)
            ->update($contact->id, $data);

        if ($request->expectsJson()) {
            return response()->json($response, $response['success'] ? 200 : 422);
        }

        if ($response['success']) {
            return redirect()
                ->route('contacts.show', ['workspace' => $workspace->id, 'contact' => $contact->id])
                ->with('success', $response['message']);
        }

        return redirect()
            ->back()
            ->withErrors(['error' => $response['message']])
            ->withInput();
    }

    public function destroy(Request $request, Workspace $workspace, Contact $contact) {
        // Verify contact belongs to workspace
        if ($contact->workspace_id !== $workspace->id) {
            abort(404);
        }

        $response = $this->contactService
            ->setWorkspaceId($workspace->id)
            ->delete($contact->id);

        if ($request->expectsJson()) {
            return response()->json($response, $response['success'] ? 200 : 422);
        }

        if ($response['success']) {
            return redirect()
                ->route('contacts.index', ['workspace' => $workspace->id])
                ->with('success', $response['message']);
        }

        return redirect()
            ->back()
            ->withErrors(['error' => $response['message']]);
    }

    public function search(Request $request, Workspace $workspace) {
        $term = $request->get('q', '');
        $filters = $request->except(['q']);

        $response = $this->contactService
            ->setWorkspaceId($workspace->id)
            ->search($term, $filters);

        return response()->json($response);
    }
}
```

## 🔄 Event-Driven Architecture

### Domain Events System

**Event Base Class**
```php
// app/Events/BaseEvent.php
abstract class BaseEvent {
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $occurredAt;
    public $workspaceId;
    public $userId;
    public $metadata;

    public function __construct() {
        $this->occurredAt = now();
        $this->userId = auth()->id();
        $this->metadata = [];
    }

    abstract public function getEventType();
    abstract public function getEventDescription();

    public function toArray() {
        return [
            'event_type' => $this->getEventType(),
            'description' => $this->getEventDescription(),
            'occurred_at' => $this->occurredAt->toISOString(),
            'workspace_id' => $this->workspaceId,
            'user_id' => $this->userId,
            'metadata' => $this->metadata
        ];
    }
}
```

**Contact Domain Events**
```php
// app/Events/ContactCreatedEvent.php
class ContactCreatedEvent extends BaseEvent {
    public $contact;

    public function __construct(Contact $contact) {
        $this->contact = $contact;
        $this->workspaceId = $contact->workspace_id;
        parent::__construct();

        $this->metadata = [
            'contact_id' => $contact->id,
            'contact_uuid' => $contact->uuid,
            'contact_name' => $contact->full_name,
            'contact_email' => $contact->email,
            'contact_phone' => $contact->phone
        ];
    }

    public function getEventType() {
        return 'contact.created';
    }

    public function getEventDescription() {
        return "Contact {$this->contact->full_name} was created";
    }

    public function broadcastOn() {
        return new PrivateChannel('workspace.' . $this->workspaceId);
    }

    public function broadcastAs() {
        return 'contact.created';
    }
}

// app/Events/ContactUpdatedEvent.php
class ContactUpdatedEvent extends BaseEvent {
    public $contact;
    public $changes;

    public function __construct(Contact $contact, array $changes) {
        $this->contact = $contact;
        $this->changes = $changes;
        $this->workspaceId = $contact->workspace_id;
        parent::__construct();

        $this->metadata = array_merge([
            'contact_id' => $contact->id,
            'contact_uuid' => $contact->uuid,
            'changes' => $changes
        ], $this->calculateChangeImpact($changes));
    }

    public function getEventType() {
        return 'contact.updated';
    }

    public function getEventDescription() {
        return "Contact {$this->contact->full_name} was updated";
    }

    private function calculateChangeImpact($changes) {
        $impact = [];

        if (isset($changes['email'])) {
            $impact['email_changed'] = true;
        }

        if (isset($changes['phone'])) {
            $impact['phone_changed'] = true;
        }

        if (isset($changes['groups'])) {
            $impact['groups_changed'] = true;
        }

        return $impact;
    }
}
```

### Event Listeners & Handlers

**Automated Event Processing**
```php
// app/Listeners/ProcessContactCreatedEvent.php
class ProcessContactCreatedEvent {
    private UsageTrackingService $usageService;
    private NotificationService $notificationService;
    private AnalyticsService $analyticsService;

    public function __construct(
        UsageTrackingService $usageService,
        NotificationService $notificationService,
        AnalyticsService $analyticsService
    ) {
        $this->usageService = $usageService;
        $this->notificationService = $notificationService;
        $this->analyticsService = $analyticsService;
    }

    public function handle(ContactCreatedEvent $event) {
        // Update usage metrics
        $this->usageService->trackContactCreated($event->workspaceId);

        // Update analytics
        $this->analyticsService->recordContactCreation($event->contact);

        // Check if welcome automation should be triggered
        $this->checkWelcomeAutomation($event);

        // Update search index
        $this->updateSearchIndex($event->contact);

        // Log activity
        activity()
            ->causedBy(auth()->user())
            ->performedOn($event->contact)
            ->log('created_contact');
    }

    private function checkWelcomeAutomation(ContactCreatedEvent $event) {
        $workspace = Workspace::find($event->workspaceId);
        $settings = json_decode($workspace->metadata ?? '{}');

        if (isset($settings->automation) && $settings->automation->welcome_message_enabled) {
            dispatch(new SendWelcomeMessageJob(
                $event->contact->id,
                $settings->automation->welcome_message_delay ?? 0
            ));
        }
    }

    private function updateSearchIndex(Contact $contact) {
        // Index contact for search
        SearchIndex::upsert([
            [
                'searchable_type' => Contact::class,
                'searchable_id' => $contact->id,
                'workspace_id' => $contact->workspace_id,
                'title' => $contact->full_name,
                'content' => $contact->email . ' ' . $contact->phone . ' ' . $contact->company,
                'metadata' => json_encode([
                    'type' => 'contact',
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'company' => $contact->company
                ])
            ]
        ], ['searchable_type', 'searchable_id'], ['title', 'content', 'metadata']);
    }
}
```

## 🔌 Repository Pattern Implementation

### Data Access Abstraction

**Base Repository Interface**
```php
// app/Contracts/Repositories/BaseRepositoryInterface.php
interface BaseRepositoryInterface {
    public function find($id);
    public function findByUuid($uuid);
    public function findWhere($column, $value);
    public function findWhereIn($column, array $values);
    public function all();
    public function get();
    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
    public function restore($id);
    public function forceDelete($id);
    public function with($relations);
    public function where($column, $operator = null, $value = null, $boolean = 'and');
    public function whereIn($column, array $values, $boolean = 'and', $not = false);
    public function orderBy($column, $direction = 'asc');
    public function latest($column = 'created_at');
    public function oldest($column = 'created_at');
    public function count();
    public function exists();
    public function first();
    public function firstWhere($column, $operator = null, $value = null);
}

// app/Contracts/Repositories/ContactRepositoryInterface.php
interface ContactRepositoryInterface extends BaseRepositoryInterface {
    public function forWorkspace($workspaceId);
    public function search($term, $workspaceId);
    public function withGroups();
    public function withLastChat();
    public function withCustomFields();
    public function findByPhone($phone, $workspaceId);
    public function findByEmail($email, $workspaceId);
    public function onlyFavorites();
    public function withRecentActivity($days = 7);
    public function inGroups(array $groupIds);
}
```

**Concrete Repository Implementation**
```php
// app/Repositories/ContactRepository.php
class ContactRepository implements ContactRepositoryInterface {
    protected $model;
    protected $query;

    public function __construct(Contact $model) {
        $this->model = $model;
        $this->query = $model->newQuery();
    }

    public function find($id) {
        return $this->query->find($id);
    }

    public function findByUuid($uuid) {
        return $this->query->where('uuid', $uuid)->first();
    }

    public function forWorkspace($workspaceId) {
        $this->query->where('workspace_id', $workspaceId);
        return $this;
    }

    public function search($term, $workspaceId) {
        $this->forWorkspace($workspaceId);

        $this->query->where(function ($query) use ($term) {
            $query->where('first_name', 'like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"])
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%")
                  ->orWhere('company', 'like', "%{$term}%");
        });

        return $this;
    }

    public function withGroups() {
        $this->query->with('contactGroups');
        return $this;
    }

    public function withLastChat() {
        $this->query->with(['lastChat' => function ($query) {
            $query->with('media');
        }]);
        return $this;
    }

    public function withCustomFields() {
        $this->query->with('customFields.field');
        return $this;
    }

    public function findByPhone($phone, $workspaceId) {
        return $this->forWorkspace($workspaceId)
                    ->where('phone', $phone)
                    ->first();
    }

    public function findByEmail($email, $workspaceId) {
        return $this->forWorkspace($workspaceId)
                    ->where('email', $email)
                    ->first();
    }

    public function onlyFavorites() {
        $this->query->where('is_favorite', true);
        return $this;
    }

    public function withRecentActivity($days = 7) {
        $this->query->whereNotNull('latest_chat_created_at')
                    ->where('latest_chat_created_at', '>=', now()->subDays($days));
        return $this;
    }

    public function inGroups(array $groupIds) {
        $this->query->whereHas('contactGroups', function ($query) use ($groupIds) {
            $query->whereIn('contact_groups.id', $groupIds);
        });
        return $this;
    }

    // Implement other required interface methods...
    public function create(array $data) {
        return $this->model->create($data);
    }

    public function update($id, array $data) {
        $model = $this->find($id);
        $model->update($data);
        return $model;
    }

    public function delete($id) {
        $model = $this->find($id);
        return $model->delete();
    }

    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null) {
        return $this->query->paginate($perPage, $columns, $pageName, $page);
    }

    public function get() {
        return $this->query->get();
    }

    public function with($relations) {
        $this->query->with($relations);
        return $this;
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and') {
        $this->query->where($column, $operator, $value, $boolean);
        return $this;
    }

    public function orderBy($column, $direction = 'asc') {
        $this->query->orderBy($column, $direction);
        return $this;
    }

    public function latest($column = 'created_at') {
        $this->query->latest($column);
        return $this;
    }

    public function count() {
        return $this->query->count();
    }

    public function exists() {
        return $this->query->exists();
    }

    public function first() {
        return $this->query->first();
    }

    public function all() {
        return $this->model->all();
    }

    public function findWhere($column, $value) {
        return $this->query->where($column, $value)->first();
    }

    public function findWhereIn($column, array $values) {
        return $this->query->whereIn($column, $values)->get();
    }

    public function whereIn($column, array $values, $boolean = 'and', $not = false) {
        $this->query->whereIn($column, $values, $boolean, $not);
        return $this;
    }

    public function oldest($column = 'created_at') {
        $this->query->oldest($column);
        return $this;
    }

    public function firstWhere($column, $operator = null, $value = null) {
        return $this->query->firstWhere($column, $operator, $value);
    }

    public function restore($id) {
        $model = $this->query->withTrashed()->find($id);
        if ($model) {
            $model->restore();
        }
        return $model;
    }

    public function forceDelete($id) {
        $model = $this->query->withTrashed()->find($id);
        if ($model) {
            return $model->forceDelete();
        }
        return false;
    }

    // Reset query for new operations
    public function newQuery() {
        $this->query = $this->model->newQuery();
        return $this;
    }
}
```

## 🎯 Service Container & Dependency Injection

### Service Provider Configuration

**Business Service Provider**
```php
// app/Providers/BusinessServiceProvider.php
class BusinessServiceProvider extends ServiceProvider {
    public function register() {
        // Service bindings
        $this->app->bind(ContactServiceInterface::class, function ($app) {
            return new ContactService(
                $app->make(Request::class)->route('workspace')->id ?? null,
                $app->make(ContactFieldService::class),
                $app->make(ContactGroupService::class)
            );
        });

        $this->app->bind(MessageServiceInterface::class, function ($app) {
            return new MessageService(
                $app->make(Request::class)->route('workspace')->id ?? null,
                $app->make(WhatsAppAdapterInterface::class),
                $app->make(MediaProcessingService::class)
            );
        });

        $this->app->bind(CampaignServiceInterface::class, CampaignService::class);
        $this->app->bind(AnalyticsServiceInterface::class, AnalyticsService::class);
        $this->app->bind(SubscriptionServiceInterface::class, SubscriptionService::class);

        // Repository bindings
        $this->app->bind(ContactRepositoryInterface::class, function ($app) {
            return new ContactRepository($app->make(Contact::class));
        });

        $this->app->bind(ChatRepositoryInterface::class, function ($app) {
            return new ChatRepository($app->make(Chat::class));
        });

        $this->app->bind(CampaignRepositoryInterface::class, function ($app) {
            return new CampaignRepository($app->make(Campaign::class));
        });

        // WhatsApp service bindings
        $this->app->bind(WhatsAppAdapterInterface::class, function ($app) {
            $workspace = $app->make(Request::class)->route('workspace');

            if (!$workspace) {
                throw new \Exception('Workspace context required');
            }

            $provider = $workspace->metadata['whatsapp']['provider'] ?? 'meta';

            switch ($provider) {
                case 'webjs':
                    return new WebJSAdapter($workspace->id);
                case 'meta':
                    return new MetaAPIAdapter($workspace->id);
                default:
                    throw new \Exception("Unsupported WhatsApp provider: {$provider}");
            }
        });

        // Singleton services
        $this->app->singleton(UsageTrackingService::class, function ($app) {
            return new UsageTrackingService();
        });

        $this->app->singleton(PermissionService::class, function ($app) {
            return new PermissionService();
        });

        $this->app->singleton(EncryptionService::class, function ($app) {
            return new EncryptionService();
        });
    }

    public function boot() {
        // Register event listeners
        Event::listen(
            ContactCreatedEvent::class,
            ProcessContactCreatedEvent::class
        );

        Event::listen(
            ContactUpdatedEvent::class,
            ProcessContactUpdatedEvent::class
        );

        Event::listen(
            MessageSentEvent::class,
            ProcessMessageSentEvent::class
        );

        Event::listen(
            CampaignCompletedEvent::class,
            ProcessCampaignCompletedEvent::class
        );
    }
}
```

### Contextual Service Resolution

**Workspace Context Service**
```php
// app/Services/WorkspaceContextService.php
class WorkspaceContextService {
    private static $currentWorkspace = null;
    private static $currentUser = null;

    public static function setCurrentWorkspace(Workspace $workspace) {
        self::$currentWorkspace = $workspace;
    }

    public static function getCurrentWorkspace(): ?Workspace {
        if (self::$currentWorkspace === null) {
            self::$currentWorkspace = request()->route('workspace');
        }
        return self::$currentWorkspace;
    }

    public static function getCurrentWorkspaceId(): ?int {
        $workspace = self::getCurrentWorkspace();
        return $workspace ? $workspace->id : null;
    }

    public static function setCurrentUser(User $user) {
        self::$currentUser = $user;
    }

    public static function getCurrentUser(): ?User {
        if (self::$currentUser === null && auth()->check()) {
            self::$currentUser = auth()->user();
        }
        return self::$currentUser;
    }

    public static function getCurrentUserRole(): ?string {
        $user = self::getCurrentUser();
        $workspace = self::getCurrentWorkspace();

        if (!$user || !$workspace) {
            return null;
        }

        $team = Team::where('user_id', $user->id)
                    ->where('workspace_id', $workspace->id)
                    ->first();

        return $team ? $team->role : null;
    }

    public static function isCurrentWorkspaceOwner(): bool {
        return self::getCurrentUserRole() === 'owner';
    }

    public static function isCurrentWorkspaceAdmin(): bool {
        $role = self::getCurrentUserRole();
        return in_array($role, ['owner', 'admin']);
    }

    public static function getWorkspaceSettings() {
        $workspace = self::getCurrentWorkspace();
        if (!$workspace || !$workspace->metadata) {
            return [];
        }

        return json_decode($workspace->metadata, true);
    }

    public static function getWorkspaceSetting($key, $default = null) {
        $settings = self::getWorkspaceSettings();
        return data_get($settings, $key, $default);
    }
}
```

## 🚀 Performance Optimization Patterns

### Caching Strategy Implementation

**Multi-Level Caching System**
```php
// app/Services/Cache/CacheService.php
class CacheService {
    private static $cacheStrategies = [
        'contacts' => [
            'ttl' => 3600, // 1 hour
            'tags' => ['contacts', 'workspace']
        ],
        'chats' => [
            'ttl' => 300, // 5 minutes
            'tags' => ['chats', 'workspace']
        ],
        'campaigns' => [
            'ttl' => 1800, // 30 minutes
            'tags' => ['campaigns', 'workspace']
        ],
        'analytics' => [
            'ttl' => 600, // 10 minutes
            'tags' => ['analytics', 'workspace']
        ],
        'user_permissions' => [
            'ttl' => 7200, // 2 hours
            'tags' => ['permissions', 'user']
        ]
    ];

    public static function remember($key, $callback, $type = 'default') {
        $strategy = self::$cacheStrategies[$type] ?? ['ttl' => 3600, 'tags' => []];

        return Cache::tags($strategy['tags'])
                   ->remember($key, $strategy['ttl'], $callback);
    }

    public static function forget($key) {
        Cache::forget($key);
    }

    public static function forgetByTags(array $tags) {
        Cache::tags($tags)->flush();
    }

    public static function getWorkspaceCacheKey($workspaceId, $identifier) {
        return "workspace_{$workspaceId}:{$identifier}";
    }

    public static function getUserCacheKey($userId, $identifier) {
        return "user_{$userId}:{$identifier}";
    }

    public static function getContactCacheKey($workspaceId, $contactId, $identifier) {
        return "workspace_{$workspaceId}:contact_{$contactId}:{$identifier}";
    }
}
```

**Query Optimization Service**
```php
// app/Services/QueryOptimizationService.php
class QueryOptimizationService {
    public static function optimizeContactQuery($workspaceId, $filters = []) {
        return Contact::select([
                'contacts.id',
                'contacts.uuid',
                'contacts.first_name',
                'contacts.last_name',
                'contacts.email',
                'contacts.phone',
                'contacts.is_favorite',
                'contacts.latest_chat_created_at',
                'contacts.created_at'
            ])
            ->with(['lastChat' => function ($query) {
                $query->select(['id', 'contact_id', 'message', 'type', 'created_at']);
            }])
            ->where('contacts.workspace_id', $workspaceId)
            ->whereNull('contacts.deleted_at')
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $query->where(function ($q) use ($filters) {
                    $q->where('contacts.first_name', 'like', "%{$filters['search']}%")
                      ->orWhere('contacts.last_name', 'like', "%{$filters['search']}%")
                      ->orWhereRaw("CONCAT(contacts.first_name, ' ', contacts.last_name) LIKE ?", ["%{$filters['search']}%"]);
                });
            })
            ->when(isset($filters['groups']), function ($query) use ($filters) {
                $query->whereHas('contactGroups', function ($q) use ($filters) {
                    $q->whereIn('contact_groups.id', $filters['groups']);
                });
            });
    }

    public static function optimizeChatQuery($workspaceId, $contactId = null) {
        $query = Chat::select([
                'chats.id',
                'chats.contact_id',
                'chats.message',
                'chats.type',
                'chats.status',
                'chats.created_at',
                'contacts.first_name',
                'contacts.last_name'
            ])
            ->join('contacts', 'chats.contact_id', '=', 'contacts.id')
            ->where('chats.workspace_id', $workspaceId)
            ->whereNull('chats.deleted_at')
            ->orderBy('chats.created_at', 'desc');

        if ($contactId) {
            $query->where('chats.contact_id', $contactId);
        }

        return $query;
    }
}
```

---

**Hybrid Service-Oriented Architecture** ini menyediakan foundation yang robust dan scalable untuk enterprise WhatsApp Business Platform, dengan clear separation of concerns, maintainable codebase, dan excellent testability.