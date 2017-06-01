<?php

class bulutfon extends WP_SMS {
	private $wsdl_link = "https://api.bulutfon.com/messages";
	public $tariff = "http://bulutfon.com/";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "90xxxxxxxxxx";
	}

	public function SendSMS() {
		// Check gateway credit
		if ( is_wp_error( $this->GetCredit() ) ) {
			return new WP_Error( 'account-credit', __( 'Your account does not credit for sending sms.', 'wp-sms' ) );
		}

		$msg = urlencode( $this->msg );

		$data = array(
			'title'     => $this->from,
			'email'     => $this->username,
			'password'  => $this->password,
			'receivers' => implode( ',', $this->to ),
			'content'   => $this->msg,
		);

		$data = http_build_query( $data );
		$ch   = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $this->wsdl_link );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

		$result = curl_exec( $ch );
		$json   = json_decode( $result, true );

		if ( $result ) {
			$this->InsertToDB( $this->from, $this->msg, $this->to );

			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 *
			 * @param string $result result output.
			 */
			do_action( 'wp_sms_send', $result );

			return $json;
		}

		return new WP_Error( 'send-sms', $result );
	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		$result     = file_get_contents( 'https://api.bulutfon.com/me' . '?email=' . $this->username . '&password=' . $this->password );
		$result_arr = json_decode( $result );

		return $result_arr->credit->sms_credit;
	}
}