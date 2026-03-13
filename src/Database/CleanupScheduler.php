<?php

namespace WSms\Database;

use WSms\Audit\AuditLogger;
use WSms\Auth\AccountManager;

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
        $this->cleanExpiredPendingUsers();
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

    private function cleanExpiredPendingUsers(): void
    {
        $settings = get_option('wsms_auth_settings', []);

        if (empty($settings['pending_user_cleanup_enabled'] ?? true)) {
            return;
        }

        $ttlHours = (int) ($settings['pending_user_ttl_hours'] ?? AccountManager::DEFAULT_PENDING_USER_TTL_HOURS);
        if ($ttlHours <= 0) {
            return;
        }

        $cutoff = gmdate('Y-m-d H:i:s', time() - ($ttlHours * 3600));

        $users = get_users([
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'wsms_registration_status', 'value' => 'pending'],
                ['key' => 'wsms_registration_created_at', 'value' => $cutoff, 'compare' => '<', 'type' => 'DATETIME'],
            ],
            'number' => 100,
        ]);

        if (empty($users)) {
            return;
        }

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        foreach ($users as $user) {
            wp_delete_user($user->ID);
        }
    }
}
