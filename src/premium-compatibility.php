<?php

defined('ABSPATH') || exit;

/**
 * Check if Premium is active.
 *
 * @return bool
 */
function wp_sms_is_premium_active(): bool
{
    return defined('WP_SMS_PREMIUM_FILE');
}

/**
 * Display admin notice when Premium is active.
 *
 * @return void
 */
function wp_sms_premium_active_notice(): void
{
    ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php esc_html_e('WSMS', 'wp-sms'); ?>:</strong>
            <?php esc_html_e('WSMS Premium is active and includes all free features. Please deactivate the free version to avoid conflicts.', 'wp-sms'); ?>
        </p>
    </div>
    <?php
}

/**
 * Initialize premium compatibility mode.
 *
 * @param string $pluginFile Main plugin file path.
 * @return void
 */
function wp_sms_init_premium_compatibility(string $pluginFile): void
{
    add_action('init', function () use ($pluginFile) {
        load_plugin_textdomain(
            'wp-sms',
            false,
            dirname(plugin_basename($pluginFile)) . '/public/languages'
        );
    }, 1);

    add_action('admin_notices', 'wp_sms_premium_active_notice');

    if (is_multisite()) {
        add_action('network_admin_notices', 'wp_sms_premium_active_notice');
    }
}
