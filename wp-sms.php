<?php
/**
 * Plugin Name: WP SMS
 * Plugin URI: https://wp-sms-pro.com/
 * Description: The Best WordPress SMS Messaging and Notification Plugin for WordPress!
 * Version: 6.9
 * Author: VeronaLabs
 * Author URI: https://veronalabs.com/
 * Text Domain: wp-sms
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/veronalabs/wp-sms
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 4.1
 * Requires PHP: 5.6
 */

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * Load Plugin Defines
 */
include_once __DIR__ . '/includes/defines.php';

/**
 * Load plugin Special Functions
 */
require_once WP_SMS_DIR . 'includes/functions.php';

/**
 * Initial gateway
 */
require_once WP_SMS_DIR . 'includes/class-wpsms-gateway.php';

$sms = wp_sms_initial_gateway();

/**
 * Load Plugin
 */
require WP_SMS_DIR . 'includes/class-wpsms.php';

/**
 * @return object|WP_SMS|null
 */
function WPSms()
{
    return WP_SMS::get_instance();
}

WPSms();
