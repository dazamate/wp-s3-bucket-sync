<?php

namespace Dazamate\S3ImageSync\Tests\Manager;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Dazamate\S3ImageSync\Manager\ImageTransformManager;
use Dazamate\S3ImageSync\Dto\S3UploadJob;

class ImageTransformManagerTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when('wp_upload_dir')->justReturn(['basedir' => '/var/www/uploads']);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testMapVariantJobsAppendsRecordedVariants(): void {
        Functions\when('get_post_meta')->justReturn([
            '2026/06/photo.jpg'         => '2026/06/photo.webp',
            '2026/06/photo-150x150.jpg' => '2026/06/photo-150x150.webp',
        ]);

        $jobs = ImageTransformManager::map_variant_jobs([], 42, 'media');

        $this->assertCount(2, $jobs);
        $this->assertContainsOnlyInstancesOf(S3UploadJob::class, $jobs);

        $this->assertSame('media/2026/06/photo.webp', $jobs[0]->object_key);
        $this->assertSame('/var/www/uploads/2026/06/photo.webp', $jobs[0]->local_path);
        $this->assertSame('image/webp', $jobs[0]->mime);

        $this->assertSame('media/2026/06/photo-150x150.webp', $jobs[1]->object_key);
    }

    public function testMapVariantJobsPreservesAccumulator(): void {
        Functions\when('get_post_meta')->justReturn('');

        $existing = [new S3UploadJob('/var/www/uploads/a.jpg', 'media/a.jpg', 'image/jpeg')];

        $this->assertSame($existing, ImageTransformManager::map_variant_jobs($existing, 42, 'media'));
    }

    public function testMapVariantJobsDerivesMimeFromExtension(): void {
        Functions\when('get_post_meta')->justReturn(['2026/06/photo.jpg' => '2026/06/photo.avif']);

        $jobs = ImageTransformManager::map_variant_jobs([], 42, '');

        $this->assertSame('image/avif', $jobs[0]->mime);
        $this->assertSame('2026/06/photo.avif', $jobs[0]->object_key);
    }
}
