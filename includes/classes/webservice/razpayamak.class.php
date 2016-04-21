<?php
	class razpayamak extends WP_SMS {
		private $wsdl_link = "http://api.payamak-panel.com/post/send.asmx?WSDL";
		public $tariff = "http://razpayamak.com/";
		public $unitrial = true;
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
			
			try
			{
				$client = new SoapClient($this->wsdl_link);
				$parameters['username'] = $this->username;
				$parameters['password'] = $this->password;
				$parameters['from'] = $this->from;
				$parameters['to'] = $this->to;
				$parameters['text'] = $this->msg;
				$parameters['isflash'] = $this->isflash;
				$parameters['udh'] = "";
				$parameters['recId'] = array(0);
				$parameters['status'] = 0x0;
				
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
				
				return $client->SendSms($parameters)->SendSmsResult;
			}
			catch(SoapFault $ex)
			{
				echo $ex->faultstring;
			}
		}

		public function GetCredit() {
			try
			{
				$client = new SoapClient($this->wsdl_link);
				return $client->GetCredit(array("username" => $this->username, "password" => $this->password))->GetCreditResult;
			}
			catch(SoapFault $ex)
			{
				echo $ex->faultstring;
			}
		}
	}
?>