<?php
/*
 * Plugin Name:       Daz S3 Image Sync
 * Description:       Intercepts the media gallery and syncs images with an Amazon S3 bucket.
 * Version:           1.0
 * Requires PHP:      8.5
 * Author:            Dale Woods
 * Author URI:        https://dalewoods.me
 * Requires Plugins:
 */

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

use Dazamate\S3ImageSync\S3Connection;
use Dazamate\S3ImageSync\Settings\AdminSettings;
use Dazamate\S3ImageSync\Settings\ServerCheck;
use Dazamate\S3ImageSync\Manager\AttachmentSyncManager;
use Dazamate\S3ImageSync\Manager\UrlRewriteManager;
use Dazamate\S3ImageSync\Manager\ImageTransformManager;
use Dazamate\S3ImageSync\Manager\ImageVariantServeManager;
use Dazamate\S3ImageSync\Mapper\AttachmentMapper;
use Dazamate\S3ImageSync\Utils\ErrorManager;
use Dazamate\S3ImageSync\Cli\ResyncCommand;

add_action( 'plugins_loaded', function() {
    S3Connection::init_connection();
    S3Connection::load_hooks();

    // The settings menu must always be available — it's where the bucket
    // credentials are entered. Gating it behind has_connection() would make it
    // impossible to configure the connection when there isn't one yet.
    AdminSettings::load_hooks();
    ServerCheck::load_hooks();
});

add_action( 'init', function() {
    if ( ! S3Connection::has_connection() ) return;

    ErrorManager::load_hooks();

    ImageTransformManager::load_hooks();
    AttachmentSyncManager::load_hooks();
    UrlRewriteManager::load_hooks();
    ImageVariantServeManager::load_hooks();

    AttachmentMapper::register();
});

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 's3-image-sync resync', ResyncCommand::class );
}
