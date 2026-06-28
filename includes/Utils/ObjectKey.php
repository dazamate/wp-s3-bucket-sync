<?php

namespace Dazamate\S3ImageSync\Utils;

class ObjectKey {
    // Build a deterministic S3 object key of the form {prefix}/{post_id}/{file}.
    // Grouping every file of an attachment under its post id keeps originals and
    // their generated sizes/variants together and avoids one flat folder. Only
    // the filename of the WP-relative path is used; the optional prefix is
    // normalised so there's never a leading or doubled slash.
    public static function build(string $prefix, int $post_id, string $relative_path): string {
        $prefix = trim($prefix, '/');
        $filename = basename($relative_path);

        $parts = [];

        if ($prefix !== '') {
            $parts[] = $prefix;
        }

        $parts[] = (string) $post_id;
        $parts[] = $filename;

        return implode('/', $parts);
    }
}
