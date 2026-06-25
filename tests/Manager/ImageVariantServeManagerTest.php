<?php

namespace Dazamate\S3ImageSync\Tests\Manager;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Dazamate\S3ImageSync\Manager\ImageVariantServeManager;

class ImageVariantServeManagerTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testRewriteImageSrcSwapsFullSizeToVariant(): void {
        Functions\when('get_post_meta')->justReturn([
            '2026/06/photo.jpg' => '2026/06/photo.webp',
        ]);

        $image = ['https://cdn.example.com/media/2026/06/photo.jpg', 800, 600, false];

        $result = ImageVariantServeManager::rewrite_image_src($image, 42);

        $this->assertSame('https://cdn.example.com/media/2026/06/photo.webp', $result[0]);
        $this->assertSame(800, $result[1]);
    }

    public function testRewriteImageSrcLeavesUnmappedUrlsUntouched(): void {
        Functions\when('get_post_meta')->justReturn([]);

        $image = ['https://cdn.example.com/media/2026/06/photo.jpg', 800, 600, false];

        $this->assertSame($image, ImageVariantServeManager::rewrite_image_src($image, 42));
    }

    public function testRewriteSrcsetSwapsEverySource(): void {
        Functions\when('get_post_meta')->justReturn([
            '2026/06/photo-150x150.jpg' => '2026/06/photo-150x150.webp',
            '2026/06/photo-300x200.jpg' => '2026/06/photo-300x200.webp',
        ]);

        $sources = [
            150 => ['url' => 'https://cdn.example.com/media/2026/06/photo-150x150.jpg', 'descriptor' => 'w', 'value' => 150],
            300 => ['url' => 'https://cdn.example.com/media/2026/06/photo-300x200.jpg', 'descriptor' => 'w', 'value' => 300],
        ];

        $result = ImageVariantServeManager::rewrite_srcset($sources, [], '', [], 42);

        $this->assertSame('https://cdn.example.com/media/2026/06/photo-150x150.webp', $result[150]['url']);
        $this->assertSame('https://cdn.example.com/media/2026/06/photo-300x200.webp', $result[300]['url']);
    }

    public function testRewriteSrcsetKeepsQueryString(): void {
        Functions\when('get_post_meta')->justReturn([
            '2026/06/photo-150x150.jpg' => '2026/06/photo-150x150-opt.jpg',
        ]);

        $sources = [
            150 => ['url' => 'https://cdn.example.com/media/2026/06/photo-150x150.jpg?v=2', 'descriptor' => 'w', 'value' => 150],
        ];

        $result = ImageVariantServeManager::rewrite_srcset($sources, [], '', [], 42);

        $this->assertSame('https://cdn.example.com/media/2026/06/photo-150x150-opt.jpg?v=2', $result[150]['url']);
    }

    public function testRewriteImageSrcIgnoresFalse(): void {
        $this->assertFalse(ImageVariantServeManager::rewrite_image_src(false, 42));
    }
}
