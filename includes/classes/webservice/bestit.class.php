<?php
	class bestit extends WP_SMS {
		private $wsdl_link = "http://panelsms.bestit.co/WsSms.asmx?wsdl";
		public $tariff = "http://panelsms.bestit.co/";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "09xxxxxxxx";
			$this->has_key = true;
			
			ini_set("soap.wsdl_cache_enabled", "0");
		}

		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
		
			$client = new SoapClient($this->wsdl_link);
			
			$result= $client->sendsms(
				array(
					'username'	=> $this->username,
					'password'	=> $this->password,
					'to'		=> implode(',', $this->to),
					'text'		=> $this->msg,
					'from'		=> $this->from,
					'api'		=> $this->has_key,
				)
			);
			
			if(
				$result->sendsmsResult->long == 1000 or
				$result->sendsmsResult->long == 1001 or
				$result->sendsmsResult->long == 1002 or
				$result->sendsmsResult->long == 1003 or
				$result->sendsmsResult->long == 1004 or
				$result->sendsmsResult->long == 1005 or
				$result->sendsmsResult->long == 1006 or
				$result->sendsmsResult->long == 1007 or
				$result->sendsmsResult->long == 1008 or
				$result->sendsmsResult->long == 1009 or
				$result->sendsmsResult->long == 1010
			)
				return false;
			
			$this->InsertToDB($this->from, $this->msg, $this->to);
			$this->Hook('wp_sms_send', $result);
			
			return $result;
		}

		public function GetCredit() {
			$client = new SoapClient($this->wsdl_link);
			$result = $client->Credites(array('username' => $this->username, 'password' => $this->password));
			return $result->CreditesResult;
		}
	}