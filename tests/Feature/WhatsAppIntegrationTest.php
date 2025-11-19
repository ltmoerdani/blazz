<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WhatsAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const WHATSAPP_ACCOUNTS_ROUTE = '/settings/whatsapp-accounts';

    private User $user;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create([
            'created_by' => $this->user->id,
        ]);

        // Set current workspace in session
        session(['current_workspace' => $this->workspace->id]);
    }

    /** @test */
    public function it_can_create_whatsapp_account()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(self::WHATSAPP_ACCOUNTS_ROUTE, [
            'provider_type' => 'webjs',
            'is_primary' => false,
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'WhatsApp session created successfully'
                ]);

        $this->assertDatabaseHas('whatsapp_accounts', [
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'status' => 'initializing',
        ]);
    }

    /** @test */
    public function it_can_list_whatsapp_accounts()
    {
        $this->actingAs($this->user);

        // Create test sessions
        WhatsAppAccount::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'status' => 'connected',
        ]);

        $response = $this->get(self::WHATSAPP_ACCOUNTS_ROUTE);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('User/Settings/WhatsAppAccounts')
                ->has('sessions')
        );
    }

    /** @test */
    public function it_broadcasts_qr_code_generation_event()
    {
        Event::fake();

        $account = WhatsAppAccount::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'qr_scanning',
        ]);

        $qrCode = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...';

        // Simulate QR code generation event
        event(new \App\Events\WhatsAppQRGeneratedEvent(
            $qrCode,
            300,
            $this->workspace->id,
            $session->session_id
        ));

        Event::assertDispatched(\App\Events\WhatsAppQRGeneratedEvent::class);
    }

    /** @test */
    public function it_broadcasts_session_status_change_event()
    {
        Event::fake();

        $account = WhatsAppAccount::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'connected',
            'phone_number' => '+6281234567890',
        ]);

        // Simulate session status change event
        event(new \App\Events\WhatsAppAccountStatusChangedEvent(
            $session->session_id,
            'connected',
            $this->workspace->id,
            $session->phone_number
        ));

        Event::assertDispatched(\App\Events\WhatsAppAccountStatusChangedEvent::class);
    }

    /** @test */
    public function it_can_set_primary_session()
    {
        $this->actingAs($this->user);

        $session1 = WhatsAppAccount::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_primary' => true,
        ]);

        $account2 = WhatsAppAccount::factory()->create([
            'workspace_id' => $this->workspace->id,
            'is_primary' => false,
        ]);

        $response = $this->postJson(self::WHATSAPP_ACCOUNTS_ROUTE . "/{$account2->uuid}/set-primary");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Session set as primary successfully'
                ]);

        $session1->refresh();
        $account2->refresh();

        $this->assertFalse($session1->is_primary);
        $this->assertTrue($account2->is_primary);
    }

    /** @test */
    public function it_can_disconnect_session()
    {
        $this->actingAs($this->user);

        $account = WhatsAppAccount::factory()->create([
            'workspace_id' => $this->workspace->id,
            'status' => 'connected',
        ]);

        $response = $this->postJson("/settings/whatsapp-accounts/{$session->uuid}/disconnect");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Session disconnected successfully'
                ]);

        $session->refresh();
        $this->assertEquals('disconnected', $session->status);
    }

    /** @test */
    public function it_validates_session_ownership()
    {
        $otherUser = User::factory()->create();
        $otherWorkspace = Workspace::factory()->create([
            'created_by' => $otherUser->id,
        ]);

        $account = WhatsAppAccount::factory()->create([
            'workspace_id' => $otherWorkspace->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->get("/settings/whatsapp-accounts/{$session->uuid}");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_enforces_plan_limits()
    {
        $this->actingAs($this->user);

        // Create maximum allowed sessions (5 for testing)
        for ($i = 0; $i < 5; $i++) {
            WhatsAppAccount::factory()->create([
                'workspace_id' => $this->workspace->id,
            ]);
        }

        $response = $this->postJson('/settings/whatsapp-accounts', [
            'provider_type' => 'webjs',
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'You have reached the maximum number of WhatsApp sessions for your plan.'
                ]);
    }
}
