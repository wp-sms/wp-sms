<?php
	class tsms extends WP_SMS {
		private $wsdl_link = "http://www.tsms.ir/soapWSDL/?wsdl";
		private $client = null;
		public $tariff = "http://sms.tsms.ir/";
		public $unitrial = true;
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
			
			$messagid = rand();
			$mclass = array('');
			$this->client = new SoapClient('http://www.tsms.ir/soapWSDL/?wsdl');
			
			$result = $this->client->sendSms($this->username, $this->password, array($this->from), $this->to, array($this->msg), $mclass, $messagid);
			
			if( $result ) {
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
		}
		
		public function GetCredit() {
			// Check credit for the gateway
			if(!$this->username or !$this->password) return;
			
			$this->client = new SoapClient('http://www.tsms.ir/soapWSDL/?wsdl');
			$result = $this->client->userinfo($this->username, $this->password);
			
			if($result)
			return $result[0]->credit;
		}
	}
