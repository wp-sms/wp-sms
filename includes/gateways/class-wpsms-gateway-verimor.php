<?php

namespace WP_SMS\Gateway;

class verimor extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://sms.verimor.com.tr/v2/";
    public $tariff = "https://www.verimor.com.tr/";
    public $unitrial = false;
    public $unit;
    public $flash = "disabled";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "Phone numbers must start with 90 and be 12 digits.";
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

        $msg = urlencode($this->msg);
        $to  = implode(',', $this->to);

        $response = wp_remote_get($this->wsdl_link . "send?username=" . $this->username . "&password=" . $this->password . "&source_addr=" . $this->from . "&msg=" . $msg . "&dest=" . $to . "&datacoding=0");

        // Check response error
        if (is_wp_error($response)) {
            // Log th result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body']);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response['body']);

            return $response['body'];
        } else {
            // Log th result
            $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

            return new \WP_Error('send-sms', $response['body']);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('The Username/API Key for this gateway is not set', 'wp-sms'));
        }

        return 1;
    }
}
