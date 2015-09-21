<?php
	class smsclick extends WP_SMS {
		private $wsdl_link = "http://smsclick.ir/post/send.asmx?wsdl";
		public $tariff = "http://smsclick.info/register";
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
			
			$client = new SoapClient($this->wsdl_link);
			$parameters['username'] = $this->username;
			$parameters['password'] = $this->password;
			$parameters['from'] = $this->from;
			$parameters['to'] = $this->to;
			$parameters['text'] = $this->msg;
			$parameters['isflash'] = false;
			$parameters['udh'] = "";
			$parameters['recId'] = array(0);
			$parameters['status'] = 0x0;
			
			$result= $client->SendSms($parameters);
			
			if($result) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
			}
			
			return $result;
		}

		public function GetCredit() {
			$client = new SoapClient($this->wsdl_link);
			
			$result = $client->GetCredit(array('username' => $this->username, 'password' => $this->password));

			return $result->GetCreditResult;
		}
	}
?>