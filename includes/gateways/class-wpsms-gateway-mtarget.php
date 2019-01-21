<?php

namespace WP_SMS\Gateway;

class mtarget extends \WP_SMS\Gateway {
	private $wsdl_link = "http://smswebservices.public.mtarget.fr/SmsWebServices/ServletSms";
	public $tariff = "http://mtarget.fr/";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "33xxxxxxxxx";
	}

	public function SendSMS() {

		$msg = urlencode( $this->msg );

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

		// Get the credit.
		$credit = $this->GetCredit();

		// Check gateway credit
		if ( is_wp_error( $credit ) ) {
			// Log the result
			$this->log( $this->from, $this->msg, $this->to, $credit->get_error_message(), 'error' );

			return $credit;
		}

		foreach ( $this->to as $to ) {
			// Check credit for the gateway
			if ( ! $this->GetCredit() ) {
				$this->log( $this->from, $this->msg, $this->to, $this->GetCredit()->get_error_message(), 'error' );

				return;
			}

			$result = file_get_contents( $this->wsdl_link . '?method=sendText&username=' . $this->username . '&password=' . $this->password . '&serviceid=' . $this->from . '&destinationAddress=' . $to . '&originatingAddress=00000&operatorid=0&paycode=0&msgtext=' . $msg );
		}

		if ( $result == '0' ) {
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
		$this->log( $this->from, $this->msg, $this->to, $this->GetCredit()->get_error_message(), 'error' );

		return new \WP_Error( 'send-sms', $result );
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new \WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		return true;
	}
}