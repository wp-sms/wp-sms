<?php
class pichakhostGateway {

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
	private $api_url = "http://sms.sitralweb.com/API/Send.asmx?WSDL";

	/**
	 * Gateway website
	 * @var string
	 */
	public $website = "http://sms.sitralweb.com/";

	/**
	 * Constructor for the gateways class
	 */
	public function __construct() {
		//parent::__construct();
		$this->validateNumber = "09xxxxxxxx";

		ini_set("soap.wsdl_cache_enabled", "0");
	}

	/**
	 * Fire sms!
	 * 
	 * @param string $message SMS message
	 * @param array $to SMS recipients
	 */
	public function send($message, $to, $from = null) {

		// Check credit for the gateway
		if(!$this->GetCredit()) return;

		$client = new SoapClient($this->api_url);
		
		$result= $client->SendSms(
			array(
				'username'	=> $this->username,
				'password'	=> $this->password,
				'from'		=> $from,
				'to'		=> $to,
				'text'		=> $message,
				'flash'		=> false,
				'udh'		=> ''
			)
		);
		
		if($result) {
			return $result;
		}
		
	}

	/**
	 * Get credit
	 * 
	 */
	public function get_credit() {
		$client = new SoapClient($this->api_url);

		$result = $client->Credit(array('username' => $this->username, 'password' => $this->password));

		return $result->CreditResult;
	}
}