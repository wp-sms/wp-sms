<?php

class kavenegar extends WP_SMS {
	const APIPATH = "http://api.kavenegar.com/v1/%s/%s/%s.json/";

	private function get_path( $method, $base = 'sms' ) {
		return sprintf( self::APIPATH, trim( $this->has_key ), $base, $method );
	}

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "+xxxxxxxxxxxxx";
		$this->has_key        = true;
	}

	public function SendSMS() {
		$this->from = apply_filters( 'wp_sms_from', $this->from );
		$this->to   = apply_filters( 'wp_sms_to', $this->to );
		$this->msg  = apply_filters( 'wp_sms_msg', $this->msg );
		$to         = implode( $this->to, "," );
		$msg        = urlencode( $this->msg );
		$path       = $this->get_path( "send" );
		$response   = wp_remote_get( $path, array(
			'body' => array(
				'receptor' => $to,
				'sender'   => $this->from,
				'message'  => $msg
			)
		) );
		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			try {
				$json = json_decode( $response['body'] );
				if ( $json && $json->return->status == 200 ) {
					$this->InsertToDB( $this->from, $this->msg, $this->to );
					do_action( 'wp_sms_send', $response );

					return $response;
				}
			} catch ( Exception $ex ) {
				return new WP_Error( 'send-sms', $response );
			}
		}

		return new WP_Error( 'send-sms', $response );
	}

	public function GetCredit() {
		$remaincredit = 0;
		$path         = $this->get_path( "info", "account" );
		$response     = wp_remote_get( $path );
		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			try {
				$json = json_decode( $response['body'] );
				if ( $json ) {
					$remaincredit = $json->entries->remaincredit;
				}
			} catch ( Exception $ex ) {
				$remaincredit = 0;
			}
		}

		return $remaincredit;
	}
}