<?php

namespace WP_SMS\Components;

use Exception;
use WP_SMS\Helper;
use WP_SMS\Traits\TransientCacheTrait;

class RemoteRequest
{
    use TransientCacheTrait;

    public $requestUrl;
    private $parsedParams;
    private $method;
    private $response;
    private $responseCode;
    private $responseBody;

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
         * Prepare the arguments including the method
         */
        $this->parsedParams = wp_parse_args($params, [
            'timeout' => 10,
            'headers' => [],
            'method' => strtoupper($method)
        ]);

        if (defined('WP_SMS_API_USERNAME') && defined('WP_SMS_API_PASSWORD')) {
            if (empty($this->parsedParams['headers']['Authorization'])) {
                $this->parsedParams['headers']['Authorization'] =
                    'Basic ' . base64_encode(WP_SMS_API_USERNAME . ':' . WP_SMS_API_PASSWORD);
            }
        }

        /**
         * Store the method
         */
        $this->method = strtoupper($method);
    }

    /**
     * Returns request URL.
     *
     * @return string
     */
    public function getRequestUrl()
    {
        return $this->requestUrl;
    }

    /**
     * Returns parsed request arguments.
     *
     * @return array
     */
    public function getParsedArgs()
    {
        return $this->parsedParams;
    }

    /**
     * Generates a cache key based on the request URL and arguments.
     *
     * @return string
     */
    public function generateCacheKey()
    {
        return $this->getCacheKey($this->requestUrl . serialize($this->parsedParams));
    }

    /**
     * Checks if the given HTTP response code indicates a successful request.
     *
     * @return bool True if the response code indicates a successful request, false otherwise.
     */
    public function isRequestSuccessful()
    {
        return in_array($this->responseCode, [200, 201, 202]);
    }

    /**
     * Execute the request
     * @throws Exception
     */
    public function execute($throwFailedHttpCodeResponse = true, $useCache = true, $cacheExpiration = HOUR_IN_SECONDS)
    {
        // Generate the cache key
        $cacheKey = $this->generateCacheKey();

        // Check if cached result exists if caching is enabled
        if ($useCache) {
            $cachedResponse = $this->getCachedResult($cacheKey);
            if ($cachedResponse !== false) {
                return $cachedResponse;
            }
        }

        // Execute the request using wp_remote_request
        $this->response = wp_remote_request($this->requestUrl, $this->parsedParams);

        if (is_wp_error($this->response)) {
            throw new Exception(esc_html($this->response->get_error_message()));
        }

        $this->responseCode = wp_remote_retrieve_response_code($this->response);
        $this->responseBody = wp_remote_retrieve_body($this->response);

        if ($throwFailedHttpCodeResponse && !$this->isRequestSuccessful()) {
            $responseBody = $this->responseBody;
            if (Helper::isJson($responseBody)) {
                $responseBody = json_decode($responseBody, true);
            }

            // translators: %s: Response message
            throw new Exception(sprintf(esc_html__('Failed to get success response, %s', 'wp-sms'), esc_html(print_r($responseBody, 1))));
        }

        $responseJson = json_decode($this->responseBody);

        // Cache the result if caching is enabled
        $resultToCache = ($responseJson === null) ? $this->responseBody : $responseJson;
        if ($useCache) {
            if ($this->isRequestSuccessful() && (is_object($resultToCache) || is_array($resultToCache))) {
                $this->setCachedResult($cacheKey, $resultToCache, $cacheExpiration);
            }
        }

        return $resultToCache;
    }

    /**
     * Returns the response body from the executed request
     *
     * @return string|null The response body or null if no request has been executed
     */
    public function getResponseBody()
    {
        return $this->responseBody;
    }

    /**
     * Returns the HTTP response code from the last executed request
     *
     * @return int|null The HTTP response code or null if no request has been executed
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }

    /**
     * Retrieves the complete WordPress HTTP API response object
     *
     * @return array|null Complete response array or null if no request executed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Validate if response is a valid JSON array
     *
     * @return bool Returns true if valid JSON array, false otherwise
     */
    public function isValidJsonResponse()
    {
        if (
            !empty($this->responseBody) &&
            is_string($this->responseBody) &&
            is_array(json_decode($this->responseBody, true)) &&
            json_last_error() == 0
        ) {
            return true;
        }

        return false;
    }
}