<?php

namespace WP_SMS\Gateway;

class suresms extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.suresms.com/";
    public $tariff = "https://www.suresms.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "+4520202020, The recipient of the message. (remember countrycode)	";
        $this->has_key        = true;
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

        $msg      = urlencode($this->msg);
        $response = array();

        foreach ($this->to as $to) {
            $response = wp_remote_get($this->wsdl_link . "script/SendSMS.aspx?login=" . $this->username . "&password=" . $this->password . "&to=" . $to . "&text=" . $msg . "&from=" . $this->from . "&flash=" . $this->isflash);
        }

        // Check response error
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        if (strpos($response['body'], 'sent') !== false) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body']);

            /**
             * Run hook after send sms.
             *
             * @since 2.4
             */
            do_action('wp_sms_send', $response['body']);

            return $response['body'];
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

            return new \WP_Error('send-sms', $response['body']);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->has_key) {
            return new \WP_Error('account-credit', __('The Username/API Key for this gateway is not set', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . "script/GetUserBalance.aspx?login=" . $this->username . "&password=" . $this->password);

        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $xml = new \SimpleXMLElement($response['body']);

        if (!is_object($xml)) {
            return new \WP_Error('account-credit', 'The XML is not valid, Please contact with gateways administrator.');
        }

        // Convert to array
        $arr = json_decode(json_encode($xml), 1);

        return $arr['Balance'];
    }
}
