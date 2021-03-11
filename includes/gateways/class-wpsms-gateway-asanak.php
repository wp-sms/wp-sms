<?php

namespace WP_SMS\Gateway;

class asanak extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://panel.asanak.ir/webservice/v1rest/sendsms";
    public $tariff = "http://asanak.ir/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxx";
    }

    function SendSMS()
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

        $to  = implode('-', $this->to);
        $msg = urlencode(trim($this->msg));
        $url = $this->wsdl_link . '?username=' . $this->username . '&password=' . urlencode($this->password) . '&source=' . $this->from . '&destination=' . $to . '&message=' . $msg;

        $headers[] = 'Accept: text/html';
        $headers[] = 'Connection: Keep-Alive';
        $headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';

        $process = curl_init($url);
        curl_setopt($process, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($process, CURLOPT_HEADER, 0);
        curl_setopt($process, CURLOPT_TIMEOUT, 30);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);

        if (curl_exec($process)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $process);

            /**
             * Run hook after send sms.
             *
             * @param string $process result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $process);

            return curl_error($process);
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $process, 'error');

            return new \WP_Error('send-sms', $process);
        }
    }

    function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        return true;
    }
}