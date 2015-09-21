<?php
	class smsmelli extends WP_SMS {
		private $wsdl_link = "http://82.99.218.146/class/sms/webservice3/server.php?wsdl";
		private $client = null;
		public $tariff = "http://smsmelli.com/";
		public $unitrial = true;
		public $unit;
		public $flash = "enable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "09xxxxxxxx";
			
			if(!class_exists('nusoap_client'))
				include_once dirname( __FILE__ ) . '/../nusoap.class.php';
			
			$this->client = new nusoap_client($this->wsdl_link,array('trace'=>true));
			
			$this->client->soap_defencoding = 'UTF-8';
			$this->client->decode_utf8 = true;
		}

		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			$result = $this->client->call("SendSMS", array('user' => $this->username, 'pass' => $this->password, 'fromNum' => $this->from, 'toNum' => $this->to, 'messageContent' => $this->msg, 'messageType' => 'normal'));
			
			if($result) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
			}
			
			return $result;
		}

		public function GetCredit() {
			$result = $this->client->call("GetCredit", array('user' => $this->username, 'pass' => $this->password));
			
			return $result;
		}
	}
?>