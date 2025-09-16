<?php

namespace WP_SMS\Admin\AnonymizedUsageData;

use Exception;
use WP_SMS\Components\RemoteRequest;

class AnonymizedUsageDataSender
{
    /**
     * API base URL for send anonymized usage data.
     *
     * @var string
     */
    private $apiUrl = 'https://connect.wp-sms-pro.com';

    /**
     * Sends anonymized usage data to the remote API.
     *
     * @param array $data
     *
     * @return bool
     */
    public function sendAnonymizedUsageData($data)
    {
        try {
            $pluginSlug = basename(dirname(WP_SMS_MAIN_FILE));
            $url        = $this->apiUrl . '/api/v1/data';
            $method     = 'POST';
            $params     = ['plugin_slug' => $pluginSlug];
            $args       = [
                'timeout'     => 45,
                'redirection' => 5,
                'headers'     => array(
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json; charset=utf-8',
                    'user-agent'   => $pluginSlug,
                ),
                'body'        => json_encode($data),
                'cookies'     => array(),
            ];

            $remoteRequest = new RemoteRequest($method, $url, $params, $args);

            $remoteRequest->execute(false, false);

            $responseCode = $remoteRequest->getResponseCode();
            $responseBody = $remoteRequest->getResponseBody();

            // Check status code
            if (!in_array($responseCode, [200, 201], true)) {
                return false;
            }

            // Check if response is valid JSON
            $decoded = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                WPSms()->log('Unexpected response format: ' . substr($responseBody, 0, 300), 'error');
                return false;
            }

            // Check a specific "success" field in response JSON
            if (isset($decoded['status']) && $decoded['status'] !== 'success') {
                WPSms()->log('API returned failure status: ' . $responseBody, 'error');
                return false;
            }

            return true;
        } catch (Exception $e) {
            WPSms()->log($e->getMessage(), 'error');
            return false;
        }
    }
}