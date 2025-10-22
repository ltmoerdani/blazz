<?php

namespace Tests\Unit\Services;

use App\Models\Contact;
use App\Models\Workspace;
use App\Services\ContactProvisioningService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * TASK-TEST-1: Unit Tests for ContactProvisioningService
 * Reference: docs/chat-whatsappwebjs-integration/design.md (DES-5)
 *
 * Test Coverage:
 * - Contact creation from WhatsApp data
 * - Phone number formatting (E164)
 * - Duplicate contact handling
 * - Name update logic
 * - Workspace isolation
 */
class ContactProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $workspace;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->service = new ContactProvisioningService();
    }

    /**
     * Test: Creates new contact if not exists
     *
     * @test
     */
    public function test_creates_new_contact_if_not_exists()
    {
        $phone = '+6281234567890';
        $name = 'John Doe';

        $contact = $this->service->getOrCreateContact(
            $phone,
            $name,
            $this->workspace->id,
            'webjs'
        );

        $this->assertInstanceOf(Contact::class, $contact);
        $this->assertEquals($phone, $contact->phone);
        $this->assertEquals($name, $contact->first_name);
        $this->assertEquals($this->workspace->id, $contact->workspace_id);
        $this->assertDatabaseHas('contacts', [
            'phone' => $phone,
            'first_name' => $name,
            'workspace_id' => $this->workspace->id,
        ]);
    }

    /**
     * Test: Returns existing contact if already exists
     *
     * @test
     */
    public function test_returns_existing_contact_if_exists()
    {
        $phone = '+6281234567890';

        $existingContact = Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'phone' => $phone,
            'first_name' => 'Existing Name',
        ]);

        $contact = $this->service->getOrCreateContact(
            $phone,
            'New Name',
            $this->workspace->id,
            'webjs'
        );

        // Should return existing contact, not create new one
        $this->assertEquals($existingContact->id, $contact->id);
        $this->assertEquals(1, Contact::where('phone', $phone)->count());
    }

    /**
     * Test: Updates contact name if null
     *
     * @test
     */
    public function test_updates_contact_name_if_null()
    {
        $phone = '+6281234567890';

        // Create contact without name
        Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'phone' => $phone,
            'first_name' => null,
        ]);

        $contact = $this->service->getOrCreateContact(
            $phone,
            'Updated Name',
            $this->workspace->id
        );

        $this->assertEquals('Updated Name', $contact->first_name);
    }

    /**
     * Test: Does not overwrite existing name
     *
     * @test
     */
    public function test_does_not_overwrite_existing_name()
    {
        $phone = '+6281234567890';

        Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'phone' => $phone,
            'first_name' => 'Original Name',
        ]);

        $contact = $this->service->getOrCreateContact(
            $phone,
            'New Name',
            $this->workspace->id
        );

        // Should keep original name
        $this->assertEquals('Original Name', $contact->first_name);
    }

    /**
     * Test: Formats phone number to E164 standard
     *
     * @test
     */
    public function test_formats_phone_to_e164()
    {
        // Test various phone formats
        $testCases = [
            '081234567890' => '+6281234567890',
            '6281234567890' => '+6281234567890',
            '+62 812 3456 7890' => '+6281234567890',
            '0812-3456-7890' => '+6281234567890',
        ];

        foreach ($testCases as $input => $expected) {
            $formatted = $this->service->formatPhone($input);
            $this->assertEquals($expected, $formatted);
        }
    }

    /**
     * Test: Handles invalid phone numbers gracefully
     *
     * @test
     */
    public function test_handles_invalid_phone_gracefully()
    {
        $invalidPhones = [
            '',
            'abc',
            '123', // Too short
        ];

        foreach ($invalidPhones as $phone) {
            $contact = $this->service->getOrCreateContact(
                $phone,
                'Test Name',
                $this->workspace->id
            );

            // Should still create contact but with original phone
            $this->assertInstanceOf(Contact::class, $contact);
        }
    }

    /**
     * Test: Workspace isolation
     *
     * @test
     */
    public function test_workspace_isolation()
    {
        $phone = '+6281234567890';

        // Create contact in workspace 1
        Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'phone' => $phone,
            'first_name' => 'Workspace 1 Contact',
        ]);

        // Create another workspace
        $workspace2 = Workspace::factory()->create();

        // Should create new contact for workspace 2
        $contact = $this->service->getOrCreateContact(
            $phone,
            'Workspace 2 Contact',
            $workspace2->id
        );

        $this->assertEquals('Workspace 2 Contact', $contact->first_name);
        $this->assertEquals(2, Contact::where('phone', $phone)->count());
    }

    /**
     * Test: Sets source_type correctly
     *
     * @test
     */
    public function test_sets_source_type_correctly()
    {
        $phone = '+6281234567890';

        $contact = $this->service->getOrCreateContact(
            $phone,
            'Test Name',
            $this->workspace->id,
            'webjs'
        );

        $this->assertEquals('webjs', $contact->source_type);

        // Test with meta source
        $phone2 = '+6289876543210';
        $contact2 = $this->service->getOrCreateContact(
            $phone2,
            'Test Name 2',
            $this->workspace->id,
            'meta'
        );

        $this->assertEquals('meta', $contact2->source_type);
    }

    /**
     * Test: Handles soft deleted contacts
     *
     * @test
     */
    public function test_handles_soft_deleted_contacts()
    {
        $phone = '+6281234567890';

        // Create and soft delete contact
        $deletedContact = Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'phone' => $phone,
            'first_name' => 'Deleted Contact',
        ]);
        $deletedContact->delete(); // Soft delete

        // Should create new contact (not restore deleted one)
        $contact = $this->service->getOrCreateContact(
            $phone,
            'New Contact',
            $this->workspace->id
        );

        $this->assertNotEquals($deletedContact->id, $contact->id);
        $this->assertNull($contact->deleted_at);
    }

    /**
     * Test: Handles null name gracefully
     *
     * @test
     */
    public function test_handles_null_name_gracefully()
    {
        $phone = '+6281234567890';

        $contact = $this->service->getOrCreateContact(
            $phone,
            null, // Null name
            $this->workspace->id
        );

        $this->assertInstanceOf(Contact::class, $contact);
        $this->assertNull($contact->first_name);
    }

    /**
     * Test: Created_by is set to system (0)
     *
     * @test
     */
    public function test_created_by_is_system()
    {
        $phone = '+6281234567890';

        $contact = $this->service->getOrCreateContact(
            $phone,
            'Test Name',
            $this->workspace->id
        );

        $this->assertEquals(0, $contact->created_by);
    }

    /**
     * Test: Concurrent calls don't create duplicates
     *
     * @test
     */
    public function test_concurrent_calls_dont_create_duplicates()
    {
        $phone = '+6281234567890';

        // Simulate concurrent calls
        $contact1 = $this->service->getOrCreateContact(
            $phone,
            'Name 1',
            $this->workspace->id
        );

        $contact2 = $this->service->getOrCreateContact(
            $phone,
            'Name 2',
            $this->workspace->id
        );

        // Should return same contact
        $this->assertEquals($contact1->id, $contact2->id);
        $this->assertEquals(1, Contact::where('phone', $phone)->where('workspace_id', $this->workspace->id)->count());
    }
}
