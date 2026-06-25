<?php

namespace Dazamate\S3ImageSync\Manager;

use Dazamate\S3ImageSync\Dto\S3UploadJob;
use Dazamate\S3ImageSync\Dto\TransformSettings;
use Dazamate\S3ImageSync\Enum\MetaKeys;
use Dazamate\S3ImageSync\Enum\TransformMethod;
use Dazamate\S3ImageSync\Service\ImageTransformService;
use Dazamate\S3ImageSync\Utils\ErrorManager;
use Dazamate\S3ImageSync\Utils\ObjectKey;

// Produces optimised copies (JPEG / WebP / AVIF) of the original image plus
// every generated sub-size, writing them alongside WordPress's own files so the
// untouched originals remain available for a full re-sync. The optimised copies
// are recorded against the attachment and added to the S3 upload job list by the
// existing mapper pipeline, so they end up in the bucket without altering
// WordPress's native srcset behaviour.
class ImageTransformManager {
    // Transform failures collected during metadata generation, keyed by post id.
    // Surfaced via the jobs filter, which runs after the sync flow clears stale
    // errors, so the messages survive to be shown in the admin.
    private static array $deferred_errors = [];

    public static function load_hooks(): void {
        // Priority 9 so optimised copies exist before the sync flow (priority 10)
        // collects upload jobs.
        add_filter('wp_generate_attachment_metadata', [__CLASS__, 'on_metadata_generated'], 9, 2);
        // Priority 11 so variant jobs are appended after the core mapper's jobs.
        add_filter('s3_image_sync_map_jobs', [__CLASS__, 'map_variant_jobs'], 11, 3);
        // Priority 20 so the S3 delete (priority 10) can still read the variant
        // list before the local copies are removed.
        add_action('delete_attachment', [__CLASS__, 'on_delete'], 20);
    }

    public static function on_metadata_generated(array $metadata, int $post_id): array {
        $settings = self::get_settings();

        if (!$settings->is_enabled()) {
            return $metadata;
        }

        if (!ImageTransformService::is_supported($settings->method)) {
            self::$deferred_errors[$post_id][] = sprintf(
                'Image transform skipped: the server cannot encode %s images',
                strtoupper($settings->method->value)
            );
            return $metadata;
        }

        $file = get_attached_file($post_id);

        if (!is_string($file) || $file === '' || !is_readable($file)) {
            return $metadata;
        }

        $upload_dir = wp_upload_dir();
        $base_dir = rtrim((string) $upload_dir['basedir'], '/');

        if (self::relative_path($base_dir, $file) === null) {
            return $metadata;
        }

        $sources = [$file];
        $file_dir = dirname($file);

        if (is_array($metadata['sizes'] ?? null)) {
            foreach ($metadata['sizes'] as $size) {
                if (is_array($size) && !empty($size['file']) && is_string($size['file'])) {
                    $sources[] = $file_dir . '/' . $size['file'];
                }
            }
        }

        $variants = [];
        $errors = [];

        foreach (array_unique($sources) as $source) {
            $original_relative = self::relative_path($base_dir, $source);

            if ($original_relative === null) {
                continue;
            }

            $variant_relative = self::transform_one($source, $base_dir, $settings, $errors);

            if ($variant_relative !== null) {
                // Keyed by the untouched WordPress file so the serve layer can map
                // a requested size back to its optimised copy.
                $variants[$original_relative] = $variant_relative;
            }
        }

        if ($errors !== []) {
            self::$deferred_errors[$post_id] = array_merge(
                self::$deferred_errors[$post_id] ?? [],
                $errors
            );
        }

        if ($variants !== []) {
            update_post_meta($post_id, MetaKeys::S3_TRANSFORM_VARIANTS_META_KEY->value, $variants);
        } else {
            delete_post_meta($post_id, MetaKeys::S3_TRANSFORM_VARIANTS_META_KEY->value);
        }

        return $metadata;
    }

    // @param S3UploadJob[] $jobs
    // @return S3UploadJob[]
    public static function map_variant_jobs(array $jobs, int $post_id, string $prefix): array {
        if (!empty(self::$deferred_errors[$post_id])) {
            ErrorManager::add($post_id, self::$deferred_errors[$post_id]);
            unset(self::$deferred_errors[$post_id]);
        }

        $variants = get_post_meta($post_id, MetaKeys::S3_TRANSFORM_VARIANTS_META_KEY->value, true);

        if (!is_array($variants) || $variants === []) {
            return $jobs;
        }

        $upload_dir = wp_upload_dir();
        $base_dir = rtrim((string) $upload_dir['basedir'], '/');

        foreach ($variants as $relative) {
            if (!is_string($relative) || $relative === '') {
                continue;
            }

            $jobs[] = new S3UploadJob(
                local_path: $base_dir . '/' . $relative,
                object_key: ObjectKey::build($prefix, $relative),
                mime: self::mime_for($relative),
            );
        }

        return $jobs;
    }

    public static function on_delete(int $post_id): void {
        $variants = get_post_meta($post_id, MetaKeys::S3_TRANSFORM_VARIANTS_META_KEY->value, true);

        if (!is_array($variants)) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $base_dir = rtrim((string) $upload_dir['basedir'], '/');

        foreach ($variants as $relative) {
            if (!is_string($relative) || $relative === '') {
                continue;
            }

            $path = $base_dir . '/' . $relative;

            if (is_file($path)) {
                wp_delete_file($path);
            }
        }

        delete_post_meta($post_id, MetaKeys::S3_TRANSFORM_VARIANTS_META_KEY->value);
    }

    // Encode a single source file into the configured format. Returns the path of
    // the optimised copy relative to the uploads base dir, or null on failure.
    // @param string[] $errors
    private static function transform_one(
        string $source,
        string $base_dir,
        TransformSettings $settings,
        array &$errors
    ): ?string {
        if (!is_readable($source)) {
            return null;
        }

        $dest = self::dest_path($source, $settings->method);

        $error = ImageTransformService::transform($source, $dest, $settings->method, $settings->quality);

        if ($error !== null) {
            $errors[] = $error;
            return null;
        }

        return self::relative_path($base_dir, $dest);
    }

    // Build a collision-free destination path next to the source. Differing
    // extensions (webp/avif) never clash with the source; for same-extension
    // optimisation (jpeg) an "-opt" suffix keeps the untouched original intact.
    private static function dest_path(string $source, TransformMethod $method): string {
        $dir = dirname($source);
        $name = pathinfo($source, PATHINFO_FILENAME);
        $ext = $method->extension();

        $dest = $dir . '/' . $name . '.' . $ext;

        if ($dest === $source) {
            $dest = $dir . '/' . $name . '-opt.' . $ext;
        }

        return $dest;
    }

    private static function mime_for(string $relative): string {
        return match (strtolower((string) pathinfo($relative, PATHINFO_EXTENSION))) {
            'webp'        => 'image/webp',
            'avif'        => 'image/avif',
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            default       => 'application/octet-stream',
        };
    }

    private static function relative_path(string $base_dir, string $file): ?string {
        if ($base_dir === '' || !str_starts_with($file, $base_dir . '/')) {
            return null;
        }

        return ltrim(substr($file, strlen($base_dir)), '/');
    }

    private static function get_settings(): TransformSettings {
        return apply_filters('get_transform_settings', TransformSettings::from_array([]));
    }
}
