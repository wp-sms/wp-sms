<?php

namespace WP_SMS\Gateway;

class farazsms extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://ippanel.com/services.jspd";
    public $tariff = "http://farazsms.com/";
    public $unitrial = true;
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

        $args = array(
            'body' => array(
                'uname'   => $this->username,
                'pass'    => $this->password,
                'from'    => $this->from,
                'message' => $this->msg,
                'to'      => json_encode($this->to),
                'op'      => 'send'
            )
        );

        $response = wp_remote_post($this->wsdl_link, $args);

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        // Ger response code
        $response_code = wp_remote_retrieve_response_code($response);

        // Decode response
        $response = json_decode($response['body']);

        if ($response[0] == '0') {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Run hook after send sms.
             *
             * @param string $response result output.
             *
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response);

            return $response;
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response[1], 'error');

            return new \WP_Error('account-credit', $response[1]);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $args = array(
            'body' => array(
                'uname' => $this->username,
                'pass'  => $this->password,
                'op'    => 'credit'
            )
        );

        $response = wp_remote_post($this->wsdl_link, $args);

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('send-sms', $response->get_error_message());
        }

        // Decode response
        $response = json_decode($response['body']);

        return (int)$response[1];
    }
}