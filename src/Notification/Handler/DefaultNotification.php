<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

class DefaultNotification extends Notification
{
    protected $variables = [
        '%site_name%'    => 'getSiteName',
        '%site_url%'     => 'getSiteUrl',
        '%site_version%' => 'getWordPressVersion',
    ];

    public function getSiteName()
    {
        return get_bloginfo('name');
    }

    public function getSiteUrl()
    {
        return get_bloginfo('url');
    }

    public function getWordPressVersion()
    {
        return get_bloginfo('version');
    }
}
