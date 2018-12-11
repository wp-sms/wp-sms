<?php
/**
 * Plugin Name: WP SMS
 * Plugin URI: http://wp-sms-pro.com/
 * Description: A powerful texting plugin for WordPress
 * Version: 4.1.1
 * Author: Verona Labs
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
 * Load plugin Database Options
 */
require_once WP_SMS_DIR . 'includes/class-wpsms-option.php';

/**
 * Initial gateway
 */
require_once WP_SMS_DIR . 'includes/class-wpsms-gateway.php';

$sms = \WP_SMS\Gateway::initial();

/**
 * Load Plugin
 */
require WP_SMS_DIR . 'includes/class-wpsms.php';

$WP_SMS = new WP_SMS();
