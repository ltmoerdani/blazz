<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ContactPresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use App\Events\ContactPresenceUpdated;
use App\Events\TypingIndicator;

class ContactPresenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContactPresenceService $service;
    private Workspace $workspace;
    private User $user;
    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ContactPresenceService();
        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->contact = Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_online' => false,
            'typing_status' => 'idle'
        ]);

        // Attach user to workspace
        $this->workspace->users()->attach($this->user->id);
    }

    /**
     * Test updating online status
     */
    public function test_update_online_status(): void
    {
        $this->assertFalse($this->contact->is_online);

        $this->service->updateOnlineStatus($this->contact->id, true, $this->user->id);

        $this->contact->refresh();
        $this->assertTrue($this->contact->is_online);
        $this->assertNotNull($this->contact->last_activity);

        // Check cache was updated
        $cached = Cache::get("contact.presence.{$this->contact->id}");
        $this->assertNotNull($cached);
        $this->assertTrue($cached['is_online']);
    }

    /**
     * Test updating typing status
     */
    public function test_update_typing_status(): void
    {
        $this->assertEquals('idle', $this->contact->typing_status);

        Event::fake();

        $this->service->updateTypingStatus($this->contact->id, 'typing', $this->user->id);

        $this->contact->refresh();
        $this->assertEquals('typing', $this->contact->typing_status);

        // Check that TypingIndicator event was dispatched
        Event::assertDispatched(TypingIndicator::class, function ($event) {
            return $event->contact->id === $this->contact->id &&
                   $event->userId === $this->user->id &&
                   $event->isTyping === true;
        });
    }

    /**
     * Test updating last message time
     */
    public function test_update_last_message_time(): void
    {
        $this->assertNull($this->contact->last_message_at);

        $messageId = 'test-message-123';
        $this->service->updateLastMessageTime($this->contact->id, $messageId);

        $this->contact->refresh();
        $this->assertNotNull($this->contact->last_message_at);
        $this->assertNotNull($this->contact->last_activity);

        // Check cache was updated
        $cached = Cache::get("contact.last_message.{$this->contact->id}");
        $this->assertNotNull($cached);
    }

    /**
     * Test getting contact presence
     */
    public function test_get_contact_presence(): void
    {
        // Update some fields first
        $this->contact->update([
            'is_online' => true,
            'typing_status' => 'typing',
            'last_activity' => now()->subMinutes(5)
        ]);

        $presence = $this->service->getContactPresence($this->contact->id);

        $this->assertTrue($presence['is_online']);
        $this->assertEquals('typing', $presence['typing_status']);
        $this->assertNotNull($presence['last_activity']);
    }

    /**
     * Test getting workspace contacts with presence
     */
    public function test_get_workspace_contacts_with_presence(): void
    {
        // Create additional contacts
        $contact2 = Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_online' => true,
            'typing_status' => 'idle',
            'last_message_at' => now()->subMinutes(10)
        ]);

        $contact3 = Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_online' => false,
            'typing_status' => 'typing',
            'last_message_at' => now()->subMinutes(5)
        ]);

        $contacts = $this->service->getWorkspaceContactsWithPresence(
            $this->workspace->id
        );

        $this->assertCount(3, $contacts);
        $this->assertEquals($contact3->id, $contacts->first()->id); // Should be ordered by last_message_at
    }

    /**
     * Test cleaning up offline contacts
     */
    public function test_cleanup_offline_contacts(): void
    {
        // Create contacts with different last_activity times
        $onlineContact = Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_online' => true,
            'last_activity' => now()->subMinutes(2) // Recent
        ]);

        $offlineContact = Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_online' => true,
            'last_activity' => now()->subMinutes(10) // Old - should be marked offline
        ]);

        $updated = $this->service->cleanupOfflineContacts();

        $this->assertGreaterThanOrEqual(1, $updated);

        $offlineContact->refresh();
        $this->assertFalse($offlineContact->is_online);
        $this->assertEquals('idle', $offlineContact->typing_status);

        $onlineContact->refresh();
        $this->assertTrue($onlineContact->is_online);
    }

    /**
     * Test bulk update presence
     */
    public function test_bulk_update_presence(): void
    {
        $contact2 = Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_online' => false
        ]);

        $contactIds = [$this->contact->id, $contact2->id];
        $presenceData = [
            'is_online' => true,
            'typing_status' => 'idle'
        ];

        $this->service->bulkUpdatePresence($contactIds, $presenceData);

        $this->contact->refresh();
        $contact2->refresh();

        $this->assertTrue($this->contact->is_online);
        $this->assertTrue($contact2->is_online);
        $this->assertEquals('idle', $this->contact->typing_status);
        $this->assertEquals('idle', $contact2->typing_status);
    }

    /**
     * Test getting online contacts count
     */
    public function test_get_online_contacts_count(): void
    {
        // Create some online and offline contacts
        Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_online' => true
        ]);

        Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_online' => false
        ]);

        $count = $this->service->getOnlineContactsCount($this->workspace->id);

        $this->assertEquals(1, $count); // Only 1 online contact (created above)
    }

    /**
     * Test getting typing contacts
     */
    public function test_get_typing_contacts(): void
    {
        // Create contacts with different typing status
        Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'typing_status' => 'typing'
        ]);

        Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'typing_status' => 'idle'
        ]);

        $typingContacts = $this->service->getTypingContacts($this->workspace->id);

        $this->assertCount(1, $typingContacts);
        $this->assertEquals('typing', $typingContacts->first()->typing_status);
    }

    /**
     * Test ContactPresenceUpdated event is dispatched
     */
    public function test_contact_presence_updated_event_dispatched(): void
    {
        Event::fake();

        $this->service->updateOnlineStatus($this->contact->id, true, $this->user->id);

        Event::assertDispatched(ContactPresenceUpdated::class, function ($event) {
            return $event->contact->id === $this->contact->id &&
                   $event->isOnline === true &&
                   $event->userId === $this->user->id;
        });
    }

    /**
     * Test error handling when contact not found
     */
    public function test_handles_contact_not_found(): void
    {
        // Should not throw exception when contact doesn't exist
        $this->service->updateOnlineStatus(999, true, $this->user->id);

        // Should return default presence when getting non-existent contact
        $presence = $this->service->getContactPresence(999);

        $this->assertFalse($presence['is_online']);
        $this->assertEquals('idle', $presence['typing_status']);
    }

    /**
     * Test workspace scope enforcement
     */
    public function test_workspace_scope_enforcement(): void
    {
        // Create contact in different workspace
        $otherWorkspace = Workspace::factory()->create();
        $otherContact = Contact::factory()->create([
            'workspace_id' => $otherWorkspace->id
        ]);

        // Service should only work with contacts in the specified workspace
        $contacts = $this->service->getWorkspaceContactsWithPresence($this->workspace->id);

        $this->assertNotContains($otherContact->id, $contacts->pluck('id')->toArray());
        $this->assertContains($this->contact->id, $contacts->pluck('id')->toArray());
    }
}
