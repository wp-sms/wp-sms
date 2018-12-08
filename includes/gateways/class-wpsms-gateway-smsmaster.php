<?php

// Set namespace class
namespace WP_SMS\Gateway;

class smsmaster extends \WP_SMS\Gateway {
	private $wsdl_link = "http://smsmaster.ir/send_webservice2.php";
	public $tariff = "http://smsmaster.ir/";
	public $unitrial = true;
	public $unit;
	public $flash = "disable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "09xxxxxxxx";

		if ( ! class_exists( 'nusoap_client' ) ) {
			include_once WP_SMS_DIR . 'includes/libraries/nusoap.class.php';
		}

		$this->client = new nusoap_client( $this->wsdl_link );
	}

	public function SendSMS() {

		/**
		 * Modify sender number
		 *
		 * @since 3.4
		 *
		 * @param string $this ->from sender number.
		 */
		$this->from = apply_filters( 'wp_sms_from', $this->from );

		/**
		 * Modify Receiver number
		 *
		 * @since 3.4
		 *
		 * @param array $this ->to receiver number
		 */
		$this->to = apply_filters( 'wp_sms_to', $this->to );

		/**
		 * Modify text message
		 *
		 * @since 3.4
		 *
		 * @param string $this ->msg text message.
		 */
		$this->msg = apply_filters( 'wp_sms_msg', $this->msg );

		// Get the credit.
		$credit = $this->GetCredit();

		// Check gateway credit
		if ( is_wp_error( $credit ) ) {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $credit->get_error_message(), 'error' );

			return $credit;
		}

		$result = $this->client->call( 'sending_sms', array(
			$this->username,
			$this->password,
			$this->msg,
			implode( '*', $this->to ),
			1,
			0,
			$this->from
		) );
		if ( $result ) {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $result );
			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 *
			 * @param string $result result output.
			 */
			do_action( 'wp_sms_send', $result );

			return $result;
		} else {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $result, 'error' );

			return new \WP_Error( 'send-sms', $result );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new \WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		return $this->client->call( 'getcredit', array( $this->username, $this->password ) );
	}
}