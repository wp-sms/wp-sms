<?php

namespace WP_SMS\BackgroundProcess\Queues;

use Exception;
use WP_SMS\Library\BackgroundProcessing\WP_Background_Process;
use WP_SMS\Utils\Logger;
use WP_SMS\Utils\RemoteRequest;

class RemoteRequestQueue extends WP_Background_Process
{
    /**
     * @var string
     */
    protected $prefix = 'wp_sms';

    /**
     * @var string
     */
    protected $action = 'remote_request_background_process';

    /**
     * Perform task with queued item.
     *
     * Override this method to perform any actions required on each
     * queue item. Return the modified item for further processing
     * in the next pass through. Or, return false to remove the
     * item from the queue.
     *
     * @param mixed $item Queue item to iterate over.
     *
     * @return mixed
     */
    protected function task($item)
    {
        try {

            /** @var RemoteRequest $request */
            $request = $item['request'];

            $response = $request->execute();

            // log the response
            Logger::logOutbox($item['from'], $item['msg'], $item['to'], $response);

            /**
             * Run hook after send sms.
             *
             * @param string $response result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response);

        } catch (Exception $e) {
            Logger::logOutbox($item['from'], $item['msg'], $item['to'], $e->getMessage(), 'error');
        }

        return false;
    }

    /**
     * Complete processing.
     *
     * Override if applicable, but ensure that the below actions are
     * performed, or, call parent::complete().
     */
    protected function complete()
    {
        parent::complete();

        // Show notice to user or perform some other arbitrary task...
    }
}