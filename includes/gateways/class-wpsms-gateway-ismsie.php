<?php

// Set namespace class
namespace WP_SMS\Gateway;

class ismsie extends \WP_SMS\Gateway {
	private $wsdl_link = "http://ws3584.isms.ir/sendWS";
	public $tariff = "http://isms.ir/";
	public $unitrial = true;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "09xxxxxxxx";
		$this->has_key        = true;
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

		$data = array(
			'username' => $this->username,
			'password' => $this->password,
			'mobiles'  => $this->to,
			'body'     => $this->msg,
		);

		$data = http_build_query( $data );

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $this->wsdl_link );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

		$result = curl_exec( $ch );
		$json   = json_decode( $result, true );

		if ( $result ) {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $result );
			$this->Hook( 'wp_sms_send', $json );

			return $json;
		}
		// Log th result
		$this->log( $this->from, $this->msg, $this->to, $result, 'error' );

		return new \WP_Error( 'send-sms', $result );
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new \WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		return true;
	}
}