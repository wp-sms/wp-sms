<?php
	class bulutfon extends WP_SMS {
		private $wsdl_link = "https://api.bulutfon.com/messages";
		public $tariff = "http://bulutfon.com/";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "90xxxxxxxxxx";
		}

		public function SendSMS() {
			// Check credit for the gateway
			if(!$this->GetCredit()) return;
			$msg = urlencode($this->msg);

			$data = array(
				'title'	=> $this->from,
				'email' => $this->username,
				'password' => $this->password,
				'receivers' => implode(',',$this->to),
				'content' => $this->msg,
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
				
				/**
				 * Run hook after send sms.
				 *
				 * @since 2.4
				 * @param string $result result output.
				 */
				do_action('wp_sms_send', $result);
				
				return $json;
			}
		}
		
		public function GetCredit() {
			$result = file_get_contents('https://api.bulutfon.com/me'.'?email='.$this->username.'&password='.$this->password);
			$result_arr = json_decode($result);
			
			
			return $result_arr->credit->sms_credit;
		}
	}