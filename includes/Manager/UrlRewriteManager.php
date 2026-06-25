<?php

namespace Dazamate\S3ImageSync\Manager;

use Dazamate\S3ImageSync\Enum\MetaKeys;

class UrlRewriteManager {
    public static function load_hooks(): void {
        add_filter('wp_get_attachment_url', [__CLASS__, 'rewrite_attachment_url'], 10, 2);
    }

    // Serve a synced attachment from its stored S3/CDN url instead of the local
    // uploads directory. Falls through to the original url when the attachment
    // has not been synced.
    public static function rewrite_attachment_url(string $url, int $post_id): string {
        $s3_url = get_post_meta($post_id, MetaKeys::S3_URL_META_KEY->value, true);

        if (is_string($s3_url) && $s3_url !== '') {
            return $s3_url;
        }

        return $url;
    }
}
