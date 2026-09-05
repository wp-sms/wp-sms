<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

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
    public $body_format;
    public $http_parameters;
    public $raw_payload;
    public $is_post_body;
    public $post_body_format;
    public $encode_message;
    public $documentUrl = 'https://wsms.io/docs/custom-gateway/';

    public function __construct()
    {
        parent::__construct();
        $this->help          = esc_html__('Use this gateway to integrate any HTTP-based SMS provider. Configure the endpoint and parameters below.', 'wp-sms');
        $this->gatewayFields = [
            'api_url'         => [
                'id'   => 'gateway_api_url',
                'name' => esc_html__('Send SMS API URL', 'wp-sms'),
                'desc' => esc_html__('Enter the Send SMS URL for the SMS gateway API where the SMS requests will be sent. This URL is provided by the SMS gateway service.', 'wp-sms'),
            ],
            'http_headers'    => [
                'id'         => 'gateway_http_headers',
                'name'       => esc_html__('HTTP Headers', 'wp-sms'),
                'desc'       => __('One <code>Header-Name: value</code> per line. Example: <code>Content-Type: application/json</code>, <code>Authorization: Bearer ***', 'wp-sms'),
                'type'       => 'textarea',
                'isPassword' => true,
            ],
            'body_format'     => [
                'id'      => 'gateway_body_format',
                'name'    => esc_html__('Body Format', 'wp-sms'),
                'desc'    => __('Choose how the request body is built. Use Key-Value for simple flat APIs, or Raw JSON for Postman-style payloads with nested objects or arrays.', 'wp-sms'),
                'type'    => 'select',
                'options' => [
                    'key_value' => 'Key-Value',
                    'raw_json'  => 'Raw JSON',
                ],
            ],
            'http_parameters' => [
                'id'        => 'gateway_http_parameters',
                'name'      => esc_html__('HTTP Parameters', 'wp-sms'),
                'desc'      => __('One <code>key:value</code> per line. Placeholders: <code>{from}</code>, <code>{to}</code>, <code>{message}</code>. Wrap a value in square brackets to send it as a JSON array. Example: <code>to:{to}</code>, <code>message:{message}</code>, <code>recipients:[{to}]</code>', 'wp-sms'),
                'type'      => 'textarea',
                'className' => 'js-wpsms-show_if_gateway_body_format_equal_key_value',
            ],
            'raw_payload'     => [
                'id'        => 'gateway_raw_payload',
                'name'      => esc_html__('Request Body (JSON)', 'wp-sms'),
                'desc'      => __('Paste the full JSON payload, just like in Postman. Placeholders <code>{from}</code>, <code>{to}</code>, <code>{message}</code>, <code>{recipients}</code> are replaced at send time. Any other keys are sent as-is, so you can add provider-specific fields (sender, route, callback URL, etc.). Example:<pre>{
  "body": "{message}",
  "originator": "{from}",
  "recipients": [{recipients}],
  "route": "business",
  "reference": "wsms-order-42"
}</pre>', 'wp-sms'),
                'type'      => 'textarea',
                'className' => 'js-wpsms-show_if_gateway_body_format_equal_raw_json',
            ],
            'is_post_body'    => [
                'id'        => 'gateway_is_post_body',
                'name'      => 'Send As POST',
                'desc'      => __('Send HTTP Parameters as a POST body using the selected encoding, or as URL query parameters.', 'wp-sms'),
                'type'      => 'select',
                'options'   => [
                    'no'  => 'No',
                    'yes' => 'Yes',
                ],
                'className' => 'js-wpsms-show_if_gateway_body_format_equal_key_value',
            ],
            'post_body_format' => [
                'id'        => 'gateway_post_body_format',
                'name'      => esc_html__('POST Body Encoding', 'wp-sms'),
                'desc'      => esc_html__('Choose how Key-Value parameters are encoded when Send As POST is enabled.', 'wp-sms'),
                'type'      => 'select',
                'options'   => [
                    'json'            => 'application/json',
                    'form_urlencoded' => 'application/x-www-form-urlencoded',
                ],
                'className' => 'js-wpsms-show_if_gateway_body_format_equal_key_value',
            ],
            'encode_message'  => [
                'id'      => 'gateway_encode_message',
                'name'    => 'Encode Message',
                'desc'    => __("Select 'Yes' to encode the SMS message content to ensure compatibility with the gateway. Encoding may be necessary for special characters or binary data", 'wp-sms'),
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
                    $line = trim($line);
                    if ($line === '' || strpos($line, ':') === false) {
                        continue;
                    }
                    list($key, $value) = explode(':', $line, 2);
                    $key = trim($key);
                    if ($key === '') {
                        continue;
                    }
                    $headers[$key] = trim($value);
                }
            }

            $isFormEncodedPost = $this->is_post_body === 'yes' && $this->post_body_format === 'form_urlencoded';

            if ($this->encode_message === 'yes' && !$isFormEncodedPost) {
                $this->msg = urlencode($this->msg);
            }

            $args       = ['headers' => $headers];
            $httpMethod = 'GET';
            $params     = [];

            if ($this->body_format === 'raw_json' && !empty($this->raw_payload)) {
                // Raw JSON mode: substitute placeholders inside the JSON payload (JSON-safe escaping) and POST as-is.
                $recipientsList = array_map(function ($number) {
                    return wp_json_encode((string) $number);
                }, (array) $this->to);

                $jsonSafeFrom = substr(wp_json_encode((string) $this->from), 1, -1);
                $jsonSafeTo   = substr(wp_json_encode(implode(',', (array) $this->to)), 1, -1);
                $jsonSafeMsg  = substr(wp_json_encode((string) $this->msg), 1, -1);

                $args['body'] = str_replace(
                    ['{from}', '{to}', '{message}', '{recipients}'],
                    [$jsonSafeFrom, $jsonSafeTo, $jsonSafeMsg, implode(',', $recipientsList)],
                    $this->raw_payload
                );
                $httpMethod = 'POST';
            } else {
                // Key-Value mode: parse `key:value` lines, replace placeholders, and send as query parameters or an encoded POST body.
                $definedParams = [];
                if ($this->http_parameters) {
                    $lines = explode("\n", $this->http_parameters);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '' || strpos($line, ':') === false) {
                            continue;
                        }
                        list($key, $value) = explode(':', $line, 2);
                        $key = trim($key);
                        if ($key === '') {
                            continue;
                        }
                        $definedParams[$key] = trim($value);
                    }
                }

                $finalParams = [];
                foreach ($definedParams as $key => $value) {
                    $replaced = str_replace(['{from}', '{to}', '{message}'], [$this->from, implode(',', (array) $this->to), $this->msg], $value);

                    // Values wrapped in [...] are sent as a JSON array (split by comma, trimmed).
                    if (is_string($replaced) && strlen($replaced) >= 2 && $replaced[0] === '[' && substr($replaced, -1) === ']') {
                        $inner             = substr($replaced, 1, -1);
                        $items             = array_values(array_filter(array_map('trim', explode(',', $inner)), 'strlen'));
                        $finalParams[$key] = $items;
                    } else {
                        $finalParams[$key] = $replaced;
                    }
                }

                if ($this->is_post_body && $this->is_post_body == 'yes') {
                    if ($this->post_body_format === 'form_urlencoded') {
                        $args['body'] = http_build_query($finalParams, '', '&');

                        $hasContentType = false;
                        foreach (array_keys($args['headers']) as $headerName) {
                            if (strcasecmp($headerName, 'Content-Type') === 0) {
                                $hasContentType = true;
                                break;
                            }
                        }

                        if (!$hasContentType) {
                            $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
                        }
                    } else {
                        $args['body'] = wp_json_encode($finalParams);
                    }
                    $httpMethod = 'POST';
                } else {
                    $params = $finalParams;
                }
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
                throw new Exception(esc_html__('Please complete the send SMS API URL.', 'wp-sms'));
            }

            return 'Unable to check balance!';

        } catch (\Throwable $e) {
            return new \WP_Error('get-credit', $e->getMessage());
        }
    }
}

