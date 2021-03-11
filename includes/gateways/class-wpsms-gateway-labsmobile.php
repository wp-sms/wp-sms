<?php

namespace WP_SMS\Gateway;

class labsmobile extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://api.labsmobile.com/ws/services/LabsMobileWsdl.php?wsdl";
    public $tariff = "http://www.labsmobile.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "34XXXXXXXXX";
        $this->has_key        = true;
    }

    public function SendSMS()
    {

        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         * @since 3.4
         *
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         * @since 3.4
         *
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         * @since 3.4
         *
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        // Get the credit.
        $credit = $this->GetCredit();

        // Check gateway credit
        if (is_wp_error($credit)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');

            return $credit;
        }

        $client = new \SoapClient($this->wsdl_link);
        $str_to = "";

        if (is_array($this->to)) {
            foreach ($this->to as $item_to) {
                $str_to .= "<msisdn>$item_to</msisdn>";
            }
        } else {
            $str_to = $this->to;
        }

        $to_message = urlencode(htmlspecialchars($this->msg, ENT_QUOTES));
        $xmldata    = "
            <sms>
                <recipient>
                    $str_to
                </recipient>
                <message>$to_message</message>
                <tpoa>$this->from</tpoa>
            </sms>";

        $result = $client->__soapCall("SendSMS", array(
            "client"   => $this->has_key,
            "username" => $this->username,
            "password" => $this->password,
            "xmldata"  => $xmldata
        ));

        if ($this->_xml_extract("code", $result) == "0") {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $result);

            return $result;
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result, 'error');
            return new \WP_Error('send-sms', $result);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return new \WP_Error('required-class', __('Class SoapClient not found. please enable php_soap in your php.', 'wp-sms'));
        }

        try {
            $client = new \SoapClient($this->wsdl_link);
        } catch (\Exception $e) {
            return new \WP_Error('account-credit', $e->getMessage());
        }

        $result = $client->GetCredit($this->username, $this->password);

        return $this->_xml_extract("messages", $result);
    }

    private function _xml_extract($attr, $xml)
    {
        $init     = stripos($xml, "<" . $attr . ">");
        $end_pos  = stripos($xml, "</" . $attr . ">");
        $init_pos = $init + strlen($attr) + 2;

        return substr($xml, $init_pos, $end_pos - $init_pos);
    }
}