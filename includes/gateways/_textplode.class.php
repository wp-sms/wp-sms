<?php

class _textplode extends WP_SMS {
	private $wsdl_link = "";
	public $tariff = "https://www.textplode.com/";
	public $unitrial = true;
	public $unit;
	public $flash = "disable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "440000000000,440000000001";

		// Enable api key
		$this->has_key = true;

		// includes library
		include( 'includes/textplode/textplode.class.php' );
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

		// Init class
		$textplode = new Textplode( $this->has_key );

		// Add recipient
		foreach ( $this->to as $to ) {
			$textplode->messages->add_recipient( $to, array() );
		}

		// Set From Name
		$textplode->messages->set_from( $this->from );

		// Set Message
		$textplode->messages->set_message( $this->msg );

		// Send sms
		$result = $textplode->messages->send();

		// Check result
		if ( ! $result ) {
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

		// Init class
		$textplode = new Textplode( $this->has_key );

		// Get credit
		$credits = $textplode->account->get_credits();

		return $credits;
	}
}