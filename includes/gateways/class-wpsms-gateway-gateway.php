<?php

namespace WP_SMS\Gateway;

class gateway extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://apps.gateway.sa/vendorsms/";
    public $tariff = "http://sms.gateway.sa/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "+966556xxxxxx";
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

        $to  = implode($this->to, ",");
        $msg = urlencode($this->msg);

        if ($this->isflash) {
            $flash = 1;
        } else {
            $flash = 0;
        }

        if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
            $response = wp_remote_get($this->wsdl_link . "pushsms.aspx?user=" . $this->username . "&password=" . $this->password . "&msisdn=" . $to . "&sid=" . $this->from . "&msg=" . $msg . "&fl=" . $flash . "&dc=8");
        } else {
            $response = wp_remote_get($this->wsdl_link . "pushsms.aspx?user=" . $this->username . "&password=" . $this->password . "&msisdn=" . $to . "&sid=" . $this->from . "&msg=" . $msg . "&fl=" . $flash);
        }


        // Check response error
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $result = json_decode($response['body']);

        if ($result->ErrorCode == '000') {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result);

            /**
             * Run hook after send sms.
             *
             * @since 2.4
             */
            do_action('wp_sms_send', $response['body']);

            return $result;
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result->ErrorMessage, 'error');

            return new \WP_Error('send-sms', $result->ErrorMessage);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('Username/Password does not set for this gateway', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . "CheckBalance.aspx?user=" . $this->username . "&password=" . $this->password);

        if (!is_wp_error($response)) {
            if (strpos($response['body'], 'Success') !== false) {
                return trim($response['body'], 'Success#');
            } else {
                return new \WP_Error('account-credit', $response['body']);
            }
        } else {
            return new \WP_Error('account-credit', $response->get_error_message());
        }
    }
}