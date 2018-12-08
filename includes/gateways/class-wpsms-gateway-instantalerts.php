<?php

// Set namespace class
namespace WP_SMS\Gateway;

class instantalerts extends \WP_SMS\Gateway {
	private $wsdl_link = "http://instantalerts.co/api/";
	public $tariff = "http://springedge.com/";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "90xxxxxxxxxx";
		$this->has_key        = true;
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

		// Encode message
		$msg = urlencode( $this->msg );

		foreach ( $this->to as $to ) {
			$result = file_get_contents( $this->wsdl_link . 'web/send/?apikey=' . $this->has_key . '&sender=' . $this->from . '&to=' . $to . '&message=' . $msg . '&format=json' );
		}

		if ( isset( $result['MessageIDs'] ) ) {
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
		}
		// Log the result
		$this->log( $this->from, $this->msg, $this->to, $this->GetCredit()->get_error_message(), 'error' );

		return new \WP_Error( 'send-sms', $result );
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new \WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		// Get data
		$get_data = file_get_contents( $this->wsdl_link . 'status/credit?apikey=' . $this->has_key );

		// Check enable simplexml function in the php
		if ( ! function_exists( 'simplexml_load_string' ) ) {
			return new \WP_Error( 'account-credit', 'simplexml_load_string PHP Function disabled!' );
		}

		// Load xml
		$xml = simplexml_load_string( $get_data );

		return (int) $xml->credits;
	}
}