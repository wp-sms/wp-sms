<?php
	class smshosting extends WP_SMS {
		private $wsdl_link = "https://api.smshosting.it/rest/api";
		public $tariff = "https://www.smshosting.it/en/pricing";
		public $unitrial = false;
		public $unit;
		public $flash = "disable";
		public $isflash = false;
		private $smsh_response_status = 0;
		
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
				'to' => $to,
				'from' => $this->from,
				'text' => $sms_text
			);
			
			$to_smsh = curl_init ( "{$this->wsdl_link}/sms/send" );
			curl_setopt ( $to_smsh, CURLOPT_POST, true );
			curl_setopt ( $to_smsh, CURLOPT_RETURNTRANSFER, true );
			curl_setopt ( $to_smsh, CURLOPT_USERPWD, $this->username . ":" . $this->password );
  			curl_setopt ( $to_smsh, CURLOPT_POSTFIELDS,  http_build_query($POST));
			curl_setopt ( $to_smsh, CURLOPT_TIMEOUT, 10);
			curl_setopt( $to_smsh, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
			
			$result = curl_exec ( $to_smsh );
			
			$this->smsh_response_status= curl_getinfo($to_smsh, CURLINFO_HTTP_CODE);
			
			if ($result) {
				$jsonObj = json_decode($result);
				
				if(null===$jsonObj) {
					echo "Invalid JSON";
				} elseif($this->smsh_response_status!=200) {
					echo "An error occured: " . $jsonObj->errorMsg . "(code: " . $this->smsh_response_status . ")";
				} else {
					echo "SMS message is sent. Message id " . $jsonObj->transactionId;
					
					$this->InsertToDB($this->from, $this->msg, $this->to);
					$this->Hook('wp_sms_send', $result);
					
					return true;
				}
			} else {
				echo "API access error";
			}
		}
		
		public function GetCredit() {
			$to_smsh = curl_init ( "{$this->wsdl_link}/user" );
			
			curl_setopt ( $to_smsh, CURLOPT_RETURNTRANSFER, true );
			curl_setopt ( $to_smsh, CURLOPT_USERPWD, $this->username . ":" . $this->password );
			curl_setopt ( $to_smsh, CURLOPT_TIMEOUT, 10);
			
			$result = curl_exec ( $to_smsh );
			
			$this->smsh_response_status= curl_getinfo($to_smsh, CURLINFO_HTTP_CODE);
			
			if ($result) {
				$jsonObj = json_decode($result);
				
				if(null===$jsonObj) {
					return 0;
				} elseif($this->smsh_response_status!=200) {
					return 0;
				} else {
					return $jsonObj->italysms;					
				}
			} else {
				return 0;
			}
		}
	}