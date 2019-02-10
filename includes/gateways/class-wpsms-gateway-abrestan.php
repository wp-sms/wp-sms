<?php

namespace WP_SMS\Gateway;

class abrestan extends \WP_SMS\Gateway {
	private $wsdl_link = "http://37.130.202.188/class/sms/wssimple/server.php?wsdl";
	private $client = null;
	public $tariff = "http://abrestan.com/";
	public $unitrial = true;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "09xxxxxxxx";

		if ( ! class_exists( 'nusoap_client' ) ) {
			include_once WP_SMS_DIR . 'includes/libraries/nusoap.class.php';
		}

		$this->client = new \nusoap_client( $this->wsdl_link );

		$this->client->soap_defencoding = 'UTF-8';
		$this->client->decode_utf8      = true;
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

		$result = $this->client->call( "SendSMS", array(
			'Username'         => $this->username,
			'Password'         => $this->password,
			'SenderNumber'     => $this->from,
			'RecipientNumbers' => $this->to,
			'Message'          => $this->msg,
			'Type'             => 'normal'
		) );

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

		$result = $this->client->call( "GetCredit", array(
			'Username' => $this->username,
			'Password' => $this->password
		) );

		return $result;
	}
}