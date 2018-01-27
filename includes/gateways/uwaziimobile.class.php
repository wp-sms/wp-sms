<?php

class uwaziimobile extends WP_SMS {
	private $wsdl_link = "http://107.20.199.106/";
	public $tariff = "http://uwaziimobile.com/";
	public $unitrial = true;
	public $unit;
	public $flash = "disable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "Destination addresses must be in international format (Example: 254722123456).";
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

		// Reformat number
		$to = array();
		foreach ( $this->to as $number ) {
			if ( substr( $number, 0, 2 ) === "07" ) {
				$number = substr( $number, 2 );
				$number = '2547' . $number;
			}

			$to[] = $number;
		}

		$args = array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'accept'        => 'application/json',
				'authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
			),
			'body'    => json_encode( array(
				'messages' => array(
					array(
						'from' => $this->from,
						'to'   => $to,
						'text' => $this->msg,
					)
				)
			) )
		);

		$response = wp_remote_post( $this->wsdl_link . "restapi/sms/1/text/multi", $args );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'send-sms', $response->get_error_message() );
		}

		// Ger response code
		$response_code = wp_remote_retrieve_response_code( $response );

		// Decode response
		$response = json_decode( $response['body'] );

		// Check response code
		if ( $response_code == '200' ) {
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
			return new WP_Error( 'account-credit', $response->requestError->serviceException->text );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username or ! $this->password ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms-pro' ) );
		}

		$args     = array(
			'timeout' => 10,
			'headers' => array(
				'accept'        => 'application/json',
				'authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
			)
		);
		$response = wp_remote_get( $this->wsdl_link . "restapi/account/1/balance", $args );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'account-credit', $response->get_error_message() );
		}

		// Ger response code
		$response_code = wp_remote_retrieve_response_code( $response );

		// Decode response
		$response = json_decode( $response['body'] );

		// Check response code
		if ( $response_code == '200' ) {
			return $response->balance;
		} else {
			return new WP_Error( 'account-credit', $response->requestError->serviceException->text );
		}

		return true;
	}
}