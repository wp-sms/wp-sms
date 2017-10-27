<?php

class experttexting extends WP_SMS {
	private $wsdl_link = "https://www.experttexting.com/ExptRestApi/sms/";
	public $tariff = "http://experttexting.com/";
	public $unitrial = true;
	public $unit;
	public $flash = "disable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "The number you want to send message to. Number should be in international format. Ex: to=17327572923";
		$this->has_key        = true;
		$this->help           = "You can find the API Key under \"Account Settings\" in <a href='https://www.experttexting.com/appv2/Dashboard/Profile'>ExpertTexting Profile</a>.";
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

		foreach ( $this->to as $to ) {
			$response = wp_remote_get( $this->wsdl_link . "json/Message/Send?username=" . $this->username . "&password=" . $this->password . "&api_key=" . $this->has_key . "&from=" . $this->from . "&to=" . $to . "&text=" . urlencode( $this->msg ) . "&type=text", array( 'timeout' => 30 ) );
		}

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'send-sms', $response->get_error_message() );
		}

		// Ger response code
		$response_code = wp_remote_retrieve_response_code( $response );

		// Check response code
		if ( $response_code == '200' ) {
			$json = json_decode( $response['body'] );

			if ( $json->Status == 0 ) {
				$this->InsertToDB( $this->from, $this->msg, $this->to );

				/**
				 * Run hook after send sms.
				 *
				 * @since 2.4
				 *
				 * @param string $response result output.
				 */
				do_action( 'wp_sms_send', $json );

				return $json;
			} else {
				return new WP_Error( 'send-sms', $json->ErrorMessage );
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

		$response = wp_remote_get( $this->wsdl_link . "json/Account/Balance?username={$this->username}&password={$this->password}&api_key={$this->has_key}", array( 'timeout' => 30 ) );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'account-credit', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code == '200' ) {
			$json = json_decode( $response['body'] );

			if ( $json->Status == 0 ) {
				return $json->Response->Balance;
			} else {
				return new WP_Error( 'account-credit', $json->ErrorMessage );
			}

		} else {
			return new WP_Error( 'account-credit', $response['body'] );
		}

		return true;
	}
}