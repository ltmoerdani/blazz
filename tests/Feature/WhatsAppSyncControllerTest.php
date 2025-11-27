<?php

namespace Tests\Feature;

use App\Jobs\WhatsAppChatSyncJob;
use App\Models\WhatsAppAccount;
use App\Models\Workspace;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

/**
 * TASK-TEST-2: Integration Tests for WhatsApp Sync Controller
 * Reference: docs/chat-whatsappwebjs-integration/design.md (TASK-SVC-4)
 *
 * Test Coverage:
 * - Batch sync endpoint
 * - Queue dispatching
 * - Rate limiting
 * - HMAC validation
 * - Input validation
 */
class WhatsAppSyncControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $workspace;
    protected $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->account = WhatsAppAccount::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'status' => 'connected',
        ]);

        Queue::fake();
    }

    /**
     * Generate valid HMAC signature
     */
    protected function generateSignature($payload)
    {
        $secret = config('whatsapp.security.hmac_secret', 'test_secret');
        return hash_hmac('sha256', json_encode($payload), $secret);
    }

    /**
     * Test: Sync endpoint queues job and returns 202
     *
     * @test
     */
    public function test_sync_endpoint_queues_job()
    {
        $payload = [
            'session_id' => $this->account->id,
            'workspace_id' => $this->workspace->id,
            'chats' => [
                [
                    'type' => 'private',
                    'contact_phone' => '+6281234567890',
                    'contact_name' => 'John Doe',
                    'last_message' => 'Hello',
                    'timestamp' => now()->timestamp,
                ],
            ],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/chats/sync', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(202);
        $response->assertJson(['status' => 'queued']);

        Queue::assertPushed(WhatsAppChatSyncJob::class, function ($job) {
            return $job->sessionId === $this->account->id &&
                   $job->workspaceId === $this->workspace->id;
        });
    }

    /**
     * Test: Validates max batch size
     *
     * @test
     */
    public function test_validates_max_batch_size()
    {
        $chats = array_fill(0, 51, [ // More than max 50
            'type' => 'private',
            'contact_phone' => '+6281234567890',
            'contact_name' => 'Test',
            'last_message' => 'Test',
            'timestamp' => now()->timestamp,
        ]);

        $payload = [
            'session_id' => $this->account->id,
            'workspace_id' => $this->workspace->id,
            'chats' => $chats,
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/chats/sync', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['chats']);
    }

    /**
     * Test: Invalid signature returns 401
     *
     * @test
     */
    public function test_invalid_signature_returns_401()
    {
        $payload = [
            'session_id' => $this->account->id,
            'workspace_id' => $this->workspace->id,
            'chats' => [],
        ];

        $response = $this->postJson('/api/whatsapp/chats/sync', $payload, [
            'X-WhatsApp-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(401);

        Queue::assertNothingPushed();
    }

    /**
     * Test: Rate limiting works (60 requests per minute per session)
     *
     * @test
     */
    public function test_rate_limiting()
    {
        $payload = [
            'session_id' => $this->account->id,
            'workspace_id' => $this->workspace->id,
            'chats' => [],
        ];

        $signature = $this->generateSignature($payload);

        // Make 61 requests
        for ($i = 0; $i < 61; $i++) {
            $response = $this->postJson('/api/whatsapp/chats/sync', $payload, [
                'X-WhatsApp-Signature' => $signature,
            ]);

            if ($i < 60) {
                $response->assertStatus(202);
            } else {
                // 61st request should be rate limited
                $response->assertStatus(429);
                $response->assertJsonStructure(['error', 'retry_after']);
            }
        }
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
            'chats' => [],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/chats/sync', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['session_id', 'workspace_id']);
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
            'chats' => [],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/chats/sync', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['session_id']);
    }

    /**
     * Test: Invalid workspace ID returns error
     *
     * @test
     */
    public function test_invalid_workspace_returns_error()
    {
        $payload = [
            'session_id' => $this->account->id,
            'workspace_id' => 99999, // Non-existent
            'chats' => [],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/chats/sync', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['workspace_id']);
    }

    /**
     * Test: Empty chats array is valid
     *
     * @test
     */
    public function test_empty_chats_array_is_valid()
    {
        $payload = [
            'session_id' => $this->account->id,
            'workspace_id' => $this->workspace->id,
            'chats' => [], // Empty is okay
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/chats/sync', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(202);
    }

    /**
     * Test: Handles large batch efficiently
     *
     * @test
     */
    public function test_handles_large_batch()
    {
        $chats = array_fill(0, 50, [ // Max 50
            'type' => 'private',
            'contact_phone' => '+6281234567890',
            'contact_name' => 'Test User',
            'last_message' => 'Test message',
            'timestamp' => now()->timestamp,
        ]);

        $payload = [
            'session_id' => $this->account->id,
            'workspace_id' => $this->workspace->id,
            'chats' => $chats,
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/chats/sync', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(202);

        Queue::assertPushed(WhatsAppChatSyncJob::class, function ($job) {
            return count($job->chats) === 50;
        });
    }

    /**
     * Test: Validates chat data structure
     *
     * @test
     */
    public function test_validates_chat_data_structure()
    {
        $payload = [
            'session_id' => $this->account->id,
            'workspace_id' => $this->workspace->id,
            'chats' => [
                [
                    // Missing required fields in chat object
                    'invalid' => 'data',
                ],
            ],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/chats/sync', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['chats.0.type']);
    }

    /**
     * Test: Workspace isolation
     *
     * @test
     */
    public function test_workspace_isolation()
    {
        $workspace2 = Workspace::factory()->create();

        $payload = [
            'session_id' => $this->account->id,
            'workspace_id' => $workspace2->id, // Different workspace
            'chats' => [],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/chats/sync', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        // Should fail because session doesn't belong to workspace2
        $response->assertStatus(422);
    }

    /**
     * Test: Returns correct response format
     *
     * @test
     */
    public function test_returns_correct_response_format()
    {
        $payload = [
            'session_id' => $this->account->id,
            'workspace_id' => $this->workspace->id,
            'chats' => [
                [
                    'type' => 'private',
                    'contact_phone' => '+6281234567890',
                    'contact_name' => 'Test',
                    'last_message' => 'Test',
                    'timestamp' => now()->timestamp,
                ],
            ],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson('/api/whatsapp/chats/sync', $payload, [
            'X-WhatsApp-Signature' => $signature,
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'status',
            'message',
            'batch_size',
        ]);
        $response->assertJson([
            'status' => 'queued',
            'batch_size' => 1,
        ]);
    }
}
