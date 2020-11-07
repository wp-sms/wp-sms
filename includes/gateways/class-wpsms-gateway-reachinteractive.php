<?php

namespace WP_SMS\Gateway;

class reachinteractive extends \WP_SMS\Gateway {
	private $wsdl_link = "http://http-1-uat.reach-interactive.com/sms";
	public $tariff = "https://reach-interactive.com/";
	public $unitrial = false;
	public $unit;
	public $flash = "disable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		define( 'TOTAL_PER_SECOND', 2 );
		$this->validateNumber = "The phone number(s) the message should be sent to (must be in international format, like 447xxxxxxxxx).";
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

		$numbersCount   = count( $this->to );
		$endNumbersList = $this->to;

		if ( $numbersCount > TOTAL_PER_SECOND ) {
			$numbers        = array_chunk( $this->to, TOTAL_PER_SECOND );
			$endNumbersList = end( $numbers );
			// Remove end numbers list from the array and ready to send others
			unset( $numbers[ array_key_last( $numbers ) ] );

			foreach ( $numbers as $k => $number ) {
				$response = $this->sendStaticSMS( $number );

				// Check response
				if ( is_wp_error( $response ) ) {
					// Log the result
					$this->log( $this->from, $this->msg, $number, $response->get_error_message(), 'error' );
				}

				$finalResponse = $this->responseHandler( $response, $number );
				// Check response
				if ( is_wp_error( $finalResponse ) ) {
					// Log the result
					$this->log( $this->from, $this->msg, $endNumbersList, $finalResponse, 'error' );
				}
			}
		}

		$response = $this->sendStaticSMS( $endNumbersList );
		// Check response
		if ( is_wp_error( $response ) ) {
			// Log the result
			$this->log( $this->from, $this->msg, $endNumbersList, $response->get_error_message(), 'error' );

			return new \WP_Error( 'send-sms', $response->get_error_message() );
		}

		$finalResponse = $this->responseHandler( $response, $endNumbersList );

		// Check response
		if ( is_wp_error( $finalResponse ) ) {
			// Log the result
			$info = $finalResponse->get_error_message();
			$this->log( $this->from, $this->msg, $endNumbersList, $info, 'error' );

			return new \WP_Error( 'send-sms', ! is_array( $info ) ? $info : 'Error on sending message, Please check your Outbox for more information.' );
		}

		return $finalResponse;
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username or ! $this->password ) {
			return new \WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms-pro' ) );
		}

		$args = array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'username'     => $this->username,
				'password'     => $this->password,
			)
		);

		$response = wp_remote_get( $this->wsdl_link . "/balance", $args );

		// Check response
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'send-sms', $response->get_error_message() );
		}

		$result = json_decode( $response['body'], true );

		if ( $result['Success'] == true ) {

			return $result['Balance'];
		}

		return new \WP_Error( 'account-credit', $result['Description'] );
	}

	/**
	 * send SMS private method
	 *
	 * @param $numbers
	 *
	 * @return array|\WP_Error
	 */
	private function sendStaticSMS( $numbers ) {

		$encoding = 1;
		if ( isset( $this->options['send_unicode'] ) and $this->options['send_unicode'] ) {
			$encoding = 2;
		}

		$args = array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'username'     => $this->username,
				'password'     => $this->password,
			),
			'body'    => json_encode( array(
				'to'      => implode( ',', $numbers ),
				'from'    => $this->from,
				'message' => $this->msg,
				'coding'  => $encoding,
			) )
		);

		// Time to wait
		sleep( 1 );

		return wp_remote_post( $this->wsdl_link . "/message", $args );
	}

	/**
	 * Check response
	 *
	 * @param $response
	 *
	 * @param $numbers
	 *
	 * @return array|\WP_Error
	 */
	private function responseHandler( $response, $numbers ) {
		if ( ! is_wp_error( $response ) ) {
			$result = json_decode( $response['body'] );

			if ( wp_remote_retrieve_response_code( $response ) == 200 ) {

				// Log the result
				$this->log( $this->from, $this->msg, $numbers, $result );

				/**
				 * Run hook after send sms.
				 *
				 * @param string $result result output.
				 *
				 * @since 2.4
				 *
				 */
				do_action( 'wp_sms_send', $result );

				return $result;

			} else {

				return new \WP_Error( 'send-sms', $result );
			}
		}
		return new \WP_Error( 'send-sms', $response->get_error_message() );
	}
}