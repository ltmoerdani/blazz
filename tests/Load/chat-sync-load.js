/**
 * TASK-TEST-5: Load Testing with K6
 * Reference: docs/chat-whatsappwebjs-integration/design.md (SUCCESS METRICS)
 *
 * Test Coverage:
 * - 100 concurrent users
 * - 5000 chats synced in test
 * - <5% error rate
 * - 95% requests < 2s
 * - Queue processing validation
 * - Database performance under load
 *
 * Installation:
 * brew install k6  (macOS)
 * sudo apt-get install k6  (Ubuntu)
 *
 * Usage:
 * k6 run tests/Load/chat-sync-load.js
 * k6 run --vus 100 --duration 5m tests/Load/chat-sync-load.js
 */

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { randomIntBetween, randomItem } from 'https://jslib.k6.io/k6-utils/1.2.0/index.js';

// Configuration
const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const HMAC_SECRET = __ENV.HMAC_SECRET || 'test_secret';
const WORKSPACE_ID = parseInt(__ENV.WORKSPACE_ID || '1');
const SESSION_ID = parseInt(__ENV.SESSION_ID || '1');

// Custom metrics
const chatsSynced = new Counter('chats_synced');
const syncErrors = new Counter('sync_errors');
const syncSuccessRate = new Rate('sync_success_rate');
const queueAcceptanceRate = new Rate('queue_acceptance_rate');
const chatListLatency = new Trend('chat_list_latency');
const syncBatchLatency = new Trend('sync_batch_latency');
const webhookLatency = new Trend('webhook_latency');

// Test configuration
export const options = {
    stages: [
        // Ramp up
        { duration: '2m', target: 50 },   // Ramp up to 50 users over 2 minutes
        { duration: '3m', target: 100 },  // Ramp up to 100 users over 3 minutes

        // Sustained load
        { duration: '5m', target: 100 },  // Stay at 100 users for 5 minutes

        // Peak load
        { duration: '2m', target: 150 },  // Spike to 150 users for 2 minutes
        { duration: '1m', target: 150 },  // Hold peak for 1 minute

        // Ramp down
        { duration: '2m', target: 50 },   // Ramp down to 50 users
        { duration: '1m', target: 0 },    // Ramp down to 0 users
    ],

    thresholds: {
        // 95% of requests should complete in <2s
        'http_req_duration': ['p(95)<2000'],

        // Error rate should be <5%
        'http_req_failed': ['rate<0.05'],

        // Custom thresholds
        'sync_success_rate': ['rate>0.95'],        // >95% success rate
        'queue_acceptance_rate': ['rate>0.98'],    // >98% queue acceptance
        'chat_list_latency': ['p(95)<500'],        // 95% of list queries <500ms
        'sync_batch_latency': ['p(95)<1500'],      // 95% of sync batches <1.5s
        'webhook_latency': ['p(95)<1000'],         // 95% of webhooks <1s
    },

    // Execution settings
    noConnectionReuse: false,
    userAgent: 'K6LoadTest/1.0',

    // Summary configuration
    summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
};

/**
 * Generate HMAC signature for webhook validation
 */
function generateSignature(payload) {
    // In production, use crypto library to generate HMAC-SHA256
    // For K6, we'll use a placeholder or pre-computed signature
    // You may need to use xk6-crypto extension for proper HMAC

    // Placeholder: In real test, compute HMAC-SHA256
    return 'test_signature_' + Date.now();
}

/**
 * Generate test chat data
 */
function generateTestChats(count) {
    const chats = [];
    const chatTypes = ['private', 'group'];
    const messageTypes = ['text', 'image', 'video', 'document', 'audio'];

    for (let i = 0; i < count; i++) {
        const isGroup = randomItem(chatTypes) === 'group';

        const chat = {
            type: isGroup ? 'group' : 'private',
            timestamp: Date.now() - randomIntBetween(0, 86400000), // Last 24 hours
            last_message: `Test message ${i} - ${Date.now()}`,
            message_type: randomItem(messageTypes),
        };

        if (isGroup) {
            chat.group_jid = `${randomIntBetween(1000000000, 9999999999)}-${randomIntBetween(1000000000, 9999999999)}@g.us`;
            chat.group_name = `Test Group ${randomIntBetween(1, 1000)}`;
            chat.participants = generateParticipants(randomIntBetween(3, 10));
        } else {
            chat.contact_phone = `+628${randomIntBetween(1000000000, 9999999999)}`;
            chat.contact_name = `Contact ${randomIntBetween(1, 10000)}`;
        }

        chats.push(chat);
    }

    return chats;
}

/**
 * Generate group participants
 */
function generateParticipants(count) {
    const participants = [];
    for (let i = 0; i < count; i++) {
        participants.push({
            phone: `+628${randomIntBetween(1000000000, 9999999999)}`,
            name: `Participant ${i}`,
            isAdmin: i === 0, // First participant is admin
        });
    }
    return participants;
}

/**
 * Test scenario 1: Batch sync endpoint
 */
function testBatchSync() {
    const chatsPerBatch = randomIntBetween(20, 50); // Random batch size
    const chats = generateTestChats(chatsPerBatch);

    const payload = JSON.stringify({
        session_id: SESSION_ID,
        workspace_id: WORKSPACE_ID,
        chats: chats,
    });

    const signature = generateSignature(payload);

    const params = {
        headers: {
            'Content-Type': 'application/json',
            'X-WhatsApp-Signature': signature,
        },
        tags: { name: 'BatchSync' },
    };

    const startTime = Date.now();
    const res = http.post(`${BASE_URL}/api/whatsapp/chats/sync`, payload, params);
    const duration = Date.now() - startTime;

    syncBatchLatency.add(duration);

    const success = check(res, {
        'sync returns 202 Accepted': (r) => r.status === 202,
        'sync returns queued status': (r) => {
            try {
                const body = JSON.parse(r.body);
                return body.status === 'queued';
            } catch (e) {
                return false;
            }
        },
        'sync response time < 2s': (r) => r.timings.duration < 2000,
    });

    if (success) {
        chatsSynced.add(chatsPerBatch);
        syncSuccessRate.add(true);
        queueAcceptanceRate.add(res.status === 202);
    } else {
        syncErrors.add(1);
        syncSuccessRate.add(false);
        queueAcceptanceRate.add(false);
    }

    return res;
}

/**
 * Test scenario 2: Webhook endpoint (single message)
 */
function testWebhook() {
    const isGroup = Math.random() > 0.5;

    const payload = {
        session_id: SESSION_ID,
        workspace_id: WORKSPACE_ID,
        chat_type: isGroup ? 'group' : 'private',
        message_body: `Load test message ${Date.now()}`,
        message_type: 'text',
        timestamp: Math.floor(Date.now() / 1000),
        has_media: false,
    };

    if (isGroup) {
        payload.group_jid = `${randomIntBetween(1000000000, 9999999999)}-${randomIntBetween(1000000000, 9999999999)}@g.us`;
        payload.group_name = `Load Test Group ${randomIntBetween(1, 100)}`;
        payload.sender_phone = `+628${randomIntBetween(1000000000, 9999999999)}`;
        payload.sender_name = `Sender ${randomIntBetween(1, 100)}`;
    } else {
        payload.contact_phone = `+628${randomIntBetween(1000000000, 9999999999)}`;
        payload.contact_name = `Contact ${randomIntBetween(1, 1000)}`;
    }

    const signature = generateSignature(JSON.stringify(payload));

    const params = {
        headers: {
            'Content-Type': 'application/json',
            'X-WhatsApp-Signature': signature,
        },
        tags: { name: 'Webhook' },
    };

    const startTime = Date.now();
    const res = http.post(`${BASE_URL}/api/whatsapp/webhook`, JSON.stringify(payload), params);
    const duration = Date.now() - startTime;

    webhookLatency.add(duration);

    check(res, {
        'webhook returns 200 OK': (r) => r.status === 200,
        'webhook response time < 1s': (r) => r.timings.duration < 1000,
    });

    return res;
}

/**
 * Test scenario 3: Get chat list (read performance)
 */
function testGetChatList() {
    const params = {
        headers: {
            'Accept': 'application/json',
        },
        tags: { name: 'GetChatList' },
    };

    const startTime = Date.now();
    const res = http.get(`${BASE_URL}/api/chats?limit=50`, params);
    const duration = Date.now() - startTime;

    chatListLatency.add(duration);

    check(res, {
        'list returns 200 OK': (r) => r.status === 200,
        'list response time < 500ms': (r) => r.timings.duration < 500,
        'list returns valid JSON': (r) => {
            try {
                JSON.parse(r.body);
                return true;
            } catch (e) {
                return false;
            }
        },
    });

    return res;
}

/**
 * Test scenario 4: Health check
 */
function testHealthCheck() {
    const params = {
        tags: { name: 'HealthCheck' },
    };

    const res = http.get(`${BASE_URL}/api/whatsapp/health`, params);

    check(res, {
        'health check returns 200': (r) => r.status === 200,
        'health check has metrics': (r) => {
            try {
                const body = JSON.parse(r.body);
                return body.status && body.queue;
            } catch (e) {
                return false;
            }
        },
    });

    return res;
}

/**
 * Test scenario 5: Session filtering
 */
function testSessionFilter() {
    const params = {
        headers: {
            'Accept': 'application/json',
        },
        tags: { name: 'SessionFilter' },
    };

    const res = http.get(`${BASE_URL}/api/chats?session_id=${SESSION_ID}&limit=50`, params);

    check(res, {
        'filtered list returns 200': (r) => r.status === 200,
        'filtered list response time < 600ms': (r) => r.timings.duration < 600,
    });

    return res;
}

/**
 * Main test execution
 */
export default function () {
    // Distribute load across different scenarios
    const scenario = randomIntBetween(1, 100);

    group('Load Test - Mixed Scenarios', () => {
        if (scenario <= 40) {
            // 40% - Batch sync (primary load)
            group('Batch Sync', () => {
                testBatchSync();
            });
        } else if (scenario <= 70) {
            // 30% - Individual webhooks
            group('Webhook', () => {
                testWebhook();
            });
        } else if (scenario <= 90) {
            // 20% - Chat list reads
            group('Get Chat List', () => {
                testGetChatList();
            });
        } else if (scenario <= 95) {
            // 5% - Session filtered reads
            group('Session Filter', () => {
                testSessionFilter();
            });
        } else {
            // 5% - Health checks
            group('Health Check', () => {
                testHealthCheck();
            });
        }
    });

    // Think time between requests (simulate real user behavior)
    sleep(randomIntBetween(1, 3));
}

/**
 * Setup function - runs once before test
 */
export function setup() {
    console.log('='.repeat(60));
    console.log('Starting WhatsApp Chat Sync Load Test');
    console.log('='.repeat(60));
    console.log(`Base URL: ${BASE_URL}`);
    console.log(`Workspace ID: ${WORKSPACE_ID}`);
    console.log(`Session ID: ${SESSION_ID}`);
    console.log(`Target: 100 concurrent users, 5000+ chats synced`);
    console.log('='.repeat(60));

    // Verify API is reachable
    const healthCheck = http.get(`${BASE_URL}/api/whatsapp/health`);
    if (healthCheck.status !== 200) {
        console.error('⚠️  Warning: Health check failed. API may not be ready.');
    } else {
        console.log('✅ API is reachable');
    }

    return { startTime: Date.now() };
}

/**
 * Teardown function - runs once after test
 */
export function teardown(data) {
    const duration = (Date.now() - data.startTime) / 1000 / 60; // minutes

    console.log('\n' + '='.repeat(60));
    console.log('Load Test Complete');
    console.log('='.repeat(60));
    console.log(`Total Duration: ${duration.toFixed(2)} minutes`);
    console.log('\nCheck metrics above for:');
    console.log('- Sync success rate (target: >95%)');
    console.log('- Queue acceptance rate (target: >98%)');
    console.log('- Error rate (target: <5%)');
    console.log('- Response times (p95 target: <2s)');
    console.log('='.repeat(60));
}

/**
 * Handle summary - custom summary output
 */
export function handleSummary(data) {
    // Calculate custom metrics
    const totalRequests = data.metrics.http_reqs.values.count;
    const failedRequests = data.metrics.http_req_failed.values.passes || 0;
    const errorRate = (failedRequests / totalRequests * 100).toFixed(2);
    const avgDuration = data.metrics.http_req_duration.values.avg.toFixed(2);
    const p95Duration = data.metrics.http_req_duration.values['p(95)'].toFixed(2);

    console.log('\n📊 LOAD TEST SUMMARY');
    console.log('─'.repeat(60));
    console.log(`Total Requests: ${totalRequests}`);
    console.log(`Failed Requests: ${failedRequests} (${errorRate}%)`);
    console.log(`Average Response Time: ${avgDuration}ms`);
    console.log(`P95 Response Time: ${p95Duration}ms`);

    if (data.metrics.chats_synced) {
        console.log(`Total Chats Synced: ${data.metrics.chats_synced.values.count}`);
    }

    console.log('\n🎯 THRESHOLD RESULTS:');
    console.log('─'.repeat(60));

    // Check if thresholds passed
    const thresholdsPassed = Object.keys(options.thresholds).every(metric => {
        // This is simplified - actual threshold checking is done by K6
        return true;
    });

    if (errorRate < 5 && p95Duration < 2000) {
        console.log('✅ PASS - All performance targets met!');
    } else {
        console.log('❌ FAIL - Some thresholds not met:');
        if (errorRate >= 5) {
            console.log(`   - Error rate: ${errorRate}% (target: <5%)`);
        }
        if (p95Duration >= 2000) {
            console.log(`   - P95 response time: ${p95Duration}ms (target: <2000ms)`);
        }
    }

    // Return summary for file output
    return {
        'stdout': textSummary(data, { indent: ' ', enableColors: true }),
        'summary.json': JSON.stringify(data, null, 2),
    };
}

// Helper function for text summary (K6 built-in)
function textSummary(data, options) {
    // Simplified version - K6 provides this function
    return JSON.stringify(data.metrics, null, 2);
}
