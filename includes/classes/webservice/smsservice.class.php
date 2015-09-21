<?php
	class smsservice extends WP_SMS {
		private $wsdl_link = "http://mihansmscenter.com/webservice/?wsdl";
		public $tariff = "http://smsservice.ir/";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "09xxxxxxxx";
			
			if(!class_exists('nusoap_client'))
				include_once dirname( __FILE__ ) . '/../nusoap.class.php';
		}

		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			$client = new nusoap_client($this->wsdl_link, 'wsdl');
			$client->decodeUTF8(false);
			$result = $client->call('multiSend', array(
				'username'	=> $this->username,
				'password'	=> $this->password,
				'to'		=> $this->to,
				'from'		=> $this->from,
				'message'	=> $this->msg
			));
			
			if($result['status'] === 0) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
				return true;
			}
		}

		public function GetCredit() {
			$client = new nusoap_client($this->wsdl_link, 'wsdl');
			$client->decodeUTF8(false);
			$result = $client->call('accountInfo', array(
				'username'	=> $this->username,
				'password'	=> $this->password
			));
			
			return (int) $result['balance'];
		}
	}
