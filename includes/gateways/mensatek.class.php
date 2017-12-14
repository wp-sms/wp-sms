<?php

class mensatek extends WP_SMS {
	private $wsdl_link = "https://api.mensatek.com/v5";
	public $tariff = "https://www.mensatek.com/precios-sms.php";
	public $unitrial = false;
	public $unit;
	public $flash = "disable";
	public $isflash = false;

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
		$to        = implode( $this->to, ";" );
		$sms_text  = iconv( 'utf-8', 'ISO-8859-1//TRANSLIT', $this->msg );

		$response = wp_remote_get( $this->wsdl_link . "/enviar.php?Correo=" . $this->username . "&Passwd=" . $this->password . "&Destinatarios=" . $to . "&Remitente=" . $this->from . "&Mensaje=" . $sms_text . "&Report=0&Resp=JSON" );

		// Check response error
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'send-sms', $response->get_error_message() );
		}

		$result = json_decode( $response['body'] );

		if ( $result->Res != '-1' ) {
			$this->InsertToDB( $this->from, $this->msg, $this->to );

			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 */
			do_action( 'wp_sms_send', $response['body'] );

			return $result;
		} else {
			return new WP_Error( 'send-sms', $result->Msgid );
		}
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		$response = wp_remote_get( $this->wsdl_link . "/creditos.php?Correo=" . $this->username . "&Passwd=" . $this->password . "&Resp=JSON" );

		if ( ! is_wp_error( $response ) ) {
			$data = json_decode( $response['body'] );

			return $data->Cred;
		} else {
			return new WP_Error( 'account-credit', $response->get_error_message() );
		}
	}
}