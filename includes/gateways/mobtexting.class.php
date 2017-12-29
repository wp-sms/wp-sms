<?php

class Mobtexting extends WP_SMS {
	private $wsdl_link = "http://api.mobtexting.com/v1";
	public $tariff = "https://www.mobtexting.com/pricing.php";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "91[9,8,7,6]XXXXXXXXX";
		$this->help           = "Login authentication key (this key is unique for every user).<br>For BRAND Sender id Please Make it Approve Before Sending SMS";
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

		// comma seperated receivers
		$to = implode( ',', $this->to );
		$msg = urlencode( $this->msg );
		$api_end_point = $this->wsdl_link."/smses";
		$api_args = Array(
			'api_key' => $this->has_key,
			'sender_id' => $this->from,
			'message' => $msg,
			'mobile_no' => $to
		);
		$response = wp_remote_post( $api_end_point, Array('body'=>$api_args, 'timeout'=>30) );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'account-credit', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$result        = json_decode( $response['body'] );

		if ( $response_code == '201' ) {
			if ( $result->status == 'success' ) {
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
				return $result->message;
			}

		} else {
			return new WP_Error( 'send-sms', $result->message );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->has_key ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}
		$api_end_point = $this->wsdl_link."/credit";
		$api_args = Array(
			'timeout'=> 3000
		);
		$response = wp_remote_get( $api_end_point.'?api_key='.$this->has_key, $api_args );
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

			if ( isset( $result->status ) and $result->status != 'success' ) {
				return new WP_Error( 'account-credit', $result->msg . $result->description );
			} else {
				return $result->balance;
			}
		} else {
			return new WP_Error( 'account-credit', $response['body'] );
		}
	}
}
