<?php

namespace App\Services\Media;

use App\Models\ChatMedia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

/**
 * Image Processing Service
 * 
 * Handles image compression, thumbnail generation, and WebP conversion
 * for campaign media storage.
 * 
 * Uses Intervention/Image v3 API with Imagick or GD driver.
 */
class ImageProcessor
{
    // ==========================================
    // CONFIGURATION
    // ==========================================

    /** @var int Maximum width for compressed images */
    protected int $maxWidth = 1200;

    /** @var int Maximum height for compressed images */
    protected int $maxHeight = 1200;

    /** @var int JPEG quality for compressed images (0-100) */
    protected int $compressionQuality = 80;

    /** @var int WebP quality (0-100) */
    protected int $webpQuality = 85;

    /** @var int Thumbnail width */
    protected int $thumbnailWidth = 200;

    /** @var int Thumbnail height */
    protected int $thumbnailHeight = 200;

    /** @var ImageManager */
    protected ImageManager $manager;

    /** @var array Supported MIME types */
    protected array $supportedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    // ==========================================
    // CONSTRUCTOR
    // ==========================================

    public function __construct()
    {
        $this->initializeManager();
        $this->loadConfig();
    }

    /**
     * Initialize Image Manager with best available driver
     */
    protected function initializeManager(): void
    {
        // Prefer Imagick if available for better quality
        if (extension_loaded('imagick')) {
            $this->manager = new ImageManager(new ImagickDriver());
            Log::debug('[ImageProcessor] Using Imagick driver');
        } else {
            $this->manager = new ImageManager(new GdDriver());
            Log::debug('[ImageProcessor] Using GD driver');
        }
    }

    /**
     * Load configuration from config/media.php
     */
    protected function loadConfig(): void
    {
        $this->maxWidth = config('media.image.max_width', 1200);
        $this->maxHeight = config('media.image.max_height', 1200);
        $this->compressionQuality = config('media.image.compression_quality', 80);
        $this->webpQuality = config('media.image.webp_quality', 85);
        $this->thumbnailWidth = config('media.image.thumbnail_width', 200);
        $this->thumbnailHeight = config('media.image.thumbnail_height', 200);
    }

    // ==========================================
    // PUBLIC METHODS
    // ==========================================

    /**
     * Process an image file (compress, generate thumbnail, convert to WebP)
     * 
     * @param string $sourcePath Absolute path to source image
     * @param string $outputDirectory Directory for processed files
     * @param array $options Processing options
     * @return array Paths to processed files
     * @throws \Exception
     */
    public function process(string $sourcePath, string $outputDirectory, array $options = []): array
    {
        if (!file_exists($sourcePath)) {
            throw new \Exception("Source image not found: {$sourcePath}");
        }

        $mimeType = mime_content_type($sourcePath);
        if (!$this->isSupported($mimeType)) {
            throw new \Exception("Unsupported image type: {$mimeType}");
        }

        $filename = pathinfo($sourcePath, PATHINFO_FILENAME);
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);

        // Ensure output directory exists
        if (!is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }

        $results = [
            'original' => $sourcePath,
            'compressed' => null,
            'thumbnail' => null,
            'webp' => null,
            'dimensions' => null,
            'file_sizes' => [],
        ];

        try {
            // Read the source image
            $image = $this->manager->read($sourcePath);
            
            // Get original dimensions
            $results['dimensions'] = [
                'width' => $image->width(),
                'height' => $image->height(),
            ];

            // Compress image (resize if needed)
            $compress = $options['compress'] ?? true;
            if ($compress) {
                $results['compressed'] = $this->compress(
                    $image,
                    $outputDirectory,
                    $filename,
                    $extension
                );
                $results['file_sizes']['compressed'] = filesize($results['compressed']);
            }

            // Generate thumbnail
            $generateThumbnail = $options['thumbnail'] ?? true;
            if ($generateThumbnail) {
                $results['thumbnail'] = $this->generateThumbnail(
                    $image,
                    $outputDirectory,
                    $filename,
                    $extension
                );
                $results['file_sizes']['thumbnail'] = filesize($results['thumbnail']);
            }

            // Convert to WebP
            $generateWebp = $options['webp'] ?? true;
            if ($generateWebp) {
                $results['webp'] = $this->convertToWebP(
                    $sourcePath,
                    $outputDirectory,
                    $filename
                );
                $results['file_sizes']['webp'] = filesize($results['webp']);
            }

            Log::info('[ImageProcessor] Successfully processed image', [
                'source' => $sourcePath,
                'results' => array_map(fn($v) => is_string($v) ? basename($v) : $v, $results),
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('[ImageProcessor] Failed to process image', [
                'source' => $sourcePath,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if MIME type is supported
     */
    public function isSupported(string $mimeType): bool
    {
        return in_array($mimeType, $this->supportedMimeTypes);
    }

    /**
     * Get image dimensions
     */
    public function getDimensions(string $filePath): array
    {
        $image = $this->manager->read($filePath);
        return [
            'width' => $image->width(),
            'height' => $image->height(),
        ];
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
     * Compress image by resizing and reducing quality
     */
    protected function compress($image, string $outputDir, string $filename, string $extension): string
    {
        $outputPath = "{$outputDir}/{$filename}_compressed.{$extension}";

        // Clone the image for compression
        $compressedImage = clone $image;

        // Resize if larger than max dimensions (maintain aspect ratio)
        $width = $compressedImage->width();
        $height = $compressedImage->height();

        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            $compressedImage->scaleDown($this->maxWidth, $this->maxHeight);
        }

        // Encode and save based on extension
        $this->saveImage($compressedImage, $outputPath, $extension, $this->compressionQuality);

        return $outputPath;
    }

    /**
     * Generate thumbnail image
     */
    protected function generateThumbnail($image, string $outputDir, string $filename, string $extension): string
    {
        $outputPath = "{$outputDir}/{$filename}_thumb.{$extension}";

        // Clone the image for thumbnail
        $thumbnailImage = clone $image;

        // Cover (crop to fit) for consistent thumbnail dimensions
        $thumbnailImage->cover($this->thumbnailWidth, $this->thumbnailHeight);

        // Save thumbnail
        $this->saveImage($thumbnailImage, $outputPath, $extension, $this->compressionQuality);

        return $outputPath;
    }

    /**
     * Convert image to WebP format
     */
    protected function convertToWebP(string $sourcePath, string $outputDir, string $filename): string
    {
        $outputPath = "{$outputDir}/{$filename}.webp";

        // Read fresh for WebP conversion
        $webpImage = $this->manager->read($sourcePath);

        // Resize if needed
        if ($webpImage->width() > $this->maxWidth || $webpImage->height() > $this->maxHeight) {
            $webpImage->scaleDown($this->maxWidth, $this->maxHeight);
        }

        // Encode to WebP
        $encoded = $webpImage->toWebp($this->webpQuality);
        $encoded->save($outputPath);

        return $outputPath;
    }

    /**
     * Save image to file with appropriate encoding
     */
    protected function saveImage($image, string $path, string $extension, int $quality): void
    {
        $extension = strtolower($extension);

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $encoded = $image->toJpeg($quality);
                break;
            case 'png':
                // PNG uses 0-9 compression level, not quality
                // Higher = more compression
                $encoded = $image->toPng();
                break;
            case 'gif':
                $encoded = $image->toGif();
                break;
            case 'webp':
                $encoded = $image->toWebp($quality);
                break;
            default:
                $encoded = $image->toJpeg($quality);
        }

        $encoded->save($path);
    }

    // ==========================================
    // UTILITY METHODS
    // ==========================================

    /**
     * Get optimal quality based on file size target
     */
    public function calculateOptimalQuality(string $filePath, int $targetSizeKb = 500): int
    {
        $currentSize = filesize($filePath) / 1024; // KB
        
        if ($currentSize <= $targetSizeKb) {
            return 90; // Already small, keep high quality
        }

        // Rough calculation: reduce quality proportionally
        $ratio = $targetSizeKb / $currentSize;
        $quality = (int) min(90, max(40, $ratio * 100));

        return $quality;
    }

    /**
     * Check if image needs processing (already optimized)
     */
    public function needsProcessing(string $filePath): bool
    {
        $size = filesize($filePath);
        $maxSizeBytes = config('media.image.max_file_size', 5 * 1024 * 1024); // 5MB default

        // If file is larger than max, needs processing
        if ($size > $maxSizeBytes) {
            return true;
        }

        // Check dimensions
        try {
            $dimensions = $this->getDimensions($filePath);
            if ($dimensions['width'] > $this->maxWidth || $dimensions['height'] > $this->maxHeight) {
                return true;
            }
        } catch (\Exception $e) {
            return true; // If we can't read dimensions, process it
        }

        return false;
    }

    /**
     * Get available driver name
     */
    public function getDriverName(): string
    {
        return extension_loaded('imagick') ? 'imagick' : 'gd';
    }
}
