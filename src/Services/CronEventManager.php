<?php

namespace WP_SMS\Services;

use WP_SMS\Components\Event;
use WP_SMS\Option;
use WP_SMS\Components\DateTime;
use WP_SMS\Admin\MarketingCampaign\MarketingCampaignFetcher;
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
            $this->fetchMarketingCampaigns();
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
     * Fetches the marketing banner for the dashboard header, managed in Horizon under Marketing Campaigns.
     */
    private function fetchMarketingCampaigns()
    {
        $fetcher = new MarketingCampaignFetcher();
        $fetcher->fetchMarketingCampaigns();
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