<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for campaign media storage and processing.
    |
    */

    'storage' => [
        // Storage disk to use: 'local', 's3'
        'disk' => env('MEDIA_STORAGE_DISK', 's3'),

        // Base path for general chat media
        'base_path' => 'chat_media',

        // Base path for campaign media
        'campaign_path' => 'campaign_media',

        // Maximum file size in bytes (default: 100MB)
        'max_file_size' => env('MEDIA_MAX_FILE_SIZE', 100 * 1024 * 1024),

        // S3 URL for public access
        's3_url' => env('AWS_URL', env('AWS_ENDPOINT')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Processing Settings
    |--------------------------------------------------------------------------
    */

    'image' => [
        // Maximum dimensions for compressed images
        'max_width' => env('MEDIA_IMAGE_MAX_WIDTH', 1200),
        'max_height' => env('MEDIA_IMAGE_MAX_HEIGHT', 1200),

        // JPEG compression quality (0-100)
        'compression_quality' => env('MEDIA_IMAGE_QUALITY', 80),

        // WebP quality (0-100)
        'webp_quality' => env('MEDIA_WEBP_QUALITY', 85),

        // Thumbnail dimensions
        'thumbnail_width' => env('MEDIA_THUMBNAIL_WIDTH', 200),
        'thumbnail_height' => env('MEDIA_THUMBNAIL_HEIGHT', 200),

        // Maximum file size before compression is required (5MB)
        'max_file_size' => env('MEDIA_IMAGE_MAX_SIZE', 5 * 1024 * 1024),

        // WhatsApp recommended dimensions for campaign images
        'whatsapp' => [
            'header_width' => 800,
            'header_height' => 418,
            'max_size' => 5 * 1024 * 1024, // 5MB
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Video Processing Settings
    |--------------------------------------------------------------------------
    */

    'video' => [
        // Maximum dimensions for compressed video
        'max_width' => env('MEDIA_VIDEO_MAX_WIDTH', 1280),
        'max_height' => env('MEDIA_VIDEO_MAX_HEIGHT', 720),

        // Video codec
        'codec' => env('MEDIA_VIDEO_CODEC', 'libx264'),

        // Audio codec
        'audio_codec' => env('MEDIA_VIDEO_AUDIO_CODEC', 'aac'),

        // Thumbnail dimensions for video
        'thumbnail_width' => env('MEDIA_VIDEO_THUMB_WIDTH', 320),
        'thumbnail_height' => env('MEDIA_VIDEO_THUMB_HEIGHT', 180),

        // Maximum video duration in seconds (5 minutes)
        'max_duration' => env('MEDIA_VIDEO_MAX_DURATION', 300),

        // Maximum file size (50MB)
        'max_file_size' => env('MEDIA_VIDEO_MAX_SIZE', 50 * 1024 * 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audio Processing Settings
    |--------------------------------------------------------------------------
    */

    'audio' => [
        // Maximum file size (16MB - WhatsApp limit)
        'max_file_size' => env('MEDIA_AUDIO_MAX_SIZE', 16 * 1024 * 1024),

        // Maximum duration in seconds (10 minutes)
        'max_duration' => env('MEDIA_AUDIO_MAX_DURATION', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Settings
    |--------------------------------------------------------------------------
    */

    'document' => [
        // Maximum file size (100MB)
        'max_file_size' => env('MEDIA_DOCUMENT_MAX_SIZE', 100 * 1024 * 1024),

        // Allowed MIME types
        'allowed_types' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | FFmpeg Configuration
    |--------------------------------------------------------------------------
    */

    'ffmpeg' => [
        // Path to FFmpeg binary
        'path' => env('FFMPEG_PATH', 'ffmpeg'),

        // Path to FFprobe binary
        'ffprobe_path' => env('FFPROBE_PATH', 'ffprobe'),

        // Processing timeout in seconds
        'timeout' => env('FFMPEG_TIMEOUT', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */

    'queue' => [
        // Queue name for media processing jobs
        'name' => env('MEDIA_QUEUE_NAME', 'media'),

        // Connection to use
        'connection' => env('MEDIA_QUEUE_CONNECTION', null), // null = default

        // Maximum retries
        'tries' => env('MEDIA_QUEUE_TRIES', 3),

        // Timeout in seconds
        'timeout' => env('MEDIA_QUEUE_TIMEOUT', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deduplication Settings
    |--------------------------------------------------------------------------
    */

    'deduplication' => [
        // Enable content hash deduplication
        'enabled' => env('MEDIA_DEDUPLICATION_ENABLED', true),

        // Hash algorithm
        'algorithm' => 'md5',
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN Configuration (Optional)
    |--------------------------------------------------------------------------
    */

    'cdn' => [
        // Enable CDN for media delivery
        'enabled' => env('MEDIA_CDN_ENABLED', false),

        // CDN base URL
        'url' => env('MEDIA_CDN_URL', null),

        // CDN invalidation settings
        'invalidation' => [
            'enabled' => env('MEDIA_CDN_INVALIDATION_ENABLED', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Settings
    |--------------------------------------------------------------------------
    */

    'cleanup' => [
        // Days to keep orphaned media (not attached to any campaign)
        'orphan_retention_days' => env('MEDIA_ORPHAN_RETENTION_DAYS', 30),

        // Days to keep soft-deleted media
        'deleted_retention_days' => env('MEDIA_DELETED_RETENTION_DAYS', 7),
    ],

];
