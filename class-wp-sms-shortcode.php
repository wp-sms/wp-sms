<?php

/**
 * WP SMS Shortcode Class
 */
class WP_SMS_Shortcode {

	public $sms;
	public $date;
	public $options;

	protected $db;
	protected $tb_prefix;

	/**
	 * WP_SMS_Features constructor.
	 */
	public function __construct() {
		global $wpsms_option, $sms, $wpdb, $table_prefix;

		$this->sms       = $sms;
		$this->db        = $wpdb;
		$this->tb_prefix = $table_prefix;
		$this->date      = WP_SMS_CURRENT_DATE;
		$this->options   = $wpsms_option;

		add_shortcode( 'wp-sms-subscriber-form', array( $this, 'register_shortcode' ) );
	}

	/**
	 * Shortcodes plugin
	 *
	 * @param $atts
	 * @param null $content
	 *
	 * @internal param param $Not
	 */
	public function register_shortcode( $atts ) {
		return 'test';
	}
}

new WP_SMS_Shortcode();