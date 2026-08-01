<?php
/**
 * Plugin Name: WSMS (formerly WP SMS)
 * Plugin URI: https://wsms.io/
 * Description: SMS & MMS Notifications, 2FA, OTP, and Integrations with E-Commerce and Form Builders
 * Version: 7.2.6
 * Author: VeronaLabs
 * Author URI: https://veronalabs.com/
 * Text Domain: wp-sms
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/veronalabs/wp-sms
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 4.1
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * Load Autoloader (handles both WP_SMS\ classes and prefixed dependencies)
 *
 * A partial or interrupted update can leave the plugin directory without the
 * generated autoloader. Returning silently makes the plugin look active while
 * loading nothing, which then surfaces as an unrelated fatal error in add-ons
 * that expect our classes. Tell the administrator what actually happened.
 */
if (file_exists(__DIR__ . '/packages/autoload.php')) {
    require_once __DIR__ . '/packages/autoload.php';
} else {
    add_action('admin_notices', function () {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__('WP SMS could not start because some of its files are missing. This usually means an update did not finish. Reinstalling the plugin restores the missing files; your settings and data are not affected.', 'wp-sms')
        );
    });

    return;
}

/**
 * Load Plugin Defines
 */
include_once __DIR__ . '/includes/defines.php';

// Set the plugin version
define('WP_SMS_VERSION', '7.2.6');

/**
 * Load plugin Special Functions
 */
require_once WP_SMS_DIR . 'includes/functions.php';

/**
 * Load plugin option
 */
require_once WP_SMS_DIR . 'includes/class-wpsms-option.php';

/**
 * Initial gateway
 */
require_once WP_SMS_DIR . 'includes/class-wpsms-gateway.php';

/**
 * Load Plugin
 */
require WP_SMS_DIR . 'includes/class-wpsms.php';

/**
 * @return WP_SMS
 */
function WPSms()
{
    return WP_SMS::get_instance();
}

WPSms();