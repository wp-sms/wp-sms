<?php
	class smsban extends WP_SMS {
		private $wsdl_link = "http://smsban.ir/API/SendSMS.asmx?WSDL";
		public $tariff = "http://smsban.ir/";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $api;
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
				$parameters['USERNAME']	= $this->username;
				$parameters['PASSWORD']	= $this->password;
				$parameters['TO']	= $this->to;
				$parameters['FROM'] = $this->from;
				$parameters['TEXT'] = $this->msg;
				$parameters['API']	= $this->api;
				$parameters['API_CHANGE_ALLOW'] = 1;
				$parameters['FLASH'] = $this->isflash;
				$parameters['Internation']	= false;
				
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
				
				return $client->Send_Sms4($parameters)->Send_Sms4Result;
			}
			catch(SoapFault $ex)
			{
				return $ex->faultstring;
			}
		}

		public function GetCredit() {
			try
			{
				$client = new SoapClient($this->wsdl_link);
				return $client->CHECK_CREDIT(array("USERNAME" => $this->username, "PASSWORD" => $this->password))->CHECK_CREDITResult;
			}
			catch(SoapFault $ex)
			{
				return $ex->faultstring;
			}

		}
	}
?>