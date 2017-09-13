<?php

class iransmspanel extends WP_SMS {
	/**
	 * Host
	 *
	 * @var    string
	 */
	private $host = "2972.ir";

	/**
	 * URI
	 *
	 * @var    string
	 */
	private $uri = '/api';

	private $wsdl_link = "http://www.2972.ir/wsdl?XML";

	public $tariff = "http://iransmspanel.ir/?TheAction=viewpage&title=smscharge";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $port = 0;
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "09xxxxxxxx";
	}

	/**
	 * This function is used to send SMS via socket.
	 *
	 * @param $username
	 * @param $password
	 * @param $number
	 * @param $recipient
	 * @param $port
	 * @param $message
	 * @param $flash
	 *
	 * @return bool|mixed|string
	 * @internal param Username $string
	 * @internal param Password $string
	 * @internal param Number $string (From - Example: 100002972)
	 * @internal param Recipient $string Number
	 * @internal param Port $integer Number
	 * @internal param Message $string
	 * @internal param Is $bool Flash SMS?
	 *
	 */
	private function Send_Via_Socket( $username, $password, $number, $recipient, $port, $message, $flash ) {
		$result     = $response = '';
		$params     = array(
			'username'  => $username,
			'password'  => $password,
			'number'    => $number,
			'recipient' => $recipient,
			'port'      => $port,
			'message'   => $message,
			'flash'     => $flash
		);
		$parameters = '';
		foreach ( $params AS $name => $value ) {
			$parameters .= ( $parameters != '' ? '&' : '' ) . "$name=" . urlencode( $value );
		}
		$sockerrno = 0;
		$sockerr   = '';
		$socket    = @fsockopen( $this->host, 80, $sockerrno, $sockerr, 2 );
		if ( $sockerr == '' ) {
			@fputs( $socket, "POST $this->uri HTTP/1.1\nHost: $this->host\nContent-type: application/x-www-form-urlencoded\nContent-length: " . strlen( $parameters ) . "\nConnection: close\n\n$parameters" );
			$result = trim( fgets( $socket ) );
			while ( ! @feof( $socket ) ) {
				$response .= @fread( $socket, 256 );
			}
			@fclose( $socket );
			#################### SPLIT HEADER AND DOCUMENT BODY ##################
			if ( $result == 'HTTP/1.1 200 OK' ) {
				$hunks = explode( "\r\n\r\n", trim( $response ) );
				if ( ! is_array( $hunks ) OR sizeof( $hunks ) < 2 ) {
					return false;
				} else {
					$response = $hunks[ count( $hunks ) - 1 ];
				}
				if ( preg_match( '#(.+)[\r\n](.+)[\r\n](.+)#', $response, $match ) ) {
					$response = $match[2];
				}
			}
		} else {
			return false;
		}

		######################################################################
		return ( $result == 'HTTP/1.1 200 OK' ) ? $response : false;
	}

	/**
	 * This function allows class to set post values.
	 *
	 * @param    string        Reference to options variable
	 * @param    array        Options array
	 *
	 */
	private function curl_post_fields( &$options, $fields ) {
		$options[ CURLOPT_POSTFIELDS ] = $fields;
	}

	/**
	 * This function allows class to execute the given url and return result
	 *
	 * @param    string        Reference to cURL handle
	 * @param    string        URL
	 * @param    array        Options for cURL transfer
	 *
	 * @return    string
	 *
	 */
	private function curl_execute( &$handle, $url, $options = null ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		} else if ( in_array( CURLOPT_POSTFIELDS, $options ) AND sizeof( $options[ CURLOPT_POSTFIELDS ] ) > 0 ) {
			$options[ CURLOPT_POST ] = true;
		}

		$options[ CURLOPT_USERAGENT ]      = 'PHP';
		$options[ CURLOPT_RETURNTRANSFER ] = true;
		$options[ CURLOPT_URL ]            = $url;

		$handle = @curl_init(); // initialize cURL session
		if ( $handle AND @is_resource( $handle ) ) {
			@curl_setopt_array( $handle, $options ); // set options for cURL transfer
			$result = @curl_exec( $handle ); // execute cURL session
			@curl_close( $handle ); // close cURL session
		} else {
			$result = false;
		}

		return $result;
	}

	/**
	 * This function is used to send SMS via cURL.
	 *
	 * @param $username
	 * @param $password
	 * @param $number
	 * @param $recipient
	 * @param $port
	 * @param $message
	 * @param $flash
	 *
	 * @return string
	 * @internal param Username $string
	 * @internal param Password $string
	 * @internal param Number $string (From - Example: 100002972)
	 * @internal param Recipient $string Number
	 * @internal param Port $integer Number
	 * @internal param Message $string
	 * @internal param Is $bool Flash SMS?
	 *
	 */
	private function Send_Via_cURL( $username, $password, $number, $recipient, $port, $message, $flash ) {
		$handle  = null;
		$options = array();
		$this->curl_post_fields( $options, array(
			'username'  => $username,
			'password'  => $password,
			'number'    => $number,
			'recipient' => $recipient,
			'port'      => $port,
			'message'   => $message,
			'flash'     => $flash
		) );

		return $this->curl_execute( $handle, "http://www.$this->host{$this->uri}", $options );
	}

	/**
	 * This function is used to send SMS via http://www.2972.ir
	 * @return bool|WP_Error
	 * @internal param Username $string
	 * @internal param Password $string
	 * @internal param Number $string (From - Example: 100002972)
	 * @internal param Recipient $string Number
	 * @internal param Port $integer Number
	 * @internal param Message $string
	 * @internal param Is $bool Flash SMS?
	 *
	 */
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

		if ( @function_exists( 'curl_init' ) ) {
			$this->Send_Via_cURL( $this->username, $this->password, $this->from, implode( ',', $this->to ), $this->port, $this->msg, $this->isflash );
		}

		$result = $this->Send_Via_Socket( $this->username, $this->password, $this->from, implode( ',', $this->to ), $this->port, $this->msg, $this->isflash );

		if ( ! $result ) {
			$this->InsertToDB( $this->from, $this->msg, $this->to );

			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 *
			 * @param string $result result output.
			 */
			do_action( 'wp_sms_send', $result );

			return true;
		}

		return new WP_Error( 'send-sms', $result );

	}

	public function GetCredit() {
		// Check username and password
		if ( ! $this->username && ! $this->password ) {
			return new WP_Error( 'account-credit', __( 'Username/Password does not set for this gateway', 'wp-sms' ) );
		}

		if ( ! class_exists( 'SoapClient' ) ) {
			return new WP_Error( 'required-class', __( 'Class SoapClient not found. please enable php_soap in your php.', 'wp-sms' ) );
		}

		try {
			$client = new SoapClient( $this->wsdl_link );
		} catch ( Exception $e ) {
			return new WP_Error( 'account-credit', $e->getMessage() );
		}

		$result = $client->Authentication( $this->username, $this->password );

		if ( $result == '1' ) {
			return $client->GetCredit();
		} else {
			return new WP_Error( 'account-credit', $result );
		}
	}
}