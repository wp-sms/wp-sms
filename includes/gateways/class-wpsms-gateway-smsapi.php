<?php

namespace WP_SMS\Gateway;

class smsapi extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.smsapi.pl/";
    public $tariff = "https://smsapi.pl/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "48500500500 or with country code";
        $this->help           = "Please enter your username to username and api pass MD5 to password field.";
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

        $response = wp_remote_post($this->wsdl_link . 'sms.do?username=' . urlencode($this->username) . '&password=' . $this->password . '&message=' . urlencode($this->msg) . '&to=' . implode(",",$this->to) . '&from=' . urlencode($this->from));

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        // Ger response code
        $response_code = wp_remote_retrieve_response_code($response);

        // Check response code
        if ($response_code == '200' and strpos($response['body'], 'OK') !== false) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body']);

            /**
             * Run hook after send sms.
             *
             * @param string $response result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response['body']);

            return $response['body'];
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

            return new \WP_Error('send-sms', $response['body']);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $result = @file_get_contents($this->wsdl_link . 'user.do?username=' . urlencode($this->username) . '&credits=1&details=1&password=' . $this->password);

        return $result;
    }
}