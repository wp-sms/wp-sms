<?php
	class parandhost extends WP_SMS {
		private $wsdl_link = "http://sms.parandhost.com/webservice/?WSDL";
		public $tariff = "http://www.parandhost.com/sms/";
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
			
			$options = array('login' => $this->username, 'password' => $this->password);
			$client = new SoapClient($this->wsdl_link, $options);
			
			$result = $client->sendToMany($this->to, $this->msg, $this->from);
			
			if($result) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
			}
			
			return $result;
		}

		public function GetCredit() {
			if(!$this->username && !$this->password) return;
			
			$options = array('login' => $this->username, 'password' => $this->password);
			$client = new SoapClient($this->wsdl_link, $options);
			
			try {
				$credit = $client->accountInfo();
				return $credit->remaining;
			}
			
			catch (SoapFault $sf) {
				return $sf->faultcode."\n";
				return $sf->faultstring."\n";
			}
		}
	}