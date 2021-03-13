<?php

namespace WP_SMS\Gateway;

class resalaty extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://www.resalaty.com/api/";
    public $tariff = "https://resalaty.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
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

        $to = implode(',', $this->to);

        $msg = urlencode($this->msg);

        // Get response
        $response = wp_remote_get($this->wsdl_link . 'sendsms.php?username=' . $this->username . '&password=' . $this->password . '&message=' . $msg . '&numbers=' . $to . '&sender=' . $this->from . '&unicode=e&Rmduplicated=1&return=json');

        // Decode response
        $response = json_decode($response['body']);

        if ($response->Code == 100) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response);

            return true;
        } else {
            // Log th result
            $this->log($this->from, $this->msg, $this->to, $response->MessageIs, 'error');

            return new \WP_Error('send-sms', $response->MessageIs);
        }

    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        // Get response
        $response = wp_remote_get($this->wsdl_link . 'getbalance.php?username=' . $this->username . '&password=' . $this->password . '&return=json');

        // Decode response
        $response = json_decode($response['body']);

        if ($response->Code == 117) {
            // Return blance
            return $response->currentuserpoints;
        } else {
            return new \WP_Error('account-credit', $response->MessageIs);
        }
    }
}