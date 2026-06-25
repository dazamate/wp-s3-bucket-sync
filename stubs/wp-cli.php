<?php

/**
 * Minimal stub for the WP-CLI runtime, which is only present when the plugin
 * runs under `wp` on the command line. Used by PHPStan to resolve the WP_CLI
 * symbol; it is never bundled or loaded at runtime.
 */
class WP_CLI {
    public static function add_command(string $name, mixed $callable, array $args = []): bool {}

    public static function log(string $message): void {}

    public static function warning(string $message): void {}

    public static function success(string $message): void {}

    public static function error(string $message, bool|int $exit = true): void {}
}
