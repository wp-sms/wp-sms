<?php
	class parsasms extends WP_SMS {
		private $wsdl_link = "http://parsasms.com/webservice/v2.asmx?WSDL";
		public $tariff = "http://www.parsasms.com/";
		public $unitrial = true;
		public $unit;
		public $flash = "disable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "09xxxxxxxx";
			
			ini_set("soap.wsdl_cache_enabled", "0");
		}

		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			$client = new SoapClient("http://parsasms.com/webservice/v2.asmx?WSDL");
			
			$params = array(
				'username'			=> $this->username,
				'password'			=> $this->password,
				'senderNumbers'		=> array($this->from),
				'recipientNumbers'	=> $this->to,
				'messageBodies'		=> array($this->msg)
			);
			
			$result = $this->client->SendSMS( $params );
			
			if( $result ) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
			}
		}

		public function GetCredit() {
			if(!$this->username && !$this->password) return;
			$this->client = new SoapClient($this->wsdl_link);
			
			$params = array(
				'username' 	=> $this->username,
				'password' 	=> $this->password
			);
			
			$results = $this->client->GetCredit( $params );
			
			return $results->GetCreditResult;
		}
	}
?>