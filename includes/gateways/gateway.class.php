<?php

class gateway extends WP_SMS {
	private $wsdl_link = "http://apps.gateway.sa/vendorsms/";
	public $tariff = "http://sms.gateway.sa/";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "+xxxxxxxxxxxxx";
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

		$response = wp_remote_get( $this->wsdl_link . "pushsms.aspx?user=" . $this->username . "&password=" . $this->password . "&msisdn=" . $to . "&sid=" . $this->from . "&msg=" . $msg . "&fl=0" );

		// Check response error
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'send-sms', $response->get_error_message() );
		}

		$result = json_decode( $response['body'] );

		if ( $result->ErrorCode == '000' ) {
			$this->InsertToDB( $this->from, $this->msg, $this->to );

			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 */
			do_action( 'wp_sms_send', $response['body'] );

			return $result;
		} else {
			return new WP_Error( 'send-sms', $result->ErrorMessage );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		$response = wp_remote_get( $this->wsdl_link . "CheckBalance.aspx?user=" . $this->username . "&password=" . $this->password );

		if ( ! is_wp_error( $response ) ) {
			if ( strpos( $response['body'], 'Success' ) !== false ) {
				return trim( $response['body'], 'Success#' );
			} else {
				return new WP_Error( 'account-credit', $response['body'] );
			}
		} else {
			return new WP_Error( 'account-credit', $response->get_error_message() );
		}
	}
}