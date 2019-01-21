<?php

namespace WP_SMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * WP SMS Shortcode Class
 */
class Shortcode {

	public function __construct() {

		// Add the shortcode [wp-sms-subscriber-form]
		add_shortcode( 'wp-sms-subscriber-form', array( $this, 'register_shortcode' ) );
	}

	/**
	 * Shortcode plugin
	 *
	 * @param $atts
	 *
	 * @internal param param $Not
	 */
	public function register_shortcode( $atts ) {
		Newsletter::loadNewsLetter();
	}
}

new Shortcode();