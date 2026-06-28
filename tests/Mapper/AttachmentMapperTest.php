<?php

namespace Dazamate\S3ImageSync\Tests\Mapper;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Dazamate\S3ImageSync\Mapper\AttachmentMapper;
use Dazamate\S3ImageSync\Dto\S3UploadJob;

class AttachmentMapperTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when('wp_upload_dir')->justReturn(['basedir' => '/var/www/uploads']);
        Functions\when('get_post_mime_type')->justReturn('image/jpeg');
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testMapAddsOriginalAndSizeJobs(): void {
        Functions\when('get_attached_file')->justReturn('/var/www/uploads/2026/06/photo.jpg');
        Functions\when('wp_get_attachment_metadata')->justReturn([
            'sizes' => [
                'thumbnail' => ['file' => 'photo-150x150.jpg', 'mime-type' => 'image/jpeg'],
                'medium'    => ['file' => 'photo-300x200.jpg', 'mime-type' => 'image/jpeg'],
            ],
        ]);

        $jobs = AttachmentMapper::map([], 42, 'media');

        $this->assertCount(3, $jobs);
        $this->assertContainsOnlyInstancesOf(S3UploadJob::class, $jobs);

        $this->assertSame('media/42/photo.jpg', $jobs[0]->object_key);
        $this->assertSame('/var/www/uploads/2026/06/photo.jpg', $jobs[0]->local_path);

        $this->assertSame('media/42/photo-150x150.jpg', $jobs[1]->object_key);
        $this->assertSame('/var/www/uploads/2026/06/photo-150x150.jpg', $jobs[1]->local_path);

        $this->assertSame('media/42/photo-300x200.jpg', $jobs[2]->object_key);
    }

    public function testMapWithoutPrefixGroupsByPostId(): void {
        Functions\when('get_attached_file')->justReturn('/var/www/uploads/2026/06/photo.jpg');
        Functions\when('wp_get_attachment_metadata')->justReturn([]);

        $jobs = AttachmentMapper::map([], 42, '');

        $this->assertCount(1, $jobs);
        $this->assertSame('42/photo.jpg', $jobs[0]->object_key);
    }

    public function testMapReturnsAccumulatorWhenNoFile(): void {
        Functions\when('get_attached_file')->justReturn(false);

        $this->assertSame([], AttachmentMapper::map([], 42, 'media'));
    }

    public function testMapIgnoresFileOutsideUploadsDir(): void {
        Functions\when('get_attached_file')->justReturn('/etc/passwd');
        Functions\when('wp_get_attachment_metadata')->justReturn([]);

        $this->assertSame([], AttachmentMapper::map([], 42, 'media'));
    }
}
