<?php

namespace WP_SMS\Gateway;

class aruba extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://adminsms.aruba.it/";
    public $tariff = "http://adminsms.aruba.it/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "";
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

        $to  = implode(",",$this->to);
        $msg = urlencode($this->msg);

        $response = wp_remote_post($this->wsdl_link . "Aruba/SENDSMS?login=" . urlencode($this->username) . "&password=" . urlencode($this->password) . "&message=" . $msg . "&message_type=N&order_id=999FFF111&sender=" . $this->from . "&recipient=" . $to);

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        // Ger response code
        $response_code = wp_remote_retrieve_response_code($response);

        // Check response code
        if ($response_code == '200' and substr($response['body'], 0, 2) == 'OK') {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body']);

            /**
             * Run hook after send sms.
             *
             * @param string $response result output.
             *
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response['body']);

            return $response;
        } else {
            // Log th result
            $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

            return new \WP_Error('account-credit', $response['body']);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username or !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . "Aruba/CREDITS?login=" . urlencode($this->username) . "&password=" . urlencode($this->password));

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        // Ger response code
        $response_code = wp_remote_retrieve_response_code($response);

        // Check response code
        if ($response_code == '200' and substr($response['body'], 0, 2) == 'OK') {
            return $response['body'];
        } else {
            return new \WP_Error('account-credit', $response['body']);
        }
    }
}