<?php

namespace WP_SMS\Utils;

use Exception;
use WP_SMS\Helper;

class RemoteRequest
{
    public $requestUrl;
    private $parsedParams;

    public function __construct($method, $url, $arguments = [], $params = [])
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
            'method' => $method
        ]);
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
        $response = wp_remote_request(
            $this->requestUrl,
            $this->parsedParams
        );

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);

        if ($throwFailedHttpCodeResponse) {
            if (in_array($responseCode, [200, 201, 202]) === false) {

                if (Helper::isJson($responseBody)) {
                    $responseBody = json_decode($responseBody, true);
                }

                throw new Exception(sprintf(__('Failed to get success response, %s', 'wp-sms'), print_r($responseBody, 1)));
            }
        }

        $responseJson = json_decode($responseBody);

        return ($responseJson == null) ? $responseBody : $responseJson;
    }
}