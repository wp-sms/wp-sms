<?php
	class spadbs extends WP_SMS {
		private $wsdl_link = "http://s-card.ir/webservice/wsdl.wsdl";
		private $client = null;
		public $tariff = "http://spadsms.ir/";
		public $unitrial = true;
		public $unit;
		public $flash = "enable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "09xxxxxxxx";
		}

		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			$client = new SoapClient($this->wsdl_link);
			
			foreach($this->to as $items) {
				$to[] = array('number' => $items);
			}
			
			$result = $client->send($this->username, $this->password, $to, $this->from, $this->msg);
			
			if($result) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
			}
			
			return $result;
		}

		public function GetCredit() {
			if(!$this->username and !$this->password)
				return;
			
			$client = new SoapClient($this->wsdl_link);
			$result = $client->getCredit($this->username, $this->password);
			return $result[0]['id'];
		}
	}
?>