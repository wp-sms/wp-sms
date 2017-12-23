<?php

class infodomain extends WP_SMS {
	private $wsdl_link = "http://sms.infodomain.asia/websmsapi";
	public $tariff = "http://sms.infodomain.asia";
	public $unitrial = false;
	public $unit;
	public $flash = "disable";
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

		$to       = implode( $this->to, "," );
		$msg      = urlencode( $this->msg );
		$response = wp_remote_get( $this->wsdl_link . "/ISendSMSNoDR.aspx?username=" . $this->username . "&password=" . $this->password . "&message=" . $msg . "&mobile=" . $to . "&Sender=" . $this->from . "&type=1" );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'send-sms', $response->get_error_message() );
		}

		// Ger response code
		$response_code = wp_remote_retrieve_response_code( $response );

		// Check response code
		if ( $response_code == '200' ) {
			if ( strpos( $response['body'], '1701:' ) !== false ) {
				$this->InsertToDB( $this->from, $this->msg, $this->to );

				/**
				 * Run hook after send sms.
				 *
				 * @since 2.4
				 *
				 * @param string $response result output.
				 */
				do_action( 'wp_sms_send', $response['body'] );

				return $response['body'];
			} else {
				$error_message = '';

				switch ( $response['body'] ) {
					case '1702':
						$error_message = 'Invalid Username/Password';
						break;

					case '1703':
						$error_message = 'Internal Server Error';
						break;

					case '1704':
						$error_message = 'Insufficient Credits';
						break;

					case '1705':
						$error_message = 'Invalid Mobile Number';
						break;

					case '1706':
						$error_message = 'Invalid Message / Invalid SenderID';
						break;

					case '1707':
						$error_message = 'Transfer Credits Successful';
						break;

					case '1708':
						$error_message = 'Account not existing for Credits Transfer';
						break;

					case '1709':
						$error_message = 'Invalid Credits Value for Credits Transfer';
						break;

					case '1718':
						$error_message = 'Duplicate record received';
						break;
				}

				return new WP_Error( 'send-sms', $error_message );
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

		$response      = wp_remote_get( $this->wsdl_link . "/creditsLeft.aspx?username=" . $this->username . "&password=" . $this->password );
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code == '200' ) {
			return $response['body'];
		} else {
			return new WP_Error( 'account-credit', __( 'Username/Password is not valid.', 'wp-sms' ) );
		}
	}
}