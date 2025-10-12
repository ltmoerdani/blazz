<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WhatsAppSession;
use App\Services\ProviderSelector;
use App\Services\Adapters\WebJSAdapter;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WhatsAppServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private WhatsAppSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create([
            'created_by' => $this->user->id,
        ]);

        $this->session = WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'status' => 'connected',
            'phone_number' => '+6281234567890',
        ]);
    }

    /** @test */
    public function provider_selector_can_select_available_provider()
    {
        $selector = new ProviderSelector();

        $provider = $selector->selectProvider($this->workspace->id);

        $this->assertInstanceOf(WebJSAdapter::class, $provider);
        $this->assertEquals('webjs', $provider->getProviderType());
    }

    /** @test */
    public function provider_selector_throws_exception_when_no_provider_available()
    {
        $this->expectException(\App\Exceptions\WhatsAppProviderNotFoundException::class);

        $selector = new ProviderSelector();
        $provider = $selector->selectProvider(999); // Non-existent workspace
    }

    /** @test */
    public function webjs_adapter_can_send_message()
    {
        $adapter = new WebJSAdapter($this->workspace->id, $this->session);
        $contact = Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'phone' => '+6281234567891',
        ]);

        // Mock HTTP response for testing
        $this->mockHttpResponse();

        $result = $adapter->sendMessage($contact, 'Test message');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /** @test */
    public function webjs_adapter_returns_error_when_session_unavailable()
    {
        $session = WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'disconnected',
        ]);

        $adapter = new WebJSAdapter($this->workspace->id, $session);
        $contact = Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);

        $result = $adapter->sendMessage($contact, 'Test message');

        $this->assertFalse($result['success']);
        $this->assertEquals('WebJS session not available', $result['error']);
    }

    /** @test */
    public function whatsapp_session_calculates_health_score_correctly()
    {
        $session = WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'connected',
            'last_activity_at' => now()->subMinutes(30),
        ]);

        $this->assertEquals(100, $session->health_score);

        // Test with disconnected session
        $session->update(['status' => 'disconnected']);
        $this->assertEquals(50, $session->health_score);

        // Test with old activity
        $session->update([
            'status' => 'connected',
            'last_activity_at' => now()->subHours(2),
        ]);
        $this->assertEquals(80, $session->health_score);
    }

    /** @test */
    public function whatsapp_session_is_healthy_when_score_above_threshold()
    {
        $session = WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'connected',
            'last_activity_at' => now()->subMinutes(30),
        ]);

        $this->assertTrue($session->isHealthy());

        $session->update(['status' => 'disconnected']);
        $this->assertFalse($session->isHealthy());
    }

    /** @test */
    public function whatsapp_session_formats_phone_number_correctly()
    {
        $session = WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'phone_number' => '6281234567890',
        ]);

        $this->assertEquals('+6281234567890', $session->formatted_phone_number);
    }

    private function mockHttpResponse()
    {
        // Mock HTTP client for testing
        $this->mock(\Illuminate\Support\Facades\Http::class, function ($mock) {
            $mock->shouldReceive('timeout')
                ->andReturnSelf()
                ->shouldReceive('post')
                ->andReturnSelf()
                ->shouldReceive('json')
                ->andReturn([
                    'success' => true,
                    'message_id' => 'test-message-id'
                ]);
        });
    }
}
