<?php

/**
 * WP SMS RestApi class
 *
 * @category   class
 * @package    WP_SMS
 * @version    4.0
 */
class WP_SMS_RestApi {

	/**
	 * SMS object
	 * @var object
	 */
	public $sms;

	/**
	 * Options
	 *
	 * @var string
	 */
	protected $option;

	/**
	 * Wordpress Database
	 *
	 * @var string
	 */
	protected $db;

	/**
	 * Wordpress Table prefix
	 *
	 * @var string
	 */
	protected $tb_prefix;

	/**
	 * Name space
	 * @var string
	 */
	public $namespace;

	/**
	 * WP_SMS_RestApi constructor.
	 */
	public function __construct() {
		global $wpsms_option, $sms, $wpdb, $table_prefix;

		$this->sms       = $sms;
		$this->options   = $wpsms_option;
		$this->db        = $wpdb;
		$this->tb_prefix = $table_prefix;
		$this->namespace = 'wpsms';
	}

	public static function response( $message, $status = 200 ) {
		return new WP_REST_Response( $message, $status );
	}

}

new WP_SMS_RestApi();