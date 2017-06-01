<?php

class opilo extends WP_SMS {
	private $wsdl_link = "http://webservice.opilo.com/WS/";
	public $tariff = "http://cms.opilo.com/?p=179";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "09xxxxxxxx";
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

		$to_numbers = null;

		foreach ( $this->to as $number ) {
			if ( ! empty( $to_numbers ) ) {
				$to_numbers .= ',' . $number;
			} else {
				$to_numbers = $number;
			}
		}

		if ( empty( $to_numbers ) ) {

			echo "Error";

			return;
		}

		$url = $this->wsdl_link .
		       "httpsend/?username=" . $this->username
		       . "&password=" . $this->password .
		       "&from=" . $this->from .
		       "&to=" . $to_numbers
		       . "&text=" . urlencode( $this->msg )
		       . "&flash=" . $this->isflash;

		$response = file( $url );

		if ( $response[0] ) {
			return true;
		}

		if ( ! is_numeric( $response[1] ) ) {
			echo "Error";

			return;

		}

		if ( strlen( $response[1] ) > 2 ) {

			$this->InsertToDB( $this->from, $this->msg, $this->to );

			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 *
			 * @param string $result result output.
			 */
			do_action( 'wp_sms_send', $response );

			return $response[1];

		} else {
			return new WP_Error( 'send-sms', $result );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		$url      = $this->wsdl_link . "getCredit/?username=" . $this->username
		            . "&password=" . $this->password;
		$response = file( $url );

		return $response[0];

		if ( strstr( $response[1], "Error" ) ) {
			return new WP_Error( 'account-credit', $response );
		}

		return $response[1];
	}
}