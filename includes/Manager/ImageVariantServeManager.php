<?php

namespace Dazamate\S3ImageSync\Manager;

use Dazamate\S3ImageSync\Enum\MetaKeys;

// Serves an attachment's images from its S3/CDN location. Because every file of
// an attachment (original, generated sizes and optimised variants) is stored in
// the same {prefix}/{post_id}/ folder, each requested image URL is rebased onto
// the directory of the attachment's recorded S3 url and, where an optimised
// variant exists, its filename is swapped for the variant. This decouples
// serving from WordPress's uploads path layout, so the flattened key scheme
// resolves correctly. Runs after the S3 url rewrite.
class ImageVariantServeManager {
    public static function load_hooks(): void {
        add_filter('wp_get_attachment_image_src', [__CLASS__, 'rewrite_image_src'], 20, 2);
        add_filter('wp_calculate_image_srcset', [__CLASS__, 'rewrite_srcset'], 20, 5);
    }

    // @param mixed $image  [url, width, height, is_intermediate] or false
    // @return mixed
    public static function rewrite_image_src($image, $attachment_id) {
        if (!is_array($image) || !isset($image[0]) || !is_string($image[0])) {
            return $image;
        }

        $base = self::base_url((int) $attachment_id);

        if ($base === null) {
            return $image;
        }

        $image[0] = self::rebase($image[0], $base, self::basename_map((int) $attachment_id));

        return $image;
    }

    // @param mixed $sources  array of srcset descriptors keyed by width
    // @return mixed
    public static function rewrite_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        if (!is_array($sources)) {
            return $sources;
        }

        $base = self::base_url((int) $attachment_id);

        if ($base === null) {
            return $sources;
        }

        $map = self::basename_map((int) $attachment_id);

        foreach ($sources as $key => $source) {
            if (is_array($source) && isset($source['url']) && is_string($source['url'])) {
                $sources[$key]['url'] = self::rebase($source['url'], $base, $map);
            }
        }

        return $sources;
    }

    // The directory the attachment's files live in on S3/CDN, derived from the
    // recorded original url (e.g. https://cdn/media/42/photo.jpg => …/media/42).
    // Returns null when the attachment has not been synced.
    private static function base_url(int $post_id): ?string {
        $s3_url = get_post_meta($post_id, MetaKeys::S3_URL_META_KEY->value, true);

        if (!is_string($s3_url) || $s3_url === '') {
            return null;
        }

        $last_slash = strrpos($s3_url, '/');

        if ($last_slash === false) {
            return null;
        }

        return substr($s3_url, 0, $last_slash);
    }

    // Map of original filename => optimised filename for one attachment.
    // @return array<string, string>
    private static function basename_map(int $post_id): array {
        $variants = get_post_meta($post_id, MetaKeys::S3_TRANSFORM_VARIANTS_META_KEY->value, true);

        if (!is_array($variants)) {
            return [];
        }

        $map = [];

        foreach ($variants as $original => $variant) {
            if (!is_string($original) || !is_string($variant) || $variant === '') {
                continue;
            }

            $map[basename($original)] = basename($variant);
        }

        return $map;
    }

    // Rebuild a url so it points at the attachment's S3 directory, swapping the
    // filename for its optimised variant when one is recorded and preserving any
    // query string or fragment.
    // @param array<string, string> $map
    private static function rebase(string $url, string $base, array $map): string {
        $parts = parse_url($url);
        $parts = is_array($parts) ? $parts : [];

        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        $filename = $path !== '' ? basename($path) : basename($url);

        if ($filename === '') {
            return $url;
        }

        if (isset($map[$filename])) {
            $filename = $map[$filename];
        }

        $suffix = '';

        if (isset($parts['query']) && $parts['query'] !== '') {
            $suffix .= '?' . $parts['query'];
        }

        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $suffix .= '#' . $parts['fragment'];
        }

        return $base . '/' . $filename . $suffix;
    }
}
