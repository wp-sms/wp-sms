<?php

/**
 * @category   class
 * @package    WP_SMS
 * @version    1.0
 */
abstract class WP_SMS {

	/**
	 * Webservice username
	 *
	 * @var string
	 */
	public $username;

	/**
	 * Webservice password
	 *
	 * @var string
	 */
	public $password;

	/**
	 * Webservice API/Key
	 *
	 * @var string
	 */
	public $has_key = false;

	/**
	 * Validation mobile number
	 *
	 * @var string
	 */
	public $validateNumber = "";

	/**
	 * Help to gateway
	 *
	 * @var string
	 */
	public $help = false;

	/**
	 * Bulk send
	 *
	 * @var boolean
	 */
	public $bulk_send = true;

	/**
	 * SMsS send from number
	 *
	 * @var string
	 */
	public $from;

	/**
	 * Send SMS to number
	 *
	 * @var string
	 */
	public $to;

	/**
	 * SMS text
	 *
	 * @var string
	 */
	public $msg;

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
	 * Plugin options
	 *
	 * @var string
	 */
	public $options;

	/**
	 * Constructors
	 */
	public function __construct() {
		global $wpdb, $table_prefix, $wpsms_option;

		$this->db        = $wpdb;
		$this->tb_prefix = $table_prefix;
		$this->options   = $wpsms_option;

		// Check option for add country code to prefix numbers
		if ( isset( $this->options['mobile_county_code'] ) and $this->options['mobile_county_code'] ) {
			add_filter( 'wp_sms_to', array( $this, 'applyCountryCode' ) );
		}

		if ( isset( $this->options['send_unicode'] ) and $this->options['send_unicode'] ) {
			//add_filter( 'wp_sms_msg', array( $this, 'applyUnicode' ) );
		}
	}

	/**
	 * @param $sender
	 * @param $message
	 * @param $to
	 * @param $response
	 * @param string $status
	 *
	 * @return false|int
	 */
	public function log( $sender, $message, $to, $response, $status = 'success' ) {
		return $this->db->insert(
			$this->tb_prefix . "sms_send",
			array(
				'date'      => WP_SMS_CURRENT_DATE,
				'sender'    => $sender,
				'message'   => $message,
				'recipient' => implode( ',', $to ),
				'response'  => $response,
				'status'    => $status,
			)
		);
	}

	/**
	 * This method required for old version of wp-sms-pro
	 *
	 * @param $sender
	 * @param $message
	 * @param $to
	 * @param $response
	 *
	 * @return false|int
	 */
	public function InsertToDB( $sender, $message, $to, $response ) {
		return $this->log( $sender, $message, $to, $response, $status = 'success' );
	}

	/**
	 * Apply Country code to prefix numbers
	 *
	 * @param $recipients
	 *
	 * @return array
	 */
	public function applyCountryCode( $recipients = array() ) {
		$country_code = $this->options['mobile_county_code'];
		$numbers      = array();

		foreach ( $recipients as $number ) {
			// Remove zero from first number
			$number = ltrim( $number, '0' );

			// Add country code to prefix number
			$numbers[] = $country_code . $number;
		}

		return $numbers;
	}

	/**
	 * Apply Unicode for non-English characters
	 *
	 * @param string $msg
	 *
	 * @return string
	 */
	public function applyUnicode( $msg = '' ) {
		$encodedMessage = bin2hex( mb_convert_encoding( $msg, 'utf-16', 'utf-8' ) );

		return $encodedMessage;
	}
}
