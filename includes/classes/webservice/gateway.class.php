<?php
	class gateway extends WP_SMS {
		private $wsdl_link = "http://sms.gateway.sa/api/";
		public $tariff = "http://sms.gateway.sa/";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;
		
		public function __construct() {
			parent::__construct();
			$this->validateNumber = "+xxxxxxxxxxxxx";
		}
		
		public function SendSMS() {
			$to = implode($this->to, ",");
			$msg = urlencode($this->msg);
			
			$result = file_get_contents($this->wsdl_link."sendsms.php?username=".$this->username."&password=".$this->password."&message=".$msg."&numbers=".$to."&sender=".$this->from."&unicode=e&Rmduplicated=1&return=json");
			$result = json_decode($result);
			
			if($result->Code == 100) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
				return true;
			}
		}
		
		public function GetCredit() {
			if(!$this->username && !$this->password) return;
			
			$result = file_get_contents($this->wsdl_link."getbalance.php?username=".$this->username."&password=".$this->password."&hangedBalance=false");
			return $result;
		}
	}