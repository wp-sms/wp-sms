<?php

namespace WP_SMS\Gateway;

class aradpayamak extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://aradpayamak.net/APPs/SMS/WebService.php?wsdl";
    public $tariff = "http://aradpayamak.net/";
    public $unitrial = true;
    public $unit;
    public $flash = "disabled";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxx";
        $this->has_key        = true;
        $this->help           = 'Please Enter Your Domain Name in API Key';

        @ini_set("soap.wsdl_cache_enabled", "0");
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

        // Reformat number
        $to = array();
        foreach ($this->to as $number) {
            $to[] = $number;
        }

        $client = new \SoapClient($this->wsdl_link);
        $result = $client->sendSMS($this->has_key, $this->username, $this->password, $this->from, implode(";",$to), $this->msg, 1);
        if ($result) {
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
        }
        // Log the result
        $this->log($this->from, $this->msg, $this->to, $result, 'error');

        return new \WP_Error('send-sms', $result);

    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', esc_html__('Username and Password are required.', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return new \WP_Error('required-class', esc_html__('Class SoapClient not found. please enable php_soap in your php.', 'wp-sms'));
        }

        try {
            $client = new \SoapClient($this->wsdl_link);
        } catch (\Exception $e) {
            return new \WP_Error('account-credit', $e->getMessage());
        }

        return $client->getCredit($this->has_key, $this->username, $this->password);
    }
}