<?php
	class afe extends WP_SMS {
		private $wsdl_link = "http://www.afe.ir/WebService/webservice.asmx?WSDL";
		public $tariff = "http://afe.ir";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "09xxxxxxxx";
		}

		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			$client = new SoapClient('http://www.afe.ir/WebService/V4/BoxService.asmx?WSDL');
			
			if($this->isflash) {
				$type = 0;
			} else {
				$type = 1;
			}
			
			$param = array(
				'Username'	=> $this->username,
				'Password'	=> $this->password,
				'Number'	=> $this->from,
				'Mobile'	=> $this->to,
				'Message'	=> $this->msg,
				'Type'		=> $type
			);
			
			$result = $client->SendMessage($param);
			
			if($result) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
			}
			
			return $result->SendMessageResult->string;
		}

		public function GetCredit() {
			$client = new SoapClient($this->wsdl_link);
			$result = $client->GetRemainingCredit( array('Username' => $this->username, 'Password' => $this->password) );
			return $result->GetRemainingCreditResult;
		} 
	}