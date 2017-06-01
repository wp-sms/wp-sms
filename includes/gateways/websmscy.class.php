<?php

class websmscy extends WP_SMS {
	private $wsdl_link = "https://www.websms.com.cy/webservices/websms.wsdl";
	public $tariff = "https://www.websms.com.cy/";
	public $unitrial = true;
	public $unit;
	public $flash = "disable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "Phone numbers must be in the 9XXXXXXX format beginning with 99, 96 or 97";
		ini_set( "soap.wsdl_cache_enabled", "0" );
		include_once( 'includes/websmscy/soapClient.class.php' );
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

		$cfg = array(
			'wsdl_file' => $this->wsdl_link,
			'username'  => $this->username,
			'password'  => $this->password,
		);

		$ws = new WebsmsClient( $cfg );

		try {
			$result = $ws->submitSM( $this->from, $this->to, $this->msg, "GSM" );

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
		} catch ( Exception $e ) {
			return new WP_Error( 'send-sms', $e->getMessage() );
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

		$cfg = array(
			'wsdl_file' => $this->wsdl_link,
			'username'  => $this->username,
			'password'  => $this->password,
		);

		$ws = new WebsmsClient( $cfg );

		try {
			$credits = $ws->getCredits();

			return $credits;
		} catch ( Exception $e ) {
			return new WP_Error( 'account-credit', $e->getMessage() );
		}
	}
}