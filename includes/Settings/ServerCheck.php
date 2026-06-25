<?php

namespace Dazamate\S3ImageSync\Settings;

use Dazamate\S3ImageSync\Service\ServerRequirements;

if ( ! defined( 'ABSPATH' ) ) exit;

// "Server Check" page: reports whether the host can run the image optimisation
// library and which output formats it supports. Registered as a submenu of the
// main S3 Image Sync menu and always available, regardless of S3 connection.
class ServerCheck {
    const MENU_SLUG = 's3-image-sync-server-check';

    public static function load_hooks(): void {
        add_action('admin_menu', [__CLASS__, 'add_menu_item']);
    }

    public static function add_menu_item(): void {
        add_submenu_page(
            parent_slug: AdminSettings::MENU_SLUG,
            page_title: 'Server Check',
            menu_title: 'Server Check',
            capability: 'manage_options',
            menu_slug: self::MENU_SLUG,
            callback: [__CLASS__, 'render_page']
        );
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) return;

        $checks = ServerRequirements::checks();
        $satisfied = ServerRequirements::is_satisfied();
        ?>
        <div class="wrap">
            <h1>Server Check</h1>
            <p>Requirements for the image optimisation library (intervention/image).</p>

            <?php if ($satisfied): ?>
                <div class="notice notice-success inline"><p>
                    <strong>Ready.</strong> This server can run image optimisation.
                </p></div>
            <?php else: ?>
                <div class="notice notice-error inline"><p>
                    <strong>Not ready.</strong> PHP <?php echo esc_html(ServerRequirements::MIN_PHP); ?>+
                    and at least one imaging driver (GD, Imagick or vips) are required.
                </p></div>
            <?php endif; ?>

            <table class="widefat striped" style="max-width:760px;margin-top:1em;">
                <thead>
                    <tr>
                        <th>Requirement</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checks as $check): ?>
                        <tr>
                            <td>
                                <?php echo esc_html($check['name']); ?>
                                <?php if (!empty($check['required'])): ?>
                                    <span style="color:#b32d2e;">*</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($check['met'])): ?>
                                    <span style="color:#46b450;font-weight:600;">&#10003; Pass</span>
                                <?php else: ?>
                                    <span style="color:#dc3232;font-weight:600;">&#10007; Missing</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($check['detail']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description"><span style="color:#b32d2e;">*</span> Required.</p>
        </div>
        <?php
    }
}
