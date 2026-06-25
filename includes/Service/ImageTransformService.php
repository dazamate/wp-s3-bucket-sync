<?php

namespace Dazamate\S3ImageSync\Service;

use Dazamate\S3ImageSync\Enum\TransformMethod;
use Intervention\Image\ImageManager;

// Thin wrapper around intervention/image. It selects whichever PHP imaging
// extension is available (Imagick preferred, then GD), reports which output
// formats that runtime can actually produce, and re-encodes a source file into
// the requested format/quality. All failures are reported as strings rather than
// thrown so the sync flow can record them against the attachment.
class ImageTransformService {
    private static ?ImageManager $manager = null;

    // Multiplier applied to the raw bitmap size (width * height * channels) to
    // approximate the real peak: decoders allocate intermediate buffers and the
    // encode step holds a second copy. Deliberately generous to fail safe.
    private const MEMORY_FUDGE = 2.2;

    // Bitmaps are stored as 4 bytes/pixel (RGBA) regardless of the source's
    // reported channel count.
    private const BYTES_PER_PIXEL = 4;

    // Whether the runtime can encode the given method's output format. JPEG is
    // assumed always available; WebP/AVIF depend on the active driver's build.
    public static function is_supported(TransformMethod $method): bool {
        if (!$method->is_enabled()) {
            return false;
        }

        if (extension_loaded('imagick')) {
            return self::imagick_supports($method);
        }

        if (extension_loaded('gd')) {
            return self::gd_supports($method);
        }

        return false;
    }

    // Re-encode $source into $dest using the method's format and quality.
    // Returns null on success or an error message on failure.
    public static function transform(
        string $source,
        string $dest,
        TransformMethod $method,
        int $quality
    ): ?string {
        if (!$method->is_enabled()) {
            return 'Image transform error: no transform method selected';
        }

        if (!is_readable($source)) {
            return sprintf('Image transform error: source not readable (%s)', $source);
        }

        $manager = self::manager();

        if ($manager === null) {
            return 'Image transform error: no supported image library (GD or Imagick) is available';
        }

        // WordPress raises the limit for its own resizing; do the same so we
        // inherit the configured headroom even if our hook runs in isolation.
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('image');
        }

        $memory_error = self::memory_check($source);

        if ($memory_error !== null) {
            return $memory_error;
        }

        try {
            $image = $manager->read($source);
            $image->save($dest, quality: $quality);
        } catch (\Throwable $e) {
            return sprintf('Image transform error: %s', $e->getMessage());
        }

        return null;
    }

    // Estimate the memory needed to decode $source and refuse if it won't fit in
    // the remaining headroom. Returns an error string when the image is too large
    // to process safely, or null when there is enough room (or the limit is
    // unbounded / dimensions are unknown). This keeps a single oversized upload
    // from fataling the whole request and aborting the S3 sync.
    private static function memory_check(string $source): ?string {
        $size = @getimagesize($source);

        if (!is_array($size) || empty($size[0]) || empty($size[1])) {
            return null;
        }

        $required = self::estimate_required_bytes((int) $size[0], (int) $size[1]);

        $limit = self::parse_memory_limit((string) ini_get('memory_limit'));

        if ($limit <= 0) {
            return null;
        }

        $available = $limit - memory_get_usage(true);

        if ($required > $available) {
            return sprintf(
                'Image transform skipped: %dx%d image needs ~%dMB but only ~%dMB is available. Increase PHP memory_limit.',
                (int) $size[0],
                (int) $size[1],
                (int) ceil($required / 1048576),
                (int) max(0, floor($available / 1048576))
            );
        }

        return null;
    }

    // Raw bitmap footprint with a safety multiplier. Pure so it can be asserted
    // in tests without touching the runtime.
    public static function estimate_required_bytes(int $width, int $height): int {
        return (int) ceil($width * $height * self::BYTES_PER_PIXEL * self::MEMORY_FUDGE);
    }

    // Convert a PHP memory_limit string ("256M", "1G", "-1", "") to bytes.
    // Returns -1 for unbounded/unset so callers can skip the check. Pure.
    public static function parse_memory_limit(string $value): int {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit = strtolower($value[strlen($value) - 1]);
        $number = (int) $value;

        return match ($unit) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => (int) $value,
        };
    }

    private static function manager(): ?ImageManager {
        if (self::$manager instanceof ImageManager) {
            return self::$manager;
        }

        if (extension_loaded('imagick')) {
            return self::$manager = ImageManager::imagick();
        }

        if (extension_loaded('gd')) {
            return self::$manager = ImageManager::gd();
        }

        return null;
    }

    private static function gd_supports(TransformMethod $method): bool {
        return match ($method) {
            TransformMethod::WEBP => function_exists('imagewebp'),
            TransformMethod::AVIF => function_exists('imageavif'),
            default              => true,
        };
    }

    private static function imagick_supports(TransformMethod $method): bool {
        $format = match ($method) {
            TransformMethod::WEBP => 'WEBP',
            TransformMethod::AVIF => 'AVIF',
            default              => 'JPEG',
        };

        try {
            return \Imagick::queryFormats($format) !== [];
        } catch (\Throwable) {
            return false;
        }
    }
}
