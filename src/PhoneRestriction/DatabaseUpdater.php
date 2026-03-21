<?php

namespace WSms\PhoneRestriction;

defined('ABSPATH') || exit;

class DatabaseUpdater
{
    public const CRON_HOOK = 'wsms_phone_db_update';
    public const AS_GROUP  = 'wsms';

    private const CDN_URL  = 'https://cdn.jsdelivr.net/gh/wp-sms/phone-metadata@master/phone-metadata.json';
    private const TIMEOUT  = 30;
    private const MAX_SIZE = 2 * 1024 * 1024; // 2MB safety limit

    public function __construct(
        private readonly RestrictionSettings $settings,
    ) {
    }

    /**
     * Download the enhanced phone database from CDN and write to uploads dir.
     *
     * @return array{success: bool, message: string}
     */
    public function download(): array
    {
        $response = wp_remote_get(self::CDN_URL, ['timeout' => self::TIMEOUT]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $statusCode = wp_remote_retrieve_response_code($response);

        if ($statusCode !== 200) {
            return ['success' => false, 'message' => "Server returned HTTP {$statusCode}."];
        }

        $body = wp_remote_retrieve_body($response);

        if (strlen($body) > self::MAX_SIZE) {
            return ['success' => false, 'message' => 'Response exceeds size limit.'];
        }

        $data = json_decode($body, true);

        if (!is_array($data)) {
            return ['success' => false, 'message' => 'Invalid response from server.'];
        }

        if (empty($data['territories']) || !is_array($data['territories'])) {
            return ['success' => false, 'message' => 'Invalid database format.'];
        }

        $finalPath = EnhancedPhoneDatabase::getFilePath();
        $dir       = dirname($finalPath);

        if (!wp_mkdir_p($dir)) {
            return ['success' => false, 'message' => 'Could not create storage directory.'];
        }

        $tmpPath = $finalPath . '.tmp.' . uniqid();

        try {
            $written = file_put_contents($tmpPath, $body);

            if ($written === false) {
                return ['success' => false, 'message' => 'Could not write database file.'];
            }

            if (!rename($tmpPath, $finalPath)) {
                return ['success' => false, 'message' => 'Could not finalize database file.'];
            }
        } finally {
            @unlink($tmpPath);
        }

        EnhancedPhoneDatabase::resetCache();

        return ['success' => true, 'message' => 'Database downloaded successfully.'];
    }

    /**
     * Ensure cron schedule matches current settings.
     * Schedule weekly via Action Scheduler if auto-update is enabled; unschedule if disabled.
     */
    public function syncCronSchedule(): void
    {
        if (!function_exists('as_has_scheduled_action')) {
            return;
        }

        if ($this->settings->isAutoUpdateEnabled()) {
            if (!as_has_scheduled_action(self::CRON_HOOK, [], self::AS_GROUP)) {
                as_schedule_recurring_action(time(), WEEK_IN_SECONDS, self::CRON_HOOK, [], self::AS_GROUP);
            }
        } else {
            $this->unschedule();
        }
    }

    /**
     * Remove all scheduled actions for the phone DB update.
     */
    public function unschedule(): void
    {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::CRON_HOOK, [], self::AS_GROUP);
        }
    }

    /**
     * Handle the cron callback. Only downloads if auto-update is still enabled.
     */
    public function handleCron(): void
    {
        if (!$this->settings->isAutoUpdateEnabled()) {
            return;
        }

        $this->download();
    }
}
