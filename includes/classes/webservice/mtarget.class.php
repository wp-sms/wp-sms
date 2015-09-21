<?php
	class mtarget extends WP_SMS {
		private $wsdl_link = "http://smswebservices.mtarget.fr/SmsWebServices/ServletSms";
		public $tariff = "http://mtarget.fr/";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;
		
		public function __construct() {
			parent::__construct();
			$this->validateNumber = "33xxxxxxxxx";
		}
		
		public function SendSMS() {
			$msg = urlencode($this->msg);
			
			foreach($this->to as $to) {
				// Check credit for the gateway
				if(!$this->GetCredit()) return;
				
				$result = file_get_contents($this->wsdl_link.'?method=sendText&username='.$this->username.'&password='.$this->password.'&serviceid='.$this->from.'&destinationAddress='.$to.'&originatingAddress=00000&operatorid=0&paycode=0&msgtext='.$msg);
			}
			
			if($result == '0') {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				$this->Hook('wp_sms_send', $result);
				
				return $result;
			}
		}
		
		public function GetCredit() {
			if(!$this->username && !$this->password) return;
			
			return true;
		}
	}