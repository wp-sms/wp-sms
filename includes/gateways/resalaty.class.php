<?php

class resalaty extends WP_SMS {
	private $wsdl_link = "http://www.resalaty.com/api/";
	public $tariff = "https://resalaty.com/";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "";
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

		$to = implode( ',', $this->to );

		$msg = urlencode( $this->msg );

		// Get response
		$response = wp_remote_get( $this->wsdl_link . 'sendsms.php?username=' . $this->username . '&password=' . $this->password . '&message=' . $msg . '&numbers=' . $to . '&sender=' . $this->from . '&unicode=e&Rmduplicated=1&return=json' );

		// Check response
		if ( $response['response']['message'] != 'OK' ) {
			return;
		}

		// Decode response
		$response = json_decode( $response['body'] );

		if ( $response->Code == 100 ) {
			$this->InsertToDB( $this->from, $this->msg, $this->to );

			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 *
			 * @param string $result result output.
			 */
			do_action( 'wp_sms_send', $response );

			return true;
		} else {
			return new WP_Error( 'send-sms', $response->MessageIs );
		}

	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		// Get response
		$response = wp_remote_get( $this->wsdl_link . 'getbalance.php?username=' . $this->username . '&password=' . $this->password . '&return=json' );

		// Check response
		if ( $response['response']['message'] != 'OK' ) {
			return new WP_Error( 'account-credit', $response );
		}

		// Decode response
		$response = json_decode( $response['body'] );

		// Return blance
		return $response->currentuserpoints;
	}
}