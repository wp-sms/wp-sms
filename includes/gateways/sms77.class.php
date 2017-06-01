<?php

class sms77 extends WP_SMS {
	private $wsdl_link = "https://gateway.sms77.de/";
	public $tariff = "http://www.sms77.de";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "0049171999999999 or 0171999999999 or 49171999999999";
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

		$result = @file_get_contents( $this->wsdl_link . '?u=' . urlencode( $this->username ) . '&p=' . urlencode( $this->password ) . '&text=' . urlencode( $this->msg ) . '&to=' . implode( $this->to, "," ) . '&type=quality&from=' . urlencode( $this->from ) );

		if ( $result == '100' ) {
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

		$result = @file_get_contents( $this->wsdl_link . 'balance.php?u=' . urlencode( $this->username ) . '&p=' . urlencode( $this->password ) );

		return $result;
	}
}