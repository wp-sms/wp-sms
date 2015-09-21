<?php
	class payamgah extends WP_SMS {
		private $wsdl_link = "http://sms.payamgah.net/API/Send.asmx?WSDL";
		public $tariff = "http://payamgah.info/";
		public $unitrial = false;
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
			
			$result= $client->SendSms(
				array(
					'username'	=> $this->username,
					'password'	=> $this->password,
					'from'		=> $this->from,
					'to'		=> $this->to,
					'text'		=> $this->msg,
					'flash'		=> false,
					'udh'		=> ''
				)
			);
			
			if($result) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
				
				return $result;
			}
			
			return false;
		}

		public function GetCredit() {
			if(!$this->username && !$this->password) return;
			$client = new SoapClient($this->wsdl_link);
			$result = $client->Credit(array('username' => $this->username, 'password' => $this->password));
			return $result->CreditResult;
		}
	}