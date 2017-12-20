<?php

class comilio extends WP_SMS {
	private $wsdl_link = "https://api.comilio.it/rest/v1";
	public $tariff = "https://www.comilio.it/tariffe.php";
	public $unitrial = false;
	public $unit;
	public $flash = "disable";
	public $isflash = false;
	private $smsh_response_status = 0;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "";
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

		$payload = array(
			'message_type'  => 'Smart',
			'phone_numbers' => $this->to,
			'text'          => $this->msg,
			'sender_string' => $this->from
		);

		$to_smsh = curl_init( "{$this->wsdl_link}/message" );
		curl_setopt( $to_smsh, CURLOPT_POST, true );
		curl_setopt( $to_smsh, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $to_smsh, CURLOPT_USERPWD, $this->username . ":" . $this->password );
		curl_setopt( $to_smsh, CURLOPT_POSTFIELDS, json_encode( $payload ) );
		curl_setopt( $to_smsh, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $to_smsh, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json' ) );

		$result = curl_exec( $to_smsh );

		$this->smsh_response_status = curl_getinfo( $to_smsh, CURLINFO_HTTP_CODE );

		if ( $result ) {
			$jsonObj = json_decode( $result );

			if ( null === $jsonObj ) {
				return false;
			} elseif ( $this->smsh_response_status != 200 ) {
				return false;
			} else {
				$result = $jsonObj->message_id;

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
		} else {
			return new WP_Error( 'send-sms', $result );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		$to_smsh = curl_init( "{$this->wsdl_link}/credits" );

		curl_setopt( $to_smsh, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $to_smsh, CURLOPT_USERPWD, $this->username . ":" . $this->password );
		curl_setopt( $to_smsh, CURLOPT_TIMEOUT, 10 );

		$result = curl_exec( $to_smsh );

		$this->smsh_response_status = curl_getinfo( $to_smsh, CURLINFO_HTTP_CODE );

		if ( $result ) {
			$jsonObj = json_decode( $result );

			if ( null === $jsonObj ) {
				return new WP_Error( 'account-credit', $result );
			} elseif ( $this->smsh_response_status != 200 ) {
				return new WP_Error( 'account-credit', $result );
			} else {
				for ( $i = 0; $i < count( $jsonObj ); $i ++ ) {
					if ( $jsonObj[ $i ]->message_type === 'Smart' ) {
						return $jsonObj[ $i ]->quantity;
					}
				}

				return new WP_Error( 'account-credit', $result );
			}
		} else {
			return new WP_Error( 'account-credit', $result );
		}
	}
}
