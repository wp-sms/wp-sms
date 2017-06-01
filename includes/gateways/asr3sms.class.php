<?php

class asr3sms extends WP_SMS {
	private $wsdl_link = "https://www.asr3sms.com/sms/api/";
	public $tariff = "https://www.asr3sms.com/";
	public $unitrial = false;
	public $unit;
	public $flash = "disable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "";
		$this->bulk_send      = false;
	}

	/**
	 * @return array|mixed|object|WP_Error
	 */
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

		$response = wp_remote_get( $this->wsdl_link . 'sendsms.php?username=' . $this->username . '&password=' . $this->password . '&message=' . $this->msg . '&numbers=' . $this->to[0] . '&sender=' . $this->from . '&unicode=e&Rmduplicated=1&return=json' );

		// Check request
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'send-sms', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code == '200' ) {
			$result = json_decode( $response['body'] );

			if ( $result->Code == 100 ) {
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
			} else {
				return new WP_Error( 'send-sms', $result->MessageIs );
			}
		} else {
			return new WP_Error( 'send-sms', $response['body'] );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		$response = wp_remote_get( $this->wsdl_link . 'getbalance.php?username=' . $this->username . '&password=' . $this->password . '&return=json' );

		// Check request
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'account-credit', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code == '200' ) {
			$result = json_decode( $response['body'] );

			return $result->currentuserpoints;
		} else {
			return new WP_Error( 'account-credit', $response['body'] );
		}
	}
}