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
	protected $options;

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
	}

	public function InsertToDB( $sender, $message, $recipient ) {
		return $this->db->insert(
			$this->tb_prefix . "sms_send",
			array(
				'date'      => WP_SMS_CURRENT_DATE,
				'sender'    => $sender,
				'message'   => $message,
				'recipient' => implode( ',', $recipient )
			)
		);
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
}
