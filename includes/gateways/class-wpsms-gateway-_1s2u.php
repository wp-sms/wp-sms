<?php

namespace WP_SMS\Gateway;

class _1s2u extends \WP_SMS\Gateway {
	private $wsdl_link = "https://api.1s2u.io/";
	public $tariff = "https://1s2u.com/";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "The phone number must contain only digits together with the country code. It should not contain any other symbols such as (+) sign.  Instead  of  plus  sign,  please  put  (00)" . PHP_EOL . "e.g seperate numbers with comma: 12345678900, 11222338844";
	}

	public function SendSMS() {

		/**
		 * Modify sender number
		 *
		 * @param string $this ->from sender number.
		 *
		 * @since 3.4
		 *
		 */
		$this->from = apply_filters( 'wp_sms_from', $this->from );

		/**
		 * Modify Receiver number
		 *
		 * @param array $this ->to receiver number
		 *
		 * @since 3.4
		 *
		 */
		$this->to = apply_filters( 'wp_sms_to', $this->to );

		/**
		 * Modify text message
		 *
		 * @param string $this ->msg text message.
		 *
		 * @since 3.4
		 *
		 */
		$this->msg = apply_filters( 'wp_sms_msg', $this->msg );

		// Get the credit.
		$credit = $this->GetCredit();

		// Check gateway credit
		if ( is_wp_error( $credit ) ) {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $credit->get_error_message(), 'error' );

			return $credit;
		}

		$mt = 0;
		if ( isset( $this->options['send_unicode'] ) and $this->options['send_unicode'] ) {
			$mt = 1;
		}

		$fl = 0;
		if ( $this->isflash == true ) {
			$fl = 1;
		}

		$numbers = array();

		foreach ( $this->to as $number ) {
			$numbers[] = $this->clean_number( $number );
		}

		$to  = implode( ',', $numbers );
		$msg = urlencode( $this->msg );

		$response = wp_remote_get( $this->wsdl_link . "bulksms?username=" . $this->username . "&password=" . $this->password . "&mno=" . $to . "&id=" . $this->from . "&msg=" . $msg . "&mt=" . $mt . "&fl=" . $fl );

		// Check response error
		if ( is_wp_error( $response ) ) {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $response->get_error_message(), 'error' );

			return new \WP_Error( 'send-sms', $response->get_error_message() );
		}

		$result = $this->send_error_check( $response['body'] );

		if ( ! is_wp_error( $result ) ) {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $result );

			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 */
			do_action( 'wp_sms_send', $result );

			return $result;
		} else {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $result->get_error_message(), 'error' );

			return new \WP_Error( 'send-sms', $result->get_error_message() );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->has_key ) {
			return new \WP_Error( 'account-credit', __( 'Username/API-Key does not set for this gateway', 'wp-sms' ) );
		}

		$response = wp_remote_get( $this->wsdl_link . "checkbalance?user=" . $this->username . "&pass=" . $this->password );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'account-credit', $response->get_error_message() );
		}

		$result = json_decode( $response['body'] );

		if ( $result AND is_int( $result ) AND $result != 00 ) {
			return $result;
		} else {
			return new \WP_Error( 'account-credit', 'Invalid username or password' );
		}

	}

	/**
	 * Clean number
	 *
	 * @param $number
	 *
	 * @return bool|string
	 */
	private function clean_number( $number ) {
		$number = str_replace( '+', '00', $number );
		$number = trim( $number );

		return $number;
	}

	/**
	 * @param $result
	 *
	 * @return string|\WP_Error
	 */
	private function send_error_check( $result ) {

		switch ( $result ) {
			case '0000':
				return new \WP_Error( 'send-sms', 'Service Not Available or Down Temporary.' );
				break;
			case '0005':
				return new \WP_Error( 'send-sms', 'Invalid server.' );
				break;
			case '0010':
				return new \WP_Error( 'send-sms', 'Username not provided.' );
				break;
			case '0011':
				return new \WP_Error( 'send-sms', 'Password not provided.' );
				break;
			case '00':
				return new \WP_Error( 'send-sms', 'Invalid username/password.' );
				break;
			case '0020 / 0':
				return new \WP_Error( 'send-sms', 'Insufficient Credits.' );
				break;
			case '0020':
				return new \WP_Error( 'send-sms', 'Insufficient Credits.' );
				break;
			case '0':
				return new \WP_Error( 'send-sms', 'Insufficient Credits.' );
				break;
			case '0030':
				return new \WP_Error( 'send-sms', 'Invalid Sender ID' );
				break;
			case '0040':
				return new \WP_Error( 'send-sms', 'Mobile number not provided.' );
				break;
			case '0041':
				return new \WP_Error( 'send-sms', 'Invalid mobile number.' );
				break;
			case '0042':
				return new \WP_Error( 'send-sms', 'Network not supported.' );
				break;
			case '0050':
				return new \WP_Error( 'send-sms', 'Invalid message.' );
				break;
			case '0060':
				return new \WP_Error( 'send-sms', 'Invalid quantity specified.' );
				break;
			case '0066':
				return new \WP_Error( 'send-sms', 'Network not supported.' );
				break;
			case strpos( $result, 'OK' ) !== false:
				return $result;
				break;
			default:
				return new \WP_Error( 'send-sms', $result );
				break;
		}

		return $result;
	}

}
