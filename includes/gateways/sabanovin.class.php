<?php

class sabanovin extends WP_SMS {
	private $wsdl_link = "http://api.sabanovin.com/v1/";
	public $tariff = "http://sabanovin.com/";
	public $unitrial = true;
	public $unit;
	public $flash = "disable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->has_key = true;
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

		$to       = implode( ',', $this->to );
		$response = wp_remote_get( $this->wsdl_link . $this->has_key . "/sms/send.json?gateway=" . $this->from . "&text=" . urlencode( $this->msg ) . "&to=" . $to, array( 'timeout' => 30 ) );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'send-sms', $response->get_error_message() );
		}

		// Ger response code
		$response_code = wp_remote_retrieve_response_code( $response );
		$json          = json_decode( $response['body'] );

		if ( $response_code == '200' ) {
			if ( $json->status->code == 200 ) {
				$this->InsertToDB( $this->from, $this->msg, $this->to );

				/**
				 * Run hook after send sms.
				 *
				 * @since 2.4
				 *
				 * @param string $response result output.
				 */
				do_action( 'wp_sms_send', $json );

				return $json->entries;
			} else {
				return new WP_Error( 'send-sms', $json->status->message );
			}
		} else {
			return new WP_Error( 'send-sms', $json->status->message );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->has_key ) {
			return new WP_Error( 'account-credit', __( 'API/Key does not set for this gateway', 'wp-sms-pro' ) );
		}

		$response = wp_remote_get( $this->wsdl_link . $this->has_key . "/credit.json", array( 'timeout' => 30 ) );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'account-credit', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$json          = json_decode( $response['body'] );

		if ( $response_code == '200' ) {
			if ( $json->status->code == 200 ) {
				return $json->entry->credit;
			} else {
				return new WP_Error( 'account-credit', $json->status->message );
			}
		} else {
			return new WP_Error( 'account-credit', $json->status->message );
		}
	}
}