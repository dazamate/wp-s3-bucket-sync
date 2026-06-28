<?php

namespace Dazamate\S3ImageSync\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Dazamate\S3ImageSync\Utils\ObjectKey;

class ObjectKeyTest extends TestCase {
    public function testBuildWithoutPrefixGroupsByPostId(): void {
        $this->assertSame('42/image.jpg', ObjectKey::build('', 42, '2026/06/image.jpg'));
    }

    public function testBuildPrependsPrefix(): void {
        $this->assertSame('media/42/image.jpg', ObjectKey::build('media', 42, '2026/06/image.jpg'));
    }

    public function testBuildNormalisesSlashes(): void {
        $this->assertSame('media/42/image.jpg', ObjectKey::build('/media/', 42, '/2026/06/image.jpg'));
    }

    public function testBuildUsesOnlyFilename(): void {
        $this->assertSame('42/image.jpg', ObjectKey::build('', 42, '/2026/06/image.jpg'));
    }
}
