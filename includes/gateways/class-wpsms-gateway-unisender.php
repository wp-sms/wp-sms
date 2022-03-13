<?php

namespace WP_SMS\Gateway;

class unisender extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.unisender.com/en/api/";
    public $tariff = "http://www.unisender.com/en/prices/";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->has_key        = true;
        $this->validateNumber = "The recipient's phone in international format with the country code (you can omit the leading \"+\").Example: Phone = 79092020303. You can specify multiple  ecipient numbers separated by commas. Example: Phone = 79092020303,79002239878";
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

        $to   = implode(",", $this->to);
        $text = iconv('cp1251', 'utf-8', $this->msg);

        $response = wp_remote_get($this->wsdl_link . "sendSms?format=json&api_key=" . $this->has_key . "&sender=" . $this->from . "&text=" . $text . "&phone=" . $to);

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            $result = json_decode($response['body']);

            if (isset($result->result->error)) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $result->result->error, 'error');

                return new \WP_Error('send-sms', $result->result->error);
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

            return $result;

        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

            return new \WP_Error('send-sms', $response['body']);
        }
    }

    public function GetCredit()
    {
        // Check api key
        if (!$this->has_key) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . "getUserInfo?format=json&api_key={$this->has_key}");

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            $result = json_decode($response['body'], true);
            if (isset($result['error'])) {
                return new \WP_Error('account-credit', $result['error']);
            } else {
                return $result['result']['balance'];
            }
        } else {
            return new \WP_Error('account-credit', $response['body']);
        }
    }
}