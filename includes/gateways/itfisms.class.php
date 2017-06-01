<?php

class itfisms extends WP_SMS {
	private $wsdl_link = "http://websms.itfisms.com/vendorsms/";
	public $tariff = "http://www.itfisms.com/";
	public $unitrial = true;
	public $unit;
	public $flash = "disable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "e.g. 9029963999";
		$this->help           = 'Please enter Route ID in API Key field';
	}

	public function SendSMS() {
		// Check gateway credit
		if ( is_wp_error( $this->GetCredit() ) ) {
			return new WP_Error( 'account-credit', __( 'Your account does not credit for sending sms.', 'wp-sms-pro' ) );
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

		$response = wp_remote_get( $this->wsdl_link . "pushsms.aspx?user=" . $this->username . "&password=" . $this->password . "&msisdn=" . implode( ',', $this->to ) . "&sid=" . $this->from . "&msg=" . urlencode( $this->msg ) . "&fl=0&gwid=2" );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'send-sms', $response->get_error_message() );
		}

		// Ger response code
		$response_code = wp_remote_retrieve_response_code( $response );

		// Check response code
		if ( $response_code == '200' ) {
			$response = json_decode( $response['body'] );

			if ( $response->ErrorMessage == 'Success' ) {
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
				return new WP_Error( 'send-sms', $response->ErrorMessage );
			}

		} else {
			return new WP_Error( 'send-sms', $response['body'] );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username or ! $this->password ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms-pro' ) );
		}

		$response = wp_remote_get( $this->wsdl_link . "CheckBalance.aspx?user={$this->username}&password={$this->password}" );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'account-credit', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code == '200' ) {
			if ( strstr( $response['body'], 'Success' ) ) {
				return $response['body'];
			} else {
				return new WP_Error( 'account-credit', $response['body'] );
			}
		} else {
			return new WP_Error( 'account-credit', $response['body'] );
		}

		return true;
	}
}