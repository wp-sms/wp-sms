<?php
	class afilnet extends WP_SMS {
		private $wsdl_link = "http://www.afilnet.com/ws/v2/index.php?wsdl";
		public $tariff = "http://www.afilnet.com/";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;
		
		public function __construct() {
			parent::__construct();
			$this->validateNumber = "XXXXXXXXXX";
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
			$result = $client->SendSMSPlusArray($this->username, $this->password, $this->from, '34', $this->to, $this->msg, 1, 0);
			
			if ($result == 'OK') {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				
				/**
				 * Run hook after send sms.
				 *
				 * @since 2.4
				 * @param string $result result output.
				 */
				do_action('wp_sms_send', $result);
				
				return $result;
			} else {
				return false;
			}
		}
		
		public function GetCredit() {
			if(!$this->username and !$this->password)
				return false;
			
			$client = new SoapClient($this->wsdl_link);
			$result = $client->Credits($this->username, $this->password);
			
			return $result;
		}
	}
?>