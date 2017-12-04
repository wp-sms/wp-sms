<?php

class candoosms extends WP_SMS {
	private $wsdl_link = "http://my.candoosms.com/services/?wsdl";
	public $tariff = "http://candoosms.com/";
	public $unitrial = true;
	public $unit;
	public $flash = "disable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "09xxxxxxxx";

		if ( ! class_exists( 'nusoap_client' ) ) {
			include_once dirname( __FILE__ ) . '/../classes/nusoap.class.php';
		}
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

		$client                   = new nusoap_client( $this->wsdl_link, true );
		$client->soap_defencoding = 'UTF-8';
		$client->decode_utf8      = false;

		$result = $client->call( 'Send', array(
			'username'  => $this->username,
			'password'  => $this->password,
			'srcNumber' => $this->from,
			'body'      => $this->msg,
			'destNo'    => $this->to,
			'flash'     => '0'
		) );

		if ( $client->fault ) {
			return new WP_Error( 'send-sms', $result );
		} else {
			if ( $client->getError() ) {
				return new WP_Error( 'send-sms', $client->getError() );
			} else {
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
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		$client = new nusoap_client( $this->wsdl_link, true );

		if ( $client->getError() ) {
			return new WP_Error( 'account-credit', $client->getError() );
		}

		$result = $client->call( 'Balance', array( 'username' => $this->username, 'password' => $this->password ) );

		if ( $result ) {
			return $result;
		} else {
			return new WP_Error( 'account-credit', $result );
		}
	}
}