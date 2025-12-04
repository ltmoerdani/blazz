# Video Conversion Progress Tracking - Opsi 2B

## Overview

Implementasi real-time progress tracking untuk konversi video (MOV → MP4) menggunakan **Background Job + Polling** approach.

---

## Problem Statement

Saat user upload video dengan format non-MP4 (MOV, AVI, etc.), proses konversi menggunakan FFmpeg memakan waktu lama (tergantung ukuran file). User tidak tahu:
- Apakah proses masih berjalan atau stuck
- Sudah berapa persen progress konversi
- Estimasi waktu selesai

---

## Solution Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           VIDEO UPLOAD FLOW                                  │
└─────────────────────────────────────────────────────────────────────────────┘

┌──────────┐    1. Upload File     ┌──────────────┐
│  Browser │ ──────────────────────▶│   Laravel    │
│  (Vue)   │                        │  Controller  │
└──────────┘                        └──────┬───────┘
     │                                     │
     │                              2. Save to temp
     │                              3. Create Job
     │                              4. Return job_id
     │                                     │
     │         ◀───────────────────────────┘
     │         {job_id: "abc123", status: "processing"}
     │
     │    5. Start Polling (every 2 sec)
     │    ┌────────────────────────────────────────┐
     │    │  GET /api/conversion-progress/{job_id} │
     │    └────────────────────────────────────────┘
     │                                     │
     │                                     ▼
     │                            ┌───────────────┐
     │                            │  Background   │
     │                            │     Job       │
     │                            │  (FFmpeg)     │
     │                            └───────┬───────┘
     │                                    │
     │                             Parse FFmpeg
     │                             progress output
     │                                    │
     │                                    ▼
     │                            ┌───────────────┐
     │                            │    Cache      │
     │                            │  (Progress)   │
     │                            │  0%→100%      │
     │                            └───────────────┘
     │                                    │
     │    6. Response: {progress: 45, status: "converting"}
     │    ◀────────────────────────────────┘
     │
     │    7. Update Progress Bar UI
     │
     │    8. When progress = 100
     │         → Upload to S3
     │         → Save ChatMedia
     │         → Return final URL
     │
     ▼
┌──────────┐
│ Complete │  {status: "completed", media_url: "https://..."}
└──────────┘
```

---

## Component Breakdown

### 1. Database/Cache Schema

```php
// Cache key format
"video_conversion:{job_id}"

// Cache value (JSON)
{
    "job_id": "conv_abc123def456",
    "status": "converting",      // pending|converting|uploading|completed|failed
    "progress": 45,              // 0-100
    "current_time": "00:01:23",  // FFmpeg current position
    "total_duration": "00:03:00", // Total video duration
    "file_size": 52428800,       // Original file size in bytes
    "output_path": "/tmp/video_converted.mp4",
    "error_message": null,
    "created_at": "2025-12-04 10:00:00",
    "updated_at": "2025-12-04 10:00:45"
}
```

### 2. Backend Components

#### 2.1 Controller: `VideoConversionController.php`

```
Location: app/Http/Controllers/Api/VideoConversionController.php

Endpoints:
- POST   /api/campaigns/upload-video     → Start upload & conversion
- GET    /api/campaigns/conversion/{id}  → Get conversion progress
- DELETE /api/campaigns/conversion/{id}  → Cancel conversion
```

**Methods:**

```php
class VideoConversionController extends Controller
{
    /**
     * Upload video and start conversion job
     * 
     * @param Request $request
     * @return JsonResponse {job_id, status, needs_conversion}
     */
    public function upload(Request $request): JsonResponse;
    
    /**
     * Get conversion progress
     * 
     * @param string $jobId
     * @return JsonResponse {job_id, status, progress, estimated_time}
     */
    public function progress(string $jobId): JsonResponse;
    
    /**
     * Cancel ongoing conversion
     * 
     * @param string $jobId
     * @return JsonResponse
     */
    public function cancel(string $jobId): JsonResponse;
}
```

#### 2.2 Job: `ConvertVideoJob.php`

```
Location: app/Jobs/ConvertVideoJob.php
Queue: video-conversion (dedicated queue for video processing)
```

**Responsibilities:**
1. Run FFmpeg dengan progress parsing
2. Update cache dengan progress setiap detik
3. Handle errors dan cleanup temp files
4. Upload hasil konversi ke S3 setelah selesai

**FFmpeg Progress Parsing:**

```php
// FFmpeg output format yang di-parse:
// frame=  120 fps=30 q=28.0 size=    1024kB time=00:00:04.00 bitrate=2097.2kbits/s speed=1.5x

// Regex untuk extract progress
$pattern = '/time=(\d{2}:\d{2}:\d{2}\.\d{2})/';

// Calculate percentage
$progress = ($currentTimeSeconds / $totalDurationSeconds) * 100;
```

#### 2.3 Service: `VideoConversionService.php`

```
Location: app/Services/Media/VideoConversionService.php
```

**Methods:**

```php
class VideoConversionService
{
    /**
     * Start conversion process
     */
    public function startConversion(string $sourcePath, int $workspaceId): string; // returns job_id
    
    /**
     * Get progress from cache
     */
    public function getProgress(string $jobId): array;
    
    /**
     * Update progress in cache
     */
    public function updateProgress(string $jobId, int $progress, string $status): void;
    
    /**
     * Parse FFmpeg output for progress
     */
    public function parseFFmpegProgress(string $output, float $totalDuration): int;
    
    /**
     * Cancel and cleanup
     */
    public function cancelConversion(string $jobId): bool;
}
```

### 3. Frontend Components

#### 3.1 Composable: `useVideoConversion.ts`

```
Location: resources/js/Composables/useVideoConversion.ts
```

```typescript
interface ConversionState {
    jobId: string | null;
    status: 'idle' | 'uploading' | 'converting' | 'completed' | 'failed';
    progress: number;
    estimatedTime: string | null;
    error: string | null;
}

export function useVideoConversion() {
    const state = reactive<ConversionState>({...});
    
    // Upload file and start conversion
    async function uploadVideo(file: File, workspaceId: number): Promise<void>;
    
    // Start polling for progress
    function startPolling(jobId: string): void;
    
    // Stop polling
    function stopPolling(): void;
    
    // Cancel conversion
    async function cancelConversion(): Promise<void>;
    
    return {
        state,
        uploadVideo,
        cancelConversion,
        isConverting: computed(() => state.status === 'converting'),
        isCompleted: computed(() => state.status === 'completed'),
    };
}
```

#### 3.2 Component: `VideoUploadProgress.vue`

```
Location: resources/js/Components/VideoUploadProgress.vue
```

```vue
<template>
    <div v-if="isConverting" class="video-conversion-progress">
        <!-- Status Text -->
        <div class="flex items-center gap-2 mb-2">
            <SpinnerIcon class="animate-spin" />
            <span>{{ statusText }}</span>
        </div>
        
        <!-- Progress Bar -->
        <div class="w-full bg-gray-200 rounded-full h-3">
            <div 
                class="bg-blue-600 h-3 rounded-full transition-all duration-300"
                :style="{ width: `${progress}%` }"
            />
        </div>
        
        <!-- Progress Details -->
        <div class="flex justify-between text-sm text-gray-500 mt-1">
            <span>{{ progress }}%</span>
            <span v-if="estimatedTime">Est: {{ estimatedTime }}</span>
        </div>
        
        <!-- Cancel Button -->
        <button 
            @click="onCancel"
            class="mt-2 text-sm text-red-500 hover:text-red-700"
        >
            Cancel Conversion
        </button>
    </div>
</template>
```

#### 3.3 Integration in CampaignForm.vue

```vue
<script setup>
import { useVideoConversion } from '@/Composables/useVideoConversion';

const { 
    state: conversionState, 
    uploadVideo, 
    isConverting,
    isCompleted 
} = useVideoConversion();

// Modified file upload handler
async function handleFileUpload(file: File) {
    if (needsConversion(file)) {
        // Use conversion flow
        await uploadVideo(file, workspaceId);
        // Wait for completion, then set media URL
    } else {
        // Direct upload for already-compatible formats
        await directUpload(file);
    }
}

// Check if file needs conversion
function needsConversion(file: File): boolean {
    const convertTypes = ['video/quicktime', 'video/x-msvideo', 'video/x-ms-wmv'];
    return convertTypes.includes(file.type);
}
</script>
```

---

## API Contracts

### POST /api/campaigns/upload-video

**Request:**
```http
POST /api/campaigns/upload-video
Content-Type: multipart/form-data

file: (binary)
workspace_id: 5
campaign_uuid: "draft" (optional)
```

**Response (needs conversion):**
```json
{
    "success": true,
    "needs_conversion": true,
    "job_id": "conv_abc123def456",
    "status": "pending",
    "message": "Video conversion started"
}
```

**Response (no conversion needed):**
```json
{
    "success": true,
    "needs_conversion": false,
    "media": {
        "id": 123,
        "url": "https://s3.../video.mp4",
        "mime_type": "video/mp4"
    }
}
```

### GET /api/campaigns/conversion/{jobId}

**Response (in progress):**
```json
{
    "job_id": "conv_abc123def456",
    "status": "converting",
    "progress": 45,
    "current_time": "00:01:23",
    "total_duration": "00:03:00",
    "estimated_remaining": "00:01:37"
}
```

**Response (completed):**
```json
{
    "job_id": "conv_abc123def456",
    "status": "completed",
    "progress": 100,
    "media": {
        "id": 124,
        "url": "https://s3.../video_converted.mp4",
        "mime_type": "video/mp4",
        "file_size": 15728640
    }
}
```

**Response (failed):**
```json
{
    "job_id": "conv_abc123def456",
    "status": "failed",
    "progress": 67,
    "error": "FFmpeg conversion failed: codec not supported"
}
```

### DELETE /api/campaigns/conversion/{jobId}

**Response:**
```json
{
    "success": true,
    "message": "Conversion cancelled and temp files cleaned up"
}
```

---

## Implementation Steps

### Phase 1: Backend Foundation (Est: 15 min)

1. [ ] Create `VideoConversionService.php`
2. [ ] Create `ConvertVideoJob.php` 
3. [ ] Add cache methods for progress tracking
4. [ ] Update `VideoProcessor.php` untuk parse FFmpeg progress

### Phase 2: API Endpoints (Est: 10 min)

1. [ ] Create `VideoConversionController.php`
2. [ ] Add routes di `routes/api.php`
3. [ ] Add request validation

### Phase 3: Frontend Integration (Est: 15 min)

1. [ ] Create `useVideoConversion.ts` composable
2. [ ] Create `VideoUploadProgress.vue` component
3. [ ] Integrate into `CampaignForm.vue`

### Phase 4: Testing & Polish (Est: 10 min)

1. [ ] Test dengan file MOV besar
2. [ ] Test cancel functionality
3. [ ] Test error handling
4. [ ] UI polish dan loading states

---

## Queue Configuration

Add dedicated queue for video processing:

```php
// config/queue.php
'connections' => [
    'database' => [
        // ... existing config
    ],
],

// .env
QUEUE_CONNECTION=database

// Supervisor config untuk dedicated worker
// /etc/supervisor/conf.d/blazz-video.conf
[program:blazz-video-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/blazz/artisan queue:work --queue=video-conversion --timeout=900
numprocs=2
```

---

## Error Handling

| Error Type | Handling |
|------------|----------|
| FFmpeg not available | Return error, suggest using MP4 directly |
| Conversion timeout | Cancel job, cleanup temp files, notify user |
| Disk space full | Check before conversion, return error if insufficient |
| Invalid video format | Validate before starting job |
| S3 upload failed | Retry 3x, then mark as failed |

---

## Performance Considerations

1. **Polling Interval**: 2 seconds (balance between responsiveness and server load)
2. **Cache TTL**: 1 hour (cleanup abandoned conversions)
3. **Max File Size**: 100MB (sesuai config existing)
4. **Conversion Timeout**: 10 minutes (600 seconds)
5. **Concurrent Jobs**: Max 2 per server (prevent CPU overload)

---

## Security Considerations

1. Job ID menggunakan UUID untuk prevent enumeration
2. Validate workspace ownership sebelum return progress
3. Rate limit pada polling endpoint (30 req/min per user)
4. Cleanup temp files setelah 1 jam regardless of status

---

## Related Files

- `app/Services/Media/VideoProcessor.php` - Existing FFmpeg wrapper
- `app/Services/Media/MediaStorageService.php` - Storage service
- `config/media.php` - Media configuration
- `config/ffmpeg.php` - FFmpeg paths
- `resources/js/Components/CampaignForm.vue` - Form component

---

## Rollback Plan

Jika implementasi bermasalah, bisa rollback ke synchronous conversion dengan:

1. Revert `CampaignForm.vue` ke direct upload
2. Keep `MediaStorageService.uploadForCampaign()` dengan synchronous conversion
3. User akan experience loading lama tapi tetap functional
