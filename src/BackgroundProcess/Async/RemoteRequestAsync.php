<?php

namespace WP_SMS\BackgroundProcess\Async;

use WP_SMS\Components\Sms;
use WP_SMS\Library\BackgroundProcessing\WP_Async_Request;

/**
 * Class RemoteRequestAsync
 *
 * Represents a remote asynchronous request.
 */
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
        Sms::send(wp_sms_sanitize_array($_POST['parameters']));
    }
}