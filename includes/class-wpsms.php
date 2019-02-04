<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WP_SMS {

	public function __construct() {
		/*
		 * Plugin Loaded Action
		 */
		add_action( 'plugins_loaded', array( $this, 'plugin_setup' ) );

		/**
		 * Install And Upgrade plugin
		 */
		require_once WP_SMS_DIR . 'includes/class-wpsms-install.php';

		register_activation_hook( WP_SMS_DIR . 'wp-sms.php', array( '\WP_SMS\Install', 'install' ) );
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

		// Utility classes.
		require_once WP_SMS_DIR . 'includes/class-wpsms-features.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-notifications.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-integrations.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-gravityforms.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-quform.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-newsletter.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-widget.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-rest-api.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-shortcode.php';

		if ( is_admin() ) {
			// Admin classes.
			require_once WP_SMS_DIR . 'includes/admin/class-wpsms-admin.php';
			require_once WP_SMS_DIR . 'includes/admin/class-wpsms-version.php';
			require_once WP_SMS_DIR . 'includes/admin/class-wpsms-admin-helper.php';

			// Groups class.
			require_once WP_SMS_DIR . 'includes/admin/groups/class-wpsms-groups-table-edit.php';

			// Outbox class.
			require_once WP_SMS_DIR . 'includes/admin/outbox/class-wpsms-outbox.php';

			// Privacy class.
			require_once WP_SMS_DIR . 'includes/admin/privacy/class-wpsms-privacy-actions.php';

			// Send class.
			require_once WP_SMS_DIR . 'includes/admin/send/class-wpsms-send.php';

			// Settings classes.
			require_once WP_SMS_DIR . 'includes/admin/settings/class-wpsms-settings.php';
			require_once WP_SMS_DIR . 'includes/admin/settings/class-wpsms-settings-pro.php';

			// Subscribers class.
			require_once WP_SMS_DIR . 'includes/admin/subscribers/class-wpsms-subscribers-table-edit.php';
		}

		if ( ! is_admin() ) {
			// Front Class.
			require_once WP_SMS_DIR . 'includes/class-front.php';
		}

		// API class.
		require_once WP_SMS_DIR . 'includes/api/v1/class-wpsms-api-newsletter.php';

		// Template functions.
		require_once WP_SMS_DIR . 'includes/template-functions.php';
	}
}