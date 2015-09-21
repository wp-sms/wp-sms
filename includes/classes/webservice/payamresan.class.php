<?php
	class payamresan extends WP_SMS {
		private $wsdl_link = "http://www.payam-resan.com/";
		public $tariff = "http://www.payam-resan.com/CMS/";
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
		
			$to = implode(',', $this->to);
			
			$message = urlencode($this->msg);
			
			$client = file_get_contents("{$this->wsdl_link}APISend.aspx?Username={$this->username}&Password={$this->password}&From={$this->from}&To={$to}&Text={$message}");
			
			if($client) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
			}
			
			return $client;
		}

		public function GetCredit() {
		
			$client = file_get_contents("{$this->wsdl_link}Credit.aspx?Username={$this->username}&Password={$this->password}");
			
			if( $client == 'ERR' )
				return 0;
			
			return $client;
		}
	}
?>