<?php

// Set namespace class
namespace WP_SMS;

class Front_Assets {
	public function __construct() {

		global $wpsms_option;

		$this->options = $wpsms_option;

		// Load assets
		add_action( 'wp_enqueue_scripts', array( $this, 'front_assets' ) );
	}

	/**
	 * Include front table
	 *
	 * @param  Not param
	 */
	public function front_assets() {

		// Check if "Disable Style" in frontend is active or not
		if ( empty( $this->options['disable_style_in_front'] ) or ( isset( $this->options['disable_style_in_front'] ) and ! $this->options['disable_style_in_front'] ) ) {
			wp_register_style( 'wpsms-subscribe', WP_SMS_URL . 'assets/css/subscribe.css', true, '1.1' );
			wp_enqueue_style( 'wpsms-subscribe' );
		}
	}
}

new Front_Assets();