<?php

namespace WP_SMS;

class Welcome {

	public function __construct() {
		// Welcome Hooks
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'upgrader_process_complete', array( $this, 'do_welcome' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'init' ) );
	}

	/**
	 * Initial
	 */
	public function init() {
		if ( get_option( 'wpsms_show_welcome_page' ) AND ( strpos( $_SERVER['REQUEST_URI'], '/wp-admin/index.php' ) !== false OR strpos( $_SERVER['REQUEST_URI'], 'wp-sms' ) !== false ) ) {
			// Disable show welcome page

			update_option( 'wpsms_first_show_welcome_page', true );
			update_option( 'wpsms_show_welcome_page', false );

			// Redirect to welcome page
			wp_redirect( 'admin.php?page=wp-sms-welcome' );
		}

		if ( ! get_option( 'wpsms_first_show_welcome_page' ) ) {
			update_option( 'wpsms_show_welcome_page', true );
		}
	}

	/**
	 * Register menu
	 */
	public function menu() {
		add_submenu_page( __( 'WP-SMS Welcome', 'wp-sms' ), __( 'WP-SMS Welcome', 'wp-sms' ), __( 'WP-SMS Welcome', 'wp-sms' ), 'administrator', 'wp-sms-welcome', array( $this, 'page_callback' ) );
	}

	/**
	 * Welcome page
	 */
	public static function page_callback() {
		include( WP_SMS_DIR . "includes/admin/welcome/welcome.php" );
	}

	/**
	 * @param $upgrader_object
	 * @param $options
	 */
	public function do_welcome( $upgrader_object, $options ) {
		$current_plugin_path_name = 'wp-sms/wp-sms.php';

		if ( isset( $options['action'] ) and $options['action'] == 'update' and isset( $options['type'] ) and $options['type'] == 'plugin' and isset( $options['plugins'] ) ) {
			foreach ( $options['plugins'] as $each_plugin ) {
				if ( $each_plugin == $current_plugin_path_name ) {

					// Enable welcome page in database
					update_option( 'wpsms_show_welcome_page', true );
				}
			}
		}
	}

	/**
	 * Show change log
	 */
	public static function show_change_log() {
		$response = wp_remote_get( 'https://api.github.com/repos/veronalabs/wp-sms/releases/latest' );

		// Check response
		if ( is_wp_error( $response ) ) {
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code == '200' ) {
			$data = json_decode( $response['body'] );

			if ( ! class_exists( '\Parsedown' ) ) {
				include_once WP_SMS_DIR . 'includes/libraries/parsedown.class.php';
			}

			$Parsedown = new \Parsedown();

			echo $Parsedown->text( nl2br( $data->body ) );
		}
	}

	/**
	 * @return string|void
	 */
	public static function getNews() {
		$response = wp_remote_get( "https://wp-sms-pro.com/wp-json/wp/v2/pages/8247" );

		// Check response
		if ( is_wp_error( $response ) ) {
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code == '200' ) {
			$data = json_decode( $response['body'] );

			return $data->content->rendered;
		}
	}
}

new Welcome();