<?php
	class opilo extends WP_SMS {
		private $wsdl_link = "http://webservice.opilo.com/WS/";
		public $tariff = "http://cms.opilo.com/?p=179";
		public $unitrial = false;
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
			
			$to_numbers=null;
			
			foreach($this->to as $number){
				if(!empty($to_numbers)){
					$to_numbers .= ','.$number;
				}else{
					$to_numbers= $number;
				}
			}
			
			if(empty($to_numbers)){
				
				echo "Error"; 
				return;
			}
			
			$url = $this->wsdl_link .
			"httpsend/?username=" . $this->username
			. "&password=" . $this->password . 
			"&from=" .$this->from .
			"&to=" .$to_numbers 
			. "&text=" . urlencode($this->msg)
			. "&flash=" . $this->isflash

			;

			$response = file($url);

			if($response[0]) return true;

			if(!is_numeric($response[1])){ 
				echo "Error"; 
				return;

			}    

			if( strlen ($response[1]) > 2){
			
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
				
				return $response[1];

			} else {

				echo  "System Error n:" .$response[1] . ' for '. $number;

			}
		}

		public function GetCredit() {
			$url=$this->wsdl_link . "getCredit/?username=" . $this->username
			."&password=" . $this->password;
			$response = file($url);

			return $response[0];

			if(strstr($response[1],"Error")){
				echo $response[1];
				return;
			}

			return $response[1];
		} 
	}