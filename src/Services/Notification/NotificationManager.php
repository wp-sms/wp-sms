<?php

namespace WP_SMS\Services\Notification;

use Exception;
use WP_SMS\Components\Event;
use WP_SMS\Option as Option;

class NotificationManager
{
    /**
     * NotificationManager constructor.
     *
     * Initializes hooks for AJAX callbacks, cron schedules,
     * and schedules the notification fetch event.
     */
    public function __construct()
    {
        if (Option::getOption('plugin_notifications')) {
            add_action('admin_init', [$this, 'registerActions']);
            Event::schedule('wp_sms_notification_hook', time(), 'daily', [$this, 'fetchNotification']);
        } else {
            Event::unschedule('wp_sms_notification_hook');
        }
    }

    /**
     * Fetches new notifications.
     *
     * This method is triggered by the scheduled cron event
     * and retrieves new notifications.
     * @throws Exception
     */
    public function fetchNotification()
    {
        $notificationFetcher = new NotificationFetcher();
        $notificationFetcher->fetchNotification();
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