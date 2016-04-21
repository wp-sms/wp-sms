<?php
	class mydnspanel extends WP_SMS {
		private $wsdl_link = "http://mydnspanel.com/webservice/server.asmx?wsdl";
		private $client = null;
		public $tariff = "http://mydnspanel.com/";
		public $unitrial = true;
		public $unit;
		public $flash = "enable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "09xxxxxxxx";
			$this->has_key = true;
			
			if(!class_exists('nusoap_client'))
				include_once dirname( __FILE__ ) . '/../nusoap.class.php';
			
			$this->client = new nusoap_client($this->wsdl_link);
			$this->client->decode_utf8 = false;
		}

		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			$data = array(
				$this->has_key,
				$this->from,
				$this->username,
				$this->password,
				get_option('wp_sms_mcc'),
				$this->msg,
				implode(',', $this->to),
				false
			);
			
			$result = $this->client->call('Sendsms', $data);
			$result = explode(',', ($result));
			
			if( count($result)>1 && $result[0]==1 ) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
				
				return $result;
			}
		}
		
		public function GetCredit() {
			$result = $this->client->call("Credit", array(2, $this->username, $this->password));
			
			if($result == '301' or $result == '302')
				return false;
			
			return $result;
		}
	}
