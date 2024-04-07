<?php

namespace WP_SMS\Gateway;

class aspsms extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://json.aspsms.com/";
    public $tariff = "https://aspsms.com";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $supportIncoming = true;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->validateNumber = "e.g. +41780000000, +4170000001";
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

        $numbers = array();

        foreach ($this->to as $number) {
            $numbers[] = $this->clean_number($number);
        }

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json; charset=UTF-8'
            ),
            'body'    => wp_json_encode(
                array(
                    'UserName'     => $this->username,
                    'Password'     => $this->password,
                    'Originator'   => $this->from,
                    'Recipients'   => $numbers,
                    'MessageText'  => $this->msg,
                    'ForceGSM7bit' => true
                )
            )
        );

        if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
            $args[1]['body']['ForceGSM7bit'] = false;
        }

        $response = wp_remote_post($this->wsdl_link . "SendSimpleTextSMS", $args);

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $result = json_decode($response['body']);

        if (isset($result->StatusInfo) and $result->StatusInfo == 'OK') {

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             *
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $result);

            return $result;
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

            return new \WP_Error('send-sms', $response['body']);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', esc_html__('The username/password for this gateway is not set', 'wp-sms'));
        }

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json; charset=UTF-8'
            ),
            'body'    => wp_json_encode(
                array(
                    'UserName' => $this->username,
                    'Password' => $this->password
                )
            )
        );

        $response = wp_remote_post($this->wsdl_link . "CheckCredits", $args);

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $result = json_decode($response['body']);

        if (isset($result->Credits) and isset($result->StatusInfo) and $result->StatusInfo == 'OK') {
            return $result->Credits;
        } else {
            return new \WP_Error('account-credit', $result->StatusInfo);
        }

    }

    /**
     * Clean number
     *
     * @param $number
     *
     * @return bool|string
     */
    private function clean_number($number)
    {
        $number = trim($number);

        return $number;
    }
}