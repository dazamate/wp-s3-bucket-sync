<?php

namespace Dazamate\S3ImageSync\Tests\Service;

use PHPUnit\Framework\TestCase;
use Dazamate\S3ImageSync\Service\ImageTransformService;

class ImageTransformServiceTest extends TestCase {
    public function testParseMemoryLimitHandlesSuffixes(): void {
        $this->assertSame(256 * 1024 * 1024, ImageTransformService::parse_memory_limit('256M'));
        $this->assertSame(1024 * 1024 * 1024, ImageTransformService::parse_memory_limit('1G'));
        $this->assertSame(512 * 1024, ImageTransformService::parse_memory_limit('512K'));
        $this->assertSame(1048576, ImageTransformService::parse_memory_limit('1048576'));
    }

    public function testParseMemoryLimitTreatsUnboundedAsNegativeOne(): void {
        $this->assertSame(-1, ImageTransformService::parse_memory_limit('-1'));
        $this->assertSame(-1, ImageTransformService::parse_memory_limit(''));
    }

    public function testEstimateRequiredBytesScalesWithPixels(): void {
        // 1000x1000 * 4 bytes * 2.2 fudge = 8,800,000
        $this->assertSame(8_800_000, ImageTransformService::estimate_required_bytes(1000, 1000));
    }

    public function testEstimateGrowsWithDimensions(): void {
        $small = ImageTransformService::estimate_required_bytes(100, 100);
        $large = ImageTransformService::estimate_required_bytes(6000, 4000);

        $this->assertGreaterThan($small, $large);
        $this->assertSame((int) ceil(6000 * 4000 * 4 * 2.2), $large);
    }
}
