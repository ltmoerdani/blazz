# 🧪 Testing Guide

## Mobile Activity Conflict Detection System

**Version:** 1.1  
**Last Updated:** November 29, 2025

---

## 1. Testing Strategy Overview

### 1.1 Testing Pyramid

```
                 ┌─────────────────┐
                 │   E2E Tests     │  ← Browser/API Integration
                 │   (10-15%)      │
                 ├─────────────────┤
                 │ Integration     │  ← Service + Database
                 │ Tests (25-30%)  │
                 ├─────────────────┤
                 │   Unit Tests    │  ← Components in isolation
                 │   (60-70%)      │
                 └─────────────────┘
```

### 1.2 Test Categories

| Category | Focus | Tools |
|----------|-------|-------|
| **Unit Tests** | Individual methods & classes | PHPUnit, Jest |
| **Integration Tests** | Service interactions | PHPUnit + SQLite |
| **Feature Tests** | Full HTTP request/response | Laravel HTTP Tests |
| **E2E Tests** | Complete flow simulation | Pest + Dusk |

---

## 2. Unit Tests

### 2.1 PHP Unit Tests

#### 2.1.1 CampaignConflictResolverTest

```php
<?php

namespace Tests\Unit\Services\Campaign;

use App\Models\Campaign;
use App\Models\Workspace;
use App\Models\WhatsappAccount;
use App\Services\Campaign\CampaignConflictResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class CampaignConflictResolverTest extends TestCase
{
    use RefreshDatabase;

    protected CampaignConflictResolver $resolver;
    protected Workspace $workspace;
    protected WhatsappAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->workspace = Workspace::factory()->create();
        $this->account = WhatsappAccount::factory()->create([
            'workspace_id' => $this->workspace->id,
            'session_id' => 'test_session_123',
            'tier' => 2,
        ]);
        
        $this->resolver = new CampaignConflictResolver($this->workspace->id);
    }

    /** @test */
    public function it_pauses_all_ongoing_campaigns_for_session(): void
    {
        // Arrange: Create 3 ongoing campaigns
        $campaigns = Campaign::factory()
            ->count(3)
            ->for($this->workspace)
            ->ongoing()
            ->create(['session_id' => 'test_session_123']);

        // Create 1 campaign with different session (should NOT be paused)
        $otherCampaign = Campaign::factory()
            ->for($this->workspace)
            ->ongoing()
            ->create(['session_id' => 'other_session']);

        // Act
        $result = $this->resolver->pauseAllCampaigns('test_session_123', 'android');

        // Assert
        $this->assertTrue($result->success);
        $this->assertEquals(3, $result->data['paused_count']);
        
        foreach ($campaigns as $campaign) {
            $campaign->refresh();
            $this->assertEquals(Campaign::STATUS_PAUSED_MOBILE, $campaign->status);
            $this->assertNotNull($campaign->paused_at);
        }
        
        // Other campaign should NOT be paused
        $otherCampaign->refresh();
        $this->assertNotEquals(Campaign::STATUS_PAUSED_MOBILE, $otherCampaign->status);
    }

    /** @test */
    public function it_returns_zero_when_no_ongoing_campaigns(): void
    {
        // Arrange: No campaigns
        
        // Act
        $result = $this->resolver->pauseAllCampaigns('test_session_123', 'android');

        // Assert
        $this->assertTrue($result->success);
        $this->assertEquals(0, $result->data['paused_count']);
    }

    /** @test */
    public function it_resumes_campaign_and_updates_status(): void
    {
        // Arrange: Create paused campaign
        $campaign = Campaign::factory()
            ->for($this->workspace)
            ->create([
                'status' => Campaign::STATUS_PAUSED_MOBILE,
                'paused_at' => now()->subSeconds(45),
                'session_id' => 'test_session_123',
            ]);

        // Act
        $result = $this->resolver->resumeCampaign($campaign->id);

        // Assert
        $this->assertTrue($result->success);
        
        $campaign->refresh();
        $this->assertEquals(Campaign::STATUS_ONGOING, $campaign->status);
        $this->assertNull($campaign->paused_at);
    }

    /** @test */
    public function it_gets_tier_based_cooldown(): void
    {
        // Tier 1
        $this->account->update(['tier' => 1]);
        $this->assertEquals(60, $this->resolver->getTierCooldown($this->account->id));

        // Tier 2
        $this->account->update(['tier' => 2]);
        $this->assertEquals(45, $this->resolver->getTierCooldown($this->account->id));

        // Tier 3
        $this->account->update(['tier' => 3]);
        $this->assertEquals(30, $this->resolver->getTierCooldown($this->account->id));

        // Tier 4
        $this->account->update(['tier' => 4]);
        $this->assertEquals(20, $this->resolver->getTierCooldown($this->account->id));
    }

    /** @test */
    public function it_determines_should_resume_correctly(): void
    {
        $campaign = Campaign::factory()
            ->for($this->workspace)
            ->create([
                'status' => Campaign::STATUS_PAUSED_MOBILE,
                'paused_at' => now()->subSeconds(45),
            ]);

        // No activity - should resume
        $this->assertTrue($this->resolver->shouldResume($campaign->id, null));

        // Old activity (60 seconds ago) - should resume
        $oldActivity = new \DateTime('-60 seconds');
        $this->assertTrue($this->resolver->shouldResume($campaign->id, $oldActivity));

        // Recent activity (5 seconds ago) - should NOT resume
        $recentActivity = new \DateTime('-5 seconds');
        $this->assertFalse($this->resolver->shouldResume($campaign->id, $recentActivity));
    }

    /** @test */
    public function it_does_not_pause_already_paused_campaigns(): void
    {
        // Arrange: Create already paused campaign
        $campaign = Campaign::factory()
            ->for($this->workspace)
            ->create([
                'status' => Campaign::STATUS_PAUSED_MOBILE,
                'paused_at' => now()->subMinutes(5),
                'session_id' => 'test_session_123',
            ]);

        // Act
        $result = $this->resolver->pauseAllCampaigns('test_session_123', 'android');

        // Assert - should not re-pause
        $this->assertEquals(0, $result->data['paused_count']);
    }

    /** @test */
    public function it_ignores_web_device_types(): void
    {
        // Arrange
        $campaign = Campaign::factory()
            ->for($this->workspace)
            ->ongoing()
            ->create(['session_id' => 'test_session_123']);

        // Act - web should be ignored
        $result = $this->resolver->pauseAllCampaigns('test_session_123', 'web');

        // Assert
        $this->assertEquals(0, $result->data['paused_count']);
        
        $campaign->refresh();
        $this->assertNotEquals(Campaign::STATUS_PAUSED_MOBILE, $campaign->status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

---

#### 2.1.2 CampaignModelTest

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignMobileConflictTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_paused_mobile_status_constant(): void
    {
        $this->assertEquals('paused_mobile', Campaign::STATUS_PAUSED_MOBILE);
    }

    /** @test */
    public function it_checks_if_paused_for_mobile(): void
    {
        $pausedCampaign = Campaign::factory()->create([
            'status' => Campaign::STATUS_PAUSED_MOBILE,
        ]);
        
        $ongoingCampaign = Campaign::factory()->create([
            'status' => 'ongoing',
        ]);

        $this->assertTrue($pausedCampaign->isPausedForMobile());
        $this->assertFalse($ongoingCampaign->isPausedForMobile());
    }

    /** @test */
    public function it_pauses_for_mobile_activity(): void
    {
        $campaign = Campaign::factory()->ongoing()->create();

        $campaign->pauseForMobileActivity('session_123');

        $this->assertEquals(Campaign::STATUS_PAUSED_MOBILE, $campaign->status);
        $this->assertNotNull($campaign->paused_at);
    }

    /** @test */
    public function it_resumes_from_pause(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => Campaign::STATUS_PAUSED_MOBILE,
            'paused_at' => now()->subSeconds(30),
        ]);

        $campaign->resumeFromPause();

        $this->assertEquals('ongoing', $campaign->status);
        $this->assertNull($campaign->paused_at);
    }

    /** @test */
    public function scope_paused_for_mobile_filters_correctly(): void
    {
        Campaign::factory()->count(3)->create([
            'status' => Campaign::STATUS_PAUSED_MOBILE,
        ]);
        Campaign::factory()->count(2)->create([
            'status' => 'ongoing',
        ]);
        Campaign::factory()->count(1)->create([
            'status' => 'completed',
        ]);

        $paused = Campaign::pausedForMobile()->get();

        $this->assertCount(3, $paused);
        $paused->each(function ($campaign) {
            $this->assertEquals(Campaign::STATUS_PAUSED_MOBILE, $campaign->status);
        });
    }
}
```

---

### 2.2 JavaScript Unit Tests

#### 2.2.1 MobileActivityMonitor.test.js

```javascript
const MobileActivityMonitor = require('../../../whatsapp-service/monitors/MobileActivityMonitor');

// Mock logger
const mockLogger = {
    info: jest.fn(),
    warn: jest.fn(),
    error: jest.fn(),
    debug: jest.fn(),
};

// Mock axios for webhook calls
jest.mock('axios');
const axios = require('axios');

describe('MobileActivityMonitor', () => {
    let monitor;

    beforeEach(() => {
        jest.clearAllMocks();
        monitor = new MobileActivityMonitor({
            logger: mockLogger,
            webhookUrl: 'http://test.local/api/v1/whatsapp/webhook',
            activityTimeoutMs: 60000,
        });
    });

    afterEach(() => {
        monitor.destroy();
    });

    describe('trackActivity', () => {
        it('should track mobile activity for a session', async () => {
            axios.post.mockResolvedValue({ data: { success: true } });

            const result = await monitor.trackActivity(
                'session_123',
                'android',
                'msg_abc',
                1
            );

            expect(result.success).toBe(true);
            expect(result.data.tracked).toBe(true);
            expect(result.data.webhookSent).toBe(true);
        });

        it('should increment message count on repeated activity', async () => {
            axios.post.mockResolvedValue({ data: { success: true } });

            await monitor.trackActivity('session_123', 'android', 'msg_1', 1);
            await monitor.trackActivity('session_123', 'android', 'msg_2', 1);
            await monitor.trackActivity('session_123', 'android', 'msg_3', 1);

            const data = monitor.getActivityData('session_123');
            expect(data.messageCount).toBe(3);
        });

        it('should handle webhook failure gracefully', async () => {
            axios.post.mockRejectedValue(new Error('Network error'));

            const result = await monitor.trackActivity(
                'session_123',
                'android',
                'msg_abc',
                1
            );

            expect(result.success).toBe(true);
            expect(result.data.tracked).toBe(true);
            expect(result.data.webhookSent).toBe(false);
        });

        it('should ignore web device type', async () => {
            const result = await monitor.trackActivity(
                'session_123',
                'web',
                'msg_abc',
                1
            );

            expect(result.success).toBe(true);
            expect(result.data.tracked).toBe(false);
            expect(axios.post).not.toHaveBeenCalled();
        });
    });

    describe('isSessionActive', () => {
        it('should return true for recent activity', async () => {
            axios.post.mockResolvedValue({ data: { success: true } });
            await monitor.trackActivity('session_123', 'android', 'msg_1', 1);

            const isActive = monitor.isSessionActive('session_123', 30);
            expect(isActive).toBe(true);
        });

        it('should return false for old activity', async () => {
            axios.post.mockResolvedValue({ data: { success: true } });
            await monitor.trackActivity('session_123', 'android', 'msg_1', 1);

            // Simulate time passing by manipulating internal state
            const activities = monitor._getActivitiesMap();
            if (activities.has('session_123')) {
                const data = activities.get('session_123');
                data.lastActivity = new Date(Date.now() - 60000); // 60 seconds ago
            }

            const isActive = monitor.isSessionActive('session_123', 30);
            expect(isActive).toBe(false);
        });

        it('should return false for unknown session', () => {
            const isActive = monitor.isSessionActive('unknown_session', 30);
            expect(isActive).toBe(false);
        });
    });

    describe('getSecondsSinceLastActivity', () => {
        it('should return correct seconds since activity', async () => {
            axios.post.mockResolvedValue({ data: { success: true } });
            await monitor.trackActivity('session_123', 'android', 'msg_1', 1);

            const seconds = monitor.getSecondsSinceLastActivity('session_123');
            expect(seconds).toBeLessThan(2);
            expect(seconds).toBeGreaterThanOrEqual(0);
        });

        it('should return null for unknown session', () => {
            const seconds = monitor.getSecondsSinceLastActivity('unknown');
            expect(seconds).toBeNull();
        });
    });

    describe('getStats', () => {
        it('should return correct statistics', async () => {
            axios.post.mockResolvedValue({ data: { success: true } });

            await monitor.trackActivity('session_1', 'android', 'msg_1', 1);
            await monitor.trackActivity('session_2', 'ios', 'msg_2', 1);

            const stats = monitor.getStats();
            expect(stats.totalSessions).toBe(2);
            expect(stats.activeSessions).toBe(2);
            expect(stats.inactiveSessions).toBe(0);
        });
    });

    describe('cleanup', () => {
        it('should remove stale activity data', async () => {
            axios.post.mockResolvedValue({ data: { success: true } });
            await monitor.trackActivity('session_123', 'android', 'msg_1', 1);

            // Simulate old activity
            const activities = monitor._getActivitiesMap();
            if (activities.has('session_123')) {
                const data = activities.get('session_123');
                data.lastActivity = new Date(Date.now() - 120000); // 2 minutes ago
            }

            monitor._cleanup();

            expect(monitor.getActivityData('session_123')).toBeNull();
        });
    });
});
```

---

## 3. Integration Tests

### 3.1 HandleMobileActivityJobTest

```php
<?php

namespace Tests\Integration\Jobs;

use App\Jobs\HandleMobileActivityJob;
use App\Models\Campaign;
use App\Models\Workspace;
use App\Models\WhatsappAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HandleMobileActivityJobTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected WhatsappAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->workspace = Workspace::factory()->create();
        $this->account = WhatsappAccount::factory()->create([
            'workspace_id' => $this->workspace->id,
            'session_id' => 'test_session_123',
            'tier' => 2,
        ]);
    }

    /** @test */
    public function it_pauses_campaigns_and_schedules_resume(): void
    {
        Queue::fake();

        // Create ongoing campaigns
        $campaigns = Campaign::factory()
            ->count(2)
            ->for($this->workspace)
            ->ongoing()
            ->create(['session_id' => 'test_session_123']);

        // Dispatch job
        $job = new HandleMobileActivityJob(
            $this->workspace->id,
            'test_session_123',
            'android'
        );
        $job->handle();

        // Assert campaigns are paused
        foreach ($campaigns as $campaign) {
            $campaign->refresh();
            $this->assertEquals(Campaign::STATUS_PAUSED_MOBILE, $campaign->status);
        }

        // Assert AutoResumeCampaignJob was dispatched for each campaign
        Queue::assertPushed(\App\Jobs\AutoResumeCampaignJob::class, 2);
    }

    /** @test */
    public function it_does_not_process_web_activity(): void
    {
        Queue::fake();

        $campaign = Campaign::factory()
            ->for($this->workspace)
            ->ongoing()
            ->create(['session_id' => 'test_session_123']);

        // Dispatch with web device type
        $job = new HandleMobileActivityJob(
            $this->workspace->id,
            'test_session_123',
            'web'
        );
        $job->handle();

        // Campaign should NOT be paused
        $campaign->refresh();
        $this->assertNotEquals(Campaign::STATUS_PAUSED_MOBILE, $campaign->status);

        // No resume job should be dispatched
        Queue::assertNotPushed(\App\Jobs\AutoResumeCampaignJob::class);
    }
}
```

---

### 3.2 AutoResumeCampaignJobTest

```php
<?php

namespace Tests\Integration\Jobs;

use App\Jobs\AutoResumeCampaignJob;
use App\Models\Campaign;
use App\Models\Workspace;
use App\Services\Campaign\CampaignConflictResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Mockery;

class AutoResumeCampaignJobTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->workspace = Workspace::factory()->create();
        $this->campaign = Campaign::factory()
            ->for($this->workspace)
            ->create([
                'status' => Campaign::STATUS_PAUSED_MOBILE,
                'paused_at' => now()->subSeconds(30),
                'session_id' => 'test_session_123',
            ]);
    }

    /** @test */
    public function it_resumes_campaign_when_no_mobile_activity(): void
    {
        // Mock resolver to return no activity
        $resolver = Mockery::mock(CampaignConflictResolver::class);
        $resolver->shouldReceive('queryLastMobileActivity')
            ->andReturn(null);
        $resolver->shouldReceive('shouldResume')
            ->andReturn(true);
        $resolver->shouldReceive('resumeCampaign')
            ->once()
            ->with($this->campaign->id);

        $this->app->instance(CampaignConflictResolver::class, $resolver);

        $job = new AutoResumeCampaignJob(
            $this->workspace->id,
            $this->campaign->id,
            'test_session_123',
            1
        );
        $job->handle();

        // Verify resume was called
        $resolver->shouldHaveReceived('resumeCampaign');
    }

    /** @test */
    public function it_reschedules_when_still_active(): void
    {
        Queue::fake();

        // Mock resolver to return recent activity
        $resolver = Mockery::mock(CampaignConflictResolver::class);
        $resolver->shouldReceive('queryLastMobileActivity')
            ->andReturn(new \DateTime('-5 seconds'));
        $resolver->shouldReceive('shouldResume')
            ->andReturn(false);
        $resolver->shouldReceive('getTierCooldown')
            ->andReturn(45);

        $this->app->instance(CampaignConflictResolver::class, $resolver);

        $job = new AutoResumeCampaignJob(
            $this->workspace->id,
            $this->campaign->id,
            'test_session_123',
            1
        );
        $job->handle();

        // Should reschedule with next attempt
        Queue::assertPushed(AutoResumeCampaignJob::class, function ($job) {
            return $job->attemptNumber === 2;
        });
    }

    /** @test */
    public function it_force_resumes_after_max_attempts(): void
    {
        $resolver = Mockery::mock(CampaignConflictResolver::class);
        $resolver->shouldReceive('queryLastMobileActivity')
            ->andReturn(new \DateTime('-5 seconds'));
        $resolver->shouldReceive('shouldResume')
            ->andReturn(false);
        $resolver->shouldReceive('resumeCampaign')
            ->once()
            ->with($this->campaign->id);

        $this->app->instance(CampaignConflictResolver::class, $resolver);

        // Max attempts reached
        $job = new AutoResumeCampaignJob(
            $this->workspace->id,
            $this->campaign->id,
            'test_session_123',
            5 // Max attempt
        );
        $job->handle();

        // Should force resume
        $resolver->shouldHaveReceived('resumeCampaign');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

---

## 4. Feature Tests

### 4.1 WebhookEndpointTest

```php
<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MobileActivityWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** @test */
    public function it_accepts_mobile_activity_webhook(): void
    {
        $workspace = Workspace::factory()->create();

        $response = $this->postJson('/api/v1/whatsapp/webhook', [
            'event' => 'mobile_activity_detected',
            'session_id' => 'test_session_123',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'device_type' => 'android',
                'message_id' => 'msg_123',
                'workspace_id' => $workspace->id,
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        Queue::assertPushed(\App\Jobs\HandleMobileActivityJob::class);
    }

    /** @test */
    public function it_rejects_invalid_payload(): void
    {
        $response = $this->postJson('/api/v1/whatsapp/webhook', [
            'event' => 'mobile_activity_detected',
            // Missing required fields
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        Queue::assertNotPushed(\App\Jobs\HandleMobileActivityJob::class);
    }

    /** @test */
    public function it_ignores_unknown_events(): void
    {
        $response = $this->postJson('/api/v1/whatsapp/webhook', [
            'event' => 'unknown_event',
            'session_id' => 'test_session_123',
        ]);

        $response->assertStatus(200);
        Queue::assertNotPushed(\App\Jobs\HandleMobileActivityJob::class);
    }
}
```

---

## 5. E2E Tests

### 5.1 Complete Flow Test

```php
<?php

namespace Tests\E2E;

use App\Jobs\AutoResumeCampaignJob;
use App\Jobs\HandleMobileActivityJob;
use App\Models\Campaign;
use App\Models\Workspace;
use App\Models\WhatsappAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MobileConflictE2ETest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function complete_mobile_conflict_flow(): void
    {
        Queue::fake();

        // Setup
        $workspace = Workspace::factory()->create();
        $account = WhatsappAccount::factory()->create([
            'workspace_id' => $workspace->id,
            'session_id' => 'e2e_session',
            'tier' => 3,
        ]);
        
        $campaign = Campaign::factory()
            ->for($workspace)
            ->ongoing()
            ->create([
                'session_id' => 'e2e_session',
                'name' => 'E2E Test Campaign',
            ]);

        // Step 1: Receive webhook from Node.js
        $this->postJson('/api/v1/whatsapp/webhook', [
            'event' => 'mobile_activity_detected',
            'session_id' => 'e2e_session',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'device_type' => 'android',
                'message_id' => 'e2e_msg_123',
                'workspace_id' => $workspace->id,
            ],
        ])->assertStatus(200);

        // Step 2: Verify job was dispatched
        Queue::assertPushed(HandleMobileActivityJob::class);

        // Step 3: Process the job manually
        $job = new HandleMobileActivityJob(
            $workspace->id,
            'e2e_session',
            'android'
        );
        $job->handle();

        // Step 4: Verify campaign is paused
        $campaign->refresh();
        $this->assertEquals(Campaign::STATUS_PAUSED_MOBILE, $campaign->status);
        $this->assertNotNull($campaign->paused_at);

        // Step 5: Verify resume job was scheduled
        Queue::assertPushed(AutoResumeCampaignJob::class);
    }
}
```

---

## 6. Test Data Factories

### 6.1 CampaignFactory Extensions

```php
// database/factories/CampaignFactory.php

public function ongoing(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => 'ongoing',
    ]);
}

public function pausedForMobile(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => Campaign::STATUS_PAUSED_MOBILE,
        'paused_at' => now()->subSeconds(30),
    ]);
}
```

---

## 7. Running Tests

### 7.1 PHP Tests

```bash
# Run all mobile conflict tests
php artisan test --filter="MobileConflict"

# Run specific test class
php artisan test --filter="CampaignConflictResolverTest"

# Run with coverage
php artisan test --filter="MobileConflict" --coverage
```

### 7.2 JavaScript Tests

```bash
# Run all Node.js tests
cd whatsapp-service && npm test

# Run specific test file
npm test -- MobileActivityMonitor.test.js

# Run with coverage
npm test -- --coverage
```

---

## 8. Test Coverage Requirements

| Component | Minimum Coverage |
|-----------|-----------------|
| CampaignConflictResolver | 80% |
| HandleMobileActivityJob | 80% |
| AutoResumeCampaignJob | 80% |
| MobileActivityMonitor | 75% |
| Webhook Controller | 70% |
| Campaign Model Methods | 70% |

---

## 9. Manual Testing Checklist

### 9.1 Happy Path

- [ ] Start campaign via web
- [ ] Send message from mobile WhatsApp
- [ ] Verify campaign pauses immediately
- [ ] Wait for tier cooldown
- [ ] Verify campaign resumes automatically

### 9.2 Edge Cases

- [ ] Multiple campaigns running simultaneously
- [ ] Campaign already paused (should not re-pause)
- [ ] Web messages (should not trigger pause)
- [ ] Session not found (graceful handling)
- [ ] Webhook timeout (retry mechanism)

### 9.3 Stress Testing

- [ ] 100 concurrent mobile messages
- [ ] 50 campaigns per session
- [ ] Rapid pause/resume cycles

---

**Document Version:** 1.1  
**Last Updated:** November 29, 2025
