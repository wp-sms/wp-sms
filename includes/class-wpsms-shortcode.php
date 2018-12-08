<?php

// Set namespace class
namespace WP_SMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * WP SMS Shortcode Class
 */
class Shortcode {

	public $sms;
	public $date;
	public $options;

	protected $db;
	protected $tb_prefix;

	/**
	 * WP_SMS\Shortcode constructor.
	 */
	public function __construct() {
		global $wpsms_option, $wpdb, $table_prefix;

		$this->db        = $wpdb;
		$this->tb_prefix = $table_prefix;
		$this->options   = $wpsms_option;

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
		\WP_SMS::loadNewsLetter();
	}
}

new Shortcode();