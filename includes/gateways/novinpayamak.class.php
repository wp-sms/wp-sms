<?php

class novinpayamak extends WP_SMS {
	private $wsdl_link = "http://www.novinpayamak.com/services/SMSBox/wsdl";
	public $tariff = "http://www.smscall.ir/?page_id=63";
	public $unitrial = true;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "09xxxxxxxx";
		$this->has_key        = true;

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

		$client = new SoapClient( $this->wsdl_link, array( 'encoding' => 'UTF-8' ) );

		$result = $client->Send(
			array(
				'Auth'       => array( 'number' => $this->from, 'pass' => $this->has_key ),
				'Recipients' => $this->to,
				'Message'    => array( $this->msg ),
				'Flash'      => $this->isflash
			)
		);

		if ( $result->Status != 1000 ) {
			return false;
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
		}

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

		try {
			$client = new SoapClient( 'http://www.novinpayamak.com/services/CISGate/wsdl', array( 'encoding' => 'UTF-8' ) );
		} catch ( Exception $e ) {
			return new WP_Error( 'account-credit', $e->getMessage() );
		}

		$result = $client->CheckRealCredit( array(
			'Auth' => array(
				'email'    => $this->username,
				'password' => $this->password
			)
		) );

		if ( $result->Status != 1000 ) {
			return new WP_Error( 'account-credit', $result );
		}

		return $result->Credit;
	}
}