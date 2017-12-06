<?php

class primotexto extends WP_SMS {
	private $wsdl_link = "https://api.primotexto.com/v2/";
	public $tariff = "http://www.primotexto.com/";
	public $unitrial = true;
	public $unit;
	public $flash = "disable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "Format: 0600000000, +33600000000";
		$this->help           = 'Vous devez génerer une clé depuis votre <a href="https://www.primotexto.com/webapp/#/developer/keys">interface Primotexto</a> pour pouvoir utiliser l\'API.';
		$this->has_key        = true;
		//$this->bulk_send      = false;
		require_once 'includes/primotexto/baseManager.class.php';
	}

	public function SendSMS() {
		// Check gateway credit
		if ( is_wp_error( $this->GetCredit() ) ) {
			return new WP_Error( 'account-credit', __( 'Your account does not credit for sending sms.', 'wp-sms-pro' ) );
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

		// Authentication
		authenticationManager::setApiKey( $this->has_key );

		// New notification SMS
		foreach ( $this->to as $item ) {
			$sms          = new Sms;
			$sms->type    = 'notification';
			$sms->number  = $item;
			$sms->message = urlencode($this->msg);
			$sms->sender  = $this->from;

			$result = messagesManager::messagesSend( $sms );
			$json   = json_decode( $result );
		}

		if ( isset( $json->snapshotId ) ) {
			$this->InsertToDB( $this->from, $this->msg, $this->to );

			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 *
			 * @param string $result result output.
			 */
			do_action( 'wp_sms_send', $json );

			return $json;
		}

		return new WP_Error( 'credit', $json->code );
	}

	public function GetCredit() {
		// Authentication
		authenticationManager::setApiKey( $this->has_key );

		// Account Stats
		$result = accountManager::accountStats();
		$json   = json_decode( $result );

		if ( isset( $json->error ) ) {
			return new WP_Error( 'credit', $json->error );
		}

		return $json->credits;
	}
}