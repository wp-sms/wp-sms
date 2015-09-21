<?php
	class ismsie extends WP_SMS {
		private $wsdl_link = "http://ws3584.isms.ir/sendWS";
		public $tariff = "http://isms.ir/";
		public $unitrial = true;
		public $unit;
		public $flash = "enable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "09xxxxxxxx";
			$this->has_key = true;
		}

		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			$data = array(
				'username' => $this->username,
				'password' => $this->password,
				'mobiles' => $this->to,
				'body' => $this->msg,
			);
			
			$data = http_build_query($data);
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $this->wsdl_link);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			
			$result = curl_exec($ch);
			$json = json_decode($result, true);
			
			if($result) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $json);
				
				return $json;
			}
		}
		
		public function GetCredit() {
			if(!$this->username && !$this->password) return;
			return true;
		}
	}