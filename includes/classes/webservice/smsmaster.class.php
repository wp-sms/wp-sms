<?php
	class smsmaster extends WP_SMS {
		private $wsdl_link = "http://smsmaster.ir/send_webservice2.php";
		public $tariff = "http://smsmaster.ir/";
		public $unitrial = true;
		public $unit;
		public $flash = "disable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "09xxxxxxxx";
			
			if(!class_exists('nusoap_client'))
				include_once dirname( __FILE__ ) . '/../nusoap.class.php';
				
			$this->client = new nusoap_client($this->wsdl_link);
		}

		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			return $this->client->call('sending_sms',array($this->username, $this->password, $this->msg, implode('*', $this->to), 1, 0, $this->from));
		}

		public function GetCredit() {
			return $this->client->call('getcredit', array($this->username, $this->password));
		}
	}
?>