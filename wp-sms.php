<?php
/**
 * Plugin Name: WP SMS
 * Plugin URI: https://wp-sms-pro.com/
 * Description: The Best WordPress SMS Messaging and Notification Plugin for WordPress!
 * Version: 5.7.9
 * Author: VeronaLabs
 * Author URI: https://veronalabs.com/
 * Text Domain: wp-sms
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Load Plugin Defines
 */
require_once 'includes/defines.php';

/**
 * Load plugin Special Functions
 */
require_once WP_SMS_DIR . 'includes/functions.php';

/**
 * Get plugin options
 */
$wpsms_option = get_option( 'wpsms_settings' );

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
