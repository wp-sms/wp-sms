<?php

class afilnet extends WP_SMS {
	private $wsdl_link = "https://www.afilnet.com/api/http/";
	public $tariff = "http://www.afilnet.com/";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "34600000000";
		$this->bulk_send      = false;
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

		// Implode numbers
		$to = implode( ',', $this->to );

		// Unicode message
		$msg = urlencode( $this->msg );

		$response = wp_remote_get( $this->wsdl_link . "?class=sms&method=sendsms&user=" . $this->username . "&password=" . $this->password . "&from=" . $this->from . "&to=" . $this->to[0] . "&sms=" . $this->msg . "&scheduledatetime=&output=", array( 'timeout' => 30 ) );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'account-credit', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code == '200' ) {
			$result = json_decode( $response['body'] );

			if ( $result->status == 'SUCCESS' ) {
				$this->InsertToDB( $this->from, $this->msg, $this->to[0] );

				/**
				 * Run hook after send sms.
				 *
				 * @since 2.4
				 *
				 * @param string $result result output.
				 */
				do_action( 'wp_sms_send', $result );

				return $result->result;
			} else {
				return new WP_Error( 'send-sms', $result->error );
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

		$response = wp_remote_get( $this->wsdl_link . "?class=user&method=getbalance&user=" . $this->username . "&password=" . $this->password, array( 'timeout' => 30 ) );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'account-credit', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code == '200' ) {
			if ( ! $response['body'] ) {
				return new WP_Error( 'account-credit', __( 'Server API Unavailable', 'wp-sms' ) );
			}

			$result = json_decode( $response['body'] );

			if ( $result->status == 'SUCCESS' ) {
				return $result->result;
			} else {
				return new WP_Error( 'account-credit', $result->error );
			}
		} else {
			return new WP_Error( 'account-credit', $response['body'] );
		}
	}
}