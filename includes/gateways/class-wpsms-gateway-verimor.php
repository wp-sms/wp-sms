<?php

class verimor extends WP_SMS {
	private $wsdl_link = "http://sms.verimor.com.tr/v2/";
	public $tariff = "https://www.verimor.com.tr/";
	public $unitrial = false;
	public $unit;
	public $flash = "disabled";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "Phone numbers must start with 90 and be 12 digits.";
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

		// Check gateway credit
		if ( is_wp_error( $this->GetCredit() ) ) {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $this->GetCredit()->get_error_message(), 'error' );

			return $this->GetCredit();
		}

		$msg = urlencode( $this->msg );
		$to  = implode( $this->to, "," );

		$response = wp_remote_get( $this->wsdl_link . "send?username=" . $this->username . "&password=" . $this->password . "&source_addr=" . $this->from . "&msg=" . $msg . "&dest=" . $to . "&datacoding=0" );

		// Check response error
		if ( is_wp_error( $response ) ) {
			// Log th result
			$this->log( $this->from, $this->msg, $this->to, $response->get_error_message(), 'error' );

			return new WP_Error( 'send-sms', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code == '200' ) {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $response['body'] );

			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 *
			 * @param string $result result output.
			 */
			do_action( 'wp_sms_send', $response['body'] );

			return $response['body'];
		} else {
			// Log th result
			$this->log( $this->from, $this->msg, $this->to, $response['body'], 'error' );

			return new WP_Error( 'send-sms', $response['body'] );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new WP_Error( 'account-credit', __( 'Username/API-Key does not set for this gateway', 'wp-sms' ) );
		}

		return 1;
	}
}
