<?php
	class niazpardazcom extends WP_SMS {
		private $wsdl_link = "http://5.9.76.186/SendService.svc?singleWsdl";
		public $tariff = "http://www.niazpardaz.com/";
		public $unitrial = true;
		public $unit;
		public $flash = "enable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "09xxxxxxxx";
			
			ini_set("soap.wsdl_cache_enabled", "0");
		}

		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			$client = new SoapClient($this->wsdl_link);
			$result = $client->SendSMS(array('userName' => $this->username, 'password' => $this->password, 'fromNumber' => $this->from, 'toNumbers' => $this->to, 'messageContent' => $this->msg, $this->isflash));
			
			if($result->SendSMSResult == 0) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
				
				return true;
			}
		}

		public function GetCredit() {
			if( !$this->username and !$this->password )
				return;
			
			$client = new SoapClient($this->wsdl_link);
			$result = $client->GetCredit(array('userName' => $this->username, 'password' => $this->password));
			
			return $result->GetCreditResult;
		}
	}