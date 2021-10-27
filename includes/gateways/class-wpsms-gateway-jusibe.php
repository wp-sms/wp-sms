<?php

namespace WP_SMS\Gateway;

class jusibe extends \WP_SMS\Gateway
{
    public $wsdl_link = 'https://api.jusibe.com/sms/v1';
    public $tariff = "https://jusibe.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "070XXXXXXXX,+23480XXXXXXXX,23490XXXXXXXX,81XXXXXXXX";
        // Enable api key
        $this->has_key   = false;
        $this->bulk_send = true;
        $this->help      = 'Enter your <a href="https://jusibe.com" target="_blank">Jusibe.com</a> Public Key as the API Username and your Access Token as the API Password. You can get your API credentials <a href="https://dashboard.jusibe.com/api-keys" target="_blank">here</a>.';
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

        // Get the credit.
        $credit = $this->GetCredit();

        // Check gateway credit
        if (is_wp_error($credit)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');

            return $credit;
        }

        $this->msg     = apply_filters('wp_sms_msg', $this->msg);
        $total_numbers = count($this->to);
        if ($total_numbers > 1) {
            $destination = implode(',', $this->to);
            $api_url     = $this->wsdl_link . '/bulk/send';
        } else {
            $destination = reset($this->to);
            $api_url     = $this->wsdl_link . '/send';
        }
        $body    = array(
            'to'      => $destination,
            'from'    => $this->from,
            'message' => $this->msg,
        );
        $headers = array(
            'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
        );
        $args    = array(
            'body'    => $body,
            'headers' => $headers,
            'timeout' => 60,
        );

        // Send request and Get response
        $response = wp_remote_post($api_url, $args);
        if (!is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response)) {
            $response = json_decode(wp_remote_retrieve_body($response));

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Run hook after send sms.
             *
             * @param string $response result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response);

            return $response;
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response, 'error');

            return new \WP_Error('send-sms', $response);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('The Username/Password for this gateway is not set', 'wp-sms'));
        }
        $headers  = array(
            'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
        );
        $args     = array(
            'headers' => $headers,
            'timeout' => 60,
        );
        $response = wp_remote_get($this->wsdl_link . '/credits', $args);
        if (!is_wp_error($response) && 200 == wp_remote_retrieve_response_code($response)) {
            $body = json_decode(wp_remote_retrieve_body($response));

            return $body->sms_credits . ' credits';
        } else {
            return new \WP_Error('account-credit', 'Unable to connect to Jusibe, check your API Username and API Password.');
        }
    }
}