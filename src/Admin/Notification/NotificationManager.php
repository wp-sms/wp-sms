<?php

namespace WP_SMS\Admin\Notification;

use WP_SMS\Option;

class NotificationManager
{
    /**
     * NotificationManager constructor.
     */
    public function __construct()
    {
        if (Option::getOption('display_notifications')) {
            add_action('admin_init', [$this, 'registerActions']);
        }
    }

    /**
     * Registers notification actions.
     *
     * @return void
     */
    public function registerActions()
    {
        $notificationActions = new NotificationActions();

        $notificationActions->register();
    }
}