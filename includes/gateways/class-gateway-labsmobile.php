<?php
	class labsmobile extends WP_SMS {
		private $wsdl_link = "http://api.labsmobile.com/ws/services/LabsMobileWsdl.php?wsdl";
		public $tariff = "http://www.labsmobile.com/";
		public $unitrial = false;
		public $unit;
		public $flash = "disable";
		public $isflash = false;		
		public function __construct() {
			parent::__construct();
			$this->validateNumber = "34XXXXXXXXX";
            $this->has_key = true;
		}
		
		public function SendSMS() {
			
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
            $str_to = "";
            if(is_array($this->to)){
                foreach($this->to as $item_to){
                    $str_to .= "<msisdn>$item_to</msisdn>";
                }
            } else {
                $str_to = $this->to;
            }

            $to_message = urlencode(htmlspecialchars($this->msg, ENT_QUOTES));
            $xmldata = "
                <sms>
                    <recipient>
                        $str_to
                    </recipient>
                    <message>$to_message</message>
                    <tpoa>$this->from</tpoa>
                </sms>";
			$result = $client->__soapCall("SendSMS", array(
                "client" => $this->has_key,
                "username" => $this->username,
                "password" => $this->password,
                "xmldata" => $xmldata
            ));
			
			if ($this->_xml_extract("code", $result) == "0") {
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
			$result = $client->GetCredit($this->username, $this->password);
			
			return $this->_xml_extract("messages", $result);
		}		
        private function _xml_extract ($attr, $xml){
            $init = stripos($xml, "<".$attr.">");
            $end_pos = stripos($xml, "</".$attr.">");
            $init_pos = $init+strlen($attr)+2;
            return substr($xml, $init_pos, $end_pos-$init_pos);
        }
	}