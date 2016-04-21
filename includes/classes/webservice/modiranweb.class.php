<?php
	class modiranweb extends WP_SMS {
		private $wsdl_link = "http://sms.modiranweb.net/webservice/send.php?wsdl";
		public $tariff = "http://www.modiranweb.net/";
		public $unitrial = true;
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
			
			$this->client = new SoapClient($this->wsdl_link);
			
			$result = $this->client->SendMultiSMS( array($this->from), $this->to, array($this->msg), array($this->isflash), $this->username, $this->password );
			
			if( $result ) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
			}
			
		}

		public function GetCredit() {
			if(!$this->username && !$this->password) return;
			$this->client = new SoapClient($this->wsdl_link);
			$results = $this->client->GetCredit( $this->username, $this->password, array("","") );
			
			return round($results);
		}
	}