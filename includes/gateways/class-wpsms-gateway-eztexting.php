<?php

namespace WP_SMS\Gateway;

class eztexting extends \WP_SMS\Gateway {
	private $wsdl_link = "https://app.eztexting.com/";
	public $tariff = "https://www.eztexting.com/";
	public $unitrial = true;
	public $unit;
	public $flash = "disable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->has_key = false;
		$this->help    = "Please enter your Username and Password in <a href='https://www.eztexting.com'>Eztexting.com</a>.";
	}

	public function SendSMS() {

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

		// Get the credit.
		$credit = $this->GetCredit();

		// Check gateway credit
		if ( is_wp_error( $credit ) ) {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $credit->get_error_message(), 'error' );

			return $credit;
		}

		// Check unicode option if enabled.
		if ( isset( $this->options['send_unicode'] ) and $this->options['send_unicode'] ) {
			$text = $this->msg;
		} else {
			$text = urlencode( $this->msg );
		}

		$response = wp_remote_post( $this->wsdl_link . "/sending/messages?format=json", array( 'method' => 'POST', 'timeout' => 60, 'body' => array( 'User' => $this->username, 'Password' => $this->password, 'PhoneNumbers' => $this->to, 'Message' => $text, 'StampToSend' => $this->from ) ) );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $response->get_error_message(), 'error' );

			return new \WP_Error( 'send-sms', $response->get_error_message() );
		}

		// Ger response code
		$response_code = wp_remote_retrieve_response_code( $response );

		// Check response code
		if ( $response_code == '200' ) {
			$json = json_decode( $response['body'] );

			if ( $json->Response->Status != "Failure" ) {
				// Log the result
				$this->log( $this->from, $this->msg, $this->to, $response['body'] );

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
				// Log the result
				$this->log( $this->from, $this->msg, $this->to, $json->ErrorMessage, 'error' );

				return new \WP_Error( 'send-sms', $json->ErrorMessage );
			}

		} else {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $response['body'], 'error' );

			return new \WP_Error( 'send-sms', $response['body'] );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username or ! $this->password ) {
			return new \WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms-pro' ) );
		}

		$response = wp_remote_get( $this->wsdl_link . "billing/credits/get?format=json&User={$this->username}&Password={$this->password}", array( 'timeout' => 60 ) );

		// Check gateway credit
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'account-credit', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code == '200' ) {
			$json = json_decode( $response['body'] );

			if ( $json->Response->Status != "Failure" ) {
				return $json->Response->Entry->TotalCredits;
			} else {
				return new \WP_Error( 'account-credit', $json->ErrorMessage );
			}

		} else {
			return new \WP_Error( 'account-credit', $response['body'] );
		}

		return true;
	}
}