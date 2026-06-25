<?php

namespace Dazamate\S3ImageSync\Tests\Cli;

use PHPUnit\Framework\TestCase;
use Dazamate\S3ImageSync\Cli\ResyncCommand;

/**
 * Test double that bypasses WordPress/S3 by overriding the I/O seams.
 */
class ResyncCommandTestDouble extends ResyncCommand {
    /** @var array<int, int[]> Pages of IDs keyed by 1-based page number. */
    public static array $pages = [];

    /** @var array<int, string[]> Errors to return per attachment ID. */
    public static array $failures = [];

    /** @var int[] IDs that sync_one was called with, in order. */
    public static array $synced_ids = [];

    protected static function get_image_attachment_ids(int $batch_size, int $paged): array {
        return self::$pages[$paged] ?? [];
    }

    protected static function sync_one(int $post_id): array {
        self::$synced_ids[] = $post_id;

        return self::$failures[$post_id] ?? [];
    }
}

class ResyncCommandTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        ResyncCommandTestDouble::$pages = [];
        ResyncCommandTestDouble::$failures = [];
        ResyncCommandTestDouble::$synced_ids = [];
    }

    public function testResyncAllSummarisesSuccessesAndFailures(): void {
        ResyncCommandTestDouble::$pages = [
            1 => [1, 2, 3],
        ];
        ResyncCommandTestDouble::$failures = [
            2 => ['S3 sync error: boom'],
        ];

        $summary = ResyncCommandTestDouble::resync_all(null, 100);

        $this->assertSame(3, $summary['total']);
        $this->assertSame(2, $summary['synced']);
        $this->assertSame(1, $summary['failed']);
        $this->assertSame([2 => ['S3 sync error: boom']], $summary['errors']);
        $this->assertSame([1, 2, 3], ResyncCommandTestDouble::$synced_ids);
    }

    public function testResyncAllWalksEveryPageUntilShortPage(): void {
        // Full first page forces a second query; the short second page stops it.
        ResyncCommandTestDouble::$pages = [
            1 => [1, 2],
            2 => [3],
        ];

        $summary = ResyncCommandTestDouble::resync_all(null, 2);

        $this->assertSame(3, $summary['total']);
        $this->assertSame([1, 2, 3], ResyncCommandTestDouble::$synced_ids);
    }

    public function testResyncAllReportsProgressLevels(): void {
        ResyncCommandTestDouble::$pages = [
            1 => [10, 20],
        ];
        ResyncCommandTestDouble::$failures = [
            20 => ['nope'],
        ];

        $levels = [];
        ResyncCommandTestDouble::resync_all(
            function (string $level, string $message) use (&$levels): void {
                $levels[] = $level;
            },
            100
        );

        $this->assertSame(['log', 'warning'], $levels);
    }

    public function testResyncAllReturnsZeroSummaryWhenNoImages(): void {
        $summary = ResyncCommandTestDouble::resync_all(null, 100);

        $this->assertSame(
            ['total' => 0, 'synced' => 0, 'failed' => 0, 'errors' => []],
            $summary
        );
    }
}
