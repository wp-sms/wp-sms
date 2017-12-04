<?php

class _ebulksms extends WP_SMS {

	public $wsdl_link = "http://api.ebulksms.com";
	public $tariff = "http://ebulksms.com/";
	public $unitrial = true;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "2347030000000,2348020000000,23489010000000";

		// Enable api key
		$this->has_key = true;
	}

	public function SendSMS() {
		// Check gateway credit
		if ( is_wp_error( $this->GetCredit() ) ) {
			return new WP_Error( 'account-credit', __( 'Your account has no credit for sending sms.', 'wp-sms' ) );
		}

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

		$response = wp_remote_get( $this->wsdl_link . "/sendsms?username=" . $this->username . "&apikey=" . $this->has_key . "&sender=" . $this->from . "&messagetext=" . urlencode( $this->msg ) . "&flash=0&recipients=" . implode( ',', $this->to ) );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'send-sms', $response->get_error_message() );
		}

		// Ger response code
		$response_code = wp_remote_retrieve_response_code( $response );

		// Check response code
		if ( $response_code == '200' ) {
			if ( strpos( $response['body'], 'SUCCESS' ) !== false ) {
				$this->InsertToDB( $this->from, $this->msg, $this->to );

				/**
				 * Run hook after send sms.
				 *
				 * @since 2.4
				 *
				 * @param string $response result output.
				 */
				do_action( 'wp_sms_send', $response );

				return $response;
			} else {
				return new WP_Error( 'send-sms', $response['body'] );
			}

		} else {
			return new WP_Error( 'send-sms', $response['body'] );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->has_key ) {
			return new WP_Error( 'account-credit', __( 'Username/Password was not set for this gateway', 'wp-sms' ) );
		}

		// Get response
		$response = wp_remote_get( $this->wsdl_link . '/balance/' . $this->username . '/' . $this->has_key );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'account-credit', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code == '200' ) {
			return $response['body'];

		} else {
			return new WP_Error( 'account-credit', $response['body'] );
		}
	}

}
