<?php

namespace Dazamate\S3ImageSync\Tests\Service;

use PHPUnit\Framework\TestCase;
use Aws\S3\S3Client;
use Dazamate\S3ImageSync\Service\S3SyncService;
use Dazamate\S3ImageSync\Dto\S3UploadJob;

// Test double: S3Client's request methods are magic (__call), so a subclass can
// declare them directly. The parent constructor is skipped to avoid needing real
// AWS credentials/config in unit tests.
class FakeS3Client extends S3Client {
    /** @var array<int, array<string, mixed>> */
    public array $put_calls = [];
    /** @var array<int, array<string, mixed>> */
    public array $delete_calls = [];
    public bool $should_throw = false;

    public function __construct() {}

    public function putObject(array $args = []) {
        if ($this->should_throw) {
            throw new \RuntimeException('Access Denied');
        }
        $this->put_calls[] = $args;
        return null;
    }

    public function deleteObjects(array $args = []) {
        if ($this->should_throw) {
            throw new \RuntimeException('Access Denied');
        }
        $this->delete_calls[] = $args;
        return null;
    }
}

class S3SyncServiceTest extends TestCase {
    private string $temp_file;

    protected function setUp(): void {
        parent::setUp();
        $this->temp_file = tempnam(sys_get_temp_dir(), 's3test');
        file_put_contents($this->temp_file, 'binary');
    }

    protected function tearDown(): void {
        if (is_file($this->temp_file)) {
            unlink($this->temp_file);
        }
        parent::tearDown();
    }

    public function testUploadPutsEachJobAndReturnsPrimaryKey(): void {
        $client = new FakeS3Client();
        $errors = [];

        $jobs = [
            new S3UploadJob($this->temp_file, '2026/06/photo.jpg', 'image/jpeg'),
            new S3UploadJob($this->temp_file, '2026/06/photo-150x150.jpg', 'image/jpeg'),
        ];

        $key = S3SyncService::upload($client, 'my-bucket', $jobs, $errors);

        $this->assertSame('2026/06/photo.jpg', $key);
        $this->assertSame([], $errors);
        $this->assertCount(2, $client->put_calls);
        $this->assertSame('my-bucket', $client->put_calls[0]['Bucket']);
        $this->assertSame('2026/06/photo.jpg', $client->put_calls[0]['Key']);
        $this->assertSame('image/jpeg', $client->put_calls[0]['ContentType']);
    }

    public function testUploadFailsWhenBucketEmpty(): void {
        $client = new FakeS3Client();
        $errors = [];

        $key = S3SyncService::upload($client, '', [
            new S3UploadJob($this->temp_file, 'photo.jpg', 'image/jpeg'),
        ], $errors);

        $this->assertNull($key);
        $this->assertNotEmpty($errors);
    }

    public function testUploadFailsWhenNoJobs(): void {
        $client = new FakeS3Client();
        $errors = [];

        $this->assertNull(S3SyncService::upload($client, 'my-bucket', [], $errors));
        $this->assertNotEmpty($errors);
    }

    public function testUploadFailsWhenFileNotReadable(): void {
        $client = new FakeS3Client();
        $errors = [];

        $key = S3SyncService::upload($client, 'my-bucket', [
            new S3UploadJob('/does/not/exist.jpg', 'photo.jpg', 'image/jpeg'),
        ], $errors);

        $this->assertNull($key);
        $this->assertNotEmpty($errors);
        $this->assertCount(0, $client->put_calls);
    }

    public function testUploadCollectsClientException(): void {
        $client = new FakeS3Client();
        $client->should_throw = true;
        $errors = [];

        $key = S3SyncService::upload($client, 'my-bucket', [
            new S3UploadJob($this->temp_file, 'photo.jpg', 'image/jpeg'),
        ], $errors);

        $this->assertNull($key);
        $this->assertStringContainsString('Access Denied', $errors[0]);
    }

    public function testDeleteBatchesKeys(): void {
        $client = new FakeS3Client();
        $errors = [];

        $ok = S3SyncService::delete($client, 'my-bucket', ['a.jpg', '', 'b.jpg'], $errors);

        $this->assertTrue($ok);
        $this->assertSame([], $errors);
        $this->assertCount(1, $client->delete_calls);
        $this->assertSame(
            [['Key' => 'a.jpg'], ['Key' => 'b.jpg']],
            $client->delete_calls[0]['Delete']['Objects']
        );
    }

    public function testDeleteNoopWhenNoKeys(): void {
        $client = new FakeS3Client();
        $errors = [];

        $this->assertTrue(S3SyncService::delete($client, 'my-bucket', [], $errors));
        $this->assertCount(0, $client->delete_calls);
    }

    public function testDeleteCollectsClientException(): void {
        $client = new FakeS3Client();
        $client->should_throw = true;
        $errors = [];

        $ok = S3SyncService::delete($client, 'my-bucket', ['a.jpg'], $errors);

        $this->assertFalse($ok);
        $this->assertStringContainsString('Access Denied', $errors[0]);
    }
}
