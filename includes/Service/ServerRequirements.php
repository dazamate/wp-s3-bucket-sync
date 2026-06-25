<?php

namespace Dazamate\S3ImageSync\Service;

// Inspects the runtime for everything intervention/image needs. Reported as
// plain data so it can be rendered in the admin and asserted in tests. The
// library needs PHP 8.3+ and at least one imaging driver (GD, Imagick or the
// vips extension); WebP/AVIF additionally depend on that driver's build options.
class ServerRequirements {
    public const MIN_PHP = '8.3.0';

    // @return array<int, array{name: string, met: bool, required: bool, detail: string}>
    public static function checks(): array {
        return [
            self::php_check(),
            self::gd_check(),
            self::imagick_check(),
            self::vips_check(),
        ];
    }

    // The library can run if PHP is new enough and at least one driver exists.
    public static function is_satisfied(): bool {
        return self::php_ok() && self::has_any_driver();
    }

    public static function has_any_driver(): bool {
        return extension_loaded('gd')
            || extension_loaded('imagick')
            || extension_loaded('vips');
    }

    private static function php_ok(): bool {
        return version_compare(PHP_VERSION, self::MIN_PHP, '>=');
    }

    private static function php_check(): array {
        return [
            'name'     => sprintf('PHP %s or newer', self::MIN_PHP),
            'met'      => self::php_ok(),
            'required' => true,
            'detail'   => sprintf('Detected PHP %s', PHP_VERSION),
        ];
    }

    private static function gd_check(): array {
        $loaded = extension_loaded('gd');
        $detail = 'Not installed.';

        if ($loaded && function_exists('gd_info')) {
            $info = gd_info();
            $detail = sprintf(
                'Installed. WebP: %s, AVIF: %s.',
                self::yes_no(!empty($info['WebP Support'])),
                self::yes_no(!empty($info['AVIF Support']))
            );
        } elseif ($loaded) {
            $detail = 'Installed.';
        }

        return [
            'name'     => 'GD library',
            'met'      => $loaded,
            'required' => false,
            'detail'   => $detail,
        ];
    }

    private static function imagick_check(): array {
        $loaded = extension_loaded('imagick');
        $detail = 'Not installed.';

        if ($loaded && class_exists('Imagick')) {
            $detail = sprintf(
                'Installed. WebP: %s, AVIF: %s.',
                self::yes_no(self::imagick_format('WEBP')),
                self::yes_no(self::imagick_format('AVIF'))
            );
        } elseif ($loaded) {
            $detail = 'Installed.';
        }

        return [
            'name'     => 'Imagick extension',
            'met'      => $loaded,
            'required' => false,
            'detail'   => $detail,
        ];
    }

    private static function vips_check(): array {
        $loaded = extension_loaded('vips');

        return [
            'name'     => 'libvips (vips extension)',
            'met'      => $loaded,
            'required' => false,
            'detail'   => $loaded
                ? 'Installed. Requires the intervention/image-driver-vips package to be used.'
                : 'Not installed. Optional, faster driver via intervention/image-driver-vips.',
        ];
    }

    private static function imagick_format(string $format): bool {
        try {
            return \Imagick::queryFormats($format) !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    private static function yes_no(bool $value): string {
        return $value ? 'yes' : 'no';
    }
}
