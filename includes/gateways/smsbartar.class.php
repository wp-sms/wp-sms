<?php

class smsbartar extends WP_SMS {
	private $wsdl_link = "http://sms.sms-bartar.com/webservice/?WSDL";
	public $tariff = "http://www.sms-bartar.com/%D9%BE%D9%86%D9%84-%D8%A7%D8%B3-%D8%A7%D9%85-%D8%A7%D8%B3-%D8%AB%D8%A7%D8%A8%D8%AA";
	public $unitrial = true;
	public $unit;
	public $flash = "disable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "09xxxxxxxx";

		ini_set( "soap.wsdl_cache_enabled", "0" );
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

		// Check gateway credit
		if ( is_wp_error( $this->GetCredit() ) ) {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $this->GetCredit()->get_error_message(), 'error' );

			return $this->GetCredit();
		}

		$options = array( 'login' => $this->username, 'password' => $this->password );
		$client  = new SoapClient( $this->wsdl_link, $options );

		$result = $client->sendToMany( $this->to, $this->msg, $this->from );

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
		// Log the result
		$this->log( $this->from, $this->msg, $this->to, $result, 'error' );

		return new WP_Error( 'send-sms', $result );
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		if ( ! class_exists( 'SoapClient' ) ) {
			return new WP_Error( 'required-class', __( 'Class SoapClient not found. please enable php_soap in your php.', 'wp-sms' ) );
		}

		$options = array( 'login' => $this->username, 'password' => $this->password );

		try {
			$client = new SoapClient( $this->wsdl_link, $options );
		} catch ( Exception $e ) {
			return new WP_Error( 'account-credit', $e->getMessage() );
		}

		try {
			$credit = $client->accountInfo();

			return $credit->remaining;
		} catch ( SoapFault $ex ) {
			return new WP_Error( 'account-credit', $ex->faultstring );
		}
	}
}