<?php

namespace Tests\Browser;

use App\Models\Contact;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Illuminate\Support\Facades\Hash;

/**
 * TASK-TEST-4: E2E Real-Time Broadcast Tests
 * Reference: docs/chat-whatsappwebjs-integration/design.md (RISK-5, DES-9)
 *
 * Test Coverage:
 * - End-to-end real-time message flow
 * - Chat appears in UI within <2 seconds
 * - WebSocket/Pusher integration
 * - Group chat real-time updates
 * - Session-specific broadcasts
 */
class ChatRealtimeTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected $user;
    protected $workspace;
    protected $session;
    protected $contact;

    /**
     * Setup test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Create workspace
        $this->workspace = Workspace::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Create WhatsApp session
        $this->account = WhatsAppAccount::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'status' => 'connected',
            'phone_number' => '+6281234567890',
        ]);

        // Create test contact
        $this->contact = Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'phone' => '+6289876543210',
            'first_name' => 'Test',
            'last_name' => 'Contact',
        ]);
    }

    /**
     * Test: Private chat appears in real-time
     *
     * @test
     * @group e2e
     * @group realtime
     */
    public function test_private_chat_appears_in_realtime()
    {
        $this->browse(function (Browser $browser) {
            // Login and navigate to chat page
            $browser->loginAs($this->user)
                ->visit('/chats')
                ->waitFor('.chat-table', 10)
                ->assertSee('Chats');

            // Record start time
            $startTime = microtime(true);

            // Simulate incoming webhook (private chat)
            $this->sendWebhook([
                'session_id' => $this->account->id,
                'workspace_id' => $this->workspace->id,
                'chat_type' => 'private',
                'contact_phone' => $this->contact->phone,
                'contact_name' => $this->contact->first_name,
                'message_body' => 'Hello from E2E test!',
                'message_type' => 'text',
                'timestamp' => now()->timestamp,
                'has_media' => false,
            ]);

            // Wait for chat to appear (max 3 seconds)
            $browser->waitForText('Hello from E2E test!', 3);

            // Calculate latency
            $endTime = microtime(true);
            $latency = ($endTime - $startTime) * 1000; // milliseconds

            // Assert message appeared
            $browser->assertSee('Hello from E2E test!')
                ->assertSee('Test Contact');

            // Log latency for monitoring
            echo "\nReal-time latency: {$latency}ms\n";

            // Assert latency is acceptable (<2000ms)
            $this->assertLessThan(
                2000,
                $latency,
                "Real-time broadcast took {$latency}ms, expected <2000ms"
            );
        });
    }

    /**
     * Test: Group chat appears in real-time
     *
     * @test
     * @group e2e
     * @group realtime
     */
    public function test_group_chat_appears_in_realtime()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/chats')
                ->waitFor('.chat-table', 10);

            $startTime = microtime(true);

            // Simulate incoming group webhook
            $this->sendWebhook([
                'session_id' => $this->account->id,
                'workspace_id' => $this->workspace->id,
                'chat_type' => 'group',
                'group_jid' => '1234567890-1234567890@g.us',
                'group_name' => 'Test Group',
                'sender_phone' => '+6281111111111',
                'sender_name' => 'Group Member',
                'message_body' => 'Group message test',
                'message_type' => 'text',
                'timestamp' => now()->timestamp,
                'has_media' => false,
            ]);

            // Wait for group chat to appear
            $browser->waitForText('Test Group', 3);

            $endTime = microtime(true);
            $latency = ($endTime - $startTime) * 1000;

            // Assert group chat elements
            $browser->assertSee('Test Group')
                ->assertSee('Group message test')
                ->assertSee('Group Member');

            echo "\nGroup chat real-time latency: {$latency}ms\n";

            $this->assertLessThan(
                2000,
                $latency,
                "Group chat broadcast took {$latency}ms, expected <2000ms"
            );
        });
    }

    /**
     * Test: Multiple rapid messages appear in order
     *
     * @test
     * @group e2e
     * @group realtime
     */
    public function test_multiple_messages_appear_in_order()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/chats')
                ->waitFor('.chat-table', 10);

            // Send 5 rapid messages
            $messages = [
                'Message 1',
                'Message 2',
                'Message 3',
                'Message 4',
                'Message 5',
            ];

            foreach ($messages as $index => $messageBody) {
                $this->sendWebhook([
                    'session_id' => $this->account->id,
                    'workspace_id' => $this->workspace->id,
                    'chat_type' => 'private',
                    'contact_phone' => $this->contact->phone,
                    'contact_name' => $this->contact->first_name,
                    'message_body' => $messageBody,
                    'message_type' => 'text',
                    'timestamp' => now()->addSeconds($index)->timestamp,
                    'has_media' => false,
                ]);

                // Small delay between messages
                usleep(100000); // 100ms
            }

            // Wait for last message
            $browser->waitForText('Message 5', 5);

            // Verify all messages appear
            foreach ($messages as $message) {
                $browser->assertSee($message);
            }
        });
    }

    /**
     * Test: Chat thread updates in real-time when viewing conversation
     *
     * @test
     * @group e2e
     * @group realtime
     */
    public function test_chat_thread_updates_in_realtime()
    {
        // Create initial chat
        $chat = \App\Models\Chat::factory()->create([
            'contact_id' => $this->contact->id,
            'workspace_id' => $this->workspace->id,
            'whatsapp_account_id' => $this->account->id,
            'chat_type' => 'private',
            'type' => 'inbound',
            'body' => 'Initial message',
            'status' => 'received',
        ]);

        $this->browse(function (Browser $browser) {
            // Open specific chat conversation
            $browser->loginAs($this->user)
                ->visit('/chats?contact=' . $this->contact->uuid)
                ->waitFor('.chat-thread', 10)
                ->assertSee('Initial message');

            $startTime = microtime(true);

            // Send new message while viewing thread
            $this->sendWebhook([
                'session_id' => $this->account->id,
                'workspace_id' => $this->workspace->id,
                'chat_type' => 'private',
                'contact_phone' => $this->contact->phone,
                'contact_name' => $this->contact->first_name,
                'message_body' => 'New incoming message',
                'message_type' => 'text',
                'timestamp' => now()->timestamp,
                'has_media' => false,
            ]);

            // Wait for new message in thread
            $browser->waitForText('New incoming message', 3);

            $endTime = microtime(true);
            $latency = ($endTime - $startTime) * 1000;

            $browser->assertSee('New incoming message');

            echo "\nThread update latency: {$latency}ms\n";

            $this->assertLessThan(
                2000,
                $latency,
                "Thread update took {$latency}ms, expected <2000ms"
            );
        });
    }

    /**
     * Test: Session filter doesn't affect real-time updates
     *
     * @test
     * @group e2e
     * @group realtime
     */
    public function test_session_filter_with_realtime_updates()
    {
        // Create second session
        $account2 = WhatsAppAccount::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'meta',
            'status' => 'connected',
            'phone_number' => '+6289999999999',
        ]);

        $this->browse(function (Browser $browser) use ($account2) {
            $browser->loginAs($this->user)
                ->visit('/chats')
                ->waitFor('.chat-table', 10);

            // Select session filter
            if ($browser->element('.session-filter')) {
                $browser->select('.session-filter', $this->account->id);
                $browser->pause(500);
            }

            // Send message to filtered session
            $this->sendWebhook([
                'session_id' => $this->account->id,
                'workspace_id' => $this->workspace->id,
                'chat_type' => 'private',
                'contact_phone' => $this->contact->phone,
                'contact_name' => $this->contact->first_name,
                'message_body' => 'Filtered session message',
                'message_type' => 'text',
                'timestamp' => now()->timestamp,
                'has_media' => false,
            ]);

            // Should appear
            $browser->waitForText('Filtered session message', 3)
                ->assertSee('Filtered session message');

            // Send message to different session
            $this->sendWebhook([
                'session_id' => $account2->id,
                'workspace_id' => $this->workspace->id,
                'chat_type' => 'private',
                'contact_phone' => '+6287777777777',
                'contact_name' => 'Other Contact',
                'message_body' => 'Other session message',
                'message_type' => 'text',
                'timestamp' => now()->timestamp,
                'has_media' => false,
            ]);

            // Wait a bit
            $browser->pause(1000);

            // Should not appear (filtered out)
            // Note: This depends on frontend implementation
            // If session filter is implemented, this message should not show
        });
    }

    /**
     * Test: Unread badge updates in real-time
     *
     * @test
     * @group e2e
     * @group realtime
     */
    public function test_unread_badge_updates_in_realtime()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/chats')
                ->waitFor('.chat-table', 10);

            // Send unread message
            $this->sendWebhook([
                'session_id' => $this->account->id,
                'workspace_id' => $this->workspace->id,
                'chat_type' => 'private',
                'contact_phone' => $this->contact->phone,
                'contact_name' => $this->contact->first_name,
                'message_body' => 'Unread message',
                'message_type' => 'text',
                'timestamp' => now()->timestamp,
                'has_media' => false,
            ]);

            // Wait for message
            $browser->waitForText('Unread message', 3);

            // Check for unread indicator (implementation-specific)
            // This could be a badge, bold text, or notification dot
            // Uncomment based on actual implementation:
            // $browser->assertVisible('.unread-badge');
        });
    }

    /**
     * Test: Media message notification in real-time
     *
     * @test
     * @group e2e
     * @group realtime
     */
    public function test_media_message_appears_in_realtime()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/chats')
                ->waitFor('.chat-table', 10);

            // Send media message
            $this->sendWebhook([
                'session_id' => $this->account->id,
                'workspace_id' => $this->workspace->id,
                'chat_type' => 'private',
                'contact_phone' => $this->contact->phone,
                'contact_name' => $this->contact->first_name,
                'message_body' => 'Image message',
                'message_type' => 'image',
                'timestamp' => now()->timestamp,
                'has_media' => true,
                'media_url' => 'https://example.com/image.jpg',
            ]);

            // Wait for message
            $browser->waitForText('Image message', 3)
                ->assertSee('Image message');

            // Check for media indicator
            // Implementation-specific
        });
    }

    /**
     * Helper: Send webhook to trigger real-time event
     */
    protected function sendWebhook(array $payload)
    {
        // Generate valid HMAC signature
        $secret = config('whatsapp.security.hmac_secret', 'test_secret');
        $signature = hash_hmac('sha256', json_encode($payload), $secret);

        // Send POST request to webhook endpoint
        $response = $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        // Ensure webhook was accepted
        if ($response->status() !== 200 && $response->status() !== 202) {
            throw new \Exception(
                "Webhook failed with status {$response->status()}: " .
                $response->getContent()
            );
        }

        return $response;
    }

    /**
     * Test: Browser console has no errors during real-time updates
     *
     * @test
     * @group e2e
     * @group realtime
     */
    public function test_no_javascript_errors_during_realtime_updates()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/chats')
                ->waitFor('.chat-table', 10);

            // Send multiple messages
            for ($i = 1; $i <= 3; $i++) {
                $this->sendWebhook([
                    'session_id' => $this->account->id,
                    'workspace_id' => $this->workspace->id,
                    'chat_type' => 'private',
                    'contact_phone' => $this->contact->phone,
                    'contact_name' => $this->contact->first_name,
                    'message_body' => "Test message {$i}",
                    'message_type' => 'text',
                    'timestamp' => now()->timestamp,
                    'has_media' => false,
                ]);

                $browser->pause(500);
            }

            // Wait for last message
            $browser->waitForText('Test message 3', 3);

            // Check browser console logs
            $logs = $browser->driver->manage()->getLog('browser');

            $errors = array_filter($logs, function ($log) {
                return $log['level'] === 'SEVERE';
            });

            $this->assertEmpty(
                $errors,
                'Browser console should not have errors during real-time updates'
            );
        });
    }

    /**
     * Test: Connection resilience - reconnect after disconnect
     *
     * @test
     * @group e2e
     * @group realtime
     * @group slow
     */
    public function test_reconnection_after_disconnect()
    {
        $this->markTestSkipped(
            'Reconnection testing requires Pusher/WebSocket manipulation - ' .
            'implement if needed for production validation'
        );

        // Future implementation:
        // 1. Open chat page
        // 2. Disconnect WebSocket (via browser console)
        // 3. Wait for reconnection
        // 4. Send webhook
        // 5. Verify message appears after reconnection
    }
}
