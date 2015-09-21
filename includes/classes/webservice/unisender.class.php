<?php
	class unisender extends WP_SMS {
		private $wsdl_link = "http://api.unisender.com/ru/api/";
		public $tariff = "http://www.unisender.com/en/prices/";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;
		
		public function __construct() {
			parent::__construct();
			$this->validateNumber = "";
		}
		
		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			$to = implode($this->to, ",");
			
			$sms_text = iconv('cp1251', 'utf-8', $this->msg);
			
			$POST = array (
				'api_key'	=> $this->password,
				'phone'		=> $to,
				'sender'	=> $this->from,
				'text'		=> $sms_text
			);
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $POST);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_URL, "{$this->wsdl_link}sendSms?format=json");
			$result = curl_exec($ch);
			
			if ($result) {
				$jsonObj = json_decode($result);
				
				if(null===$jsonObj) {
					echo "Invalid JSON";
				} elseif(!empty($jsonObj->error)) {
					echo "An error occured: " . $jsonObj->error . "(code: " . $jsonObj->code . ")";
				} else {
					echo "SMS message is sent. Message id " . $jsonObj->result[0]->sms_id;
					
					$this->InsertToDB($this->from, $this->msg, $this->to);
					$this->Hook('wp_sms_send', $result);
					
					return true;
				}
			} else {
				echo "API access error";
			}
		}
		
		public function GetCredit() {
			$json = file_get_contents("{$this->wsdl_link}getUserInfo?format=json&api_key={$this->password}&login={$this->username}");
			
			$result = json_decode($json, true);
			
			if( $result['code'] == 'unspecified' )
				return 0;
			
			if( $result['code'] == 'invalid_api_key' )
				return 0;
			
			return $result['result']['balance'];
		}
	}
?>