<?php

namespace Dazamate\S3ImageSync\Manager;

use Dazamate\S3ImageSync\Enum\MetaKeys;

// Serves the optimised variants in place of WordPress's originals. The variant
// files live in the same directory (and therefore behind the same S3/CDN URL)
// as the files WordPress references, so serving is a matter of swapping the
// filename at the end of each URL for its recorded variant. This runs after the
// S3 url rewrite, so the URLs it sees already point at the bucket/CDN.
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

        $map = self::basename_map((int) $attachment_id);

        if ($map === []) {
            return $image;
        }

        $image[0] = self::swap_url($image[0], $map);

        return $image;
    }

    // @param mixed $sources  array of srcset descriptors keyed by width
    // @return mixed
    public static function rewrite_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        if (!is_array($sources)) {
            return $sources;
        }

        $map = self::basename_map((int) $attachment_id);

        if ($map === []) {
            return $sources;
        }

        foreach ($sources as $key => $source) {
            if (is_array($source) && isset($source['url']) && is_string($source['url'])) {
                $sources[$key]['url'] = self::swap_url($source['url'], $map);
            }
        }

        return $sources;
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

    // Replace the filename at the end of a URL's path with its variant, leaving
    // the directory, host and any query/fragment intact.
    // @param array<string, string> $map
    private static function swap_url(string $url, array $map): string {
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($path === '') {
            return $url;
        }

        $base = basename($path);

        if ($base === '' || !isset($map[$base])) {
            return $url;
        }

        $replaced = preg_replace(
            '/' . preg_quote($base, '/') . '(?=$|\?|#)/',
            $map[$base],
            $url,
            1
        );

        return is_string($replaced) ? $replaced : $url;
    }
}
