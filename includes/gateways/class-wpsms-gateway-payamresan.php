<?php

namespace WP_SMS\Gateway;

class payamresan extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://www.payam-resan.com/";
    public $tariff = "http://www.payam-resan.com/CMS/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxx";

        @ini_set("soap.wsdl_cache_enabled", "0");
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

        $message = urlencode($this->msg);

        $client = file_get_contents("{$this->wsdl_link}APISend.aspx?Username={$this->username}&Password={$this->password}&From={$this->from}&To={$to}&Text={$message}");

        if ($client) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $client);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $client);
        }
        // Log the result
        $this->log($this->from, $this->msg, $this->to, $client, 'error');

        return new \WP_Error('send-sms', $client);
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        $client = file_get_contents("{$this->wsdl_link}Credit.aspx?Username={$this->username}&Password={$this->password}");

        if ($client == 'ERR') {
            return new \WP_Error('account-credit', $client);
        }

        return $client;
    }
}