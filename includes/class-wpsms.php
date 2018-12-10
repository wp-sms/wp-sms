<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Check get_plugin_data function exist
 */
if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}

// Set Plugin path and url defines.
define( 'WP_SMS_URL', plugin_dir_url( dirname( __FILE__ ) ) );
define( 'WP_SMS_DIR', plugin_dir_path( dirname( __FILE__ ) ) );

// Get plugin Data.
$plugin_data = get_plugin_data( WP_SMS_DIR . 'wp-sms.php' );

// Set another useful Plugin defines.
define( 'WP_SMS_VERSION', $plugin_data['Version'] );
define( 'WP_SMS_ADMIN_URL', get_admin_url() );
define( 'WP_SMS_SITE', 'https://wp-sms-pro.com' );
define( 'WP_SMS_MOBILE_REGEX', '/^[\+|\(|\)|\d|\- ]*$/' );
define( 'WP_SMS_CURRENT_DATE', date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) );

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
require_once WP_SMS_DIR . 'includes/class-wpsms-initial-gateway.php';
$sms = \WP_SMS\Initial\Gateway::initial();

class WP_SMS {

	public function __construct() {
		/*
		 * Plugin Loaded Action
		 */
		add_action( 'plugins_loaded', array( $this, 'plugin_setup' ) );

		/**
		 * Install And Upgrade plugin
		 */
		if ( is_admin() ) {
			require_once WP_SMS_DIR . 'includes/admin/class-wpsms-admin.php';
		}
		register_activation_hook( WP_SMS_DIR . 'wp-sms.php', array( '\WP_SMS\Admin', 'install' ) );
		register_activation_hook( WP_SMS_DIR . 'wp-sms.php', array( '\WP_SMS\Admin', 'upgrade' ) );

	}

	/**
	 * Constructors plugin Setup
	 *
	 * @param  Not param
	 */
	public function plugin_setup() {

		// Load text domain
		add_action( 'init', array( $this, 'load_textdomain' ) );

		$this->includes();
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-sms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Includes plugin files
	 *
	 * @param  Not param
	 */
	public function includes() {

		if ( is_admin() ) {

			// Admin classes.
			require_once WP_SMS_DIR . 'includes/admin/class-wpsms-privacy.php';
			require_once WP_SMS_DIR . 'includes/admin/class-wpsms-version.php';
			require_once WP_SMS_DIR . 'includes/admin/class-wpsms-admin.php';
			require_once WP_SMS_DIR . 'includes/admin/class-wpsms-admin-helper.php';

			// Groups class.
			require_once WP_SMS_DIR . 'includes/admin/groups/class-wpsms-groups.php';
			require_once WP_SMS_DIR . 'includes/admin/groups/class-wpsms-groups-table-edit.php';

			// Outbox class.
			require_once WP_SMS_DIR . 'includes/admin/outbox/class-wpsms-outbox.php';

			// Send class.
			require_once WP_SMS_DIR . 'includes/admin/send/class-wpsms-send.php';

			// Settings classes.
			require_once WP_SMS_DIR . 'includes/admin/settings/class-wpsms-settings.php';
			require_once WP_SMS_DIR . 'includes/admin/settings/class-wpsms-settings-pro.php';

			// Subscribers class.
			require_once WP_SMS_DIR . 'includes/admin/subscribers/class-wpsms-subscribers.php';
			require_once WP_SMS_DIR . 'includes/admin/subscribers/class-wpsms-subscribers-table-edit.php';
		}

		// Utility classes.
		require_once WP_SMS_DIR . 'includes/class-wpsms-gateway.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-features.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-notifications.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-integrations.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-gravityforms.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-quform.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-newsletter.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-widget.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-rest-api.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-shortcode.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-option.php';

		if ( ! is_admin() ) {
			// Front Class.
			require_once WP_SMS_DIR . 'includes/class-front-assets.php';
		}

		// API class.
		require_once WP_SMS_DIR . 'includes/api/v1/class-wpsms-api-newsletter.php';
	}

}
