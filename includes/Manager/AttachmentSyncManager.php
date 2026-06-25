<?php

namespace Dazamate\S3ImageSync\Manager;

use Aws\S3\S3Client;
use Dazamate\S3ImageSync\Dto\S3Settings;
use Dazamate\S3ImageSync\Dto\S3UploadJob;
use Dazamate\S3ImageSync\Enum\MetaKeys;
use Dazamate\S3ImageSync\Service\S3SyncService;
use Dazamate\S3ImageSync\Utils\ErrorManager;

class AttachmentSyncManager {
    public static function load_hooks(): void {
        add_action('add_attachment', [__CLASS__, 'on_attachment_change']);
        add_action('edit_attachment', [__CLASS__, 'on_attachment_change']);
        add_filter('wp_generate_attachment_metadata', [__CLASS__, 'on_metadata_generated'], 10, 2);
        add_action('delete_attachment', [__CLASS__, 'on_attachment_delete']);

        add_filter('attachment_fields_to_edit', [__CLASS__, 'render_s3_attachment_edit'], 10, 2);
    }

    public static function on_attachment_change(int $post_id): void {
        if (!self::is_image($post_id)) {
            return;
        }

        self::sync($post_id);
    }

    public static function on_metadata_generated(array $metadata, int $post_id): array {
        if (self::is_image($post_id)) {
            self::sync($post_id);
        }

        return $metadata;
    }

    public static function on_attachment_delete(int $post_id): void {
        if (!self::is_image($post_id)) {
            return;
        }

        $settings = self::get_settings();
        $errors = [];
        $client = self::get_client($errors);

        if ($client === null) {
            return;
        }

        $jobs = self::map_jobs($post_id, $settings->prefix);
        $keys = array_map(static fn(S3UploadJob $job): string => $job->object_key, $jobs);

        S3SyncService::delete($client, $settings->bucket, $keys, $errors);
    }

    public static function render_s3_attachment_edit(array $form_fields, \WP_Post $post): array {
        $s3_url = get_post_meta($post->ID, MetaKeys::S3_URL_META_KEY->value, true);

        $form_fields['s3_url_field'] = [
            'label' => 'S3 URL',
            'input' => 'html',
            'html'  => sprintf(
                '<input type="text" readonly style="width:100%%;" value="%s" />',
                esc_attr(is_string($s3_url) ? $s3_url : '')
            ),
        ];

        return $form_fields;
    }

    // Upload the attachment's objects to S3 and persist the resulting meta.
    // Returns an array of error messages — empty on success.
    //
    // @return string[]
    public static function sync(int $post_id): array {
        ErrorManager::clear($post_id);

        $settings = self::get_settings();
        $errors = [];
        $client = self::get_client($errors);

        if ($client === null) {
            ErrorManager::add($post_id, $errors);
            return $errors;
        }

        $jobs = self::map_jobs($post_id, $settings->prefix);
        $primary_key = S3SyncService::upload($client, $settings->bucket, $jobs, $errors);

        if ($primary_key === null) {
            ErrorManager::add($post_id, $errors);
            return $errors;
        }

        update_post_meta($post_id, MetaKeys::S3_OBJECT_KEY_META_KEY->value, $primary_key);
        update_post_meta($post_id, MetaKeys::S3_URL_META_KEY->value, self::build_url($settings, $primary_key));

        return [];
    }

    // @return S3UploadJob[]
    protected static function map_jobs(int $post_id, string $prefix): array {
        $jobs = apply_filters('s3_image_sync_map_jobs', [], $post_id, $prefix);

        return is_array($jobs) ? $jobs : [];
    }

    protected static function get_client(array &$errors): ?S3Client {
        $client = apply_filters('get_s3_client', null);

        if (!($client instanceof S3Client)) {
            $errors[] = 'S3 sync error: Unable to establish S3 client connection';
            return null;
        }

        return $client;
    }

    protected static function get_settings(): S3Settings {
        return apply_filters('get_s3_settings', S3Settings::from_array([]));
    }

    protected static function build_url(S3Settings $settings, string $object_key): string {
        if ($settings->cdn_url !== '') {
            return rtrim($settings->cdn_url, '/') . '/' . $object_key;
        }

        if ($settings->endpoint !== '') {
            return rtrim($settings->endpoint, '/') . '/' . $settings->bucket . '/' . $object_key;
        }

        return sprintf('https://%s.s3.%s.amazonaws.com/%s', $settings->bucket, $settings->region, $object_key);
    }

    protected static function is_image(int $post_id): bool {
        $mime = (string) get_post_mime_type($post_id);

        return str_starts_with($mime, 'image/');
    }
}
