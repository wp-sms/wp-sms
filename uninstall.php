<?php
/**
 * WSMS uninstall handler.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 *
 * @since 8.0
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

require_once __DIR__ . '/vendor/autoload.php';

use WSms\Database\CleanupScheduler;
use WSms\Database\Migrator;

// Drop all custom tables.
Migrator::dropTables();

// Delete plugin options.
delete_option('wsms_auth_settings');

// Delete all plugin user meta.
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wsms\_%'");

// Clear scheduled hooks.
wp_clear_scheduled_hook(CleanupScheduler::HOOK_NAME);

// Delete transients (auth session tokens).
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%wsms_auth_session_%'"
);
