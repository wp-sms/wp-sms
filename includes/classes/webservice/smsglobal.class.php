<?php
	class smsglobal extends WP_SMS {
		private $wsdl_link = "http://www.smsglobal.com/mobileworks/soapserver.php?wsdl";
		public $tariff = "http://www.smsglobal.com/global/en/sms/pricing.php";
		public $unitrial = false;
		public $unit;
		public $flash = "enable";
		public $isflash = false;

		public function __construct() {
			parent::__construct();
			$this->validateNumber = "61xxxxxxxxx";
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
			
			$client = new SoapClient($this->wsdl_link);
			$validation_login = $client->apiValidateLogin($this->username, $this->password);
			$xml_praser = xml_parser_create();
			xml_parse_into_struct($xml_praser, $validation_login, $xml_data, $xml_index);
			xml_parser_free($xml_praser);
			$ticket_id = $xml_data[$xml_index['TICKET'][0]]['value'];
			$result = $client->apiSendSms($ticket_id, $this->from, implode(',', $this->to), $this->msg, 'text', '0', '0');
			
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
			
			return $result;
		}

		public function GetCredit() {
			$client = new SoapClient($this->wsdl_link);
			$validation_login = $client->apiValidateLogin($this->username, $this->password);
			$xml_praser = xml_parser_create();
			xml_parse_into_struct($xml_praser, $validation_login, $xml_data, $xml_index);
			xml_parser_free($xml_praser);
			$ticket_id = $xml_data[$xml_index['TICKET'][0]]['value'];
			$credit = $client->apiBalanceCheck($ticket_id, 'IR');
			$xml_credit = simplexml_load_string($credit);
			
			return (string) $xml_credit->credit;
		}
	}