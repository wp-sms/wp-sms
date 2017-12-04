<?php

class ozonesmsworld extends WP_SMS {
	private $wsdl_link = "http://login.ozonesmsworld.com/API";
	public $tariff = "http://login.ozonesmsworld.com";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "+xxxxxxxxxxxxx";
		$this->has_key        = true;
	}

	public function SendSMS() {
		// Check gateway credit
		if ( is_wp_error( $this->GetCredit() ) ) {
			return new WP_Error( 'account-credit', __( 'Your account does not credit for sending sms.', 'wp-sms' ) );
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

		$to  = implode( $this->to, "," );
		$msg = urlencode( $this->msg );

		$response = wp_remote_get( $this->wsdl_link . "/?action=compose&username=" . $this->username . "&api_key=" . $this->has_key . "&sender=" . $this->from . "&to=" . $to . "&message=" . $msg . "&unicode=0" );

		// Check response error
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'send-sms', $response->get_error_message() );
		}

		if ( isset( $response['body'] ) ) {
			$this->InsertToDB( $this->from, $this->msg, $this->to );

			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 */
			do_action( 'wp_sms_send', $response['body'] );

			return $response['body'];
		} else {
			return new WP_Error( 'send-sms', $response['body'] );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->has_key ) {
			return new WP_Error( 'account-credit', __( 'Username/API-Key does not set for this gateway', 'wp-sms' ) );
		}

		$response = wp_remote_get( $this->wsdl_link . "/?action=balance&username=" . $this->username . "&api_key=" . $this->has_key );

		if ( ! is_wp_error( $response ) ) {
			if ( strpos( trim( $response['body'] ), 'Balance' ) !== false ) {
				return trim( $response['body'] );
			} else {
				return new WP_Error( 'account-credit', trim( $response['body'] ) );
			}
		} else {
			return new WP_Error( 'account-credit', $response->get_error_message() );
		}
	}
}