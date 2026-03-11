<?php

namespace WSms\Database;

use WSms\Audit\AuditLogger;

defined('ABSPATH') || exit;

class CleanupScheduler
{
    public const HOOK_NAME = 'wsms_daily_cleanup';

    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    public function schedule(): void
    {
        if (!wp_next_scheduled(self::HOOK_NAME)) {
            wp_schedule_event(time(), 'daily', self::HOOK_NAME);
        }
    }

    public function unschedule(): void
    {
        wp_clear_scheduled_hook(self::HOOK_NAME);
    }

    public function run(): void
    {
        $this->cleanExpiredVerifications();
        $this->cleanOldAuditLogs();
    }

    private function cleanExpiredVerifications(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_verifications';
        $wpdb->query("DELETE FROM {$table} WHERE expires_at < NOW()");
    }

    private function cleanOldAuditLogs(): void
    {
        $settings = get_option('wsms_auth_settings', []);
        $days = $settings['log_retention_days'] ?? 90;
        $this->auditLogger->deleteOlderThan($days);
    }
}
