<?php
	class payamakalmas extends WP_SMS {
		private $wsdl_link = "http://79.175.167.50/webservice/server.php?wsdl";
		private $client = null;
		public $tariff = "http://almasac.ir/";
		public $unitrial = true;
		public $unit;
		public $flash = "enable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "09xxxxxxxx";
			
			if(!class_exists('nusoap_client'))
				include_once dirname( __FILE__ ) . '/../nusoap.class.php';
			
			$this->client = new nusoap_client($this->wsdl_link);
			
			$this->client->soap_defencoding = 'UTF-8';
			$this->client->decode_utf8 = true;
		}

		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			$result = $this->client->call("SENDSMS", array('UserName' => $this->username, 'Password' => $this->password, 'LineNumber' => $this->from, 'Recivers' => implode($this->to, ','), 'SMSSMG' => $this->msg, 'MesClass' => '1'));
			
			if($result) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
			}
			
			return $result;
		}

		public function GetCredit() {
			$result = $this->client->call("Credit", array('UserName' => $this->username, 'Password' => $this->password));
			
			// this methid is undefined in webservice.
			return '1';
		}
	}
?>