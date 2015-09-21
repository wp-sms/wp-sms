<?php
	class smsapi extends WP_SMS {
		private $wsdl_link = "https://api.smsapi.pl/";
		public $tariff = "https://smsapi.pl/";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;
		
		public function __construct() {
			parent::__construct();
			$this->validateNumber = "48500500500 or with country code";
		}
		
		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			$result = @file_get_contents($this->wsdl_link.'sms.do?username='.urlencode($this->username).'&password='.md5($this->password).'&message='.urlencode($this->msg).'&to='.implode($this->to, ",").'&from='.urlencode($this->from));
			
			if (strpos($result, 'OK') !== false) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
				
				return $result;
			}
		}
		
		public function GetCredit() {
			if(!$this->username && !$this->password) return;
			$result = @file_get_contents($this->wsdl_link.'user.do?username='.urlencode($this->username).'&credits=1&details=1&password=='.md5($this->password));
			
			return $result;
		}
	}