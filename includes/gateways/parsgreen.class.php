<?php

class parsgreen extends WP_SMS {
	private $wsdl_link = "http://login.parsgreen.com/Api/SendSMS.asmx?WSDL";
	private $wsdl_link_credit = "http://login.parsgreen.com/Api/ProfileService.asmx?WSDL";
	public $tariff = "http://www.parsgreen.com/";
	public $unitrial = true;
	public $unit = 'ریال';
	public $flash = "disable";
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
			$client                  = new SoapClient( $this->wsdl_link );
			$parameters['signature'] = $this->username;
			$parameters['from']      = $this->from;
			$parameters['to']        = $this->to;
			$parameters['text']      = $this->msg;
			$parameters['isFlash']   = $this->isflash;
			$parameters['udh']       = "";
			$parameters['success']   = 0x0;
			$parameters['retStr']    = array( 0 );

			$responseSTD = (array) $client->SendGroupSMS( $parameters );
			$result      = $responseSTD['SendGroupSMSResult'];

			if ( $result == 1 ) {
				$result = true;
			} else {
				$result = false;
			}

			if ( $result ) {
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
			} else {
				echo 'مشکلی در ارسال پیام بوجود امد';
			}
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
			$client      = new SoapClient( $this->wsdl_link_credit );
			$parameters  = array( 'signature' => $this->username );
			$responseSTD = (array) $client->GetCredit( $parameters );

			return $responseSTD['GetCreditResult'];
		} catch ( SoapFault $ex ) {
			return new WP_Error( 'account-credit', $ex->faultstring );
		}
	}
}