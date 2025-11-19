# 🚀 PANDUAN IMPLEMENTASI: MEDIA OPTIMIZATION SYSTEM
**Version:** 1.0  
**Date:** 19 November 2025  
**Target Completion:** 4 Weeks (Phased rollout)

---

## 📋 TABLE OF CONTENTS
1. [Phase 1: Critical Fixes (Week 1)](#phase-1)
2. [Phase 2: Storage Optimization (Week 2)](#phase-2)
3. [Phase 3: CDN & Advanced Features (Week 3-4)](#phase-3)
4. [Phase 4: Monitoring & Maintenance (Ongoing)](#phase-4)
5. [Testing Checklist](#testing)
6. [Rollback Plan](#rollback)

---

## 🎯 PHASE 1: CRITICAL FIXES (Week 1)
**Goal:** Fix "Content not available" issue segera
**Effort:** 2-3 developer days
**Risk:** Low

### Task 1.1: Fix Eager Loading Inconsistency

#### Step 1: Update Chat Model
**File:** `app/Models/Chat.php`

```php
<?php
namespace App\Models;

class Chat extends Model {
    // Add automatic eager loading
    protected $with = ['media'];
    
    // Or if you want more control:
    protected $with = ['contact', 'media', 'user'];
    
    // Ensure relationship is always loaded
    public function newQuery() {
        return parent::newQuery()->with('media');
    }
}
```

#### Step 2: Audit All Chat Queries
**Command to find all Chat queries:**
```bash
grep -r "Chat::" app/ | grep -v "with('media')" | grep "get\|first\|find"
```

**Fix each occurrence:**
```php
// ❌ Before
$chat = Chat::where('id', $id)->first();
$chats = Chat::where('contact_id', $contactId)->get();

// ✅ After
$chat = Chat::with('media')->where('id', $id)->first();
$chats = Chat::with('media')->where('contact_id', $contactId)->get();
```

**Key files to check:**
- `app/Http/Controllers/ChatController.php`
- `app/Services/ChatService.php`
- `app/Http/Controllers/Api/v1/WebhookController.php`
- `app/Services/WhatsApp/WebhookService.php`

---

### Task 1.2: Improve Frontend Error Handling

#### Step 1: Add Retry Mechanism
**File:** `resources/js/Components/ChatComponents/ChatBubble.vue`

```vue
<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    content: Object,
    type: String,
})

const imageError = ref(false);
const imageRetries = ref(0);
const maxRetries = 3;

const handleMediaError = (event) => {
    imageRetries.value++;
    
    if (imageRetries.value < maxRetries) {
        console.log(`Media load failed, retry ${imageRetries.value}/${maxRetries}`);
        
        // Retry with cache buster
        setTimeout(() => {
            event.target.src = props.content.media.path + '?retry=' + Date.now();
        }, 1000 * imageRetries.value); // Exponential backoff
    } else {
        imageError.value = true;
        console.error('Media failed after max retries');
    }
};

const retryMediaLoad = () => {
    imageError.value = false;
    imageRetries.value = 0;
    
    // Force reload component
    const img = document.querySelector(`[data-message-id="${props.content.id}"] img`);
    if (img) {
        img.src = props.content.media.path + '?force=' + Date.now();
    }
};
</script>

<template>
    <!-- Image rendering with better error handling -->
    <div v-else-if="metadata.type === 'image'">
        <!-- Show image if media exists -->
        <img 
            v-if="content.media?.path && !imageError" 
            :src="content.media.path" 
            @error="handleMediaError"
            alt="Image" 
            class="mb-2 max-w-[300px]"
            loading="lazy" />
        
        <!-- Show loading state -->
        <div v-else-if="content.media && !content.media.path" 
             class="text-slate-500 flex justify-center items-center p-4">
            <svg class="animate-spin h-5 w-5 mr-2" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>{{ $t('Loading media...') }}</span>
        </div>
        
        <!-- Show error with retry -->
        <div v-else class="text-slate-500 flex flex-col justify-center items-center p-4 space-y-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                <g fill="none"><path d="M24 0v24H0V0h24Z"/><path fill="currentColor" d="m13.299 3.148l8.634 14.954a1.5 1.5 0 0 1-1.299 2.25H3.366a1.5 1.5 0 0 1-1.299-2.25l8.634-14.954c.577-1 2.02-1 2.598 0ZM12 4.898L4.232 18.352h15.536L12 4.898ZM12 15a1 1 0 1 1 0 2a1 1 0 0 1 0-2Zm0-7a1 1 0 0 1 1 1v4a1 1 0 1 1-2 0V9a1 1 0 0 1 1-1Z"/></g>
            </svg>
            <span>{{ $t('Content not available') }}</span>
            <button 
                @click="retryMediaLoad" 
                class="px-3 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600">
                {{ $t('Retry') }}
            </button>
        </div>
        
        <!-- Caption if exists -->
        <div v-if="metadata.image?.caption" class="max-w-[300px]">
            {{ metadata.image?.caption }}
        </div>
    </div>
    
    <!-- Similar pattern for video, audio, sticker -->
    <!-- ... (apply same logic) -->
</template>
```

---

### Task 1.3: Add Media Validation & Queue

#### Step 1: Create Media Processing Job
**File:** `app/Jobs/ProcessWhatsAppMediaJob.php`

```php
<?php

namespace App\Jobs;

use App\Models\ChatMedia;
use App\Models\workspace;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessWhatsAppMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Retry 3 times
    public $timeout = 300; // 5 minutes timeout
    public $backoff = [10, 30, 60]; // Exponential backoff

    protected $mediaId;
    protected $mediaUrl;
    protected $mimeType;
    protected $fileSize;
    protected $workspaceId;
    protected $filename;

    public function __construct($mediaId, $mediaUrl, $mimeType, $fileSize, $workspaceId, $filename = null)
    {
        $this->mediaId = $mediaId;
        $this->mediaUrl = $mediaUrl;
        $this->mimeType = $mimeType;
        $this->fileSize = $fileSize;
        $this->workspaceId = $workspaceId;
        $this->filename = $filename ?? 'media_' . time();
        
        // High priority queue for media (5 min WhatsApp URL expiration!)
        $this->onQueue('media-high');
    }

    public function handle()
    {
        Log::info('Processing WhatsApp media', [
            'media_id' => $this->mediaId,
            'workspace_id' => $this->workspaceId,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Step 1: Validate file size (WhatsApp limit: 100MB)
            if ($this->fileSize > 100 * 1024 * 1024) {
                throw new \Exception('File size exceeds 100MB limit');
            }

            // Step 2: Download media from WhatsApp URL
            $fileContent = $this->downloadMedia();

            // Step 3: Save to storage (local or S3)
            $storedPath = $this->saveMedia($fileContent);

            // Step 4: Update chat_media record
            $this->updateMediaRecord($storedPath);

            Log::info('Media processed successfully', [
                'media_id' => $this->mediaId,
                'path' => $storedPath,
            ]);

        } catch (\Exception $e) {
            Log::error('Media processing failed', [
                'media_id' => $this->mediaId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() >= $this->tries) {
                // Final failure - mark as failed
                $this->markMediaAsFailed($e->getMessage());
            }

            throw $e; // Re-throw to trigger retry
        }
    }

    protected function downloadMedia()
    {
        $workspace = workspace::find($this->workspaceId);
        $metadata = json_decode($workspace->metadata, true);
        $accessToken = $metadata['whatsapp']['access_token'] ?? null;

        if (!$accessToken) {
            throw new \Exception('WhatsApp access token not found');
        }

        $client = new Client([
            'timeout' => 60,
            'connect_timeout' => 10,
        ]);

        $response = $client->request('GET', $this->mediaUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Failed to download media: HTTP ' . $response->getStatusCode());
        }

        return $response->getBody()->getContents();
    }

    protected function saveMedia($fileContent)
    {
        $storage = \App\Models\Setting::where('key', 'storage_system')->first()->value ?? 'local';
        $extension = $this->getExtensionFromMimetype($this->mimeType);
        $filename = $this->filename . '.' . $extension;
        $directory = 'uploads/media/whatsapp/' . $this->workspaceId . '/' . date('Y/m');
        $fullPath = $directory . '/' . $filename;

        if ($storage === 'aws') {
            Storage::disk('s3')->put($fullPath, $fileContent, [
                'ContentType' => $this->mimeType,
                'CacheControl' => 'max-age=31536000', // 1 year cache
            ]);
            
            $url = Storage::disk('s3')->url($fullPath);
        } else {
            Storage::disk('local')->put('public/' . $fullPath, $fileContent);
            $url = rtrim(config('app.url'), '/') . '/storage/' . $fullPath;
        }

        return $url;
    }

    protected function updateMediaRecord($storedPath)
    {
        $media = ChatMedia::find($this->mediaId);
        
        if ($media) {
            $media->path = $storedPath;
            $media->processing_status = 'completed';
            $media->processed_at = now();
            $media->save();
        }
    }

    protected function markMediaAsFailed($errorMessage)
    {
        $media = ChatMedia::find($this->mediaId);
        
        if ($media) {
            $media->processing_status = 'failed';
            $media->metadata = json_encode([
                'error' => $errorMessage,
                'failed_at' => now()->toIso8601String(),
            ]);
            $media->save();
        }
    }

    protected function getExtensionFromMimetype($mimeType)
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'application/pdf' => 'pdf',
        ];

        return $map[$mimeType] ?? 'bin';
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Media job permanently failed', [
            'media_id' => $this->mediaId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

#### Step 2: Update Webhook Controller to Use Queue
**File:** `app/Http/Controllers/Api/v1/WebhookController.php`

```php
use App\Jobs\ProcessWhatsAppMediaJob;

// Replace synchronous download with queue
if($response['type'] === 'image' || $response['type'] === 'video' 
   || $response['type'] === 'audio' || $response['type'] === 'document' 
   || $response['type'] === 'sticker') {
    
    $type = $response['type'];
    $mediaId = $response[$type]['id'];

    // Get media metadata (fast)
    $media = $this->getMedia($mediaId, $workspace);

    // Create placeholder media record
    $chatMedia = new ChatMedia;
    $chatMedia->name = $type === 'document' ? ($response[$type]['filename'] ?? 'N/A') : 'N/A';
    $chatMedia->path = null; // Will be filled by job
    $chatMedia->type = $media['mime_type'];
    $chatMedia->size = $media['file_size'];
    $chatMedia->location = config('settings.use_s3_as_storage') ? 'amazon' : 'local';
    $chatMedia->processing_status = 'pending';
    $chatMedia->save();

    // Link to chat immediately
    Chat::where('id', $chat->id)->update([
        'media_id' => $chatMedia->id
    ]);

    // Dispatch async job (high priority - 5 min WhatsApp URL window!)
    ProcessWhatsAppMediaJob::dispatch(
        $chatMedia->id,
        $media['url'],
        $media['mime_type'],
        $media['file_size'],
        $workspace->id,
        $response[$type]['filename'] ?? null
    )->onQueue('media-high');
}
```

#### Step 3: Update Database Migration
**File:** `database/migrations/2025_11_19_add_processing_status_to_chat_media.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('chat_media', function (Blueprint $table) {
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])
                  ->default('pending')
                  ->after('size');
            $table->timestamp('processed_at')->nullable()->after('processing_status');
            $table->json('metadata')->nullable()->after('processed_at');
            
            $table->index('processing_status');
        });
    }

    public function down()
    {
        Schema::table('chat_media', function (Blueprint $table) {
            $table->dropColumn(['processing_status', 'processed_at', 'metadata']);
        });
    }
};
```

#### Step 4: Configure Queue
**File:** `config/queue.php`

```php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 300,
        'block_for' => null,
        
        // Add dedicated queue for media
        'queues' => [
            'media-high' => [
                'timeout' => 300,
                'tries' => 3,
            ],
        ],
    ],
],
```

**Start queue worker:**
```bash
php artisan queue:work redis --queue=media-high --tries=3 --timeout=300
```

---

### Task 1.4: Add Monitoring & Alerts

#### Create Media Health Check Command
**File:** `app/Console/Commands/CheckMediaHealthCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\ChatMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckMediaHealthCommand extends Command
{
    protected $signature = 'media:health-check';
    protected $description = 'Check media processing health and alert on issues';

    public function handle()
    {
        $this->info('Checking media health...');

        // Check pending media (stuck?)
        $pendingCount = ChatMedia::where('processing_status', 'pending')
            ->where('created_at', '<', now()->subMinutes(10))
            ->count();

        if ($pendingCount > 0) {
            $this->warn("⚠️  {$pendingCount} media files pending for >10 minutes");
            Log::warning('Media processing delayed', ['count' => $pendingCount]);
        }

        // Check failed media
        $failedCount = ChatMedia::where('processing_status', 'failed')
            ->whereDate('created_at', today())
            ->count();

        if ($failedCount > 10) {
            $this->error("❌ {$failedCount} media files failed today");
            // Send alert to Slack/email
        }

        // Check missing paths
        $missingPaths = ChatMedia::whereNull('path')
            ->where('processing_status', 'completed')
            ->count();

        if ($missingPaths > 0) {
            $this->error("❌ {$missingPaths} completed media without path");
        }

        $this->info('✅ Health check complete');
        
        return 0;
    }
}
```

**Schedule in `app/Console/Kernel.php`:**
```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('media:health-check')->everyFiveMinutes();
}
```

---

## 📊 PHASE 1 TESTING CHECKLIST

### Manual Testing:
- [ ] Send image via WhatsApp → Verify displays correctly
- [ ] Send video via WhatsApp → Verify displays correctly
- [ ] Send audio via WhatsApp → Verify displays correctly
- [ ] Send sticker via WhatsApp → Verify displays correctly
- [ ] Send document via WhatsApp → Verify displays correctly
- [ ] Check "Content not available" no longer appears
- [ ] Test retry button functionality
- [ ] Verify loading state shows correctly

### Automated Testing:
```php
// tests/Feature/MediaProcessingTest.php
public function test_media_processing_job_succeeds()
{
    Queue::fake();
    
    // Trigger webhook with media
    $response = $this->postJson('/api/v1/webhook/whatsapp', [
        'entry' => [/* webhook payload with image */]
    ]);
    
    Queue::assertPushed(ProcessWhatsAppMediaJob::class);
}

public function test_media_displays_in_chat()
{
    $chat = Chat::factory()->withMedia()->create();
    
    $this->get("/api/chats/{$chat->id}")
         ->assertJsonStructure([
             'data' => [
                 'id',
                 'media' => ['id', 'path', 'type'],
             ],
         ]);
}
```

### Performance Testing:
- [ ] Queue processing time < 30s per media
- [ ] Chat load time with media < 2s
- [ ] Database queries optimized (N+1 check)

---

## 🚀 DEPLOYMENT PLAN (Phase 1)

### Pre-Deployment:
```bash
# 1. Backup database
php artisan backup:database

# 2. Run migrations
php artisan migrate

# 3. Test queue worker
php artisan queue:work redis --queue=media-high --once
```

### Deployment Steps:
```bash
# 1. Deploy code
git pull origin staging-chats-fix

# 2. Install dependencies
composer install --no-dev --optimize-autoloader
npm install && npm run build

# 3. Clear cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# 4. Restart queue workers
supervisorctl restart laravel-worker:*

# 5. Monitor logs
tail -f storage/logs/laravel.log
```

### Post-Deployment Monitoring:
- [ ] Monitor queue length: `redis-cli LLEN queues:media-high`
- [ ] Check error rate in logs
- [ ] Verify media displays correctly in production
- [ ] Monitor storage usage

---

## 🔄 ROLLBACK PLAN

If issues occur:

```bash
# 1. Revert code
git revert <commit-hash>

# 2. Rollback migration (if needed)
php artisan migrate:rollback --step=1

# 3. Clear queue
php artisan queue:flush

# 4. Restart services
supervisorctl restart all
```

---

## 📈 SUCCESS METRICS (Phase 1)

### Target KPIs:
- **Media load success rate:** 99%+ (from ~90%)
- **"Content not available" errors:** <0.5% (from ~5-10%)
- **Queue processing time:** <30s average
- **User complaints:** <1 per day

### Monitoring Dashboard:
```sql
-- Media processing stats
SELECT 
    processing_status,
    COUNT(*) as count,
    AVG(TIMESTAMPDIFF(SECOND, created_at, processed_at)) as avg_process_time
FROM chat_media
WHERE created_at >= CURDATE()
GROUP BY processing_status;

-- Failed media reasons
SELECT 
    JSON_EXTRACT(metadata, '$.error') as error,
    COUNT(*) as count
FROM chat_media
WHERE processing_status = 'failed'
AND created_at >= CURDATE()
GROUP BY error;
```

---

**END OF PHASE 1 IMPLEMENTATION GUIDE**

*Continue to Phase 2 documentation for advanced optimization features...*

