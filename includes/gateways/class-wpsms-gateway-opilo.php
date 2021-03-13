<?php

namespace WP_SMS\Gateway;

class opilo extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://webservice.opilo.com/WS/";
    public $tariff = "http://cms.opilo.com/?p=179";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxx";
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

        $to_numbers = null;

        foreach ($this->to as $number) {
            if (!empty($to_numbers)) {
                $to_numbers .= ',' . $number;
            } else {
                $to_numbers = $number;
            }
        }

        if (empty($to_numbers)) {
            $this->log($this->from, $this->msg, $this->to, 'Number is an empty!', 'error');

            return new \WP_Error('send-sms', 'Number is an empty!');
        }

        $url = $this->wsdl_link .
            "httpsend/?username=" . $this->username
            . "&password=" . $this->password .
            "&from=" . $this->from .
            "&to=" . $to_numbers
            . "&text=" . urlencode($this->msg)
            . "&flash=" . $this->isflash;

        $response = file($url);

        if ($response[0]) {
            $this->log($this->from, $this->msg, $this->to, $response[0], 'error');

            return new \WP_Error('send-sms', $response[0]);
        }

        if (!is_numeric($response[1])) {
            $this->log($this->from, $this->msg, $this->to, $response[1], 'error');

            return new \WP_Error('send-sms', $response[1]);
        }

        if (strlen($response[1]) > 2) {

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response[1]);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response);

            return $response[1];

        } else {
            $this->log($this->from, $this->msg, $this->to, $response, 'error');

            return new \WP_Error('send-sms', $response);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $url      = $this->wsdl_link . "getCredit/?username=" . $this->username
            . "&password=" . $this->password;
        $response = file($url);

        return $response[0];

        if (strstr($response[1], "Error")) {
            return new \WP_Error('account-credit', $response);
        }

        return $response[1];
    }
}