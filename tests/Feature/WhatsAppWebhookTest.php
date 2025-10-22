<?php

namespace Tests\Feature;

use App\Events\NewChatEvent;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\WhatsAppSession;
use App\Models\WhatsAppGroup;
use App\Models\Workspace;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

/**
 * TASK-TEST-2: Integration Tests for WhatsApp Webhook Flow
 * Reference: docs/chat-whatsappwebjs-integration/design.md (DES-10)
 *
 * Test Coverage:
 * - HMAC validation
 * - Private chat creation
 * - Group chat creation
 * - Event broadcasting
 * - Contact auto-provisioning
 * - Workspace isolation
 */
class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected $workspace;
    protected $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->session = WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'status' => 'connected',
        ]);

        Event::fake();
    }

    /**
     * Generate valid HMAC signature for webhook
     */
    protected function generateSignature($payload)
    {
        $secret = config('whatsapp.security.hmac_secret', 'test_secret');
        return hash_hmac('sha256', json_encode($payload), $secret);
    }

    /**
     * Test: Webhook creates private chat and broadcasts event
     *
     * @test
     */
    public function test_webhook_creates_private_chat_and_broadcasts_event()
    {
        Event::fake([NewChatEvent::class]);

        $payload = [
            'session_id' => $this->session->id,
            'workspace_id' => $this->workspace->id,
            'chat_type' => 'private',
            'contact_phone' => '+6281234567890',
            'contact_name' => 'John Doe',
            'message_body' => 'Hello World',
            'message_type' => 'text',
            'timestamp' => now()->timestamp,
            'has_media' => false,
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        // Verify contact created
        $this->assertDatabaseHas('contacts', [
            'workspace_id' => $this->workspace->id,
            'phone' => '+6281234567890',
            'first_name' => 'John Doe',
        ]);

        // Verify chat created
        $this->assertDatabaseHas('chats', [
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'chat_type' => 'private',
        ]);

        // Verify event broadcasted
        Event::assertDispatched(NewChatEvent::class);
    }

    /**
     * Test: Webhook creates group chat
     *
     * @test
     */
    public function test_webhook_creates_group_chat()
    {
        $payload = [
            'session_id' => $this->session->id,
            'workspace_id' => $this->workspace->id,
            'chat_type' => 'group',
            'group_jid' => '1234567890-1234567890@g.us',
            'group_name' => 'Test Group',
            'sender_phone' => '+6281111111111',
            'sender_name' => 'Group Member',
            'message_body' => 'Group message',
            'message_type' => 'text',
            'timestamp' => now()->timestamp,
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        // Verify group created
        $this->assertDatabaseHas('whatsapp_groups', [
            'workspace_id' => $this->workspace->id,
            'group_jid' => '1234567890-1234567890@g.us',
            'name' => 'Test Group',
        ]);

        // Verify chat created
        $this->assertDatabaseHas('chats', [
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'chat_type' => 'group',
        ]);

        $chat = Chat::where('chat_type', 'group')->first();
        $this->assertNull($chat->contact_id);
        $this->assertNotNull($chat->group_id);
    }

    /**
     * Test: Invalid HMAC signature returns 401
     *
     * @test
     */
    public function test_invalid_signature_returns_401()
    {
        $payload = [
            'session_id' => $this->session->id,
            'workspace_id' => $this->workspace->id,
            'chat_type' => 'private',
            'contact_phone' => '+6281234567890',
            'message_body' => 'Test',
        ];

        $response = $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-WhatsApp-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid webhook signature']);

        // Verify no data created
        $this->assertEquals(0, Chat::count());
        $this->assertEquals(0, Contact::count());
    }

    /**
     * Test: Missing signature returns 401
     *
     * @test
     */
    public function test_missing_signature_returns_401()
    {
        $payload = [
            'session_id' => $this->session->id,
            'workspace_id' => $this->workspace->id,
            'chat_type' => 'private',
            'contact_phone' => '+6281234567890',
            'message_body' => 'Test',
        ];

        $response = $this->postJson('/api/whatsapp/webhook', $payload);

        $response->assertStatus(401);
    }

    /**
     * Test: Auto-provisions new contact
     *
     * @test
     */
    public function test_auto_provisions_new_contact()
    {
        $payload = [
            'session_id' => $this->session->id,
            'workspace_id' => $this->workspace->id,
            'chat_type' => 'private',
            'contact_phone' => '+6281234567890',
            'contact_name' => 'New Contact',
            'message_body' => 'Hello',
            'timestamp' => now()->timestamp,
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        $contact = Contact::where('phone', '+6281234567890')->first();
        $this->assertNotNull($contact);
        $this->assertEquals('New Contact', $contact->first_name);
        $this->assertEquals($this->workspace->id, $contact->workspace_id);
    }

    /**
     * Test: Uses existing contact if available
     *
     * @test
     */
    public function test_uses_existing_contact()
    {
        $existingContact = Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'phone' => '+6281234567890',
            'first_name' => 'Existing Name',
        ]);

        $payload = [
            'session_id' => $this->session->id,
            'workspace_id' => $this->workspace->id,
            'chat_type' => 'private',
            'contact_phone' => '+6281234567890',
            'contact_name' => 'Different Name',
            'message_body' => 'Hello',
            'timestamp' => now()->timestamp,
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        // Should use existing contact
        $this->assertEquals(1, Contact::where('phone', '+6281234567890')->count());

        $chat = Chat::first();
        $this->assertEquals($existingContact->id, $chat->contact_id);
    }

    /**
     * Test: Workspace isolation
     *
     * @test
     */
    public function test_workspace_isolation()
    {
        $workspace2 = Workspace::factory()->create();
        $session2 = WhatsAppSession::factory()->create([
            'workspace_id' => $workspace2->id,
            'provider_type' => 'webjs',
            'status' => 'connected',
        ]);

        // Create contact in workspace 1
        Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'phone' => '+6281234567890',
            'first_name' => 'Workspace 1 Contact',
        ]);

        // Webhook for workspace 2 should create separate contact
        $payload = [
            'session_id' => $session2->id,
            'workspace_id' => $workspace2->id,
            'chat_type' => 'private',
            'contact_phone' => '+6281234567890',
            'contact_name' => 'Workspace 2 Contact',
            'message_body' => 'Hello',
            'timestamp' => now()->timestamp,
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        // Should have 2 separate contacts
        $this->assertEquals(2, Contact::where('phone', '+6281234567890')->count());

        // Verify correct workspace assignment
        $chat = Chat::where('workspace_id', $workspace2->id)->first();
        $this->assertEquals($workspace2->id, $chat->contact->workspace_id);
    }

    /**
     * Test: Handles media messages
     *
     * @test
     */
    public function test_handles_media_messages()
    {
        $payload = [
            'session_id' => $this->session->id,
            'workspace_id' => $this->workspace->id,
            'chat_type' => 'private',
            'contact_phone' => '+6281234567890',
            'contact_name' => 'John Doe',
            'message_body' => 'Check this image',
            'message_type' => 'image',
            'has_media' => true,
            'media_url' => 'https://example.com/image.jpg',
            'timestamp' => now()->timestamp,
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        $chat = Chat::first();
        $metadata = json_decode($chat->metadata, true);
        $this->assertTrue($metadata['has_media']);
        $this->assertEquals('image', $metadata['type']);
    }

    /**
     * Test: Validates required fields
     *
     * @test
     */
    public function test_validates_required_fields()
    {
        $payload = [
            // Missing required fields
            'session_id' => $this->session->id,
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['workspace_id', 'chat_type']);
    }

    /**
     * Test: Invalid session ID returns error
     *
     * @test
     */
    public function test_invalid_session_returns_error()
    {
        $payload = [
            'session_id' => 99999, // Non-existent
            'workspace_id' => $this->workspace->id,
            'chat_type' => 'private',
            'contact_phone' => '+6281234567890',
            'message_body' => 'Test',
            'timestamp' => now()->timestamp,
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['session_id']);
    }

    /**
     * Test: Sets correct chat status
     *
     * @test
     */
    public function test_sets_correct_chat_status()
    {
        $payload = [
            'session_id' => $this->session->id,
            'workspace_id' => $this->workspace->id,
            'chat_type' => 'private',
            'contact_phone' => '+6281234567890',
            'contact_name' => 'John Doe',
            'message_body' => 'Hello',
            'timestamp' => now()->timestamp,
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        $chat = Chat::first();
        $this->assertEquals('inbound', $chat->type);
        $this->assertEquals('delivered', $chat->status);
        $this->assertEquals(0, $chat->is_read);
    }

    /**
     * Test: Handles concurrent webhook calls
     *
     * @test
     */
    public function test_handles_concurrent_webhook_calls()
    {
        $payload = [
            'session_id' => $this->session->id,
            'workspace_id' => $this->workspace->id,
            'chat_type' => 'private',
            'contact_phone' => '+6281234567890',
            'contact_name' => 'John Doe',
            'message_body' => 'Hello',
            'wam_id' => 'unique_message_id_123',
            'timestamp' => now()->timestamp,
        ];

        $signature = $this->generateSignature($payload);

        // Simulate concurrent calls
        $response1 = $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response2 = $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // Should not create duplicate chats
        $this->assertEquals(1, Chat::where('wam_id', 'unique_message_id_123')->count());
    }
}
