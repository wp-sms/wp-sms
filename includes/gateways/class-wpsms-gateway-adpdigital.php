<?php

namespace WP_SMS\Gateway;

class adpdigital extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://ws.adpdigital.com/url/";
    public $tariff = "http://adpdigital.com/services/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
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

        $to  = str_replace("09", "989", implode(",",$this->to));
        $msg = urlencode($this->msg);

        $result = file_get_contents("{$this->wsdl_link}send?username={$this->username}&password={$this->password}&dstaddress={$to}&body={$msg}&clientid={$this->from}&type=text&unicode=1");

        if (strstr($result, 'ERR')) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result, 'error');

            return new \WP_Error('send-sms', $result);
        } else {
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

            return preg_replace('/[^0-9]/', '', $result);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        $result = file_get_contents("{$this->wsdl_link}balance?username={$this->username}&password={$this->password}&facility=send");

        if (strstr($result, 'ERR')) {
            return new \WP_Error('account-credit', $result);
        } else {
            return preg_replace('/[^0-9]/', '', $result);
        }
    }
}