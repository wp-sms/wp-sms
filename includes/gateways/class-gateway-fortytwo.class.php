<?php
	class fortytwo extends WP_SMS {
		private $wsdl_link = "http://imghttp.fortytwotele.com/api/current";
		public $tariff = "http://fortytwo.com/";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "46731111111";
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

			$msg = urlencode($this->msg);
			$route = "G1";
			
			foreach($this->to as $number) {
				$result[] = file_get_contents($this->wsdl_link . "/send/message.php?username=".$this->username."&password=".$this->password."&to=".$number."&from=".$this->from."&message=".$msg."&route=".$route);
			}

			file_put_contents('log', print_r($result, 1));

			if($result) {
				$this->InsertToDB($this->from, $this->msg, $this->to);
				
				/**
				 * Run hook after send sms.
				 *
				 * @since 2.4
				 * @param string $result result output.
				 */
				do_action('wp_sms_send', $result);
			}
		}

		public function GetCredit() {
			if( !$this->username or !$this->password )
				return;

			return true;
		}
	}