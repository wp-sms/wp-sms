<?php

namespace WP_SMS\Services;

use WP_SMS\Components\Event;
use WP_SMS\Option;
use WP_SMS\Components\DateTime;
use WP_SMS\Admin\Notification\NotificationFetcher;
use WP_SMS\Admin\SmsStorage\SmsStorageCleaner;

class CronEventManager
{
    /**
     * CronEventManager constructor.
     */
    public function __construct()
    {
        Event::schedule('wp_sms_daily_cron_hook', time(), 'daily', [$this, 'handleDailyTasks']);
        Event::schedule('wp_sms_midnight_cron_hook', DateTime::get('tomorrow midnight', 'U'), 'daily', [$this, 'handleMidnightTasks']);
    }

    /**
     * Handle daily tasks triggered by the scheduled cron event.
     */
    public function handleDailyTasks()
    {
        if (Option::getOption('display_notifications')) {
            $this->fetchNotification();
        }
    }

    /**
     * Fetches new notifications.
     *
     * This method is triggered by the scheduled cron event
     * and retrieves new notifications.
     */
    private function fetchNotification()
    {
        $notificationFetcher = new NotificationFetcher();
        $notificationFetcher->fetchNotification();
    }

    /**
     * Handle midnight tasks triggered by the scheduled cron event.
     */
    public function handleMidnightTasks()
    {
        $this->runSmsStorageCleanup();
    }

    /**
     * Run SMS storage cleanup.
     */
    private function runSmsStorageCleanup()
    {
        $smsStorageCleaner = new SmsStorageCleaner();
        $smsStorageCleaner->cleanAll();
    }
}