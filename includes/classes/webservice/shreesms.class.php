<?php
	class shreesms extends WP_SMS {
		private $wsdl_link = "http://ip.shreesms.net/";
		public $tariff = "http://www.shreesms.net";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;
		
		public function __construct() {
			parent::__construct();
		}
		
		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			$msg = urlencode($this->msg);
			
			foreach($this->to as $number) {
				$result = file_get_contents("{$this->wsdl_link}smsserver/SMS10N.aspx?Userid={$this->username}&UserPassword={$this->password}&PhoneNumber={$number}&Text={$msg}&GSM={$this->from}");
			}
			
			if ($result = 'Ok') {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
			}
			
			return $result;
		}
		
		public function GetCredit() {
			$result = file_get_contents("{$this->wsdl_link}SMSServer/SMSCnt.asp?ID={$this->username}&pw={$this->password}");
			
			if(preg_replace('/[^0-9]/', '', $result)) {
				return $result;
			} else {
				return false;
			}
		}
	}
?>