<?php

class smsozone extends WP_SMS {
	private $wsdl_link = "http://smsozone.com/api/mt/";
	public $tariff = "http://ozonecmc.com/";
	public $unitrial = true;
	public $unit;
	public $flash = "disable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "e.g. 91989xxxxxxx";
		$this->has_key        = true;
		$this->help           = "Enter the route id in this API key field. Click Here (https://smsozone.com/Web/MT/MyRoutes.aspx) for more information regarding your routeid.";
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

		$response = wp_remote_get( $this->wsdl_link . "SendSMS?user=" . $this->username . "&password=" . $this->password . "&senderid=" . $this->from . "&channel=Trans&DCS=0&flashsms=0&number=" . implode( ',', $this->to ) . "&text=" . urlencode( $this->msg ) . "&route=" . $this->has_key );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'send-sms', $response->get_error_message() );
		}

		// Ger response code
		$response_code = wp_remote_retrieve_response_code( $response );
		$json          = json_decode( $response['body'] );
		// Check response code
		if ( $response_code == '200' ) {
			if ( $json->ErrorCode == 0 ) {
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
			return new WP_Error( 'send-sms', $json->ExceptionMessage );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username or ! $this->password ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms-pro' ) );
		}

		$response = wp_remote_get( $this->wsdl_link . "GetBalance?User={$this->username}&Password={$this->password}" );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'account-credit', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code == '200' ) {
			$json = json_decode( $response['body'] );

			if ( $json->ErrorCode == 0 ) {
				return $json->Balance;
			} else {
				return new WP_Error( 'account-credit', $json->ErrorMessage );
			}

		} else {
			return new WP_Error( 'account-credit', $response['body'] );
		}

		return true;
	}
}