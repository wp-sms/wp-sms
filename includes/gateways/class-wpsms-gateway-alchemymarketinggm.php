<?php

namespace WP_SMS\Gateway;

class alchemymarketinggm extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://alchemymarketinggm.com:port/api";
    public $tariff = "http://www.alchemymarketinggm.com";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "90xxxxxxxxxx";
        $this->help           = "Use API key as Alchemy server port, like: 9443, you must ask them for it.";
        $this->has_key        = true;
    }

    public function SendSMS()
    {

        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         *
         * @since 3.4
         *
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         *
         * @since 3.4
         *
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         *
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

        foreach ($this->to as $k => $number) {
            $this->to[$k] = trim($number);
        }

        $this->wsdl_link = str_replace('port', $this->has_key, $this->wsdl_link);
        $to              = implode(',', $this->to);
        $to              = urlencode($to);
        $msg             = urlencode($this->msg);

        $result = file_get_contents($this->wsdl_link . '?username=' . $this->username . '&password=' . $this->password . '&action=sendmessage&messagetype=SMS:TEXT&recipient=' . $to . '&messagedata=' . $msg);

        $result = (array)simplexml_load_string($result);

        if (isset($result['action']) and $result['action'] == 'sendmessage' and isset($result['data']->acceptreport)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result['data']);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             *
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $result['data']);

            return $result['data'];
        }
        // Log the result
        $this->log($this->from, $this->msg, $this->to, $result['data']->errormessage, 'error');

        return new \WP_Error('send-sms', $result);
    }

    public function GetCredit()
    {

        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        // Check api key
        if (!$this->has_key) {
            return new \WP_Error('account-credit', __('The API Key for this gateway is not set', 'wp-sms'));
        }

        $this->wsdl_link = str_replace('port', $this->has_key, $this->wsdl_link);

        // Get data
        $response = wp_remote_get($this->wsdl_link . '?action=getcredits&username=' . $this->username . '&password=' . $this->password);

        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        // Check enable simplexml function in the php
        if (!function_exists('simplexml_load_string')) {
            return new \WP_Error('account-credit', 'simplexml_load_string PHP Function disabled!');
        }

        // Load xml
        $xml = (array)simplexml_load_string($response['body']);

        if (isset($xml['action']) and $xml['action'] == 'getcredits') {
            return (int)$xml['data']->account->balance;
        } else {
            $error = (array)$xml['data']->errormessage;

            return new \WP_Error('account-credit', $error[0]);
        }
    }
}