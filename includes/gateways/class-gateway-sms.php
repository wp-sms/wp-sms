<?php
/**
 * Gateway class
 * 
 * @category   class
 * @package    WP_SMS
 * @version    1.0
 */
class smsGateway {

	/**
	 * Gateway username
	 * @var string
	 */
	public $username;

	/**
	 * Gateway password
	 * @var string
	 */
	public $password;

	/**
	 * Gateway api key
	 * @var string
	 */
	public $api_key;

	/**
	 * Api url
	 * @var string
	 */
	private $api_url = "http://ip.sms.ir/ws/SendReceive.asmx?wsdl";

	/**
	 * Gateway website
	 * @var string
	 */
	public $website = "http://sms.sitralweb.com/";

	/**
	 * Constructor for the gateways class
	 */
	public function __construct() {
		$this->validateNumber = "09xxxxxxxx";
		$this->api_key = false;
	}

	/**
	 * Fire sms!
	 * 
	 * @param string $message SMS message
	 * @param array $to SMS recipients
	 */
	public function send($message, $to, $from = null) {

		$response = wp_remote_get( 'http://ip.sms.ir/SendMessage.ashx?user='.$this->username.'&pass='.$this->password.'&lineNo='.$from.'&to='.implode(',', $to).'&text='.$message);

		if( $response ) {
			return array('status' => 'success');
		} else {
			return array('status' => 'error', 'response' => $response);
		}

	}

	/**
	 * Get credit
	 * 
	 */
	public function get_credit() {
		return array('status' => 'success', 'response' => '');
	}
}