<?php
	class tgfsms extends WP_SMS {
		private $wsdl_link = "http://tgfsms.ir/smsSendWebService.asmx?WSDL";
		public $tariff = "http://tgfsms.ir/";
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
			
			$result = $client->sendSms(
				array(
					'userName'		=> $this->username,
					'password'		=> $this->password,
					'SenderNumber'	=> $this->from,
					'MobileNumber'	=> $this->to,
					'SmsText'		=> array($this->msg),
					'sendType'		=> 1,
					'smsMode'		=> 1,
				)
			);
			
			//http://www.tgfsms.ir/sendSmsViaURL2.aspx?userName=hossein_mahzoon&password=123456&smsText=myText&reciverNumber=09351523606&senderNumber=10007132302309
			
			if($result) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
			}
			
			return $result;
		}

		public function GetCredit() {
			if($this->username and !$this->password)
				return;
			
			return true;
		}
	}
?>