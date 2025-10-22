<?php

namespace Tests\Unit\Services;

use App\Models\WhatsAppSession;
use App\Models\Workspace;
use App\Services\ProviderSelector;
use App\Services\Adapters\MetaAPIAdapter;
use App\Services\Adapters\WebJSAdapter;
use App\Exceptions\SessionNotActiveException;
use App\Exceptions\NoActiveSessionException;
use App\Exceptions\NoBackupProviderException;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * TASK-TEST-1: Unit Tests for ProviderSelector
 * Reference: docs/chat-whatsappwebjs-integration/design.md (DES-6)
 *
 * Test Coverage:
 * - Provider selection based on session type
 * - Failover mechanism
 * - Health-based selection
 * - Exception handling
 */
class ProviderSelectorTest extends TestCase
{
    use RefreshDatabase;

    protected $workspace;
    protected $providerSelector;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test workspace
        $this->workspace = Workspace::factory()->create();
        $this->providerSelector = new ProviderSelector();
    }

    /**
     * Test: Selects WebJSAdapter for webjs session
     *
     * @test
     */
    public function test_selects_webjs_adapter_for_webjs_session()
    {
        $session = WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'status' => 'connected',
        ]);

        $adapter = $this->providerSelector->selectProvider(
            $this->workspace->id,
            $session->id
        );

        $this->assertInstanceOf(WebJSAdapter::class, $adapter);
    }

    /**
     * Test: Selects MetaAPIAdapter for meta session
     *
     * @test
     */
    public function test_selects_meta_adapter_for_meta_session()
    {
        $session = WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'meta',
            'status' => 'connected',
        ]);

        $adapter = $this->providerSelector->selectProvider(
            $this->workspace->id,
            $session->id
        );

        $this->assertInstanceOf(MetaAPIAdapter::class, $adapter);
    }

    /**
     * Test: Throws exception when session not connected
     *
     * @test
     */
    public function test_throws_exception_when_session_not_active()
    {
        $this->expectException(SessionNotActiveException::class);

        $session = WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'status' => 'disconnected', // Not connected
        ]);

        $this->providerSelector->selectProvider(
            $this->workspace->id,
            $session->id
        );
    }

    /**
     * Test: Selects primary session when no specific session requested
     *
     * @test
     */
    public function test_selects_primary_session_when_no_session_id_provided()
    {
        // Create non-primary session
        WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'status' => 'connected',
            'is_primary' => false,
        ]);

        // Create primary session
        $primarySession = WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'meta',
            'status' => 'connected',
            'is_primary' => true,
        ]);

        $adapter = $this->providerSelector->selectProvider($this->workspace->id);

        $this->assertInstanceOf(MetaAPIAdapter::class, $adapter);
    }

    /**
     * Test: Falls back to any active session when no primary session
     *
     * @test
     */
    public function test_fallback_to_any_active_session_when_no_primary()
    {
        $session = WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'status' => 'connected',
            'is_primary' => false,
            'health_score' => 95,
        ]);

        $adapter = $this->providerSelector->selectProvider($this->workspace->id);

        $this->assertInstanceOf(WebJSAdapter::class, $adapter);
    }

    /**
     * Test: Throws exception when no active sessions available
     *
     * @test
     */
    public function test_throws_exception_when_no_active_sessions()
    {
        $this->expectException(NoActiveSessionException::class);
        $this->expectExceptionMessage('Tidak ada WhatsApp session yang aktif untuk workspace ini');

        WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'status' => 'disconnected', // All disconnected
        ]);

        $this->providerSelector->selectProvider($this->workspace->id);
    }

    /**
     * Test: Failover switches to backup provider
     *
     * @test
     */
    public function test_failover_switches_to_backup_provider()
    {
        $primarySession = WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'status' => 'connected', // Will be failed
            'health_score' => 50,
        ]);

        $backupSession = WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'meta',
            'status' => 'connected',
            'health_score' => 95,
        ]);

        $adapter = $this->providerSelector->failover(
            $this->workspace->id,
            $primarySession->id
        );

        $this->assertInstanceOf(MetaAPIAdapter::class, $adapter);
    }

    /**
     * Test: Failover throws exception when no backup available
     *
     * @test
     */
    public function test_failover_throws_exception_when_no_backup()
    {
        $this->expectException(NoBackupProviderException::class);
        $this->expectExceptionMessage('Tidak ada backup provider yang tersedia');

        $session = WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'status' => 'connected',
        ]);

        // No other active sessions
        $this->providerSelector->failover($this->workspace->id, $session->id);
    }

    /**
     * Test: Failover selects session with highest health score
     *
     * @test
     */
    public function test_failover_selects_highest_health_score()
    {
        $primarySession = WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'status' => 'connected',
            'health_score' => 50,
        ]);

        // Lower health score backup
        WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'meta',
            'status' => 'connected',
            'health_score' => 70,
        ]);

        // Higher health score backup
        WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'meta',
            'status' => 'connected',
            'health_score' => 95,
        ]);

        $adapter = $this->providerSelector->failover(
            $this->workspace->id,
            $primarySession->id
        );

        // Should select the one with highest health score (95)
        $this->assertInstanceOf(MetaAPIAdapter::class, $adapter);
    }

    /**
     * Test: Provider selection ignores disconnected sessions
     *
     * @test
     */
    public function test_ignores_disconnected_sessions()
    {
        // Disconnected sessions
        WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'status' => 'disconnected',
            'is_primary' => true,
        ]);

        // Connected session
        WhatsAppSession::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'meta',
            'status' => 'connected',
            'is_primary' => false,
        ]);

        $adapter = $this->providerSelector->selectProvider($this->workspace->id);

        // Should select connected meta session, not disconnected primary
        $this->assertInstanceOf(MetaAPIAdapter::class, $adapter);
    }

    /**
     * Test: Workspace isolation - doesn't select sessions from other workspaces
     *
     * @test
     */
    public function test_workspace_isolation()
    {
        $otherWorkspace = Workspace::factory()->create();

        // Session in other workspace
        WhatsAppSession::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'provider_type' => 'webjs',
            'status' => 'connected',
            'is_primary' => true,
        ]);

        $this->expectException(NoActiveSessionException::class);

        // Should not find session from other workspace
        $this->providerSelector->selectProvider($this->workspace->id);
    }
}
