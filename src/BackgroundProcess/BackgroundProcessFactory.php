<?php

namespace WP_SMS\BackgroundProcess;

use WP_SMS\BackgroundProcess\Async\RemoteRequestAsync;
use WP_SMS\BackgroundProcess\Queues\RemoteRequestQueue;

class BackgroundProcessFactory
{
    /**
     * @return RemoteRequestAsync
     */
    public static function remoteRequestAsync()
    {
        return new RemoteRequestAsync();
    }

    /**
     * @return RemoteRequestQueue
     */
    public static function remoteRequestQueue()
    {
        return new RemoteRequestQueue();
    }
}