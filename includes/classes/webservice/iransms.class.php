<?php
	class iransms extends WP_SMS {
		private $wsdl_link = "http://messagingws.iransms.co/SendSMS.asmx?WSDL";
		public $tariff = "http://iransms.co";
		public $unitrial = true;
		public $unit;
		public $flash = "disable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "09xxxxxxxx";
			$this->has_key = true;
			
			ini_set("soap.wsdl_cache_enabled", "0");
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
			
			foreach($this->to as $number) {
				$result = file_get_contents('http://www.iransms.co/URLSend.aspx?Username='.$this->username.'&Password='.$this->password.'&PortalCode='.$this->has_key.'&Mobile='.$number.'&Message='.urlencode($this->msg).'&Flash=0');
			}
			
			$this->InsertToDB($this->from, $this->msg, $this->to);
			
			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 * @param string $result result output.
			 */
			do_action('wp_sms_send', $result);
			
			return $result;
		}

		public function GetCredit() {
			// Check credit for the gateway
			if(!$this->username && !$this->password) return;
			
			$client = new SoapClient($this->wsdl_link);
			$args = array(
				'PortalCode'	=>	$this->has_key,
				'UserName'		=>	$this->username,
				'PassWord'		=>	$this->password,
			);
			
			$result = $client->GetSystemCredit($args);
			return $result->GetSystemCreditResult;
		}
	}
	