<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Several plugin files guard with `if (!defined('ABSPATH')) exit;` so they
// can't be loaded outside WordPress. Define a dummy constant so those files are
// safe to autoload in the test process.
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Minimal stub for the WordPress class the plugin type-hints against, so tests
// can construct it. WordPress itself isn't loaded in tests; its functions are
// mocked per-test with Brain\Monkey.
if (!class_exists('WP_Post')) {
    class WP_Post {
        public int $ID = 0;
        public string $post_type = '';
        public string $post_title = '';
        public string $post_mime_type = '';
    }
}
