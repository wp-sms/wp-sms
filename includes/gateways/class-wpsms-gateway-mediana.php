<?php

namespace WP_SMS\Gateway;

class mediana extends \WP_SMS\Gateway {

	public $tariff = "http://mediana.ir/";
	public $unitrial = true;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	private $wsdl_link = "http://37.130.202.188/class/sms/wssimple/server.php?wsdl";
	private $client = null;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "09xxxxxxxx";

		if ( ! class_exists( 'SoapClient' ) ) {
			return new \WP_Error( 'required-class', __( 'Class SoapClient not found. please enable php_soap in your php.', 'wp-sms' ) );
		}

		$this->client = new \SoapClient( $this->wsdl_link, [ 'exceptions' => false, 'encoding' => 'UTF-8' ] );
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

		$params = [
			'Username'         => $this->username,
			'Password'         => $this->password,
			'SenderNumber'     => $this->from,
			'RecipientNumbers' => $this->to,
			'Message'          => $this->msg,
			'Type'             => 'normal'
		];

		$result = $this->client->__soapCall( "SendSMS", $params );

		if ( $result ) {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $result );

			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 *
			 * @param string $result result output.
			 */
			do_action( 'wp_sms_send', $result );

			return $result;
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

		$params = [
			'Username' => $this->username,
			'Password' => $this->password
		];

		$result = $this->client->__soapCall( "GetCredit", $params );

		return $result;
	}
}