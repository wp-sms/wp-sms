<?php

namespace WP_SMS\Gateway;

class cpsms extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.cpsms.dk/";
    public $tariff = "https://api.cpsms.dk/v2/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "The number starting with country code.";
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

        $body = array(
            'to'      => $this->to,
            'message' => $this->msg,
            'from'    => $this->from,
        );

        $response = wp_remote_post($this->wsdl_link . 'v2/send', [
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("$this->username:$this->has_key"),
                'Accept'        => 'application/json, text/javascript',
                'Content-Type'  => 'application/json'
            ),
            'body'    => json_encode($body)
        ]);

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $result        = json_decode($response['body']);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
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
            $this->log($this->from, $this->msg, $this->to, $result->error, 'error');

            return new \WP_Error('send-sms', print_r($result->error, 1));
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->has_key) {
            return new \WP_Error('account-credit', __('The Username/API Key for this gateway is not set', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . 'v2/creditvalue', [
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("$this->username:$this->has_key"),
                'Accept'        => 'application/json, text/javascript',
                'Content-Type'  => 'application/json'
            )
        ]);

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $result        = json_decode($response['body']);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            return $result->credit;
        } else {
            return new \WP_Error('credit', $result->error->message);
        }
    }
}