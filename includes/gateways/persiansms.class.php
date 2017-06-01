<?php

class persianSMS extends WP_SMS {
	private $wsdl_link = "http://persian-sms.com/API/SendSMS.asmx?WSDL";
	public $tariff = "http://www.persian-sms.com/";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $api;
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

		try {
			$client                         = new SoapClient( $this->wsdl_link );
			$parameters['USERNAME']         = $this->username;
			$parameters['PASSWORD']         = $this->password;
			$parameters['TO']               = $this->to;
			$parameters['FROM']             = $this->from;
			$parameters['TEXT']             = $this->msg;
			$parameters['API']              = $this->api;
			$parameters['API_CHANGE_ALLOW'] = 1;
			$parameters['FLASH']            = $this->isflash;
			$parameters['Internation']      = false;

			$result = $client->Send_Sms4( $parameters )->Send_Sms4Result;

			$this->InsertToDB( $this->from, $this->msg, $this->to );

			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 *
			 * @param string $result result output.
			 */
			do_action( 'wp_sms_send', $result );

			return $result;
		} catch ( SoapFault $ex ) {
			return new WP_Error( 'send-sms', $ex->faultstring );
		}
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

			return $client->CHECK_CREDIT( array(
				"USERNAME" => $this->username,
				"PASSWORD" => $this->password
			) )->CHECK_CREDITResult;
		} catch ( SoapFault $ex ) {
			return new WP_Error( 'account-credit', $ex->faultstring );
		}

	}
}