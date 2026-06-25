<?php

namespace Dazamate\S3ImageSync\Settings;

if ( ! defined( 'ABSPATH' ) ) exit;

class AdminSettings {
    const MENU_SLUG = 's3-image-sync-settings';
    private static string $options_key = 's3_image_sync_options';

    public static function load_hooks(): void {
        add_action('admin_menu', [__CLASS__, 's3_sync_add_menu_item']);
    }

    const DEFAULTS = [
        'bucket'            => '',
        'region'            => '',
        'access_key'        => '',
        'secret_key'        => '',
        'endpoint'          => '',
        'prefix'            => '',
        'cdn_url'           => '',
        'transform_method'  => 'none',
        'transform_quality' => '82',
    ];

    public static function get_settings(): array {
        return wp_parse_args(get_option(self::$options_key) ?: [], self::DEFAULTS);
    }

    public static function s3_sync_add_menu_item(): void {
        add_menu_page(
            page_title: 'S3 Image Sync Settings',
            menu_title: 'S3 Image Sync',
            capability: 'manage_options',
            menu_slug: self::MENU_SLUG,
            callback: [__CLASS__, 's3_sync_render_page'],
            icon_url: 'dashicons-format-image',
            position: null
        );
    }

    public static function s3_sync_render_page(): void {
        if (!current_user_can('manage_options')) return;

        $options = self::get_settings();

        if (isset($_POST['s3_sync_submit']) && check_admin_referer('s3_sync_settings_save', 's3_sync_nonce')) {
            $options = self::sanitize($_POST);
            update_option(self::$options_key, $options);
            ?>
            <div class="updated notice is-dismissible">
                <p>S3 image sync settings have been saved.</p>
            </div>
            <?php
        }
        ?>
        <div class="wrap">
            <h1>S3 Image Sync Settings</h1>

            <form method="post" action="">
                <?php wp_nonce_field('s3_sync_settings_save', 's3_sync_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="bucket">Bucket</label></th>
                        <td>
                            <input type="text" id="bucket" name="bucket"
                                   value="<?php echo esc_attr($options['bucket']); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="region">Region</label></th>
                        <td>
                            <input type="text" id="region" name="region"
                                   value="<?php echo esc_attr($options['region']); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="access_key">Access Key ID</label></th>
                        <td>
                            <input type="text" id="access_key" name="access_key"
                                   value="<?php echo esc_attr($options['access_key']); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="secret_key">Secret Access Key</label></th>
                        <td>
                            <input type="password" id="secret_key" name="secret_key"
                                   value="<?php echo esc_attr($options['secret_key']); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="endpoint">Custom Endpoint</label></th>
                        <td>
                            <input type="text" id="endpoint" name="endpoint"
                                   value="<?php echo esc_attr($options['endpoint']); ?>"
                                   class="regular-text" />
                            <p class="description">Optional. For S3-compatible storage (leave empty for AWS).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="prefix">Key Prefix</label></th>
                        <td>
                            <input type="text" id="prefix" name="prefix"
                                   value="<?php echo esc_attr($options['prefix']); ?>"
                                   class="regular-text" />
                            <p class="description">Optional. Prepended to every object key, e.g. <code>uploads/</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cdn_url">CDN / Custom Domain</label></th>
                        <td>
                            <input type="text" id="cdn_url" name="cdn_url"
                                   value="<?php echo esc_attr($options['cdn_url']); ?>"
                                   class="regular-text" />
                            <p class="description">Optional. Domain in front of the bucket, e.g. <code>https://cdn.example.com</code>. Used as the base of every image URL.</p>
                        </td>
                    </tr>
                </table>

                <h2>Image Optimisation</h2>
                <p>Optimised copies are generated alongside the untouched originals and uploaded to S3.</p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="transform_method">Transform Method</label></th>
                        <td>
                            <select id="transform_method" name="transform_method">
                                <?php foreach (\Dazamate\S3ImageSync\Enum\TransformMethod::cases() as $method): ?>
                                    <option value="<?php echo esc_attr($method->value); ?>"
                                        <?php selected($options['transform_method'], $method->value); ?>>
                                        <?php echo esc_html($method->label()); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">WebP and AVIF require the matching support in the server's GD or Imagick build.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="transform_quality">Quality</label></th>
                        <td>
                            <input type="number" id="transform_quality" name="transform_quality"
                                   value="<?php echo esc_attr($options['transform_quality']); ?>"
                                   min="1" max="100" class="small-text" />
                            <p class="description">1&ndash;100. Lower means smaller files. 82 is a good default.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Settings', 'primary', 's3_sync_submit'); ?>
            </form>
        </div>
        <?php
    }

    public static function sanitize(array $input): array {
        return [
            'bucket'     => sanitize_text_field($input['bucket'] ?? ''),
            'region'     => sanitize_text_field($input['region'] ?? ''),
            'access_key' => sanitize_text_field($input['access_key'] ?? ''),
            'secret_key' => sanitize_text_field($input['secret_key'] ?? ''),
            'endpoint'   => esc_url_raw($input['endpoint'] ?? ''),
            'prefix'     => sanitize_text_field($input['prefix'] ?? ''),
            'cdn_url'    => esc_url_raw($input['cdn_url'] ?? ''),
            'transform_method'  => self::sanitize_transform_method($input['transform_method'] ?? ''),
            'transform_quality' => (string) max(1, min(100, (int) ($input['transform_quality'] ?? 82))),
        ];
    }

    private static function sanitize_transform_method(string $value): string {
        return \Dazamate\S3ImageSync\Enum\TransformMethod::from_value($value)->value;
    }
}
