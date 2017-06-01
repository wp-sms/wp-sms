<?php

class instantalerts extends WP_SMS {
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
		// Check gateway credit
		if ( is_wp_error( $this->GetCredit() ) ) {
			return new WP_Error( 'account-credit', __( 'Your account does not credit for sending sms.', 'wp-sms' ) );
		}

		// Encode message
		$msg = urlencode( $this->msg );

		foreach ( $this->to as $to ) {
			$result = file_get_contents( $this->wsdl_link . 'web/send/?apikey=' . $this->has_key . '&sender=' . $this->from . '&to=' . $to . '&message=' . $msg . '&format=json' );
		}

		if ( isset( $result['MessageIDs'] ) ) {
			$this->InsertToDB( $this->from, $this->msg, $this->to );

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

		return new WP_Error( 'send-sms', $result );
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		// Get data
		$get_data = file_get_contents( $this->wsdl_link . 'status/credit?apikey=' . $this->has_key );

		// Check enable simplexml function in the php
		if ( ! function_exists( 'simplexml_load_string' ) ) {
			return new WP_Error( 'account-credit', $result );
		}

		// Load xml
		$xml = simplexml_load_string( $get_data );

		return (int) $xml->credits;
	}
}