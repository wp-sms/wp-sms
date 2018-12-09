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


require 'includes/class-wpsms.php';

/**
 * Load Plugin
 */
$WP_SMS = new WP_SMS();
