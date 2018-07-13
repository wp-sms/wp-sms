<?php

class idehpayam extends WP_SMS {
	private $wsdl_link = "http://87.107.124.66/webservice/send.php?wsdl";
	public $tariff = "https://www.idehpayam.com/";
	public $unitrial = true;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "09xxxxxxxx";

		ini_set( "soap.wsdl_cache_enabled", "0" );
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

		$from    = array();
		$message = array();
		$type    = array();
		$result  = '';

		try {
			$client = new SoapClient( $this->wsdl_link );

			foreach ( $this->to as $to ) {
				$from[]    = $this->from;
				$message[] = $this->msg;
				$type[]    = $this->isflash;
			}

			$result = $client->SendMultiSMS( $from, $this->to, $message, $type, $this->username, $this->password );
		} catch ( Exception $e ) {
			$error = new WP_Error( 'send-sms', $e->getMessage() );
		}

		/**
		 * Run hook after send sms.
		 *
		 * @since 2.4
		 *
		 * @param string $result result output.
		 */
		do_action( 'wp_sms_send', $result );

		$this->InsertToDB( $this->from, $this->msg, $this->to );

		return $result;
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		if ( ! class_exists( 'SoapClient' ) ) {
			return new WP_Error( 'required-class', __( 'Class SoapClient not found. please enable php_soap in your php.', 'wp-sms' ) );
		}

		try {
			$client = new SoapClient( $this->wsdl_link );
		} catch ( Exception $e ) {
			return new WP_Error( 'account-credit', $e->getMessage() );
		}

		$results = $client->GetCredit( $this->username, $this->password, array( "", "" ) );

		return round( $results );
	}
}