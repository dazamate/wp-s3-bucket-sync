<?php

namespace Dazamate\S3ImageSync\Tests\Enum;

use PHPUnit\Framework\TestCase;
use Dazamate\S3ImageSync\Enum\TransformMethod;

class TransformMethodTest extends TestCase {
    public function testExtensionAndMime(): void {
        $this->assertSame('webp', TransformMethod::WEBP->extension());
        $this->assertSame('image/webp', TransformMethod::WEBP->mime());

        $this->assertSame('avif', TransformMethod::AVIF->extension());
        $this->assertSame('image/avif', TransformMethod::AVIF->mime());

        $this->assertSame('jpg', TransformMethod::JPEG->extension());
        $this->assertSame('image/jpeg', TransformMethod::JPEG->mime());
    }

    public function testIsEnabled(): void {
        $this->assertFalse(TransformMethod::NONE->is_enabled());
        $this->assertTrue(TransformMethod::JPEG->is_enabled());
        $this->assertTrue(TransformMethod::WEBP->is_enabled());
    }

    public function testFromValueFallsBackToNone(): void {
        $this->assertSame(TransformMethod::WEBP, TransformMethod::from_value('webp'));
        $this->assertSame(TransformMethod::NONE, TransformMethod::from_value('nope'));
        $this->assertSame(TransformMethod::NONE, TransformMethod::from_value(null));
    }
}
