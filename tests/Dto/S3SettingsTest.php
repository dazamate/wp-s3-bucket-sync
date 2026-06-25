<?php

namespace Dazamate\S3ImageSync\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Dazamate\S3ImageSync\Dto\S3Settings;

class S3SettingsTest extends TestCase {
    public function testFromArrayAppliesDefaults(): void {
        $settings = S3Settings::from_array([]);

        $this->assertSame('', $settings->bucket);
        $this->assertSame('', $settings->region);
        $this->assertSame('', $settings->access_key);
        $this->assertSame('', $settings->secret_key);
        $this->assertSame('', $settings->endpoint);
        $this->assertSame('', $settings->prefix);
        $this->assertSame('', $settings->cdn_url);
    }

    public function testIsConfiguredRequiresCoreCredentials(): void {
        $settings = S3Settings::from_array([
            'bucket'     => 'my-bucket',
            'region'     => 'us-east-1',
            'access_key' => 'AKIA',
            'secret_key' => 'secret',
        ]);

        $this->assertTrue($settings->is_configured());
    }

    public function testIsConfiguredFalseWhenMissingBucket(): void {
        $settings = S3Settings::from_array([
            'region'     => 'us-east-1',
            'access_key' => 'AKIA',
            'secret_key' => 'secret',
        ]);

        $this->assertFalse($settings->is_configured());
    }
}
