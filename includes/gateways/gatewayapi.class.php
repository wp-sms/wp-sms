<?php

class gatewayapi extends WP_SMS {
	private $wsdl_link = "https://gatewayapi.com/rest";
	public $tariff = "https://gatewayapi.com";
	public $has_key = true;
	public $unit;
	public $unitrial = true;
	public $flash = "disable";
	public $isflash = false;
	protected $accountBalance = null;
	public $help = 'All you need is the API Token available from the <a href="https://gatewayapi.com/app" target="_blank">GatewayAPI Dashboard &rarr;</a>.<br>Just leave the username and password fields blank.';

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "45xxxxxxxx or 49xxxxxxxxxxx";
	}

	public function SendSMS() {
		// Check gateway credit
		if ( ! is_string( $this->has_key ) ) {
			return new WP_Error( 'account-credit', __( 'Invalid API token provided in the settings. Please get your token from the <a href="https://gatewayapi.com/app" target="_blank">GatewayAPI Dashboard &rarr;</a>', 'wp-sms' ) );
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


		/**
		 * Construct recipients array
		 *
		 * @param array $recipients recipients array
		 */
		$recipients = [];

		foreach ( $this->to as $index => $number ) {
			$recipients[] = array( 'msisdn' => $number );
		}

		$payload = array(
			'sender'     => $this->from,
			'message'    => $this->msg,
			'recipients' => $recipients
		);

		if ( $this->isflash ) {
			$payload['destaddr'] = 'DISPLAY';
		}

		/**
		 * Send SMS with POST request as JSON
		 */
		$res = wp_remote_request( $this->wsdl_link . '/mtsms', [
			'method'  => 'POST',
			'body'    => json_encode( $payload ),
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( "$this->has_key:" ),
				'Accept'        => 'application/json, text/javascript',
				'Content-Type'  => 'application/json'
			)
		] );

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		// Decode the response body
		$responseBody = json_decode( $res['body'] );

		// Check of send was successful
		if ( $res['response']['code'] === 200 ) {
			$this->InsertToDB( $this->from, $this->msg, $this->to );

			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 *
			 * @param string $result result output.
			 */
			do_action( 'wp_sms_send', $result );

			return 200; // 200 OK
		} else if ( $res['response']['code'] >= 500 ) {
			return new WP_Error( 'send-sms', $res['body'] ?: 'An unexpected error occured.' );
		}

		// Return error and format error message from the API to the client
		return new WP_Error( 'send-sms', $this->formatErrorMessage( $responseBody ) );
	}

	/**
	 * Get formatted credit string.
	 *
	 * @return string
	 */
	public function GetCredit() {
		return $this->balance()->credit . " " . $this->balance()->currency;
	}

	/**
	 * Check if client has credits.
	 *
	 * @return boolean
	 */
	public function hasCredit() {
		return $this->balance()->credit > 0;
	}

	/**
	 * Retrive and cache the account balance for the client.
	 *
	 * @return object
	 */
	public function balance() {
		if ( is_null( $this->accountBalance ) ) {
			$res = wp_remote_request( $this->wsdl_link . '/me', [
				'method'  => 'GET',
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode( "$this->has_key:" )
				]
			] );

			$responseBody = json_decode( $res['body'] );

			if ( $res['response']['code'] === 200 ) {
				$this->accountBalance = $responseBody;
			} else {
				// Mock the account balance object
				$this->accountBalance = (object) [ 'credit' => 0, 'currency' => '' ];
			}
		}

		return $this->accountBalance;
	}

	/**
	 * Format the error message from the API.
	 *
	 * @param  object $error
	 *
	 * @return string
	 */
	public function formatErrorMessage( $error ) {
		if ( ! $error->variables ) {
			return $error->message;
		}

		$message = $error->message;

		foreach ( $error->variables as $index => $value ) {
			$placeholder = $index + 1;

			$message = str_replace( "%{$placeholder}", $value, $message );
		}

		return $message;
	}
}