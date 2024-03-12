<?php

namespace WP_SMS\BackgroundProcess\Queues;

use WP_SMS\Components\Sms;
use WP_SMS\Library\BackgroundProcessing\WP_Background_Process;

/**
 * Class RemoteRequestQueue
 *
 * This class extends the WP_Background_Process class and represents a queue for remote requests.
 * It is responsible for processing queued items and performing necessary actions on each item.
 */
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
        Sms::send($item['parameters']);

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