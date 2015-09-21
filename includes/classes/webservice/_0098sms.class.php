<?php
	class _0098sms extends WP_SMS {
		private $wsdl_link = "http://www.0098sms.com/";
		public $tariff = "http://www.0098sms.com/";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;
		
		public function __construct() {
			parent::__construct();
			$this->validateNumber = "09xxxxxxxxx";
		}
		
		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			$msg = urlencode($this->msg);
			
			foreach($this->to as $to) {
				$result = file_get_contents($this->wsdl_link."sendsmslink.aspx?FROM=".$this->from."&TO=".$to."&TEXT=".$msg."&USERNAME=".$this->username."&PASSWORD=".$this->password."&DOMAIN=0098");
			}
			
			if($result->Code == 0) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
				return true;
			}
		}
		
		public function GetCredit() {
			if(!$this->username && !$this->password) return;
			
			return true;
		}
	}