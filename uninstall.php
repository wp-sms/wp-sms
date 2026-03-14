<?php
/**
 * WSMS uninstall handler.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 * Handles both single-site and multisite cleanup.
 *
 * @since 8.0
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

require_once __DIR__ . '/vendor/autoload.php';

use WSms\Database\CleanupScheduler;
use WSms\Database\Migrator;

/**
 * Clean up a single site's plugin data (tables, options, transients, cron).
 */
function wsms_uninstall_single_site(): void
{
    global $wpdb;

    Migrator::dropTables();

    delete_option('wsms_auth_settings');

    wp_clear_scheduled_hook(CleanupScheduler::HOOK_NAME);

    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%wsms_auth_session_%'"
    );
}

if (is_multisite()) {
    $siteIds = get_sites(['fields' => 'ids', 'number' => 0]);
    foreach ($siteIds as $siteId) {
        switch_to_blog($siteId);
        wsms_uninstall_single_site();
        restore_current_blog();
    }
} else {
    wsms_uninstall_single_site();
}

// User meta is global in multisite — clean once.
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wsms\_%'");
