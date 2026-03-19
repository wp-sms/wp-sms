<?php

namespace WSms\Database;

use WSms\Audit\AuditLogger;
use WSms\Auth\AccountManager;
use WSms\Flow\Storage\FlowExecutionRepository;
use WSms\Support\UserMeta;
use WSms\Log\MessageLogger;
use WSms\Verification\VerificationRepository;

defined('ABSPATH') || exit;

class CleanupScheduler
{
    public const HOOK_NAME = 'wsms_daily_cleanup';

    public function __construct(
        private AuditLogger $auditLogger,
        private FlowExecutionRepository $flowExecutionRepo,
        private MessageLogger $messageLogger,
        private VerificationRepository $verificationRepo,
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
        $authSettings = get_option('wsms_auth_settings', []);

        $this->cleanExpiredVerifications();
        $this->cleanOldAuditLogs($authSettings);
        $this->cleanExpiredPendingUsers($authSettings);
        $this->cleanExpiredFlowWaits();
        $this->cleanOldMessageLogs();
    }

    private function cleanExpiredVerifications(): void
    {
        $this->verificationRepo->deleteExpired();
    }

    private function cleanOldAuditLogs(array $settings): void
    {
        $days = $settings['log_retention_days'] ?? 30;
        $this->auditLogger->deleteOlderThan($days);
    }

    private function cleanExpiredPendingUsers(array $settings): void
    {

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
                ['key' => UserMeta::REGISTRATION_STATUS, 'value' => 'pending'],
                ['key' => UserMeta::REGISTRATION_CREATED_AT, 'value' => $cutoff, 'compare' => '<', 'type' => 'DATETIME'],
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

    private function cleanExpiredFlowWaits(): void
    {
        $this->flowExecutionRepo->cleanupExpiredWaits();
    }

    private function cleanOldMessageLogs(): void
    {
        $settings = get_option('wsms_messaging_settings', []);
        $days = (int) ($settings['message_log_retention_days'] ?? 90);
        $this->messageLogger->deleteOlderThan($days);
    }
}
