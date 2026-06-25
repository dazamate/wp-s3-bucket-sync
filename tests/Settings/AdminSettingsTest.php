<?php

namespace Dazamate\S3ImageSync\Tests\Settings;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Dazamate\S3ImageSync\Settings\AdminSettings;

class AdminSettingsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when('sanitize_text_field')->alias(fn(string $v): string => trim($v));
        Functions\when('esc_url_raw')->alias(fn(string $v): string => trim($v));
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testSanitizeKeepsKnownKeysOnly(): void {
        $result = AdminSettings::sanitize([
            'bucket'     => ' my-bucket ',
            'region'     => 'us-east-1',
            'access_key' => 'AKIA',
            'secret_key' => 'secret',
            'endpoint'   => 'https://s3.example.com',
            'prefix'     => 'media',
            'cdn_url'    => 'https://cdn.example.com',
            'evil'       => 'dropme',
        ]);

        $this->assertSame('my-bucket', $result['bucket']);
        $this->assertSame('us-east-1', $result['region']);
        $this->assertSame('media', $result['prefix']);
        $this->assertArrayNotHasKey('evil', $result);
    }

    public function testSanitizeFillsMissingKeysWithEmptyString(): void {
        $result = AdminSettings::sanitize([]);

        $this->assertSame('', $result['bucket']);
        $this->assertSame('', $result['region']);
        $this->assertSame('', $result['cdn_url']);
    }
}
