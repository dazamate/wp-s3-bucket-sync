<?php

namespace Dazamate\S3ImageSync\Cli;

use Dazamate\S3ImageSync\Manager\AttachmentSyncManager;

/**
 * Re-upload every image attachment in the media library to S3.
 */
class ResyncCommand {
    // Default number of attachments fetched per query when walking the library.
    protected const BATCH_SIZE = 100;

    /**
     * Re-sync all image attachments with the S3 bucket.
     *
     * ## OPTIONS
     *
     * [--batch-size=<number>]
     * : How many attachments to load per database query.
     * ---
     * default: 100
     * ---
     *
     * ## EXAMPLES
     *
     *     wp s3-image-sync resync
     *     wp s3-image-sync resync --batch-size=250
     *
     * @param string[]              $args
     * @param array<string, string> $assoc_args
     */
    public function __invoke(array $args, array $assoc_args): void {
        $batch_size = max(1, (int) ($assoc_args['batch-size'] ?? self::BATCH_SIZE));

        $summary = self::resync_all(
            static function (string $level, string $message): void {
                match ($level) {
                    'success' => \WP_CLI::success($message),
                    'warning' => \WP_CLI::warning($message),
                    default   => \WP_CLI::log($message),
                };
            },
            $batch_size
        );

        if ($summary['failed'] > 0) {
            \WP_CLI::warning(sprintf(
                'Finished with errors: %d synced, %d failed (of %d).',
                $summary['synced'],
                $summary['failed'],
                $summary['total']
            ));

            return;
        }

        \WP_CLI::success(sprintf('Re-synced %d image(s).', $summary['synced']));
    }

    /**
     * Walk every image attachment and sync it, reporting progress via $report.
     *
     * @param (callable(string, string): void)|null $report Called with a level
     *        ('log'|'warning'|'success') and a human-readable message.
     * @return array{total:int, synced:int, failed:int, errors:array<int, string[]>}
     */
    public static function resync_all(?callable $report = null, int $batch_size = self::BATCH_SIZE): array {
        $report ??= static function (string $level, string $message): void {};

        $synced = 0;
        $failed = 0;
        $errors = [];
        $paged = 1;

        do {
            $ids = static::get_image_attachment_ids($batch_size, $paged);

            foreach ($ids as $post_id) {
                $result = static::sync_one($post_id);

                if (empty($result)) {
                    $synced++;
                    $report('log', sprintf('Synced attachment %d', $post_id));
                } else {
                    $failed++;
                    $errors[$post_id] = $result;
                    $report('warning', sprintf(
                        'Attachment %d failed: %s',
                        $post_id,
                        implode('; ', $result)
                    ));
                }
            }

            $paged++;
        } while (count($ids) === $batch_size);

        return [
            'total'  => $synced + $failed,
            'synced' => $synced,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Sync a single attachment. Wraps the manager so it can be stubbed in tests.
     *
     * @return string[] Error messages — empty on success.
     */
    protected static function sync_one(int $post_id): array {
        return AttachmentSyncManager::sync($post_id);
    }

    /**
     * Fetch a page of image attachment IDs.
     *
     * @return int[]
     */
    protected static function get_image_attachment_ids(int $batch_size, int $paged): array {
        $ids = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => $batch_size,
            'paged'          => $paged,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ]);

        return array_map('intval', $ids);
    }
}
