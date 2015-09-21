<?php
	class bearsms extends WP_SMS {
		private $wsdl_link = "http://app.bearsms.com/index.php?app=ws";
		public $tariff = "http://www.bearsms.com/";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;
		
		public function __construct() {
			parent::__construct();
			$this->validateNumber = "97xxxxxxxxxxx";
		}
		
		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			
			$to = implode(',', $this->to);
			$msg = urlencode($this->msg);
			
			$result = file_get_contents($this->wsdl_link.'&u='.$this->username.'&h='.$this->password.'&op=pv&to='.$to.'&msg='.$msg);
			$result_arr = json_decode($result);
			
			if($result_arr->data[0]->status == 'ERR')
				return;
			
			$this->InsertToDB($this->from, $this->msg, $this->to);
			$this->Hook('wp_sms_send', $result_arr);
			
			return $result_arr;
		}
		
		public function GetCredit() {
			$result = file_get_contents($this->wsdl_link.'&u='.$this->username.'&h='.$this->password.'&op=cr');
			$result_arr = json_decode($result);
			
			if($result_arr->status == 'ERR')
				return;
			
			return $result_arr->credit;
		}
	}
?>