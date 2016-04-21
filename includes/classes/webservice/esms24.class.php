<?php
	class esms24 extends WP_SMS {
		private $wsdl_link = "http://esms24.ir/sendSmsViaURL.aspx";
		public $tariff = "http://esms24.ir";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "09xxxxxxxx";
			$this->has_key = true;
		}

		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			$msg = urlencode($this->msg);
			
			foreach($this->to as $number) {
				$result = file_get_contents("{$this->wsdl_link}?userName={$this->username}&password={$this->password}&domainName={$this->has_key}&smsText={$msg}&reciverNumber={$number}&senderNumber={$this->from}");
			}
			
			if($result) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
			}
			
			return $result;
		}

		public function GetCredit() {
			if(!$this->username && !$this->password) return;
			return true;
		} 
	}