<?php

namespace WP_SMS\Gateway;

class alchemymarketinggm extends \WP_SMS\Gateway {
	private $wsdl_link = "http://alchemymarketinggm.com:9501/api";
	public $tariff = "http://www.alchemymarketinggm.com";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "90xxxxxxxxxx";
		$this->has_key        = false;
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

		// Encode message

		foreach ( $this->to as $k => $number ) {
			$this->to[ $k ] = trim( $number );
		}

		$to  = implode( ',', $this->to );
		$to  = urlencode( $to );
		$msg = urlencode( $this->msg );

		$result = file_get_contents( $this->wsdl_link . '?username=' . $this->username . '&password=' . $this->password . '&action=sendmessage&messagetype=SMS:TEXT&recipient=' . $to . '&messagedata=' . $msg );

		$result = (array) simplexml_load_string( $result );

		if ( isset( $result['action'] ) AND $result['action'] == 'sendmessage' AND isset( $result['data']->acceptreport ) ) {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $result['data'] );

			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 *
			 * @param string $result result output.
			 */
			do_action( 'wp_sms_send', $result['data'] );

			return $result['data'];
		}
		// Log the result
		$this->log( $this->from, $this->msg, $this->to, $result['data']->errormessage, 'error' );

		return new \WP_Error( 'send-sms', $result );
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new \WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		// Get data
		$get_data = file_get_contents( $this->wsdl_link . '?action=getcredits&username=' . $this->username . '&password=' . $this->password );

		// Check enable simplexml function in the php
		if ( ! function_exists( 'simplexml_load_string' ) ) {
			return new \WP_Error( 'account-credit', 'simplexml_load_string PHP Function disabled!' );
		}

		// Load xml
		$xml = (array) simplexml_load_string( $get_data );

		if ( isset( $xml['action'] ) AND $xml['action'] == 'getcredits' ) {
			return (int) $xml['data']->account->balance;
		} else {
			$error = (array) $xml['data']->errormessage;

			return new \WP_Error( 'account-credit', $error[0] );
		}
	}
}