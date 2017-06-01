<?php

class bestit extends WP_SMS {
	private $wsdl_link = "http://panelsms.bestit.co/WsSms.asmx?wsdl";
	public $tariff = "http://panelsms.bestit.co/";
	public $unitrial = false;
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

		$client = new SoapClient( $this->wsdl_link );

		$result = $client->sendsms(
			array(
				'username' => $this->username,
				'password' => $this->password,
				'to'       => implode( ',', $this->to ),
				'text'     => $this->msg,
				'from'     => $this->from,
				'api'      => $this->has_key,
			)
		);

		if (
			$result->sendsmsResult->long == 1000 or
			$result->sendsmsResult->long == 1001 or
			$result->sendsmsResult->long == 1002 or
			$result->sendsmsResult->long == 1003 or
			$result->sendsmsResult->long == 1004 or
			$result->sendsmsResult->long == 1005 or
			$result->sendsmsResult->long == 1006 or
			$result->sendsmsResult->long == 1007 or
			$result->sendsmsResult->long == 1008 or
			$result->sendsmsResult->long == 1009 or
			$result->sendsmsResult->long == 1010
		) {
			return new WP_Error( 'send-sms', $result );
		}

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

		$result = $client->Credites( array( 'username' => $this->username, 'password' => $this->password ) );

		return $result->CreditesResult;
	}
}