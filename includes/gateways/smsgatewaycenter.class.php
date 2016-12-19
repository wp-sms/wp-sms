<?php
	class smsgatewaycenter extends WP_SMS {
		private $wsdl_link = "https://www.smsgatewaycenter.com/library/";
		public $tariff = "https://www.smsgatewaycenter.com/";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "91xxxxxxxxxx";
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
			
			$result = file_get_contents($this->wsdl_link . "send_sms_2.php?UserName=".$this->username."&Password=".$this->password."&Type=Bulk&To=".implode(',', $this->to)."&Mask=".$this->from."&Message=".$msg);

			if( strpos( $result, 'error' ) !== false ) {
				return false;
			}

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

			$result = file_get_contents($this->wsdl_link . "checkbalance.php?Username=".$this->username."&Password=".$this->password);

			if( strpos( $result, 'error' ) !== false ) {
				return false;
			}

			return true;
		}
	}