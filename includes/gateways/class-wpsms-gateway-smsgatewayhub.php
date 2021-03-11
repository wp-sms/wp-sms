<?php

namespace WP_SMS\Gateway;

class smsgatewayhub extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://login.smsgatewayhub.com/api/mt/";
    public $tariff = "https://www.smsgatewayhub.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "91989xxxxxxx,91999xxxxxxx";

        // Enable api key
        $this->has_key = true;
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

        // Implode numbers
        $to = implode(',', $this->to);

        // Unicode message
        $msg = urlencode($this->msg);

        $response = wp_remote_get($this->wsdl_link . 'SendSMS?APIKey=' . $this->has_key . '&senderid=' . $this->from . '&channel=2&DCS=0&flashsms=0&number=' . $to . '&text=' . $msg . '&route=clickhere');

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            // Decode json
            $result = json_decode($response['body']);

            // Check response
            if ($result->ErrorMessage != 'Success') {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $result->ErrorMessage, 'error');

                return new \WP_Error('send-sms', $result->ErrorMessage);
            }

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

        $response = wp_remote_get($this->wsdl_link . 'GetBalance?APIKey=' . $this->has_key);

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            $result = json_decode($response['body']);

            // Check response
            if ($result->ErrorMessage != 'Success') {
                return new \WP_Error('account-credit', $result->ErrorMessage);
            }

            return $result->Balance;
        } else {
            return new \WP_Error('account-credit', $response['body']);
        }
    }
}