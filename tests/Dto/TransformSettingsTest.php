<?php

namespace Dazamate\S3ImageSync\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Dazamate\S3ImageSync\Dto\TransformSettings;
use Dazamate\S3ImageSync\Enum\TransformMethod;

class TransformSettingsTest extends TestCase {
    public function testFromArrayDefaultsToDisabled(): void {
        $settings = TransformSettings::from_array([]);

        $this->assertSame(TransformMethod::NONE, $settings->method);
        $this->assertSame(82, $settings->quality);
        $this->assertFalse($settings->is_enabled());
    }

    public function testFromArrayParsesMethodAndQuality(): void {
        $settings = TransformSettings::from_array([
            'transform_method'  => 'webp',
            'transform_quality' => '70',
        ]);

        $this->assertSame(TransformMethod::WEBP, $settings->method);
        $this->assertSame(70, $settings->quality);
        $this->assertTrue($settings->is_enabled());
    }

    public function testQualityIsClamped(): void {
        $this->assertSame(100, TransformSettings::from_array(['transform_quality' => '250'])->quality);
        $this->assertSame(1, TransformSettings::from_array(['transform_quality' => '0'])->quality);
    }

    public function testUnknownMethodFallsBackToNone(): void {
        $settings = TransformSettings::from_array(['transform_method' => 'bogus']);

        $this->assertSame(TransformMethod::NONE, $settings->method);
    }
}
