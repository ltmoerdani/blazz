# 📋 IMPLEMENTATION GUIDE - MEDIA STORAGE FOR CAMPAIGNS

**Version:** 1.1  
**Date:** 3 Desember 2025  
**Status:** ✅ VERIFIED - READY FOR IMPLEMENTATION  
**Prerequisites:** `01-technical-specification.md`  
**Last Verification:** 2025-12-03 (localhost:3306, database: blazz)

> **✅ PRE-IMPLEMENTATION CHECKLIST VERIFIED:**
> - [x] Database scanned - `chat_media` table has 0 records (safe)
> - [x] PHP extensions verified (gd, imagick, fileinfo)
> - [ ] intervention/image - NEEDS INSTALL
> - [ ] FFmpeg - NEEDS INSTALL (video processing deferred)
> - [x] Queue configured (database driver)

---

## 📑 TABLE OF CONTENTS

1. [Pre-Implementation Checklist](#1-pre-implementation-checklist)
2. [Phase 1: Database Enhancement](#2-phase-1-database-enhancement)
3. [Phase 2: Model & Service Layer](#3-phase-2-model--service-layer)
4. [Phase 3: Queue & Jobs](#4-phase-3-queue--jobs)
5. [Phase 4: Integration](#5-phase-4-integration)
6. [Testing Guide](#6-testing-guide)
7. [Deployment Checklist](#7-deployment-checklist)
8. [Rollback Procedures](#8-rollback-procedures)

---

## 1. PRE-IMPLEMENTATION CHECKLIST

### 1.1 Environment Requirements

> **⚠️ ENVIRONMENT STATUS (Verified 2025-12-03)**
> | Component | Status | Notes |
> |-----------|--------|-------|
> | PHP GD | ✅ Installed | Available |
> | PHP Imagick | ✅ Installed | Better quality option |
> | PHP Fileinfo | ✅ Installed | MIME detection |
> | FFmpeg | ❌ NOT INSTALLED | Video processing disabled |
> | intervention/image | ❌ NOT INSTALLED | Run `composer require` |
> | Queue Driver | `database` | OK for dev |
> | Storage | `local` | S3 not configured |

```bash
# Check PHP extensions
php -m | grep -E "gd|imagick|fileinfo"

# Expected output should include:
# - gd (or imagick) ✅
# - fileinfo ✅

# Check FFmpeg (optional, for video processing)
ffmpeg -version
# ⚠️ If not installed: brew install ffmpeg (macOS) or apt install ffmpeg (Linux)
# Video processing will be SKIPPED if FFmpeg not available

# Check Composer packages
composer show intervention/image
# If not installed: composer require intervention/image
```

### 1.2 Required Packages

```bash
# Install required packages
composer require intervention/image:^3.0
composer require php-ffmpeg/php-ffmpeg --optional  # For video processing

# If using GD driver (default)
# No additional setup needed

# If using Imagick driver (better quality)
# sudo apt-get install php-imagick
```

### 1.3 Storage Configuration

**Verify `.env` settings:**

```env
# CURRENT VALUES (from .env scan 2025-12-03):
# FILESYSTEM_DISK=local
# QUEUE_CONNECTION=database
# AWS credentials: NOT CONFIGURED

# Storage Configuration
FILESYSTEM_DISK=local  # Change to 's3' for production

# AWS S3 (if using S3) - CURRENTLY NOT CONFIGURED
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=your-media-bucket
AWS_URL=https://your-cdn-domain.cloudfront.net  # Optional: CloudFront URL

# Queue Configuration - CURRENTLY using 'database' (OK for dev)
QUEUE_CONNECTION=database  # Change to 'redis' for production
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Media Settings (add these to .env)
MEDIA_IMAGE_QUALITY=75
MEDIA_THUMBNAIL_SIZE=150
MEDIA_MAX_FILE_SIZE=16777216
MEDIA_VIDEO_PROCESSING=false  # Set to false until FFmpeg installed
```

### 1.4 Backup Current State

> **✅ DATABASE STATUS:** `chat_media` table has 0 records (safe for migration testing)

```bash
# Backup database (optional - table is empty)
mysqldump -u root -p blazz chat_media > backup_chat_media_$(date +%Y%m%d).sql

# Backup existing media files (if local)
tar -czvf backup_media_$(date +%Y%m%d).tar.gz storage/app/public/uploads/media/
```

---

## 2. PHASE 1: DATABASE ENHANCEMENT

### Step 1.1: Create Migration File

```bash
php artisan make:migration enhance_chat_media_for_campaigns
```

**Copy content from `01-technical-specification.md` Section 1.1**

### Step 1.2: Run Migration

```bash
# Run in development first
php artisan migrate --pretend  # Preview changes

# Execute migration
php artisan migrate

# Verify
php artisan tinker
>>> \Schema::getColumnListing('chat_media');
```

### Step 1.3: Create Pivot Table Migration

```bash
php artisan make:migration create_campaign_media_table
```

**Copy content from `01-technical-specification.md` Section 1.2**

### Step 1.4: Data Migration (Existing Records)

```bash
php artisan make:command MigrateChatMediaWorkspace
```

**File:** `app/Console/Commands/MigrateChatMediaWorkspace.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\ChatMedia;
use App\Models\Campaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateChatMediaWorkspace extends Command
{
    protected $signature = 'media:migrate-workspace {--dry-run}';
    protected $description = 'Migrate existing chat_media records to include workspace_id';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        $this->info($dryRun ? 'DRY RUN MODE' : 'EXECUTING MIGRATION');
        
        // Get media IDs from campaigns metadata
        $campaigns = Campaign::whereNotNull('metadata')->get();
        
        $bar = $this->output->createProgressBar($campaigns->count());
        $updated = 0;
        
        foreach ($campaigns as $campaign) {
            $metadata = json_decode($campaign->metadata, true);
            $mediaId = $metadata['media'] ?? null;
            
            if ($mediaId) {
                $media = ChatMedia::find($mediaId);
                if ($media && !$media->workspace_id) {
                    if (!$dryRun) {
                        $media->update(['workspace_id' => $campaign->workspace_id]);
                    }
                    $updated++;
                }
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("Updated {$updated} media records");
        
        return 0;
    }
}
```

```bash
# Test first
php artisan media:migrate-workspace --dry-run

# Execute
php artisan media:migrate-workspace
```

---

## 3. PHASE 2: MODEL & SERVICE LAYER

### Step 2.1: Update ChatMedia Model

**Replace content of `app/Models/ChatMedia.php`** with the enhanced model from `01-technical-specification.md` Section 2.1

### Step 2.2: Create Service Directory Structure

```bash
mkdir -p app/Services/Media
```

### Step 2.3: Create MediaStorageService

```bash
touch app/Services/Media/MediaStorageService.php
touch app/Services/Media/ImageProcessor.php
touch app/Services/Media/VideoProcessor.php
```

**Copy content from `01-technical-specification.md` Sections 3.1, 3.2, 3.3**

### Step 2.4: Create Configuration File

```bash
touch config/media.php
```

**Copy content from `01-technical-specification.md` Section 7.2**

### Step 2.5: Register Service Provider (Optional)

**Add to `app/Providers/AppServiceProvider.php`:**

```php
use App\Services\Media\MediaStorageService;
use App\Services\Media\ImageProcessor;
use App\Services\Media\VideoProcessor;

public function register(): void
{
    $this->app->singleton(MediaStorageService::class, function ($app) {
        return new MediaStorageService(
            $app->make(ImageProcessor::class),
            $app->make(VideoProcessor::class)
        );
    });
}
```

### Step 2.6: Update Campaign Model

**Add relationship to `app/Models/Campaign.php`:**

```php
// Add at top
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Add method
public function media(): BelongsToMany
{
    return $this->belongsToMany(ChatMedia::class, 'campaign_media', 'campaign_id', 'media_id')
        ->withPivot(['usage_type', 'parameters'])
        ->withTimestamps();
}

public function getHeaderMedia(): ?ChatMedia
{
    return $this->media()->wherePivot('usage_type', 'header')->first();
}

public function attachMedia(ChatMedia $media, string $usageType = 'header', array $parameters = []): void
{
    $this->media()->attach($media->id, [
        'usage_type' => $usageType,
        'parameters' => json_encode($parameters),
    ]);
}
```

---

## 4. PHASE 3: QUEUE & JOBS

### Step 3.1: Create Job

```bash
php artisan make:job ProcessCampaignMediaJob
```

**Copy content from `01-technical-specification.md` Section 4.1**

### Step 3.2: Create Event

```bash
php artisan make:event MediaProcessingCompleted
```

**Copy content from `01-technical-specification.md` Section 5.1**

### Step 3.3: Configure Queue Worker

**Update `config/queue.php`** as per Section 7.1

### Step 3.4: Create Supervisor Configuration

**File:** `/etc/supervisor/conf.d/blazz-media-worker.conf`

```ini
[program:blazz-media-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /Applications/MAMP/htdocs/blazz/artisan queue:work redis --queue=media-high,media-low --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/Applications/MAMP/htdocs/blazz/storage/logs/media-worker.log
stopwaitsecs=3600
```

```bash
# Update supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start blazz-media-worker:*
```

---

## 5. PHASE 4: INTEGRATION

### Step 4.1: Create MediaController

```bash
php artisan make:controller Api/v1/MediaController
```

**Copy content from `01-technical-specification.md` Section 6.2**

### Step 4.2: Add API Routes

**Add to `routes/api.php`:**

```php
// Media Management Routes
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::prefix('media')->group(function () {
        Route::post('/upload', [MediaController::class, 'upload']);
        Route::get('/{uuid}', [MediaController::class, 'show']);
        Route::get('/{uuid}/status', [MediaController::class, 'status']);
        Route::delete('/{uuid}', [MediaController::class, 'destroy']);
    });
});
```

### Step 4.3: Update CampaignService

**Modify `app/Services/CampaignService.php`:**

Replace the media upload section:

```php
// OLD CODE (to be replaced):
// if ($storage === 'local') { ... }

// NEW CODE:
use App\Services\Media\MediaStorageService;

// In constructor or method:
$mediaService = app(MediaStorageService::class);

// Upload media
$media = $mediaService->uploadAsync($parameter['value'], $workspaceId);
$mediaId = $media->id;
$mediaUrl = $media->url;
```

**Full integration example:**

```php
private function handleTemplateMediaUpload(array $parameter, int $workspaceId): ?int
{
    if (!isset($parameter['value']) || !$parameter['value'] instanceof \Illuminate\Http\UploadedFile) {
        return null;
    }

    /** @var MediaStorageService $mediaService */
    $mediaService = app(MediaStorageService::class);
    
    // Use async upload for better performance
    $media = $mediaService->uploadAsync($parameter['value'], $workspaceId);
    
    return $media->id;
}
```

### Step 4.4: Update Frontend (Optional - Status Indicator)

**Add to Vue component for media upload:**

```vue
<template>
  <div class="media-upload">
    <input type="file" @change="handleFileUpload" />
    
    <div v-if="media" class="media-status">
      <span v-if="media.status === 'pending'" class="text-yellow-500">
        ⏳ Processing...
      </span>
      <span v-else-if="media.status === 'completed'" class="text-green-500">
        ✅ Ready
      </span>
      <span v-else-if="media.status === 'failed'" class="text-red-500">
        ❌ Failed: {{ media.error }}
      </span>
      
      <img v-if="media.thumbnail_url" :src="media.thumbnail_url" class="w-20 h-20 object-cover" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const media = ref(null);
let statusPollInterval = null;

const handleFileUpload = async (event) => {
  const file = event.target.files[0];
  if (!file) return;
  
  const formData = new FormData();
  formData.append('file', file);
  formData.append('async', true);
  
  try {
    const response = await axios.post('/api/v1/media/upload', formData);
    media.value = response.data.data;
    
    // Poll for status if pending
    if (media.value.status === 'pending') {
      startStatusPoll();
    }
  } catch (error) {
    console.error('Upload failed:', error);
  }
};

const startStatusPoll = () => {
  statusPollInterval = setInterval(async () => {
    if (!media.value?.uuid) return;
    
    try {
      const response = await axios.get(`/api/v1/media/${media.value.uuid}/status`);
      media.value = { ...media.value, ...response.data.data };
      
      if (response.data.data.status !== 'pending' && response.data.data.status !== 'processing') {
        clearInterval(statusPollInterval);
      }
    } catch (error) {
      clearInterval(statusPollInterval);
    }
  }, 2000);
};

onUnmounted(() => {
  if (statusPollInterval) {
    clearInterval(statusPollInterval);
  }
});
</script>
```

---

## 6. TESTING GUIDE

### 6.1 Unit Tests

```bash
# Create test files
php artisan make:test Services/Media/MediaStorageServiceTest --unit
php artisan make:test Services/Media/ImageProcessorTest --unit
```

### 6.2 Feature Tests

```bash
php artisan make:test Api/MediaUploadTest
```

**File:** `tests/Feature/Api/MediaUploadTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Workspace;
use App\Models\ChatMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        
        $this->workspace = Workspace::factory()->create();
        $this->user = User::factory()->create();
        
        session(['current_workspace' => $this->workspace->id]);
    }

    public function test_can_upload_image(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/media/upload', [
                'file' => $file,
                'async' => false,
            ]);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'uuid',
                    'name',
                    'type',
                    'size',
                    'status',
                    'url',
                ]
            ]);
    }

    public function test_can_get_media_status(): void
    {
        $media = ChatMedia::factory()->create([
            'workspace_id' => $this->workspace->id,
            'processing_status' => 'completed',
        ]);
        
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/media/{$media->uuid}/status");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'uuid' => $media->uuid,
                    'status' => 'completed',
                ]
            ]);
    }

    public function test_upload_validates_file_type(): void
    {
        $file = UploadedFile::fake()->create('malicious.exe', 1000);
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/media/upload', [
                'file' => $file,
            ]);
        
        $response->assertStatus(422);
    }

    public function test_upload_validates_file_size(): void
    {
        $file = UploadedFile::fake()->create('large.jpg', 20000); // 20MB
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/media/upload', [
                'file' => $file,
            ]);
        
        $response->assertStatus(422);
    }
}
```

### 6.3 Run Tests

```bash
# Run all tests
php artisan test

# Run specific test class
php artisan test --filter=MediaUploadTest

# Run with coverage
php artisan test --coverage
```

### 6.4 Manual Testing Checklist

- [ ] Upload image via campaign form
- [ ] Verify thumbnail generated
- [ ] Verify compressed version created
- [ ] Check processing status updates
- [ ] Test with different file types (JPEG, PNG, PDF)
- [ ] Test file size limits
- [ ] Test invalid file rejection
- [ ] Verify S3 upload (if configured)
- [ ] Test queue job execution
- [ ] Verify WebSocket event broadcast

---

## 7. DEPLOYMENT CHECKLIST

### 7.1 Pre-Deployment

- [ ] Backup database
- [ ] Backup existing media files
- [ ] Review all migrations
- [ ] Test in staging environment
- [ ] Update documentation

### 7.2 Deployment Steps

```bash
# 1. Enable maintenance mode
php artisan down --message="System upgrade in progress" --retry=60

# 2. Pull latest code
git pull origin staging-broadcast-campaign

# 3. Install dependencies
composer install --no-dev --optimize-autoloader

# 4. Run migrations
php artisan migrate --force

# 5. Clear caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Restart queue workers
php artisan queue:restart
sudo supervisorctl restart blazz-media-worker:*

# 7. Run data migration
php artisan media:migrate-workspace

# 8. Verify deployment
php artisan tinker
>>> \App\Models\ChatMedia::count();
>>> \App\Models\ChatMedia::whereNull('workspace_id')->count();

# 9. Disable maintenance mode
php artisan up
```

### 7.3 Post-Deployment Verification

```bash
# Check queue is processing
php artisan queue:monitor media-high,media-low

# Check for errors
tail -f storage/logs/laravel.log | grep -i media

# Test upload via API
curl -X POST http://your-domain/api/v1/media/upload \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@/path/to/test.jpg"
```

---

## 8. ROLLBACK PROCEDURES

### 8.1 Database Rollback

```bash
# Rollback migrations
php artisan migrate:rollback --step=2

# Restore from backup
mysql -u root -p blazz < backup_chat_media_YYYYMMDD.sql
```

### 8.2 Code Rollback

```bash
# Revert to previous commit
git revert HEAD~2..HEAD

# Or checkout previous tag
git checkout v2.8.0
```

### 8.3 Restore Media Files

```bash
# Restore from backup
tar -xzvf backup_media_YYYYMMDD.tar.gz -C storage/app/public/
```

---

## 📋 QUICK REFERENCE

### Commands Summary

```bash
# Migrations
php artisan migrate
php artisan migrate:rollback

# Queue
php artisan queue:work redis --queue=media-high,media-low
php artisan queue:restart

# Testing
php artisan test --filter=Media

# Maintenance
php artisan media:migrate-workspace
```

### File Locations

| Purpose | Location |
|---------|----------|
| Model | `app/Models/ChatMedia.php` |
| Service | `app/Services/Media/MediaStorageService.php` |
| Job | `app/Jobs/ProcessCampaignMediaJob.php` |
| Controller | `app/Http/Controllers/Api/v1/MediaController.php` |
| Config | `config/media.php` |
| Tests | `tests/Feature/Api/MediaUploadTest.php` |

---

**END OF IMPLEMENTATION GUIDE**

**Status:** ✅ READY FOR IMPLEMENTATION  
**Next Step:** Start with Phase 1 (Database Enhancement)
