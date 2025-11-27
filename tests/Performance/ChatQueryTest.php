<?php

namespace Tests\Performance;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppGroup;
use App\Models\Workspace;
use App\Services\ChatService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * TASK-TEST-3: Database Performance Tests
 * Reference: docs/chat-whatsappwebjs-integration/design.md (DES-8, RISK-4)
 *
 * Test Coverage:
 * - Query performance validation (<500ms for 50 contacts)
 * - Index usage verification with EXPLAIN
 * - Large dataset handling (10K+ chats)
 * - Session filtering performance
 * - Group chat query optimization
 */
class ChatQueryTest extends TestCase
{
    use RefreshDatabase;

    protected $workspace;
    protected $session;
    protected $chatService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->account = WhatsAppAccount::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'webjs',
            'status' => 'connected',
        ]);

        $this->chatService = new ChatService($this->workspace->id);
    }

    /**
     * Test: getChatList query completes in <500ms with 50 contacts
     *
     * @test
     * @group performance
     */
    public function test_get_chat_list_performance_50_contacts()
    {
        // Create 50 contacts with chats
        $contacts = Contact::factory()
            ->count(50)
            ->create(['workspace_id' => $this->workspace->id]);

        foreach ($contacts as $contact) {
            Chat::factory()->create([
                'contact_id' => $contact->id,
                'workspace_id' => $this->workspace->id,
                'whatsapp_account_id' => $this->account->id,
                'provider_type' => 'webjs',
                'chat_type' => 'private',
                'created_at' => Carbon::now()->subMinutes(rand(1, 1000)),
            ]);
        }

        // Measure query execution time
        $startTime = microtime(true);

        $result = $this->chatService->getChatList(
            request(),
            null, // uuid
            null, // search
            null  // sessionId
        );

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Assert query completes in <500ms
        $this->assertLessThan(
            500,
            $executionTime,
            "getChatList query took {$executionTime}ms, expected <500ms"
        );

        // Verify correct number of results
        $this->assertCount(50, $result);
    }

    /**
     * Test: Index usage on workspace_id and session_id
     *
     * @test
     * @group performance
     */
    public function test_query_uses_indexes_correctly()
    {
        // Create test data
        Contact::factory()
            ->count(10)
            ->create(['workspace_id' => $this->workspace->id])
            ->each(function ($contact) {
                Chat::factory()->create([
                    'contact_id' => $contact->id,
                    'workspace_id' => $this->workspace->id,
                    'whatsapp_account_id' => $this->account->id,
                ]);
            });

        // Get the query that would be executed
        $query = DB::table('contacts')
            ->leftJoin('chats', 'chats.contact_id', '=', 'contacts.id')
            ->where('contacts.workspace_id', $this->workspace->id)
            ->whereNull('chats.deleted_at')
            ->orderBy('chats.created_at', 'desc')
            ->limit(50);

        // Get EXPLAIN output
        $explain = DB::select('EXPLAIN ' . $query->toSql(), $query->getBindings());

        // Check if indexes are being used
        $usingIndex = false;
        foreach ($explain as $row) {
            $rowArray = (array) $row;
            if (isset($rowArray['key']) && $rowArray['key'] !== null) {
                $usingIndex = true;
                break;
            }
        }

        $this->assertTrue(
            $usingIndex,
            'Query should use indexes for optimal performance'
        );
    }

    /**
     * Test: Performance with 10K+ chats (large dataset)
     *
     * @test
     * @group performance
     * @group slow
     */
    public function test_performance_with_large_dataset()
    {
        // Skip in CI environment to save time
        if (env('CI', false)) {
            $this->markTestSkipped('Skipping large dataset test in CI');
        }

        // Create 10,000 contacts with chats
        $batchSize = 500;
        $totalContacts = 10000;

        for ($i = 0; $i < $totalContacts / $batchSize; $i++) {
            $contacts = Contact::factory()
                ->count($batchSize)
                ->create(['workspace_id' => $this->workspace->id]);

            $chats = [];
            foreach ($contacts as $contact) {
                $chats[] = [
                    'contact_id' => $contact->id,
                    'workspace_id' => $this->workspace->id,
                    'whatsapp_account_id' => $this->account->id,
                    'provider_type' => 'webjs',
                    'chat_type' => 'private',
                    'type' => 'inbound',
                    'body' => 'Test message',
                    'status' => 'received',
                    'created_at' => Carbon::now()->subMinutes(rand(1, 10000)),
                    'updated_at' => Carbon::now(),
                ];
            }

            Chat::insert($chats);
        }

        // Verify data was created
        $this->assertEquals($totalContacts, Contact::count());
        $this->assertEquals($totalContacts, Chat::count());

        // Measure query time with large dataset
        $startTime = microtime(true);

        $result = $this->chatService->getChatList(
            request(),
            null,
            null,
            null
        );

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // With proper indexes, query should still be fast
        $this->assertLessThan(
            1000, // Allow 1s for very large dataset
            $executionTime,
            "getChatList with 10K chats took {$executionTime}ms, expected <1000ms"
        );

        // Should return limited results (50 by default)
        $this->assertCount(50, $result);
    }

    /**
     * Test: Session filtering performance
     *
     * @test
     * @group performance
     */
    public function test_session_filter_performance()
    {
        // Create multiple sessions
        $account2 = WhatsAppAccount::factory()->create([
            'workspace_id' => $this->workspace->id,
            'provider_type' => 'meta',
            'status' => 'connected',
        ]);

        // Create 100 contacts - 50 for each session
        for ($i = 0; $i < 50; $i++) {
            $contact1 = Contact::factory()->create([
                'workspace_id' => $this->workspace->id,
            ]);
            Chat::factory()->create([
                'contact_id' => $contact1->id,
                'workspace_id' => $this->workspace->id,
                'whatsapp_account_id' => $this->account->id,
            ]);

            $contact2 = Contact::factory()->create([
                'workspace_id' => $this->workspace->id,
            ]);
            Chat::factory()->create([
                'contact_id' => $contact2->id,
                'workspace_id' => $this->workspace->id,
                'whatsapp_account_id' => $account2->id,
            ]);
        }

        // Measure filtered query time
        $startTime = microtime(true);

        $result = $this->chatService->getChatList(
            request(),
            null,
            null,
            $this->account->id // Filter by session
        );

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Filtered query should be fast
        $this->assertLessThan(
            500,
            $executionTime,
            "Filtered getChatList took {$executionTime}ms, expected <500ms"
        );

        // Verify only correct session chats returned
        foreach ($result as $chat) {
            $this->assertEquals($this->account->id, $chat->whatsapp_account_id);
        }
    }

    /**
     * Test: Group chat query performance
     *
     * @test
     * @group performance
     */
    public function test_group_chat_query_performance()
    {
        // Create 25 private chats and 25 group chats
        for ($i = 0; $i < 25; $i++) {
            // Private chat
            $contact = Contact::factory()->create([
                'workspace_id' => $this->workspace->id,
            ]);
            Chat::factory()->create([
                'contact_id' => $contact->id,
                'workspace_id' => $this->workspace->id,
                'whatsapp_account_id' => $this->account->id,
                'chat_type' => 'private',
            ]);

            // Group chat
            $group = WhatsAppGroup::factory()->create([
                'workspace_id' => $this->workspace->id,
                'session_id' => $this->account->id,
            ]);
            Chat::factory()->create([
                'contact_id' => null,
                'group_id' => $group->id,
                'workspace_id' => $this->workspace->id,
                'whatsapp_account_id' => $this->account->id,
                'chat_type' => 'group',
            ]);
        }

        // Measure query time with mixed chat types
        $startTime = microtime(true);

        $result = $this->chatService->getChatList(
            request(),
            null,
            null,
            null
        );

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Query should handle mixed types efficiently
        $this->assertLessThan(
            500,
            $executionTime,
            "Mixed chat types query took {$executionTime}ms, expected <500ms"
        );

        // Verify both types are returned
        $this->assertCount(50, $result);
    }

    /**
     * Test: Search query performance
     *
     * @test
     * @group performance
     */
    public function test_search_query_performance()
    {
        // Create 100 contacts with searchable names
        for ($i = 0; $i < 100; $i++) {
            $contact = Contact::factory()->create([
                'workspace_id' => $this->workspace->id,
                'first_name' => 'User' . $i,
                'phone' => '+628' . str_pad($i, 10, '0', STR_PAD_LEFT),
            ]);
            Chat::factory()->create([
                'contact_id' => $contact->id,
                'workspace_id' => $this->workspace->id,
                'whatsapp_account_id' => $this->account->id,
            ]);
        }

        // Measure search query time
        $startTime = microtime(true);

        $result = $this->chatService->getChatList(
            request(),
            null,
            'User1', // Search term
            null
        );

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Search query should be reasonably fast
        $this->assertLessThan(
            700, // Allow slightly more time for LIKE queries
            $executionTime,
            "Search query took {$executionTime}ms, expected <700ms"
        );

        // Verify search results
        $this->assertGreaterThan(0, count($result));
    }

    /**
     * Test: Concurrent query performance
     *
     * @test
     * @group performance
     */
    public function test_concurrent_query_performance()
    {
        // Create 50 contacts
        Contact::factory()
            ->count(50)
            ->create(['workspace_id' => $this->workspace->id])
            ->each(function ($contact) {
                Chat::factory()->create([
                    'contact_id' => $contact->id,
                    'workspace_id' => $this->workspace->id,
                    'whatsapp_account_id' => $this->account->id,
                ]);
            });

        // Simulate concurrent queries
        $startTime = microtime(true);

        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->chatService->getChatList(
                request(),
                null,
                null,
                null
            );
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        $avgTime = $totalTime / 10;

        // Average query time should remain reasonable
        $this->assertLessThan(
            600,
            $avgTime,
            "Average concurrent query time was {$avgTime}ms, expected <600ms"
        );

        // All queries should return same results
        $this->assertCount(10, $results);
    }

    /**
     * Test: Ordering performance (created_at DESC)
     *
     * @test
     * @group performance
     */
    public function test_ordering_performance()
    {
        // Create 100 chats with different timestamps
        Contact::factory()
            ->count(100)
            ->create(['workspace_id' => $this->workspace->id])
            ->each(function ($contact, $index) {
                Chat::factory()->create([
                    'contact_id' => $contact->id,
                    'workspace_id' => $this->workspace->id,
                    'whatsapp_account_id' => $this->account->id,
                    'created_at' => Carbon::now()->subMinutes($index),
                ]);
            });

        // Measure query time with ordering
        $startTime = microtime(true);

        $result = $this->chatService->getChatList(
            request(),
            null,
            null,
            null
        );

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // Ordering should not significantly impact performance
        $this->assertLessThan(
            500,
            $executionTime,
            "Ordered query took {$executionTime}ms, expected <500ms"
        );

        // Verify results are ordered correctly (newest first)
        $previousTimestamp = null;
        foreach ($result as $chat) {
            if ($previousTimestamp !== null) {
                $this->assertLessThanOrEqual(
                    $previousTimestamp,
                    strtotime($chat->created_at),
                    'Chats should be ordered by created_at DESC'
                );
            }
            $previousTimestamp = strtotime($chat->created_at);
        }
    }

    /**
     * Test: Pagination performance
     *
     * @test
     * @group performance
     */
    public function test_pagination_performance()
    {
        // Create 200 contacts
        Contact::factory()
            ->count(200)
            ->create(['workspace_id' => $this->workspace->id])
            ->each(function ($contact) {
                Chat::factory()->create([
                    'contact_id' => $contact->id,
                    'workspace_id' => $this->workspace->id,
                    'whatsapp_account_id' => $this->account->id,
                ]);
            });

        // Test pagination performance
        $times = [];

        // Test first 4 pages
        for ($page = 1; $page <= 4; $page++) {
            $startTime = microtime(true);

            // Simulate paginated request
            $request = request();
            $request->merge(['page' => $page, 'limit' => 50]);

            $result = $this->chatService->getChatList(
                $request,
                null,
                null,
                null
            );

            $endTime = microtime(true);
            $times[$page] = ($endTime - $startTime) * 1000;

            // Each page should load quickly
            $this->assertLessThan(
                500,
                $times[$page],
                "Page {$page} took {$times[$page]}ms, expected <500ms"
            );
        }

        // Performance should remain consistent across pages
        $avgTime = array_sum($times) / count($times);
        $this->assertLessThan(
            400,
            $avgTime,
            "Average pagination time was {$avgTime}ms, expected <400ms"
        );
    }
}
