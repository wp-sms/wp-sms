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
 * Plugin defines
 */
if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}
$plugin_data = get_plugin_data( __FILE__ );

define( 'WP_SMS_VERSION', $plugin_data['Version'] );
define( 'WP_SMS_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_SMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_SMS_ADMIN_URL', get_admin_url() );
define( 'WP_SMS_SITE', 'https://wp-sms-pro.com' );
define( 'WP_SMS_MOBILE_REGEX', '/^[\+|\(|\)|\d|\- ]*$/' );
define( 'WP_SMS_CURRENT_DATE', date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) );


require WP_SMS_DIR . 'includes/class-wpsms.php';

/**
 * Load Plugin
 */
$WP_SMS = new WP_SMS();
