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

    // Stub get_post_meta so the s3 url and variant map are returned per key.
    private function stubMeta(string $s3_url, array|string $variants): void {
        Functions\when('get_post_meta')->alias(static function ($post_id, $key, $single = false) use ($s3_url, $variants) {
            return match ($key) {
                's3_url'                => $s3_url,
                's3_transform_variants' => $variants,
                default                 => '',
            };
        });
    }

    public function testRewriteImageSrcRebasesAndSwapsFullSizeToVariant(): void {
        $this->stubMeta('https://cdn.example.com/media/42/photo.jpg', [
            '2026/06/photo.jpg' => '2026/06/photo.webp',
        ]);

        $image = ['https://cdn.example.com/media/42/photo.jpg', 800, 600, false];

        $result = ImageVariantServeManager::rewrite_image_src($image, 42);

        $this->assertSame('https://cdn.example.com/media/42/photo.webp', $result[0]);
        $this->assertSame(800, $result[1]);
    }

    public function testRewriteImageSrcRebasesLocalUrlWithoutVariant(): void {
        $this->stubMeta('https://cdn.example.com/media/42/photo.jpg', []);

        $image = ['http://site.test/wp-content/uploads/2026/06/photo-300x200.jpg', 300, 200, true];

        $result = ImageVariantServeManager::rewrite_image_src($image, 42);

        $this->assertSame('https://cdn.example.com/media/42/photo-300x200.jpg', $result[0]);
    }

    public function testRewriteImageSrcReturnsUnchangedWhenNotSynced(): void {
        $this->stubMeta('', []);

        $image = ['http://site.test/wp-content/uploads/2026/06/photo.jpg', 800, 600, false];

        $this->assertSame($image, ImageVariantServeManager::rewrite_image_src($image, 42));
    }

    public function testRewriteSrcsetRebasesEverySource(): void {
        $this->stubMeta('https://cdn.example.com/media/42/photo.jpg', [
            '2026/06/photo-150x150.jpg' => '2026/06/photo-150x150.webp',
            '2026/06/photo-300x200.jpg' => '2026/06/photo-300x200.webp',
        ]);

        $sources = [
            150 => ['url' => 'http://site.test/wp-content/uploads/2026/06/photo-150x150.jpg', 'descriptor' => 'w', 'value' => 150],
            300 => ['url' => 'http://site.test/wp-content/uploads/2026/06/photo-300x200.jpg', 'descriptor' => 'w', 'value' => 300],
        ];

        $result = ImageVariantServeManager::rewrite_srcset($sources, [], '', [], 42);

        $this->assertSame('https://cdn.example.com/media/42/photo-150x150.webp', $result[150]['url']);
        $this->assertSame('https://cdn.example.com/media/42/photo-300x200.webp', $result[300]['url']);
    }

    public function testRewriteSrcsetKeepsQueryString(): void {
        $this->stubMeta('https://cdn.example.com/media/42/photo.jpg', [
            '2026/06/photo-150x150.jpg' => '2026/06/photo-150x150-opt.jpg',
        ]);

        $sources = [
            150 => ['url' => 'http://site.test/wp-content/uploads/2026/06/photo-150x150.jpg?v=2', 'descriptor' => 'w', 'value' => 150],
        ];

        $result = ImageVariantServeManager::rewrite_srcset($sources, [], '', [], 42);

        $this->assertSame('https://cdn.example.com/media/42/photo-150x150-opt.jpg?v=2', $result[150]['url']);
    }

    public function testRewriteImageSrcIgnoresFalse(): void {
        $this->assertFalse(ImageVariantServeManager::rewrite_image_src(false, 42));
    }
}
