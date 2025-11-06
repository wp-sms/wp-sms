<?php

namespace WP_SMS\Services;

use WP_SMS\Components\Event;
use WP_SMS\Services\SmsStorage\SmsStorageCleaner;
use WP_SMS\Components\DateTime;

class CronEventManager
{
    /**
     * CronEventManager constructor.
     */
    public function __construct()
    {
        Event::schedule('wp_sms_daily_cron_hook', DateTime::get('tomorrow midnight', 'U'), 'daily', [$this, 'handleDailyTasks']);
    }

    /**
     * Handle daily tasks triggered by the scheduled cron event.
     */
    public function handleDailyTasks()
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