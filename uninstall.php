<?php
/**
 * WSMS uninstall handler.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 * Cleans up options, database tables, and transients created by the plugin.
 *
 * @since 8.0
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// Remove plugin options.
delete_option('wpsms_settings');
delete_option('wpsms_db_version');

// Clean up transients.
delete_transient('wpsms_gateway_status');

// Drop custom database tables.
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sms_subscribes");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sms_subscribes_group");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sms_send");

// Clear scheduled cron events.
wp_clear_scheduled_hook('wp_sms_cron');
