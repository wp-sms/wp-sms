<?php

namespace WP_SMS\BackgroundProcess\Async;

use Exception;
use WP_SMS\Library\BackgroundProcessing\WP_Async_Request;
use WP_SMS\Utils\Logger;
use WP_SMS\Utils\RemoteRequest;

class RemoteRequestAsync extends WP_Async_Request
{
    /**
     * @var string
     */
    protected $prefix = 'wp_sms';

    /**
     * @var string
     */
    protected $action = 'remote_async_request';

    /**
     * Handle a dispatched request.
     *
     * Override this method to perform any actions required
     * during the async request.
     */
    protected function handle()
    {
        try {

            // Get data from input
            $requestData = $_POST['requestData'];

            // Make remote request
            $request = new RemoteRequest(
                sanitize_text_field($requestData['method']),
                sanitize_url($requestData['url']),
                isset($requestData['arguments']) ? wp_sms_sanitize_array($requestData['arguments']) : [],
                isset($requestData['params']) ? wp_sms_sanitize_array($requestData['params']) : []
            );

            $response = $request->execute();

            // log the response
            Logger::logOutbox($_POST['from'], $_POST['msg'], $_POST['to'], $response);

            /**
             * Run hook after send sms.
             *
             * @param string $response result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response);

        } catch (Exception $e) {
            Logger::logOutbox($_POST['from'], $_POST['msg'], $_POST['to'], $e->getMessage(), 'error');
        }
    }
}