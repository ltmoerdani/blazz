# Campaign Message Tracking Implementation Guide
**Date:** November 20, 2025  
**Version:** 1.0  
**Status:** Ready for Implementation  
**Estimated Time:** 4-8 hours

---

## 📑 Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Phase 1: Backend Synchronization](#phase-1-backend-synchronization)
4. [Phase 2: Frontend Real-Time Updates](#phase-2-frontend-real-time-updates)
5. [Phase 3: Testing & Validation](#phase-3-testing--validation)
6. [Troubleshooting](#troubleshooting)
7. [Performance Considerations](#performance-considerations)
8. [Future Enhancements](#future-enhancements)

---

## Overview

### Goal
Implement real-time campaign message tracking (Sent ✓, Delivered ✓✓, Read ✓✓✓, Failed ❌) using existing WhatsApp Web.js infrastructure.

### Current Status
- ✅ 95% infrastructure already exists
- ✅ WhatsApp Web.js `message_ack` event working
- ✅ Database schema ready
- ✅ WebSocket (Reverb + Echo) configured
- ❌ Campaign statistics not synced in real-time
- ❌ Frontend not auto-refreshing

### Architecture
```
WhatsApp → message_ack → Node.js → Webhook → Laravel → UpdateMessageStatusJob
                                                            ↓
                                                    UpdateCampaignStatisticsJob
                                                            ↓
                                                    Broadcast Event
                                                            ↓
                                                    Reverb WebSocket
                                                            ↓
                                                    Frontend Echo Listener
                                                            ↓
                                                    Auto-refresh UI
```

---

## Prerequisites

### Required Knowledge
- Laravel Queue System
- Laravel Broadcasting (Reverb)
- Vue.js 3 Composition API
- Echo.js for WebSocket
- WhatsApp Web.js events

### Check Before Starting

1. **Verify WhatsApp Service Running:**
```bash
curl http://127.0.0.1:3001/health
```

Expected response:
```json
{
    "status": "healthy",
    "uptime": 123.45,
    "sessions": {
        "total": 1,
        "connected": 1
    }
}
```

2. **Verify Reverb Running:**
```bash
ps aux | grep reverb
```

3. **Verify Queue Workers:**
```bash
php artisan queue:work --queue=messaging,campaign-stats
```

4. **Check Database Connectivity:**
```bash
php artisan migrate:status
```

---

## Phase 1: Backend Synchronization

### Task 1.1: Update UpdateMessageStatusJob

**File:** `app/Jobs/UpdateMessageStatusJob.php`  
**Purpose:** Sync campaign_logs when chat status changes

**Implementation:**

```php
<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Models\Contact;
use App\Models\CampaignLog;
use App\Events\MessageStatusUpdated;
use App\Events\MessageDelivered;
use App\Events\MessageRead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class UpdateMessageStatusJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $maxExceptions = 3;

    public function __construct(
        public string $messageId,
        public string $status,
        public ?int $recipientId = null,
        public ?int $ackLevel = null,
        public ?string $eventType = null
    ) {
        $this->onQueue('messaging');
    }

    public function handle(): void
    {
        try {
            $chat = Chat::where('whatsapp_message_id', $this->messageId)->first();

            if (!$chat) {
                Log::warning('Chat not found for status update', [
                    'whatsapp_message_id' => $this->messageId,
                    'status' => $this->status
                ]);
                return;
            }

            // Update chat status and timestamps in database
            $updateData = [
                'message_status' => $this->status,
                'ack_level' => $this->ackLevel,
            ];

            if ($this->status === 'delivered') {
                $updateData['delivered_at'] = now();
            } elseif ($this->status === 'read') {
                $updateData['read_at'] = now();
            }

            $chat->update($updateData);

            // Update contact last activity
            $contact = $chat->contact;
            if ($contact) {
                $contact->update([
                    'last_message_at' => now(),
                    'last_activity' => now()
                ]);
            }

            Log::info('Message status updated successfully', [
                'chat_id' => $chat->id,
                'whatsapp_message_id' => $this->messageId,
                'status' => $this->status,
                'ack_level' => $this->ackLevel
            ]);

            // ✅ NEW: Update campaign_log if this chat is part of a campaign
            $this->updateCampaignLog($chat);

            // Broadcast real-time event based on event type
            switch ($this->eventType) {
                case 'message_delivered':
                    MessageDelivered::dispatch($chat, $this->recipientId ?? $contact->id, $this->messageId);
                    break;

                case 'message_read':
                    MessageRead::dispatch($chat, $this->recipientId ?? $contact->id, $this->messageId);
                    break;

                default:
                    MessageStatusUpdated::dispatch($chat, $this->status, $this->recipientId);
                    break;
            }

        } catch (\Exception $e) {
            Log::error('Failed to update message status', [
                'whatsapp_message_id' => $this->messageId,
                'status' => $this->status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * ✅ NEW METHOD: Update campaign log metadata and trigger statistics update
     */
    private function updateCampaignLog(Chat $chat): void
    {
        try {
            $campaignLog = CampaignLog::where('chat_id', $chat->id)->first();

            if (!$campaignLog) {
                // Not a campaign message, skip
                return;
            }

            Log::info('Updating campaign log status', [
                'campaign_log_id' => $campaignLog->id,
                'campaign_id' => $campaignLog->campaign_id,
                'old_status' => $campaignLog->status,
                'new_message_status' => $this->status,
                'ack_level' => $this->ackLevel
            ]);

            // Update metadata with real-time status tracking
            $metadata = $campaignLog->metadata ? json_decode($campaignLog->metadata, true) : [];
            
            $metadata['message_status'] = $this->status;
            $metadata['ack_level'] = $this->ackLevel;
            $metadata['status_updated_at'] = now()->toISOString();

            if ($this->status === 'delivered') {
                $metadata['delivered_at'] = now()->toISOString();
            } elseif ($this->status === 'read') {
                $metadata['read_at'] = now()->toISOString();
            } elseif ($this->status === 'failed') {
                $metadata['failed_at'] = now()->toISOString();
            }

            $campaignLog->update(['metadata' => json_encode($metadata)]);

            Log::info('Campaign log metadata updated', [
                'campaign_log_id' => $campaignLog->id,
                'metadata' => $metadata
            ]);

            // Trigger campaign statistics recalculation (throttled)
            dispatch(new \App\Jobs\UpdateCampaignStatisticsJob($campaignLog->campaign_id))
                ->onQueue('campaign-stats')
                ->delay(now()->addSeconds(5)); // 5 second delay for batching

        } catch (\Exception $e) {
            Log::error('Failed to update campaign log', [
                'chat_id' => $chat->id,
                'error' => $e->getMessage()
            ]);
            // Don't throw - campaign log update is secondary
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('UpdateMessageStatusJob failed permanently', [
            'message_id' => $this->messageId,
            'status' => $this->status,
            'event_type' => $this->eventType,
            'attempt' => $this->attempts(),
            'exception' => [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ]
        ]);

        try {
            $chat = Chat::where('whatsapp_message_id', $this->messageId)->first();

            if ($chat) {
                $chat->update([
                    'message_status' => 'failed',
                    'retry_count' => ($chat->retry_count ?? 0) + 1,
                    'metadata' => array_merge(
                        $chat->metadata ? json_decode($chat->metadata, true) : [],
                        [
                            'job_error' => $exception->getMessage(),
                            'job_failed_at' => now()->toISOString(),
                            'job_attempts' => $this->attempts()
                        ]
                    )
                ]);
            }
        } catch (\Exception $e) {
            Log::critical('Failed to mark chat as failed', [
                'message_id' => $this->messageId,
                'additional_error' => $e->getMessage()
            ]);
        }
    }

    public function tags(): array
    {
        return ['messaging', 'message-status', "message:{$this->messageId}"];
    }
}
```

**Changes Made:**
- ✅ Added `updateCampaignLog()` method
- ✅ Updates `campaign_logs.metadata` with real-time status
- ✅ Dispatches `UpdateCampaignStatisticsJob` with 5-second delay for batching
- ✅ Non-blocking error handling (campaign update is secondary)

---

### Task 1.2: Create UpdateCampaignStatisticsJob

**File:** `app/Jobs/UpdateCampaignStatisticsJob.php` (NEW FILE)  
**Purpose:** Recalculate campaign statistics and broadcast to frontend

**Implementation:**

```php
<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Events\CampaignStatisticsUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UpdateCampaignStatisticsJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 60;
    public $maxExceptions = 3;

    public function __construct(
        public int $campaignId
    ) {
        $this->onQueue('campaign-stats');
    }

    public function handle(): void
    {
        try {
            // Use cache lock to prevent concurrent updates for same campaign
            $lock = Cache::lock("campaign_stats_update_{$this->campaignId}", 10);

            if (!$lock->get()) {
                Log::info('Campaign stats update already in progress', [
                    'campaign_id' => $this->campaignId
                ]);
                return;
            }

            try {
                $campaign = Campaign::find($this->campaignId);

                if (!$campaign) {
                    Log::warning('Campaign not found for stats update', [
                        'campaign_id' => $this->campaignId
                    ]);
                    return;
                }

                Log::info('Updating campaign statistics', [
                    'campaign_id' => $this->campaignId,
                    'campaign_uuid' => $campaign->uuid,
                    'campaign_name' => $campaign->name
                ]);

                // Get previous counts for comparison
                $previousCounts = [
                    'sent' => $campaign->messages_sent,
                    'delivered' => $campaign->messages_delivered,
                    'read' => $campaign->messages_read,
                    'failed' => $campaign->messages_failed
                ];

                // Update performance counters using optimized aggregation query
                $campaign->updatePerformanceCounters();

                // Refresh model to get updated values
                $campaign->refresh();

                $newCounts = [
                    'sent' => $campaign->messages_sent,
                    'delivered' => $campaign->messages_delivered,
                    'read' => $campaign->messages_read,
                    'failed' => $campaign->messages_failed
                ];

                Log::info('Campaign statistics updated successfully', [
                    'campaign_id' => $this->campaignId,
                    'previous' => $previousCounts,
                    'new' => $newCounts,
                    'changes' => [
                        'sent_delta' => $newCounts['sent'] - $previousCounts['sent'],
                        'delivered_delta' => $newCounts['delivered'] - $previousCounts['delivered'],
                        'read_delta' => $newCounts['read'] - $previousCounts['read'],
                        'failed_delta' => $newCounts['failed'] - $previousCounts['failed']
                    ]
                ]);

                // Broadcast real-time update to frontend
                $statistics = [
                    'total_message_count' => $campaign->contactsCount(),
                    'total_sent_count' => $campaign->messages_sent,
                    'total_delivered_count' => $campaign->messages_delivered,
                    'total_read_count' => $campaign->messages_read,
                    'total_failed_count' => $campaign->messages_failed,
                    'pending_count' => max(0, $campaign->contactsCount() - $campaign->messages_sent - $campaign->messages_failed),
                    'updated_at' => now()->toISOString(),
                    'delivery_rate' => $campaign->messages_sent > 0 
                        ? round(($campaign->messages_delivered / $campaign->messages_sent) * 100, 2) 
                        : 0,
                    'read_rate' => $campaign->messages_delivered > 0 
                        ? round(($campaign->messages_read / $campaign->messages_delivered) * 100, 2) 
                        : 0,
                    'success_rate' => $campaign->contactsCount() > 0 
                        ? round(($campaign->messages_sent / $campaign->contactsCount()) * 100, 2) 
                        : 0
                ];

                broadcast(new CampaignStatisticsUpdated(
                    $campaign->id,
                    $campaign->workspace_id,
                    $campaign->uuid,
                    $statistics
                ));

                Log::info('Campaign statistics broadcasted', [
                    'campaign_id' => $this->campaignId,
                    'statistics' => $statistics
                ]);

            } finally {
                $lock->release();
            }

        } catch (\Exception $e) {
            Log::error('Failed to update campaign statistics', [
                'campaign_id' => $this->campaignId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('UpdateCampaignStatisticsJob failed permanently', [
            'campaign_id' => $this->campaignId,
            'attempt' => $this->attempts(),
            'exception' => [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ]
        ]);

        // Optionally notify administrators
        if ($this->attempts() >= 2) {
            Log::critical('Campaign statistics update failed multiple times', [
                'campaign_id' => $this->campaignId,
                'requires_attention' => true
            ]);
        }
    }

    public function tags(): array
    {
        return ['campaign-stats', "campaign:{$this->campaignId}"];
    }
}
```

**Features:**
- ✅ Cache lock prevents concurrent updates
- ✅ Optimized aggregation query via `updatePerformanceCounters()`
- ✅ Calculates delivery/read/success rates
- ✅ Broadcasts to both workspace and campaign-specific channels
- ✅ Comprehensive logging with deltas
- ✅ Non-blocking with proper error handling

---

### Task 1.3: Create CampaignStatisticsUpdated Event

**File:** `app/Events/CampaignStatisticsUpdated.php` (NEW FILE)  
**Purpose:** Broadcast campaign statistics to frontend via WebSocket

**Implementation:**

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignStatisticsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $campaignId,
        public int $workspaceId,
        public string $campaignUuid,
        public array $statistics
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Workspace-wide channel (all users in workspace)
            new Channel("workspace.{$this->workspaceId}"),
            
            // Campaign-specific channel (users viewing this campaign)
            new Channel("campaign.{$this->campaignUuid}")
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'campaign.statistics.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'campaign_id' => $this->campaignId,
            'campaign_uuid' => $this->campaignUuid,
            'workspace_id' => $this->workspaceId,
            'statistics' => $this->statistics,
            'timestamp' => now()->toISOString()
        ];
    }
}
```

**Broadcasting Channels:**
- `workspace.{workspaceId}` - All users in workspace receive updates
- `campaign.{campaignUuid}` - Users viewing specific campaign

**Event Name:** `campaign.statistics.updated`

**Payload Example:**
```json
{
  "campaign_id": 123,
  "campaign_uuid": "c5ab3c9f-bb93-4d59-b1f5-767fb350623d",
  "workspace_id": 1,
  "statistics": {
    "total_message_count": 1000,
    "total_sent_count": 950,
    "total_delivered_count": 920,
    "total_read_count": 450,
    "total_failed_count": 50,
    "pending_count": 0,
    "delivery_rate": 96.84,
    "read_rate": 48.91,
    "success_rate": 95.0,
    "updated_at": "2025-11-20T10:30:45.000000Z"
  },
  "timestamp": "2025-11-20T10:30:45.123456Z"
}
```

---

### Task 1.4: Update Queue Configuration

**File:** `config/queue.php`

Ensure campaign-stats queue is configured:

```php
'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => false,
    ],
],

'failed' => [
    'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
    'database' => env('DB_CONNECTION', 'mysql'),
    'table' => 'failed_jobs',
],
```

**Start Queue Workers:**

```bash
# Terminal 1: Messaging queue (high priority)
php artisan queue:work --queue=messaging --tries=3 --timeout=60

# Terminal 2: Campaign stats queue (medium priority)
php artisan queue:work --queue=campaign-stats --tries=3 --timeout=120

# Or use supervisor for production
```

---

## Phase 2: Frontend Real-Time Updates

### Task 2.1: Update Campaign View Component

**File:** `resources/js/Pages/User/Campaign/View.vue`

**Full Updated Implementation:**

```vue
<template>
    <AppLayout>
        <div class="p-4 md:p-8 rounded-[5px] h-full overflow-y-auto">
            <div class="flex justify-between capitalize">
                <div>
                    <h2 class="text-xl mb-1">{{ $t('Campaign details') }}</h2>
                    <p class="mb-6 flex items-center text-sm leading-6">
                        <span class="ml-1 mt-1">{{ $t('Ref') }}: {{ props.campaign.uuid }}</span>
                        <!-- Real-time indicator -->
                        <span v-if="isConnected" class="ml-3 flex items-center text-green-600">
                            <span class="relative flex h-2 w-2 mr-1">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                            </span>
                            <span class="text-xs">{{ $t('Live') }}</span>
                        </span>
                    </p>
                </div>
                <div class="space-x-2">
                    <a :href="'/campaigns/export/' + props.campaign.uuid" class="rounded-md bg-secondary px-3 py-2 text-sm text-white shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        {{ $t('Export as CSV') }}
                    </a>

                    <Link href="/campaigns" class="rounded-md bg-primary px-3 py-2 text-sm text-white shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        {{ $t('Back') }}
                    </Link>
                </div>
            </div>

            <div class="md:flex md:space-x-4">
                <div class="md:w-[70%] capitalize">
                    <!-- Statistics Cards with Real-time Updates -->
                    <div class="flex w-[100%] mb-8 rounded-lg">
                        <div class="stat-card w-full rounded-tl-lg rounded-bl-lg text-center bg-white py-8 border" 
                             :class="{ 'stat-card-updating': isUpdating }">
                            <h2 class="text-xl font-semibold">{{ statistics.total_message_count }}</h2>
                            <h4 class="text-sm text-gray-600">{{ $t('Messages') }}</h4>
                        </div>
                        <div class="stat-card w-full text-center bg-white py-8 border hover:bg-blue-50" 
                             :class="{ 'stat-card-updating': isUpdating }">
                            <h2 class="text-xl font-semibold text-blue-600">{{ statistics.total_sent_count }}</h2>
                            <h4 class="text-sm text-gray-600">{{ $t('Sent') }}</h4>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ statistics.success_rate }}% 
                                <span v-if="statistics.pending_count > 0" class="text-orange-500">
                                    ({{ statistics.pending_count }} pending)
                                </span>
                            </div>
                        </div>
                        <div class="stat-card w-full text-center bg-white py-8 border hover:bg-green-50" 
                             :class="{ 'stat-card-updating': isUpdating }">
                            <h2 class="text-xl font-semibold text-green-600">{{ statistics.total_delivered_count }}</h2>
                            <h4 class="text-sm text-gray-600">{{ $t('Delivered') }}</h4>
                            <div class="text-xs text-gray-500 mt-1">{{ statistics.delivery_rate }}%</div>
                        </div>
                        <div class="stat-card w-full bg-white text-center py-8 border hover:bg-indigo-50" 
                             :class="{ 'stat-card-updating': isUpdating }">
                            <h2 class="text-xl font-semibold text-indigo-600">{{ statistics.total_read_count }}</h2>
                            <h4 class="text-sm text-gray-600">{{ $t('Read') }}</h4>
                            <div class="text-xs text-gray-500 mt-1">{{ statistics.read_rate }}%</div>
                        </div>
                        <div class="stat-card w-full rounded-tr-lg rounded-br-lg bg-white text-center py-8 border hover:bg-red-50" 
                             :class="{ 'stat-card-updating': isUpdating }">
                            <h2 class="text-xl font-semibold text-red-600">{{ statistics.total_failed_count }}</h2>
                            <h4 class="text-sm text-gray-600">{{ $t('Failed') }}</h4>
                        </div>
                    </div>

                    <!-- Last Updated Timestamp -->
                    <div v-if="lastUpdated" class="text-xs text-gray-500 mb-4 text-right">
                        {{ $t('Last updated') }}: {{ formatTimestamp(lastUpdated) }}
                    </div>

                    <!-- Table Component-->
                    <CampaignLogTable :rows="props.rows" :filters="props.filters" :uuid="props.campaign.uuid"/>
                </div>
                <div class="md:w-[30%]">
                    <div class="w-full rounded-lg bg-white pt-4 pb-8 border px-4 space-y-1 capitalize">
                        <h2 class="mb-2">{{ $t('Campaign details') }}</h2>
                        <div class="text-sm bg-slate-100 p-3 rounded-lg">
                            <h3>{{ $t('Campaign name') }}</h3>
                            <p>{{ props.campaign?.name }}</p>
                        </div>
                        <div class="text-sm bg-slate-100 p-3 rounded-lg">
                            <h3>{{ $t('Campaign Type') }}</h3>
                            <p>{{ props.campaign?.campaign_type === 'direct' ? $t('Direct Message') : $t('Template-based') }}</p>
                        </div>
                        <div v-if="props.campaign?.template" class="text-sm bg-slate-100 p-3 rounded-lg">
                            <h3>{{ $t('Template') }}</h3>
                            <p>{{ props.campaign?.template?.name }}</p>
                        </div>
                        <div class="text-sm bg-slate-100 p-3 rounded-lg">
                            <h3>{{ $t('Recipients') }}</h3>
                            <p>{{ props.campaign.contact_group_id === '0' ? 'All Contacts' : props.campaign?.contact_group?.name }}</p>
                        </div>
                        <div class="text-sm bg-slate-100 p-3 rounded-lg">
                            <h3>{{ $t('Time scheduled') }}</h3>
                            <p>{{ props.campaign.scheduled_at }}</p>
                        </div>
                    </div>

                    <div class="w-full rounded-lg p-5 mt-5 border chat-bg">
                        <!-- Direct Message Preview -->
                        <div v-if="props.campaign?.campaign_type === 'direct'" class="mr-auto rounded-lg rounded-tl-none my-1 p-1 text-sm bg-white flex flex-col relative speech-bubble-left w-[25em]">
                            <div v-if="props.campaign.header_type && props.campaign.header_type !== 'text'" class="mb-4 bg-[#ccd0d5] flex justify-center py-8 rounded">
                                <img v-if="props.campaign.header_type === 'image'" :src="'/images/image-placeholder.png'">
                                <img v-if="props.campaign.header_type === 'video'" :src="'/images/video-placeholder.png'">
                                <img v-if="props.campaign.header_type === 'document'" :src="'/images/document-placeholder.png'">
                            </div>
                            <h2 v-else-if="props.campaign.header_text" class="text-gray-700 text-sm mb-1 px-2 normal-case whitespace-pre-wrap">{{ props.campaign.header_text }}</h2>
                            <p class="px-2 normal-case whitespace-pre-wrap">{{ props.campaign.body_text }}</p>
                            <div class="text-[#8c8c8c] mt-1 px-2">
                                <span class="text-[13px]">{{ props.campaign.footer_text }}</span>
                                <span class="text-right text-xs leading-none float-right" :class="props.campaign.footer_text ? 'mt-2' : ''">9:15</span>
                            </div>
                        </div>
                        
                        <!-- Template-based Preview -->
                        <WhatsappTemplate v-else :parameters="JSON.parse(props.campaign.metadata)" :placeholder="false" :visible="true"/>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from "./../Layout/App.vue";
import CampaignLogTable from '@/Components/Tables/CampaignLogTable.vue';
import WhatsappTemplate from '@/Components/WhatsappTemplate.vue';
import { Link } from "@inertiajs/vue3";
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps(['campaign', 'rows', 'filters']);
const page = usePage();

// Reactive statistics (will be updated in real-time)
const statistics = ref({
    total_message_count: props.campaign.total_message_count,
    total_sent_count: props.campaign.total_sent_count,
    total_delivered_count: props.campaign.total_delivered_count,
    total_read_count: props.campaign.total_read_count,
    total_failed_count: props.campaign.total_failed_count,
    pending_count: 0,
    delivery_rate: 0,
    read_rate: 0,
    success_rate: 0
});

// WebSocket connection state
const isConnected = ref(false);
const isUpdating = ref(false);
const lastUpdated = ref(null);
let workspaceChannel = null;
let campaignChannel = null;
let updateAnimationTimeout = null;

// Format timestamp for display
const formatTimestamp = (timestamp) => {
    if (!timestamp) return '';
    const date = new Date(timestamp);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000); // seconds

    if (diff < 60) return `${diff} seconds ago`;
    if (diff < 3600) return `${Math.floor(diff / 60)} minutes ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} hours ago`;
    return date.toLocaleString();
};

// Handle statistics update from WebSocket
const handleStatisticsUpdate = (event) => {
    console.log('📨 Campaign statistics update received', event);

    // Only update if it's for this campaign
    if (event.campaign_uuid === props.campaign.uuid) {
        // Trigger update animation
        isUpdating.value = true;
        if (updateAnimationTimeout) {
            clearTimeout(updateAnimationTimeout);
        }
        updateAnimationTimeout = setTimeout(() => {
            isUpdating.value = false;
        }, 500);

        // Update statistics
        statistics.value = {
            total_message_count: event.statistics.total_message_count,
            total_sent_count: event.statistics.total_sent_count,
            total_delivered_count: event.statistics.total_delivered_count,
            total_read_count: event.statistics.total_read_count,
            total_failed_count: event.statistics.total_failed_count,
            pending_count: event.statistics.pending_count || 0,
            delivery_rate: event.statistics.delivery_rate || 0,
            read_rate: event.statistics.read_rate || 0,
            success_rate: event.statistics.success_rate || 0
        };

        lastUpdated.value = event.statistics.updated_at || event.timestamp;

        console.log('✅ Campaign statistics updated in UI', statistics.value);

        // Optional: Show toast notification (if you have a toast library)
        // toast.success('Campaign statistics updated!');
    }
};

onMounted(() => {
    const workspaceId = page.props.auth.workspace.id;
    const campaignUuid = props.campaign.uuid;

    console.log('📊 Subscribing to campaign statistics updates', {
        workspace_id: workspaceId,
        campaign_uuid: campaignUuid
    });

    try {
        // Subscribe to workspace channel for campaign updates
        workspaceChannel = window.Echo.channel(`workspace.${workspaceId}`)
            .listen('.campaign.statistics.updated', handleStatisticsUpdate);

        // Also subscribe to campaign-specific channel
        campaignChannel = window.Echo.channel(`campaign.${campaignUuid}`)
            .listen('.campaign.statistics.updated', handleStatisticsUpdate);

        // Mark as connected
        isConnected.value = true;
        console.log('✅ Successfully subscribed to campaign statistics updates');

    } catch (error) {
        console.error('❌ Failed to subscribe to campaign statistics', error);
        isConnected.value = false;
    }
});

onUnmounted(() => {
    console.log('🔌 Cleaning up campaign statistics subscriptions');

    if (updateAnimationTimeout) {
        clearTimeout(updateAnimationTimeout);
    }

    if (workspaceChannel || campaignChannel) {
        const workspaceId = page.props.auth.workspace.id;
        const campaignUuid = props.campaign.uuid;
        
        try {
            window.Echo.leave(`workspace.${workspaceId}`);
            window.Echo.leave(`campaign.${campaignUuid}`);
            console.log('✅ Successfully unsubscribed from campaign statistics');
        } catch (error) {
            console.error('❌ Error unsubscribing from channels', error);
        }
    }

    isConnected.value = false;
});
</script>

<style scoped>
/* Animation for updating statistics */
.stat-card-updating {
    animation: pulse-scale 0.5s ease-in-out;
}

@keyframes pulse-scale {
    0%, 100% { 
        transform: scale(1); 
    }
    50% { 
        transform: scale(1.05); 
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
}

/* Hover effect for stat cards */
.stat-card {
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

/* Pulsing live indicator */
@keyframes ping {
    75%, 100% {
        transform: scale(2);
        opacity: 0;
    }
}

.animate-ping {
    animation: ping 1s cubic-bezier(0, 0, 0.2, 1) infinite;
}
</style>
```

**Key Features:**
- ✅ Real-time statistics updates via Echo
- ✅ Live connection indicator (green pulsing dot)
- ✅ Update animation on statistics change
- ✅ Percentage calculations (delivery rate, read rate, success rate)
- ✅ Last updated timestamp
- ✅ Pending count display
- ✅ Hover effects on stat cards
- ✅ Proper cleanup on component unmount
- ✅ Comprehensive console logging for debugging

---

## Phase 3: Testing & Validation

### Test 1: Backend Testing

#### **Step 1: Monitor Logs**

Open 4 terminal windows:

```bash
# Terminal 1: WhatsApp Service Logs
tail -f /Applications/MAMP/htdocs/blazz/whatsapp-service/logs/whatsapp-service.log | grep -E "message_ack|message_status"

# Terminal 2: Laravel Logs
tail -f /Applications/MAMP/htdocs/blazz/storage/logs/laravel.log | grep -E "UpdateMessageStatusJob|UpdateCampaignStatisticsJob|CampaignStatisticsUpdated"

# Terminal 3: Queue Worker (Messaging)
php artisan queue:work --queue=messaging --verbose

# Terminal 4: Queue Worker (Campaign Stats)
php artisan queue:work --queue=campaign-stats --verbose
```

#### **Step 2: Send Test Campaign**

1. Navigate to `/campaigns/create`
2. Create test campaign with 5-10 test contacts
3. Send campaign

#### **Step 3: Verify Flow**

**Expected Log Sequence:**

```
WhatsApp Service:
{"level":"debug","message":"Message ACK received","ack":1,"messageId":"..."}
{"level":"info","message":"Webhook sent","event":"message_status_updated"}

Laravel:
[timestamp] Message status updated via WebJS {message_id: "...", status: "pending", ack_level: 1}
[timestamp] UpdateMessageStatusJob dispatched
[timestamp] Chat status updated {chat_id: 123, status: "pending"}
[timestamp] Updating campaign log status {campaign_log_id: 456, campaign_id: 789}
[timestamp] UpdateCampaignStatisticsJob dispatched {campaign_id: 789}
[timestamp] Campaign statistics updated successfully {sent: 5, delivered: 0, read: 0}
[timestamp] CampaignStatisticsUpdated broadcasted
```

#### **Step 4: Verify Database**

```sql
-- Check chats table
SELECT id, whatsapp_message_id, message_status, ack_level, delivered_at, read_at
FROM chats
WHERE id IN (SELECT chat_id FROM campaign_logs WHERE campaign_id = 789)
ORDER BY id DESC
LIMIT 10;

-- Check campaign_logs table
SELECT id, campaign_id, chat_id, status, metadata
FROM campaign_logs
WHERE campaign_id = 789
ORDER BY id DESC
LIMIT 10;

-- Check campaigns table
SELECT id, uuid, name, messages_sent, messages_delivered, messages_read, messages_failed
FROM campaigns
WHERE id = 789;
```

---

### Test 2: Frontend Testing

#### **Step 1: Open Campaign View**

1. Navigate to `/campaigns/{uuid}`
2. Open browser DevTools Console (F12)
3. Check for subscription logs:

```javascript
📊 Subscribing to campaign statistics updates {workspace_id: 1, campaign_uuid: "..."}
✅ Successfully subscribed to campaign statistics updates
```

#### **Step 2: Verify WebSocket Connection**

In Console, check Echo channels:

```javascript
console.log(window.Echo.connector.channels);
// Should show: workspace.1 and campaign.{uuid}
```

#### **Step 3: Trigger Status Update**

Send test message from WhatsApp mobile app, then check:

```javascript
📨 Campaign statistics update received {campaign_uuid: "...", statistics: {...}}
✅ Campaign statistics updated in UI {total_sent_count: 5, ...}
```

#### **Step 4: Verify UI Updates**

- ✅ Statistics numbers update without page refresh
- ✅ Cards animate on update
- ✅ Last updated timestamp shows
- ✅ Percentage calculations correct

---

### Test 3: Stress Testing

#### **Test Large Campaign (1000+ messages)**

```bash
# Create campaign with 1000 contacts
php artisan tinker

>>> $campaign = App\Models\Campaign::find(789);
>>> $campaign->campaignLogs()->count();
// Should return 1000

# Monitor queue processing
php artisan queue:work --queue=messaging,campaign-stats --verbose

# Check statistics update frequency
tail -f storage/logs/laravel.log | grep "Campaign statistics updated"
```

**Expected Behavior:**
- ✅ Statistics update every 5-10 seconds (due to batching)
- ✅ No duplicate updates (cache lock prevents)
- ✅ Frontend updates smoothly without lag
- ✅ Queue workers don't get overwhelmed

---

### Test 4: Edge Cases

#### **Test 1: Multiple Users Viewing Same Campaign**

Open campaign in 2 different browsers:
- ✅ Both receive real-time updates
- ✅ No conflicts or race conditions
- ✅ Statistics stay in sync

#### **Test 2: Network Disconnection**

1. View campaign
2. Disconnect internet for 30 seconds
3. Reconnect
4. Check console:

```javascript
Echo reconnected
Resubscribing to channels...
✅ Successfully subscribed
```

#### **Test 3: Failed Messages**

1. Send campaign to invalid numbers
2. Verify `failed_count` increments
3. Check UI shows failed status

---

## Troubleshooting

### Issue 1: Statistics Not Updating

**Symptoms:**
- Frontend shows static numbers
- No console logs

**Solutions:**

```bash
# 1. Check Reverb running
ps aux | grep reverb

# 2. Restart Reverb
php artisan reverb:restart

# 3. Check Echo configuration
# In browser console:
console.log(window.Echo);

# 4. Verify queue workers running
php artisan queue:work --queue=messaging,campaign-stats
```

---

### Issue 2: Duplicate Statistics Updates

**Symptoms:**
- Multiple broadcasts for same campaign
- Statistics jump erratically

**Solutions:**

```php
// Check cache lock is working
Cache::get("campaign_stats_update_{$campaignId}");

// Increase delay in UpdateMessageStatusJob
dispatch(new UpdateCampaignStatisticsJob($campaignId))
    ->delay(now()->addSeconds(10)); // Increase from 5 to 10 seconds
```

---

### Issue 3: Job Failures

**Symptoms:**
- Jobs in failed_jobs table
- Error logs

**Solutions:**

```bash
# 1. Check failed jobs
php artisan queue:failed

# 2. Retry failed jobs
php artisan queue:retry all

# 3. Check job class exists
php artisan list | grep Update

# 4. Clear cache
php artisan cache:clear
php artisan config:clear
```

---

### Issue 4: Slow Performance

**Symptoms:**
- Statistics update takes > 10 seconds
- Queue backlog grows

**Solutions:**

```php
// Optimize getCounts() query
// Add index to campaign_logs
Schema::table('campaign_logs', function (Blueprint $table) {
    $table->index(['campaign_id', 'status', 'chat_id']);
});

// Increase queue workers
# Run 2-3 workers for campaign-stats
```

---

## Performance Considerations

### Database Optimization

```sql
-- Add composite indexes for faster queries
CREATE INDEX idx_campaign_logs_stats 
ON campaign_logs(campaign_id, status, chat_id);

CREATE INDEX idx_chats_campaign_status 
ON chats(id, message_status, ack_level);

-- Check query performance
EXPLAIN SELECT ... FROM campaign_logs WHERE campaign_id = 789;
```

### Caching Strategy

```php
// Cache campaign statistics for 30 seconds
$statistics = Cache::remember("campaign_{$campaignId}_stats", 30, function() use ($campaign) {
    return $campaign->getStatistics();
});
```

### Rate Limiting

```php
// In UpdateCampaignStatisticsJob
use Illuminate\Support\Facades\RateLimiter;

public function handle(): void
{
    $key = "campaign_stats:{$this->campaignId}";
    
    if (RateLimiter::tooManyAttempts($key, 1)) {
        Log::info('Rate limited campaign stats update', [
            'campaign_id' => $this->campaignId
        ]);
        return;
    }

    RateLimiter::hit($key, 5); // 1 update per 5 seconds
    
    // ... rest of code
}
```

---

## Future Enhancements

### 1. Per-Contact Status Display

Add detailed status for each recipient in campaign log table:

```vue
<!-- In CampaignLogTable.vue -->
<td class="px-6 py-4 whitespace-nowrap text-sm">
    <span v-if="log.chat?.message_status === 'read'" class="text-blue-600">
        ✓✓ Read
    </span>
    <span v-else-if="log.chat?.message_status === 'delivered'" class="text-gray-600">
        ✓✓ Delivered
    </span>
    <span v-else-if="log.chat?.message_status === 'sent'" class="text-gray-600">
        ✓ Sent
    </span>
    <span v-else-if="log.chat?.message_status === 'pending'" class="text-orange-600">
        ⏳ Pending
    </span>
    <span v-else class="text-red-600">
        ❌ Failed
    </span>
</td>
```

### 2. Real-Time Progress Bar

```vue
<!-- Add animated progress bar -->
<div class="w-full bg-gray-200 rounded-full h-4 mb-4">
    <div 
        class="bg-blue-600 h-4 rounded-full transition-all duration-500 ease-out"
        :style="{ width: `${(statistics.total_sent_count / statistics.total_message_count) * 100}%` }"
    >
        <span class="text-xs text-white px-2">
            {{ Math.round((statistics.total_sent_count / statistics.total_message_count) * 100) }}%
        </span>
    </div>
</div>
```

### 3. Push Notifications

```javascript
// Request notification permission
if (Notification.permission === 'default') {
    Notification.requestPermission();
}

// In Echo listener
.listen('.campaign.statistics.updated', (event) => {
    // Show notification when campaign completes
    if (event.statistics.pending_count === 0) {
        new Notification('Campaign Completed!', {
            body: `${event.statistics.total_sent_count} messages sent successfully`,
            icon: '/images/logo.png'
        });
    }
});
```

### 4. Analytics Dashboard

Create comprehensive analytics component:

```vue
<!-- CampaignAnalytics.vue -->
<template>
    <div class="grid grid-cols-2 gap-4">
        <!-- Delivery Rate Chart -->
        <LineChart :data="deliveryRateData" />
        
        <!-- Read Rate Chart -->
        <LineChart :data="readRateData" />
        
        <!-- Time Distribution -->
        <BarChart :data="timeDistributionData" />
        
        <!-- Top Performing Contacts -->
        <TopContactsTable :contacts="topContacts" />
    </div>
</template>
```

---

## Conclusion

This implementation guide provides a complete roadmap for adding real-time campaign message tracking to your WhatsApp campaign system.

**Key Takeaways:**
- ✅ Leverage existing infrastructure (95% already built)
- ✅ Follow 3-phase approach (Backend → Frontend → Testing)
- ✅ Use cache locks for concurrency control
- ✅ Implement proper error handling and logging
- ✅ Optimize database queries with indexes
- ✅ Test thoroughly before production deployment

**Next Steps:**
1. Review this guide thoroughly
2. Set up development environment
3. Implement Phase 1 (Backend)
4. Test backend flow end-to-end
5. Implement Phase 2 (Frontend)
6. Conduct comprehensive testing
7. Deploy to production with monitoring

**Support:**
- Refer to `/docs/campaign/MESSAGE-TRACKING-FEASIBILITY-REPORT.md` for detailed analysis
- Check Laravel queue documentation for advanced queue configurations
- Consult Laravel Broadcasting docs for Reverb optimization

---

**Document Version:** 1.0  
**Last Updated:** November 20, 2025  
**Maintained By:** Development Team
