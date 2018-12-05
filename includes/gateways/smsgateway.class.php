<?php

class smsgateway extends WP_SMS {
	private $wsdl_link = "https://www.sms-gateway.at/sms/";
	public $tariff = "https://www.sms-gateway.at/";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "";
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

		$to = implode( '&number[]=', $this->to );

		$msg = urlencode( $this->msg );

		$result = file_get_contents( "{$this->wsdl_link}sendsms.php?username={$this->username}&validpass={$this->password}&absender={$this->from}&number[]={$to}&message={$msg}&receipt=1" );

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

		return true;
	}
}