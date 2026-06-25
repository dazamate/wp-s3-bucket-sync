<?php

namespace Dazamate\S3ImageSync;

use Aws\S3\S3Client;
use Dazamate\S3ImageSync\Settings\AdminSettings;
use Dazamate\S3ImageSync\Dto\S3Settings;
use Dazamate\S3ImageSync\Dto\TransformSettings;

class S3Connection {
    private static bool $_connection = false;
    private static ?S3Client $_client = null;

    public static function has_connection(): bool {
        return self::$_connection;
    }

    public static function load_hooks(): void {
        add_filter('get_s3_client', [__CLASS__, 'get_s3_client']);
        add_filter('get_s3_settings', [__CLASS__, 'get_s3_settings']);
        add_filter('get_transform_settings', [__CLASS__, 'get_transform_settings']);
        add_action('admin_bar_menu', [__CLASS__, 'add_admin_bar_status'], 100);
        add_action('admin_head', [__CLASS__, 'admin_bar_status_styles']);
        add_action('wp_head', [__CLASS__, 'admin_bar_status_styles']);
    }

    public static function init_connection(): bool {
        $settings = S3Settings::from_array(AdminSettings::get_settings());

        if (!$settings->is_configured()) {
            return false;
        }

        $config = [
            'version' => 'latest',
            'region'  => $settings->region,
            'credentials' => [
                'key'    => $settings->access_key,
                'secret' => $settings->secret_key,
            ],
        ];

        if ($settings->endpoint !== '') {
            $config['endpoint'] = $settings->endpoint;
            $config['use_path_style_endpoint'] = true;
        }

        try {
            self::$_client = new S3Client($config);
            self::$_connection = true;
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            return false;
        }

        return true;
    }

    public static function get_s3_client(): ?S3Client {
        return self::$_client;
    }

    public static function get_s3_settings(): S3Settings {
        return S3Settings::from_array(AdminSettings::get_settings());
    }

    public static function get_transform_settings(): TransformSettings {
        return TransformSettings::from_array(AdminSettings::get_settings());
    }

    public static function add_admin_bar_status(\WP_Admin_Bar $admin_bar): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $connected = self::$_connection;
        $status_class = $connected ? 's3-sync-connected' : 's3-sync-disconnected';
        $label = $connected ? 'S3: Connected' : 'S3: Not configured';

        $admin_bar->add_node([
            'id'    => 's3-sync-status',
            'title' => sprintf(
                '<span class="s3-sync-status-dot %s"></span><span class="ab-label">%s</span>',
                esc_attr($status_class),
                esc_html($label)
            ),
            'href'  => esc_url(admin_url('admin.php?page=' . AdminSettings::MENU_SLUG)),
            'meta'  => [
                'title' => $label,
            ],
        ]);
    }

    public static function admin_bar_status_styles(): void {
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
            return;
        }
        ?>
        <style>
            #wpadminbar #wp-admin-bar-s3-sync-status .s3-sync-status-dot {
                display: inline-block;
                width: 8px;
                height: 8px;
                border-radius: 50%;
                margin-right: 6px;
                vertical-align: middle;
            }
            #wpadminbar #wp-admin-bar-s3-sync-status .s3-sync-connected {
                background-color: #46b450;
                box-shadow: 0 0 4px #46b450;
            }
            #wpadminbar #wp-admin-bar-s3-sync-status .s3-sync-disconnected {
                background-color: #dc3232;
                box-shadow: 0 0 4px #dc3232;
            }
        </style>
        <?php
    }
}
