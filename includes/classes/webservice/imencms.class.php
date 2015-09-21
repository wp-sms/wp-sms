<?php
	class imencms extends WP_SMS {
		private $wsdl_link = "http://www.imencms.com/SMS/sms.asmx?WSDL";
		public $tariff = "http://www.imencms.com/sms/";
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
			
			$result = $client->SendSMS( array('MobileNo' => $this->to, 'SMSText' => $this->msg, 'AcountID' => $this->password, 'LineNo' => $this->from) );
			
			if($result) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
			}
			
			return $result->Send_x0020_One_x0020_SMSResult;
		}

		public function GetCredit() {
		
			$client = new SoapClient($this->wsdl_link);

			$result = $client->GetCredit( array('AcountID' => $this->password) );

			return $result->GetCreditResult;
		}
	}
?>