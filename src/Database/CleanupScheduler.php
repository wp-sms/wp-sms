<?php

namespace WSms\Database;

use WSms\Audit\AuditLogger;
use WSms\Auth\AccountManager;
use WSms\Auth\SettingsRepository;
use WSms\Flow\Storage\FlowExecutionRepository;
use WSms\Support\UserMeta;
use WSms\Log\MessageLogger;
use WSms\Verification\VerificationRepository;

defined('ABSPATH') || exit;

class CleanupScheduler
{
    public const HOOK_NAME = 'wsms_daily_cleanup';
    public const AS_GROUP = 'wsms';

    public function __construct(
        private AuditLogger $auditLogger,
        private FlowExecutionRepository $flowExecutionRepo,
        private MessageLogger $messageLogger,
        private VerificationRepository $verificationRepo,
        private SettingsRepository $settingsRepo,
    ) {}

    public function schedule(): void
    {
        if (!as_has_scheduled_action(self::HOOK_NAME, [], self::AS_GROUP)) {
            as_schedule_recurring_action(time(), DAY_IN_SECONDS, self::HOOK_NAME, [], self::AS_GROUP);
        }
    }

    public function unschedule(): void
    {
        as_unschedule_all_actions(self::HOOK_NAME, [], self::AS_GROUP);
    }

    public function run(): void
    {
        $authSettings = $this->settingsRepo->all();

        $this->cleanExpiredVerifications();
        $this->cleanOldAuditLogs($authSettings);
        $this->cleanExpiredPendingUsers($authSettings);
        $this->cleanExpiredFlowWaits();

        $messagingSettings = get_option('wsms_messaging_settings', []);
        $retentionDays = (int) ($messagingSettings['message_log_retention_days'] ?? 90);
        $this->cleanOldFlowExecutions($retentionDays);
        $this->cleanOldMessageLogs($retentionDays);
        $this->cleanOldExportFiles();
    }

    private function cleanExpiredVerifications(): void
    {
        $this->verificationRepo->deleteExpired();
    }

    private function cleanOldAuditLogs(array $settings): void
    {
        $this->auditLogger->deleteOlderThan($settings['log_retention_days']);
    }

    private function cleanExpiredPendingUsers(array $settings): void
    {
        if (empty($settings['pending_user_cleanup_enabled'])) {
            return;
        }

        $ttlHours = (int) $settings['pending_user_ttl_hours'];
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

    private function cleanOldFlowExecutions(int $days): void
    {
        $this->flowExecutionRepo->deleteCompletedOlderThan($days);
    }

    private function cleanOldMessageLogs(int $days): void
    {
        $this->messageLogger->deleteOlderThan($days);
    }

    private function cleanOldExportFiles(): void
    {
        $uploadDir = wp_upload_dir();
        $exportDir = $uploadDir['basedir'] . '/wsms-exports';

        if (!is_dir($exportDir)) {
            return;
        }

        $maxAge = 3600; // 1 hour
        $now = time();

        foreach (glob($exportDir . '/*.csv') ?: [] as $file) {
            if (($now - filemtime($file)) > $maxAge) {
                wp_delete_file($file);
            }
        }
    }
}
