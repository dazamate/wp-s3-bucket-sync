<?php

namespace Dazamate\S3ImageSync\Mapper;

use Dazamate\S3ImageSync\Dto\S3UploadJob;
use Dazamate\S3ImageSync\Utils\ObjectKey;

class AttachmentMapper {
    public static function register(): void {
        add_filter('s3_image_sync_map_jobs', [__CLASS__, 'map'], 10, 3);
    }

    // Build an upload job for the original file plus every generated size. The
    // jobs filter receives an accumulator so other plugins can add or remove
    // jobs, mirroring the mapper pattern used across the daz plugin family.
    //
    // @param S3UploadJob[] $jobs
    // @return S3UploadJob[]
    public static function map(array $jobs, int $post_id, string $prefix): array {
        $file = get_attached_file($post_id);

        if (!is_string($file) || $file === '') {
            return $jobs;
        }

        $upload_dir = wp_upload_dir();
        $base_dir = rtrim((string) $upload_dir['basedir'], '/');

        $original_relative = self::relative_path($base_dir, $file);

        if ($original_relative === null) {
            return $jobs;
        }

        $mime = (string) get_post_mime_type($post_id);

        $jobs[] = new S3UploadJob(
            local_path: $file,
            object_key: ObjectKey::build($prefix, $post_id, $original_relative),
            mime: $mime,
        );

        $metadata = wp_get_attachment_metadata($post_id);

        if (!is_array($metadata) || empty($metadata['sizes'])) {
            return $jobs;
        }

        $file_dir = dirname($file);
        $relative_dir = dirname($original_relative);
        $relative_prefix = $relative_dir === '.' ? '' : $relative_dir . '/';

        foreach ($metadata['sizes'] as $size) {
            if (!is_array($size) || empty($size['file']) || !is_string($size['file'])) {
                continue;
            }

            $size_file = $file_dir . '/' . $size['file'];
            $size_relative = $relative_prefix . $size['file'];
            $size_mime = is_string($size['mime-type'] ?? null) ? $size['mime-type'] : $mime;

            $jobs[] = new S3UploadJob(
                local_path: $size_file,
                object_key: ObjectKey::build($prefix, $post_id, $size_relative),
                mime: $size_mime,
            );
        }

        return $jobs;
    }

    private static function relative_path(string $base_dir, string $file): ?string {
        if ($base_dir === '') {
            return null;
        }

        if (!str_starts_with($file, $base_dir . '/')) {
            return null;
        }

        return ltrim(substr($file, strlen($base_dir)), '/');
    }
}
