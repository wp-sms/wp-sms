<?php

namespace WP_SMS\Components;

use Exception;
use WP_SMS\Helper;

class RemoteRequest
{
    public $requestUrl;
    private $parsedParams;
    private $method;

    public function __construct($url, $method, $arguments = [], $params = [])
    {
        /**
         * Filter to modify arguments
         */
        $arguments = apply_filters('wp_sms_request_arguments', $arguments);

        /**
         * Build request URL
         */
        $this->requestUrl = add_query_arg($arguments, $url);

        /**
         * Filter to modify params
         */
        $params = apply_filters('wp_sms_request_params', $params);

        /**
         * Prepare the arguments
         */
        $this->parsedParams = wp_parse_args($params, [
            'timeout' => 10,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('admin' . ':' . 'subzero')
            )]);

        /**
         * Store the method
         */
        $this->method = strtoupper($method);
    }

    public function getRequestUrl()
    {
        return $this->requestUrl;
    }

    public function getParsedParams()
    {
        return $this->parsedParams;
    }

    /**
     * Execute the request
     * @throws Exception
     */
    public function execute($throwFailedHttpCodeResponse = true)
    {
        $response = null;

        switch ($this->method) {
            case 'GET':
                $response = wp_remote_get($this->requestUrl, $this->parsedParams);
                break;

            case 'POST':
                $response = wp_remote_post($this->requestUrl, $this->parsedParams);
                break;

            default:
                throw new Exception(esc_html(sprintf(__('Unsupported HTTP method: %s', 'wp-sms'), $this->method)));
        }

        if (is_wp_error($response)) {
            throw new Exception(esc_html($response->get_error_message()));
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);

        if ($throwFailedHttpCodeResponse) {
            if (!in_array($responseCode, [200, 201, 202], true)) {
                if (Helper::isJson($responseBody)) {
                    $responseBody = json_decode($responseBody, true);
                }

                // translators: %s: Response message
                throw new Exception(sprintf(esc_html__('Failed to get success response, %s', 'wp-sms'), esc_html(print_r($responseBody, 1))));
            }
        }

        $responseJson = json_decode($responseBody);

        return ($responseJson === null) ? $responseBody : $responseJson;
    }
}
