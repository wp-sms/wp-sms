<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class custom extends \WP_SMS\Gateway
{
    public $tariff = '';
    public $flash = false;
    public $isflash = false;
    public $unitrial = true;
    public $unit;
    public $api_url;
    public $http_headers;
    public $http_parameters;
    public $is_post_body;
    public $encode_message;

    public function __construct()
    {
        parent::__construct();
        $this->help          = sprintf('Check the <a target="_blank" href="%s">documentation</a> for instructions on setting up your custom API gateway.', WP_SMS_SITE . '/resources/custom-sms-gateway-setup-documentation/?utm_source=wp-sms&utm_medium=link&utm_campaign=settings');
        $this->gatewayFields = [
            'api_url'         => [
                'id'   => 'gateway_api_url',
                'name' => esc_html__('Send SMS API URL', 'wp-sms'),
                'desc' => esc_html__('Enter the Send SMS URL for the SMS gateway API where the SMS requests will be sent. This URL is provided by the SMS gateway service.', 'wp-sms'),
            ],
            'http_headers'    => [
                'id'   => 'gateway_http_headers',
                'name' => esc_html__('HTTP Headers', 'wp-sms'),
                'desc' => esc_html__('Specify any HTTP headers required for API requests. Headers often include authentication details and content specifications. Each parameter should be on a new line. For example: Content-Type:application/json;charset=UTF-8', 'wp-sms'),
                'type' => 'textarea',
            ],
            'http_parameters' => [
                'id'   => 'gateway_http_parameters',
                'name' => esc_html__('HTTP Parameters', 'wp-sms'),
                'desc' => esc_html__('List the parameters required by the API for sending SMS messages. These often include fields like the sender, recipient, and message content. Replace {from}, {to}, and {message} with actual values when making a request. Each parameter should be on a new line. For example: receptor:{to}', 'wp-sms'),
                'type' => 'textarea',
            ],
            'is_post_body'    => [
                'id'      => 'gateway_is_post_body',
                'name'    => 'Send As POST',
                'desc'    => esc_html__("Choose 'Yes' to send the HTTP parameters as body data in a POST request. Select 'No' if parameters are sent directly in the URL.", 'wp-sms'),
                'type'    => 'select',
                'options' => [
                    'no'  => 'No',
                    'yes' => 'Yes',
                ]
            ],
            'encode_message'  => [
                'id'      => 'gateway_encode_message',
                'name'    => 'Encode Message',
                'desc'    => esc_html__("Select 'Yes' to encode the SMS message content to ensure compatibility with the gateway. Encoding may be necessary for special characters or binary data", 'wp-sms'),
                'type'    => 'select',
                'options' => [
                    'no'  => 'No',
                    'yes' => 'Yes',
                ]
            ],
            'from'            => [
                'id'   => 'gateway_sender_name',
                'name' => esc_html__('Sender Name', 'wp-sms'),
                'desc' => esc_html__('Sender Name', 'wp-sms'),
            ],
        ];
    }

    public function SendSMS()
    {
        /**
         * Modify sender id
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        try {
            $headers = [];
            if ($this->http_headers) {
                // Convert the single string to an array based on newline separation
                $lines = explode("\n", $this->http_headers);
                foreach ($lines as $line) {
                    // Split each line into key and value
                    list($key, $value) = explode(':', $line, 2);
                    $headers[trim($key)] = trim($value);
                }
            }

            $definedParams = [];
            if ($this->http_parameters) {
                // Convert the single string to an array based on newline separation
                $lines = explode("\n", $this->http_parameters);
                foreach ($lines as $line) {
                    // Split each line into key and the placeholder value
                    list($key, $value) = explode(':', $line, 2);
                    $definedParams[trim($key)] = trim($value);
                }
            }

            if ($this->encode_message && $this->encode_message == 'yes') {
                $this->msg = urlencode($this->msg);
            }

            // Replace placeholders with actual values
            $finalParams = [];
            foreach ($definedParams as $key => $value) {
                $finalParams[$key] = str_replace(['{from}', '{to}', '{message}'], [$this->from, implode(',', $this->to), $this->msg], $value);
            }

            $args = [
                'headers' => $headers
            ];

            $httpMethod = 'GET';
            $params     = [];

            if ($this->is_post_body and $this->is_post_body == 'yes') {
                $args['body'] = wp_json_encode($finalParams);
                $httpMethod   = 'POST';
            } else {
                $params = $finalParams;
            }

            $response = $this->request($httpMethod, $this->api_url, $params, $args);

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response);

            /*
             * Run hook after send sms.
             *
             */
            do_action('wp_sms_send', $response);

            return $response;

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');
            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        try {
            // Check Api key
            if (!$this->api_url) {
                throw new Exception(esc_html__('Please complete the send SMS API URL.', 'wp-sms-pro'));
            }

            return 'Unable to check balance!';

        } catch (\Throwable $e) {
            return new \WP_Error('get-credit', $e->getMessage());
        }
    }
}

