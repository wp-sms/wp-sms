<?php

class fortytwo extends WP_SMS {
	private $wsdl_link = "https://rest.fortytwo.com/1/";
	public $tariff = "http://fortytwo.com/";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "Number must be in international format and can only be between 7-20 digits long. First digit cannot be a 0";
		$this->has_key        = true;
		$this->help           = 'The API token is generated through the Client Control Panel (https://controlpanel.fortytwo.com/), in the tokens section, under the IM tab.';
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

		// Reformat number
		$to = array();
		foreach ( $this->to as $number ) {
			$to[] = array( 'number' => $number );
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Token ' . $this->has_key,
				'Content-Type'  => 'application/json; charset=utf-8',
			),
			'body'    => json_encode( array(
				'destinations' => $to,
				'sms_content'  => array(
					'sender_id' => $this->from,
					'message'   => $this->msg,
				)
			) )
		);

		$response = wp_remote_post( $this->wsdl_link . "im", $args );

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
			return new WP_Error( 'account-credit', $response->result_info->description );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->has_key ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		return true;
	}
}