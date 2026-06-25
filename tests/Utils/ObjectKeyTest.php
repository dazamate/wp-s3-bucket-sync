<?php

namespace Dazamate\S3ImageSync\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Dazamate\S3ImageSync\Utils\ObjectKey;

class ObjectKeyTest extends TestCase {
    public function testBuildWithoutPrefixReturnsRelativePath(): void {
        $this->assertSame('2026/06/image.jpg', ObjectKey::build('', '2026/06/image.jpg'));
    }

    public function testBuildPrependsPrefix(): void {
        $this->assertSame('media/2026/06/image.jpg', ObjectKey::build('media', '2026/06/image.jpg'));
    }

    public function testBuildNormalisesSlashes(): void {
        $this->assertSame('media/2026/06/image.jpg', ObjectKey::build('/media/', '/2026/06/image.jpg'));
    }

    public function testBuildTrimsLeadingSlashWhenNoPrefix(): void {
        $this->assertSame('2026/06/image.jpg', ObjectKey::build('', '/2026/06/image.jpg'));
    }
}
