<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Video Processing Service
 * 
 * Handles video compression and thumbnail generation for campaign media.
 * Requires FFmpeg to be installed on the system.
 * 
 * Note: If FFmpeg is not available, methods will gracefully skip processing.
 */
class VideoProcessor
{
    // ==========================================
    // CONFIGURATION
    // ==========================================

    /** @var bool Whether FFmpeg is available */
    protected bool $ffmpegAvailable = false;

    /** @var string FFmpeg binary path */
    protected string $ffmpegPath = 'ffmpeg';

    /** @var string FFprobe binary path */
    protected string $ffprobePath = 'ffprobe';

    /** @var int Maximum video width */
    protected int $maxWidth = 1280;

    /** @var int Maximum video height */
    protected int $maxHeight = 720;

    /** @var string Video codec */
    protected string $videoCodec = 'libx264';

    /** @var string Audio codec */
    protected string $audioCodec = 'aac';

    /** @var int Thumbnail width */
    protected int $thumbnailWidth = 320;

    /** @var int Thumbnail height */
    protected int $thumbnailHeight = 180;

    /** @var array Supported MIME types */
    protected array $supportedMimeTypes = [
        'video/mp4',
        'video/mpeg',
        'video/quicktime',
        'video/x-msvideo',
        'video/webm',
        'video/3gpp',
    ];

    // ==========================================
    // CONSTRUCTOR
    // ==========================================

    public function __construct()
    {
        $this->loadConfig();
        $this->checkFfmpegAvailability();
    }

    /**
     * Load configuration from config/media.php
     */
    protected function loadConfig(): void
    {
        $this->ffmpegPath = config('media.ffmpeg.path', 'ffmpeg');
        $this->ffprobePath = config('media.ffmpeg.ffprobe_path', 'ffprobe');
        $this->maxWidth = config('media.video.max_width', 1280);
        $this->maxHeight = config('media.video.max_height', 720);
        $this->videoCodec = config('media.video.codec', 'libx264');
        $this->audioCodec = config('media.video.audio_codec', 'aac');
        $this->thumbnailWidth = config('media.video.thumbnail_width', 320);
        $this->thumbnailHeight = config('media.video.thumbnail_height', 180);
    }

    /**
     * Check if FFmpeg is available on the system
     */
    protected function checkFfmpegAvailability(): void
    {
        try {
            $result = Process::run("{$this->ffmpegPath} -version");
            $this->ffmpegAvailable = $result->successful();
            
            if ($this->ffmpegAvailable) {
                Log::debug('[VideoProcessor] FFmpeg available');
            } else {
                Log::warning('[VideoProcessor] FFmpeg not available - video processing disabled');
            }
        } catch (\Exception $e) {
            $this->ffmpegAvailable = false;
            Log::warning('[VideoProcessor] FFmpeg check failed', ['error' => $e->getMessage()]);
        }
    }

    // ==========================================
    // PUBLIC METHODS
    // ==========================================

    /**
     * Check if FFmpeg is available
     */
    public function isAvailable(): bool
    {
        return $this->ffmpegAvailable;
    }

    /**
     * Check if MIME type is supported
     */
    public function isSupported(string $mimeType): bool
    {
        return in_array($mimeType, $this->supportedMimeTypes);
    }

    /**
     * Process a video file (compress and generate thumbnail)
     * 
     * @param string $sourcePath Absolute path to source video
     * @param string $outputDirectory Directory for processed files
     * @param array $options Processing options
     * @return array Paths to processed files
     */
    public function process(string $sourcePath, string $outputDirectory, array $options = []): array
    {
        $results = [
            'original' => $sourcePath,
            'compressed' => null,
            'thumbnail' => null,
            'metadata' => null,
            'skipped' => false,
            'skip_reason' => null,
        ];

        if (!file_exists($sourcePath)) {
            $results['skip_reason'] = 'Source file not found';
            $results['skipped'] = true;
            return $results;
        }

        if (!$this->ffmpegAvailable) {
            $results['skip_reason'] = 'FFmpeg not available';
            $results['skipped'] = true;
            Log::info('[VideoProcessor] Skipping video processing - FFmpeg not available', [
                'source' => $sourcePath,
            ]);
            return $results;
        }

        $mimeType = mime_content_type($sourcePath);
        if (!$this->isSupported($mimeType)) {
            $results['skip_reason'] = "Unsupported MIME type: {$mimeType}";
            $results['skipped'] = true;
            return $results;
        }

        // Ensure output directory exists
        if (!is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }

        $filename = pathinfo($sourcePath, PATHINFO_FILENAME);

        try {
            // Get video metadata
            $results['metadata'] = $this->getMetadata($sourcePath);

            // Compress video
            $compress = $options['compress'] ?? true;
            if ($compress) {
                $results['compressed'] = $this->compress(
                    $sourcePath,
                    $outputDirectory,
                    $filename
                );
            }

            // Generate thumbnail
            $generateThumbnail = $options['thumbnail'] ?? true;
            if ($generateThumbnail) {
                $results['thumbnail'] = $this->generateThumbnail(
                    $sourcePath,
                    $outputDirectory,
                    $filename
                );
            }

            Log::info('[VideoProcessor] Successfully processed video', [
                'source' => $sourcePath,
                'compressed' => $results['compressed'] ? basename($results['compressed']) : null,
                'thumbnail' => $results['thumbnail'] ? basename($results['thumbnail']) : null,
            ]);

        } catch (\Exception $e) {
            Log::error('[VideoProcessor] Failed to process video', [
                'source' => $sourcePath,
                'error' => $e->getMessage(),
            ]);
            $results['skipped'] = true;
            $results['skip_reason'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Get video metadata using FFprobe
     */
    public function getMetadata(string $filePath): ?array
    {
        if (!$this->ffmpegAvailable) {
            return null;
        }

        try {
            $command = sprintf(
                '%s -v quiet -print_format json -show_format -show_streams "%s"',
                $this->ffprobePath,
                $filePath
            );

            $result = Process::run($command);

            if (!$result->successful()) {
                Log::warning('[VideoProcessor] FFprobe failed', [
                    'file' => $filePath,
                    'error' => $result->errorOutput(),
                ]);
                return null;
            }

            $data = json_decode($result->output(), true);

            // Extract relevant metadata
            $videoStream = collect($data['streams'] ?? [])->firstWhere('codec_type', 'video');
            $audioStream = collect($data['streams'] ?? [])->firstWhere('codec_type', 'audio');
            $format = $data['format'] ?? [];

            return [
                'duration' => (float) ($format['duration'] ?? 0),
                'size' => (int) ($format['size'] ?? 0),
                'bitrate' => (int) ($format['bit_rate'] ?? 0),
                'format' => $format['format_name'] ?? null,
                'video' => $videoStream ? [
                    'codec' => $videoStream['codec_name'] ?? null,
                    'width' => (int) ($videoStream['width'] ?? 0),
                    'height' => (int) ($videoStream['height'] ?? 0),
                    'fps' => $this->parseFrameRate($videoStream['r_frame_rate'] ?? '0/1'),
                ] : null,
                'audio' => $audioStream ? [
                    'codec' => $audioStream['codec_name'] ?? null,
                    'sample_rate' => (int) ($audioStream['sample_rate'] ?? 0),
                    'channels' => (int) ($audioStream['channels'] ?? 0),
                ] : null,
            ];

        } catch (\Exception $e) {
            Log::error('[VideoProcessor] Failed to get metadata', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Calculate content hash for deduplication
     */
    public function getContentHash(string $filePath): string
    {
        return md5_file($filePath);
    }

    // ==========================================
    // PROCESSING METHODS
    // ==========================================

    /**
     * Compress video using FFmpeg
     */
    protected function compress(string $sourcePath, string $outputDir, string $filename): ?string
    {
        $outputPath = "{$outputDir}/{$filename}_compressed.mp4";

        // Get source dimensions
        $metadata = $this->getMetadata($sourcePath);
        $sourceWidth = $metadata['video']['width'] ?? 1920;
        $sourceHeight = $metadata['video']['height'] ?? 1080;

        // Calculate target dimensions (maintain aspect ratio)
        [$targetWidth, $targetHeight] = $this->calculateDimensions(
            $sourceWidth,
            $sourceHeight,
            $this->maxWidth,
            $this->maxHeight
        );

        // Ensure even dimensions (required by most codecs)
        $targetWidth = $targetWidth - ($targetWidth % 2);
        $targetHeight = $targetHeight - ($targetHeight % 2);

        // Build FFmpeg command
        $command = sprintf(
            '%s -i "%s" -vf "scale=%d:%d" -c:v %s -preset medium -crf 23 -c:a %s -b:a 128k -movflags +faststart -y "%s" 2>&1',
            $this->ffmpegPath,
            $sourcePath,
            $targetWidth,
            $targetHeight,
            $this->videoCodec,
            $this->audioCodec,
            $outputPath
        );

        $result = Process::timeout(600)->run($command);

        if (!$result->successful()) {
            Log::error('[VideoProcessor] Compression failed', [
                'source' => $sourcePath,
                'output' => $result->errorOutput(),
            ]);
            return null;
        }

        return file_exists($outputPath) ? $outputPath : null;
    }

    /**
     * Generate video thumbnail
     */
    protected function generateThumbnail(string $sourcePath, string $outputDir, string $filename): ?string
    {
        $outputPath = "{$outputDir}/{$filename}_thumb.jpg";

        // Get video duration to extract thumbnail from middle
        $metadata = $this->getMetadata($sourcePath);
        $duration = $metadata['duration'] ?? 0;
        $seekTime = max(0, $duration / 2); // Middle of video

        // Build FFmpeg command
        $command = sprintf(
            '%s -ss %f -i "%s" -vf "scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2" -frames:v 1 -y "%s" 2>&1',
            $this->ffmpegPath,
            $seekTime,
            $sourcePath,
            $this->thumbnailWidth,
            $this->thumbnailHeight,
            $this->thumbnailWidth,
            $this->thumbnailHeight,
            $outputPath
        );

        $result = Process::timeout(60)->run($command);

        if (!$result->successful()) {
            // Try extracting from beginning if middle fails
            $command = sprintf(
                '%s -i "%s" -vf "scale=%d:%d:force_original_aspect_ratio=decrease" -frames:v 1 -y "%s" 2>&1',
                $this->ffmpegPath,
                $sourcePath,
                $this->thumbnailWidth,
                $this->thumbnailHeight,
                $outputPath
            );
            $result = Process::timeout(60)->run($command);
        }

        if (!$result->successful()) {
            Log::error('[VideoProcessor] Thumbnail generation failed', [
                'source' => $sourcePath,
                'output' => $result->errorOutput(),
            ]);
            return null;
        }

        return file_exists($outputPath) ? $outputPath : null;
    }

    // ==========================================
    // UTILITY METHODS
    // ==========================================

    /**
     * Calculate target dimensions maintaining aspect ratio
     */
    protected function calculateDimensions(int $srcWidth, int $srcHeight, int $maxWidth, int $maxHeight): array
    {
        if ($srcWidth <= $maxWidth && $srcHeight <= $maxHeight) {
            return [$srcWidth, $srcHeight];
        }

        $widthRatio = $maxWidth / $srcWidth;
        $heightRatio = $maxHeight / $srcHeight;
        $ratio = min($widthRatio, $heightRatio);

        return [
            (int) round($srcWidth * $ratio),
            (int) round($srcHeight * $ratio),
        ];
    }

    /**
     * Parse frame rate string (e.g., "30/1" or "29.97")
     */
    protected function parseFrameRate(string $frameRate): float
    {
        if (str_contains($frameRate, '/')) {
            $parts = explode('/', $frameRate);
            if (count($parts) === 2 && $parts[1] > 0) {
                return round($parts[0] / $parts[1], 2);
            }
        }
        return (float) $frameRate;
    }

    /**
     * Check if video needs processing
     */
    public function needsProcessing(string $filePath): bool
    {
        if (!$this->ffmpegAvailable) {
            return false; // Can't process without FFmpeg
        }

        $metadata = $this->getMetadata($filePath);
        if (!$metadata || !$metadata['video']) {
            return false;
        }

        // Check dimensions
        if ($metadata['video']['width'] > $this->maxWidth || 
            $metadata['video']['height'] > $this->maxHeight) {
            return true;
        }

        // Check file size (> 50MB)
        if ($metadata['size'] > 50 * 1024 * 1024) {
            return true;
        }

        return false;
    }

    /**
     * Check if video needs conversion to MP4 format
     * WhatsApp Web JS requires MP4 with H.264 codec
     */
    public function needsConversion(string $filePath): bool
    {
        if (!$this->ffmpegAvailable) {
            return false;
        }

        $mimeType = mime_content_type($filePath);
        
        // These formats need conversion to MP4
        $needsConversion = [
            'video/quicktime',      // .mov
            'video/x-msvideo',      // .avi
            'video/x-ms-wmv',       // .wmv
            'video/webm',           // .webm (sometimes has issues)
            'video/x-matroska',     // .mkv
        ];

        return in_array($mimeType, $needsConversion);
    }

    /**
     * Convert video to MP4 format (H.264 + AAC)
     * Required for WhatsApp Web JS compatibility
     * 
     * @param string $sourcePath Source video file path
     * @param string|null $outputPath Optional output path (defaults to temp directory)
     * @return string|null Path to converted MP4 file, or null on failure
     */
    public function convertToMp4(string $sourcePath, ?string $outputPath = null): ?string
    {
        if (!$this->ffmpegAvailable) {
            Log::warning('[VideoProcessor] Cannot convert video - FFmpeg not available');
            return null;
        }

        if (!file_exists($sourcePath)) {
            Log::error('[VideoProcessor] Source file not found for conversion', ['path' => $sourcePath]);
            return null;
        }

        // Generate output path if not provided
        if (!$outputPath) {
            $tempDir = sys_get_temp_dir();
            $filename = pathinfo($sourcePath, PATHINFO_FILENAME);
            $outputPath = "{$tempDir}/{$filename}_converted.mp4";
        }

        // Get source dimensions
        $metadata = $this->getMetadata($sourcePath);
        $sourceWidth = $metadata['video']['width'] ?? 1920;
        $sourceHeight = $metadata['video']['height'] ?? 1080;

        // Calculate target dimensions (maintain aspect ratio, max 1280x720 for WhatsApp)
        [$targetWidth, $targetHeight] = $this->calculateDimensions(
            $sourceWidth,
            $sourceHeight,
            $this->maxWidth,
            $this->maxHeight
        );

        // Ensure even dimensions (required by H.264)
        $targetWidth = $targetWidth - ($targetWidth % 2);
        $targetHeight = $targetHeight - ($targetHeight % 2);

        // Build FFmpeg command for WhatsApp-compatible MP4
        // Using H.264 video codec and AAC audio codec
        $command = sprintf(
            '%s -i "%s" -vf "scale=%d:%d" -c:v %s -preset medium -crf 23 -c:a %s -b:a 128k -movflags +faststart -y "%s" 2>&1',
            $this->ffmpegPath,
            $sourcePath,
            $targetWidth,
            $targetHeight,
            $this->videoCodec,  // libx264
            $this->audioCodec,  // aac
            $outputPath
        );

        Log::info('[VideoProcessor] Converting video to MP4', [
            'source' => basename($sourcePath),
            'output' => basename($outputPath),
            'dimensions' => "{$targetWidth}x{$targetHeight}",
        ]);

        $result = Process::timeout(600)->run($command);

        if (!$result->successful()) {
            Log::error('[VideoProcessor] Video conversion failed', [
                'source' => $sourcePath,
                'error' => $result->errorOutput(),
            ]);
            return null;
        }

        if (!file_exists($outputPath)) {
            Log::error('[VideoProcessor] Converted file not found', ['path' => $outputPath]);
            return null;
        }

        Log::info('[VideoProcessor] Video converted successfully', [
            'source' => basename($sourcePath),
            'output' => basename($outputPath),
            'original_size' => filesize($sourcePath),
            'converted_size' => filesize($outputPath),
        ]);

        return $outputPath;
    }

    /**
     * Get video duration in seconds
     */
    public function getDuration(string $filePath): float
    {
        $metadata = $this->getMetadata($filePath);
        return $metadata['duration'] ?? 0;
    }
}
