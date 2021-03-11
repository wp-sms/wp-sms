<?php

namespace WP_SMS\Gateway;

class instantalerts extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://instantalerts.co/api/";
    public $tariff = "http://springedge.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "90xxxxxxxxxx";
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

        // Encode message
        $msg = urlencode($this->msg);

        $result = '';
        foreach ($this->to as $to) {
            $result = file_get_contents($this->wsdl_link . 'web/send/?apikey=' . $this->has_key . '&sender=' . $this->from . '&to=' . $to . '&message=' . $msg . '&format=json');
        }

        if (isset($result['MessageIDs'])) {
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
        $this->log($this->from, $this->msg, $this->to, print_r($result, 1), 'error');

        return new \WP_Error('send-sms', $result);
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        // Get data
        $get_data = file_get_contents($this->wsdl_link . 'status/credit?apikey=' . $this->has_key);

        // Check enable simplexml function in the php
        if (!function_exists('simplexml_load_string')) {
            return new \WP_Error('account-credit', 'simplexml_load_string PHP Function disabled!');
        }

        // Load xml
        $xml = simplexml_load_string($get_data);

        return (int)$xml->credits;
    }
}