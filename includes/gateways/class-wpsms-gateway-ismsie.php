<?php

namespace WP_SMS\Gateway;

class ismsie extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://ws3584.isms.ir/sendWS";
    public $tariff = "http://isms.ir/";
    public $unitrial = true;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxx";
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

        $data = [
            'username' => $this->username,
            'password' => $this->password,
            'mobiles'  => $this->to,
            'body'     => $this->msg,
        ];

        $response = wp_remote_post($this->wsdl_link, [
            'body' => $data
        ]);

        $result = wp_remote_retrieve_body($response);
        $json   = json_decode($result, true);

        if ($result) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result);
            do_action('wp_sms_send', $json);

            return $json;
        }
        // Log th result
        $this->log($this->from, $this->msg, $this->to, $result, 'error');

        return new \WP_Error('send-sms', $result);
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        return true;
    }
}
