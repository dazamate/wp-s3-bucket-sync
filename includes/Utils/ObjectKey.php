<?php

namespace Dazamate\S3ImageSync\Utils;

class ObjectKey {
    // Build a deterministic S3 object key from a path that is relative to the WP
    // uploads base dir. The optional prefix is normalised so there's never a
    // leading or doubled slash regardless of how the admin entered it.
    public static function build(string $prefix, string $relative_path): string {
        $prefix = trim($prefix, '/');
        $relative_path = ltrim($relative_path, '/');

        if ($prefix === '') {
            return $relative_path;
        }

        return $prefix . '/' . $relative_path;
    }
}
