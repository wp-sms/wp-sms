<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Exception;

class smsto extends \WP_SMS\Gateway
{
    public $wsdl_link = "https://api.sms.to";
    public $tariff = "https://auth.sms.to/";
    public $unitrial = true;
    public $unit;
    public $flash = "enable";
    public $isflash = false;
    public $callback_url;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "XXXXXXXX,YYYYYYYY";
        $this->has_key        = true;
        $this->bulk_send      = true;
        $this->help           = 'Please enter your API key and leave the API username & API password empty.';
        $this->documentUrl    = 'https://wp-sms-pro.com/resources/sms-to-gateway-configuration/';
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
        $this->msg = apply_filters('wp_sms_msg', $this->msg, $this->to);

        if (empty($this->has_key)) {
            return [
                'error'  => true,
                'reason' => 'Invalid Credentials',
                'data'   => null,
                'status' => 'FAILED'
            ];
        }

        if ($this->CountNumberOfCharacters() > 480) {
            return new \WP_Error('account-credit', 'You have exceeded the max limit of 480 characters');
        }

        $bodyContent = array(
            'sender_id' => $this->from,
            'to'        => $this->to,
            'message'   => $this->msg,
        );

        if ((isset($this->options['gateway_smsto_callback_url']))) {
            $callback_url                = apply_filters('sms_to_callback', $this->options['gateway_smsto_callback_url']);
            $bodyContent['callback_url'] = 'https://' . $callback_url . '/wp-json/sms-to/get_post';
        }

        $apiURL = "{$this->wsdl_link}/sms/send";

        if ($this->isflash) {
            $apiURL = "{$this->wsdl_link}/fsms/send";
        }

        $args = [
            'method'      => 'POST',
            'timeout'     => 15,
            'redirection' => 10,
            'httpversion' => '1.1',
            'sslverify'   => false,
            'headers'     => [
                'authorization' => 'Bearer ' . $this->has_key,
                'content-type'  => 'application/json',
            ],
            'body'        => json_encode($bodyContent),
        ];

        try {
            $httpResponse = $this->request('POST', $apiURL, $args);

            if (is_wp_error($httpResponse)) {
                $err      = $httpResponse->get_error_message();
                $response = null;
            } else {
                $response = json_decode(wp_remote_retrieve_body($httpResponse));
                $err      = null;
            }
        } catch (Exception $e) {
            $err      = $e->getMessage();
            $response = null;
        }

        if ($err) {
            $response = [
                'error'  => true,
                'reason' => $err,
                'data'   => $bodyContent,
                'status' => 'FAILED'
            ];
            do_action('wp_sms_send', $response);

            $this->log($this->from, $this->msg, $this->to, $response);

            return $response;
        }


        if (isset($response->success) && $response->success == 'true') {
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

            $errorMessage = isset($response->message) ? $response->message : print_r($response->message, true);

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $errorMessage, 'error');
            return new \WP_Error('send-sms', $errorMessage);
        }
    }

    public function GetCredit()
    {
        // Check api
        if (!$this->has_key) {
            return new \WP_Error('account-credit', 'API not set');
        }

        /**
         * Send request
         */
        try {
            $response = $this->request('GET', $this->tariff . 'api/balance?api_key=' . $this->has_key);
        } catch (Exception $e) {
            return new \WP_Error('account-credit', $e->getMessage());
        }

        /**
         * Make sure the request doesn't have the error
         */
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        /**
         * The request() method returns decoded JSON directly (stdClass or array),
         * not the raw WordPress HTTP response.
         */
        $responseObject = $response;

        /*
         * Response validity - check if we got a valid response object
         */
        if (isset($responseObject->balance)) {
            return round($responseObject->balance, 2);
        }

        if (isset($responseObject->message)) {
            return new \WP_Error('account-credit', $responseObject->message);
        }

        return new \WP_Error('account-credit', esc_html__('Invalid response from SMS.to API', 'wp-sms'));
    }

    public function CountNumberOfCharacters()
    {
        return strlen($this->msg);
    }
}