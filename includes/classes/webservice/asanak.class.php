<?php
	class asanak extends WP_SMS {
		private $wsdl_link = "http://panel.asanak.ir/webservice/v1rest/sendsms";
		public $tariff = "http://asanak.ir/";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;

		function __construct() {
			parent::__construct();
			$this->validateNumber = "09xxxxxxxx";
		}
		
		function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			$to = implode('-', $this->to);
			$msg = urlencode(trim($this->msg));
			$url = $this->wsdl_link.'?username='.$this->username.'&password='.$this->password.'&source='.$this->from.'&destination='.$to.'&message='. $msg;
			
			$headers[] = 'Accept: text/html';
			$headers[] = 'Connection: Keep-Alive';
			$headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';
			
			$process = curl_init($url);
			curl_setopt($process, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($process, CURLOPT_HEADER, 0);
			curl_setopt($process, CURLOPT_TIMEOUT, 30);
			curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
			
			if(curl_exec($process)) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
				
				return true;
			}
		}
		
		function GetCredit() {
			if(!$this->user && !$this->password)
				return false;
			
			return true;
		}
	}