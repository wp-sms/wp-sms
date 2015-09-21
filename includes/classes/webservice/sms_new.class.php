<?php
	class sms_new extends WP_SMS {
		private $wsdl_link = "http://n.sms.ir/SendMessage.ashx";
		public $tariff = "http://sms.ir/";
		public $unitrial = false;
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
			
			$msg = urlencode($this->msg);
			
			foreach($this->to as $number) {
				$result = file_get_contents("{$this->wsdl_link}?user={$this->username}&pass={$this->password}&lineNo={$this->from}&to={$number}&text={$msg}");
			}
			
			if ($result == 'ok') {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
				
				return true;
			}
		}

		public function GetCredit() {
			return 1;
		}
	}
?>