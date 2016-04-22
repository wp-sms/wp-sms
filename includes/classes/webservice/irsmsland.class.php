<?php
	class irsmsland extends WP_SMS {
		private $wsdl_link = "http://sms.irsmsland.ir/webservice/wsdl.wsdl";
		public $tariff = "http://irsmsland.ir";
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
			
			/**
			 * Modify sender number
			 *
			 * @since 3.4
			 * @param string $this->from sender number.
			 */
			$this->from = apply_filters('wp_sms_from', $this->from);
			
			/**
			 * Modify Receiver number
			 *
			 * @since 3.4
			 * @param array $this->to receiver number
			 */
			$this->to = apply_filters('wp_sms_to', $this->to);
			
			/**
			 * Modify text message
			 *
			 * @since 3.4
			 * @param string $this->msg text message.
			 */
			$this->msg = apply_filters('wp_sms_msg', $this->msg);
			
			$client = new SoapClient($this->wsdl_link);
			$result = $client->send($this->username, $this->password, array(array('number' => implode(",", $this->to))), $this->from, $this->msg);
			
			if($result) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				
				/**
				 * Run hook after send sms.
				 *
				 * @since 2.4
				 * @param string $result result output.
				 */
				do_action('wp_sms_send', $result);
				
				return $result[0]['id'];
			}
			
		}
		
		public function GetCredit() {
			$client = new SoapClient($this->wsdl_link);
			$result = $client->getCredit($this->username, $this->password);
			
			return $result[0]['id'];
		}
	}
?>