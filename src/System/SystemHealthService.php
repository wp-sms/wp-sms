<?php

namespace WSms\System;

use WSms\Database\Connection;
use WSms\Exception\NotFoundException;

defined('ABSPATH') || exit;

class SystemHealthService
{
    private const CACHE_KEY = 'wsms_system_health';
    private const CACHE_TTL = 30;

    private const RECURRING_TASKS = [
        'wsms_check_scheduled_campaigns' => ['label' => 'Campaign Scheduler', 'interval' => 60,     'tolerance' => 3],
        'wsms_daily_cleanup'             => ['label' => 'Daily Cleanup',      'interval' => 86400,  'tolerance' => 1.5],
        'wsms_phone_db_update'           => ['label' => 'Phone DB Update',    'interval' => 604800, 'tolerance' => 1.5],
        'wsms_suppression_poll'          => ['label' => 'Suppression Sync',   'interval' => 3600,   'tolerance' => 2],
    ];

    /**
     * Translate task labels (cannot use __() inside class constants).
     */
    private static function getTranslatedTaskLabels(): array
    {
        return [
            'Campaign Scheduler' => __('Campaign Scheduler', 'wp-sms'),
            'Daily Cleanup'      => __('Daily Cleanup', 'wp-sms'),
            'Phone DB Update'    => __('Phone DB Update', 'wp-sms'),
            'Suppression Sync'   => __('Suppression Sync', 'wp-sms'),
        ];
    }

    private const HIGH_SEVERITY_TYPES = ['send_message', 'execute_flow_step', 'evaluate_trigger'];

    public function __construct(private readonly Connection $db)
    {
    }

    public function getHealthData(): array
    {
        $cached = get_transient(self::CACHE_KEY);

        if ($cached !== false) {
            return $cached;
        }

        if (!class_exists(\ActionScheduler::class) || !\ActionScheduler::is_initialized()) {
            return [
                'cron_health'      => ['wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON, 'as_runner_active' => false, 'last_as_run' => null, 'status' => 'unknown'],
                'heartbeat'        => [],
                'queue'            => ['totals' => [], 'by_type' => [], 'stuck_jobs' => []],
                'failed_jobs'      => ['items' => [], 'total' => 0, 'error_groups' => []],
                'recent_activity'  => [],
                'active_campaigns' => $this->buildActiveCampaigns(),
                'generated_at'     => gmdate('c'),
            ];
        }

        $data = [
            'cron_health'      => $this->detectCronHealth(),
            'heartbeat'        => $this->buildHeartbeat(),
            'queue'            => $this->buildQueueOverview(),
            'failed_jobs'      => $this->buildFailedJobs(50),
            'recent_activity'  => $this->buildRecentActivity(30),
            'active_campaigns' => $this->buildActiveCampaigns(),
            'generated_at'     => gmdate('c'),
        ];

        set_transient(self::CACHE_KEY, $data, self::CACHE_TTL);

        return $data;
    }

    public function retryFailedJob(int $actionId): void
    {
        $this->requireActionScheduler();

        $action = $this->getFailedAction($actionId);

        as_enqueue_async_action($action->get_hook(), $action->get_args(), 'wsms');
        \ActionScheduler::store()->delete_action($actionId);

        delete_transient(self::CACHE_KEY);
    }

    public function dismissFailedJob(int $actionId): void
    {
        $this->requireActionScheduler();

        $this->getFailedAction($actionId);

        \ActionScheduler::store()->delete_action($actionId);

        delete_transient(self::CACHE_KEY);
    }

    // ── Section builders ──────────────────────────────────────────────

    private function detectCronHealth(): array
    {
        $wpCronDisabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $actionsTable   = $this->asTable('actions');
        $groupsTable    = $this->asTable('groups');
        $complete       = \ActionScheduler_Store::STATUS_COMPLETE;

        $lastAsRun = $this->db->getVar(
            "SELECT MAX(a.last_attempt_gmt)
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms' AND a.status = '{$complete}'",
        );

        $asRunnerActive = false;
        if ($lastAsRun) {
            $elapsed        = time() - strtotime($lastAsRun . ' UTC');
            $asRunnerActive = $elapsed < 600; // 10 minutes
        }

        if ($asRunnerActive) {
            $status = 'healthy';
        } elseif ($wpCronDisabled) {
            $status = 'error';
        } elseif ($lastAsRun === null) {
            $status = 'unknown';
        } else {
            $status = 'warning';
        }

        return [
            'wp_cron_disabled' => $wpCronDisabled,
            'as_runner_active' => $asRunnerActive,
            'last_as_run'      => $lastAsRun,
            'status'           => $status,
        ];
    }

    private function buildHeartbeat(): array
    {
        $actionsTable = $this->asTable('actions');
        $groupsTable  = $this->asTable('groups');
        $complete     = \ActionScheduler_Store::STATUS_COMPLETE;
        $pending      = \ActionScheduler_Store::STATUS_PENDING;

        $allHooks = array_keys(self::RECURRING_TASKS);
        $hookPlaceholders = implode(',', array_fill(0, count($allHooks), '%s'));

        $rows = $this->db->getResults(
            "SELECT a.hook,
                    MAX(CASE WHEN a.status = '{$complete}' THEN a.last_attempt_gmt END) AS last_run,
                    MIN(CASE WHEN a.status = '{$pending}' THEN a.scheduled_date_gmt END) AS next_run
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms'
               AND a.status IN ('{$complete}', '{$pending}')
               AND a.hook IN ({$hookPlaceholders})
             GROUP BY a.hook",
            ...$allHooks,
        );

        $lastRunMap = [];
        $nextRunMap = [];
        foreach ($rows as $row) {
            if ($row['last_run']) $lastRunMap[$row['hook']] = $row['last_run'];
            if ($row['next_run']) $nextRunMap[$row['hook']] = $row['next_run'];
        }

        $translatedLabels = self::getTranslatedTaskLabels();

        $heartbeat = [];
        foreach (self::RECURRING_TASKS as $hook => $meta) {
            $lastRun = $lastRunMap[$hook] ?? null;
            $nextRun = $nextRunMap[$hook] ?? null;

            $status = $this->resolveTaskStatus($lastRun, $nextRun, $meta['interval'], $meta['tolerance']);

            $heartbeat[] = [
                'hook'     => $hook,
                'label'    => $translatedLabels[$meta['label']] ?? $meta['label'],
                'status'   => $status,
                'last_run' => $lastRun,
                'next_run' => $nextRun,
                'interval' => $meta['interval'],
            ];
        }

        // Detect dynamic flow hooks
        $flowHooks = $this->db->getResults(
            "SELECT a.hook, MAX(a.last_attempt_gmt) AS last_run,
                    MIN(CASE WHEN a.status = '{$pending}' THEN a.scheduled_date_gmt END) AS next_run
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms'
               AND a.hook LIKE 'wsms\\_schedule\\_flow\\_%'
               AND a.status IN ('{$complete}', '{$pending}')
             GROUP BY a.hook",
        );

        foreach ($flowHooks as $row) {
            $status = $row['next_run'] ? 'healthy' : ($row['last_run'] ? 'stopped' : 'unknown');

            $heartbeat[] = [
                'hook'     => $row['hook'],
                'label'    => 'Flow: ' . str_replace('wsms_schedule_flow_', '', $row['hook']),
                'status'   => $status,
                'last_run' => $row['last_run'],
                'next_run' => $row['next_run'],
                'interval' => 0,
            ];
        }

        return $heartbeat;
    }

    private function buildQueueOverview(): array
    {
        $actionsTable = $this->asTable('actions');
        $groupsTable  = $this->asTable('groups');
        $pending      = \ActionScheduler_Store::STATUS_PENDING;
        $running      = \ActionScheduler_Store::STATUS_RUNNING;

        // Totals by status
        $statusCounts = $this->db->getResults(
            "SELECT a.status, COUNT(*) AS count
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms' AND a.hook = 'wsms_process_job'
             GROUP BY a.status",
        );

        $totals = [];
        foreach ($statusCounts as $row) {
            $totals[$row['status']] = (int) $row['count'];
        }

        // By-type breakdown for pending/in-progress
        $byType = $this->db->getResults(
            "SELECT
                JSON_UNQUOTE(JSON_EXTRACT(a.args, '$[0].type')) AS job_type,
                SUM(CASE WHEN a.status = '{$pending}' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN a.status = '{$running}' THEN 1 ELSE 0 END) AS in_progress
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms'
               AND a.hook = 'wsms_process_job'
               AND a.status IN ('{$pending}', '{$running}')
             GROUP BY job_type",
        );

        $byTypeResult = [];
        foreach ($byType as $row) {
            $byTypeResult[] = [
                'type'        => $row['job_type'] ?? 'unknown',
                'pending'     => (int) $row['pending'],
                'in_progress' => (int) $row['in_progress'],
            ];
        }

        // Stuck detection: in-progress for more than 5 minutes
        $stuckJobs = $this->db->getResults(
            "SELECT a.action_id, a.last_attempt_gmt AS stuck_since,
                    JSON_UNQUOTE(JSON_EXTRACT(a.args, '$[0].type')) AS job_type
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms'
               AND a.hook = 'wsms_process_job'
               AND a.status = '{$running}'
               AND a.last_attempt_gmt < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)",
        );

        $stuckResult = [];
        foreach ($stuckJobs as $row) {
            $stuckResult[] = [
                'action_id'   => (int) $row['action_id'],
                'job_type'    => $row['job_type'] ?? 'unknown',
                'stuck_since' => $row['stuck_since'],
            ];
        }

        return [
            'totals'     => $totals,
            'by_type'    => $byTypeResult,
            'stuck_jobs' => $stuckResult,
        ];
    }

    private function buildFailedJobs(int $limit): array
    {
        $actionsTable = $this->asTable('actions');
        $groupsTable  = $this->asTable('groups');
        $logsTable    = $this->asTable('logs');
        $failed       = \ActionScheduler_Store::STATUS_FAILED;

        // Failed actions with last log message
        $items = $this->db->getResults(
            "SELECT
                a.action_id,
                a.hook,
                a.args,
                a.last_attempt_gmt AS failed_at,
                a.attempts,
                (SELECT l.message
                 FROM {$logsTable} l
                 WHERE l.action_id = a.action_id
                 ORDER BY l.log_id DESC
                 LIMIT 1) AS error_message
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms' AND a.status = '{$failed}'
             ORDER BY a.last_attempt_gmt DESC
             LIMIT %d",
            $limit,
        );

        $total = (int) $this->db->getVar(
            "SELECT COUNT(*)
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms' AND a.status = '{$failed}'",
        );

        $result = [];
        foreach ($items as $row) {
            $jobType  = self::extractJobType($row['args'], $row['hook']);
            $severity = in_array($jobType, self::HIGH_SEVERITY_TYPES, true) ? 'high' : 'low';
            $args     = json_decode($row['args'], true) ?: [];

            $argsPreview = [];
            if (isset($args[0]) && is_array($args[0])) {
                foreach (['recipient', 'campaign_id', 'flow_id', 'contact_id'] as $key) {
                    if (isset($args[0][$key])) {
                        $argsPreview[$key] = (string) $args[0][$key];
                    }
                }
            }

            $result[] = [
                'action_id'     => (int) $row['action_id'],
                'job_type'      => $jobType,
                'severity'      => $severity,
                'error_message' => $row['error_message'] ?? '',
                'failed_at'     => $row['failed_at'],
                'attempts'      => (int) $row['attempts'],
                'args_preview'  => $argsPreview,
            ];
        }

        // Error grouping
        $errorGroups = $this->db->getResults(
            "SELECT
                (SELECT l2.message
                 FROM {$logsTable} l2
                 WHERE l2.action_id = a.action_id
                 ORDER BY l2.log_id DESC
                 LIMIT 1) AS message,
                COUNT(*) AS count
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms' AND a.status = '{$failed}'
             GROUP BY message
             ORDER BY count DESC
             LIMIT 10",
        );

        $groups = [];
        foreach ($errorGroups as $row) {
            if ($row['message']) {
                $groups[] = ['message' => $row['message'], 'count' => (int) $row['count']];
            }
        }

        return [
            'items'        => $result,
            'total'        => $total,
            'error_groups' => $groups,
        ];
    }

    private function buildRecentActivity(int $limit): array
    {
        $actionsTable = $this->asTable('actions');
        $groupsTable  = $this->asTable('groups');
        $complete     = \ActionScheduler_Store::STATUS_COMPLETE;
        $failed       = \ActionScheduler_Store::STATUS_FAILED;

        $rows = $this->db->getResults(
            "SELECT a.action_id, a.hook, a.args, a.status, a.last_attempt_gmt AS completed_at
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms' AND a.status IN ('{$complete}', '{$failed}')
             ORDER BY a.last_attempt_gmt DESC
             LIMIT %d",
            $limit,
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'action_id'    => (int) $row['action_id'],
                'hook'         => $row['hook'],
                'job_type'     => self::extractJobType($row['args'], $row['hook']),
                'status'       => $row['status'],
                'completed_at' => $row['completed_at'],
            ];
        }

        return $result;
    }

    private function buildActiveCampaigns(): array
    {
        $table = $this->db->table(Connection::TABLE_CAMPAIGNS);

        $rows = $this->db->getResults(
            "SELECT id, name, channel, status, total_recipients, sent_count, delivered_count, failed_count, started_at
             FROM {$table}
             WHERE status IN ('sending', 'paused')
             ORDER BY started_at DESC",
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id'               => $row['id'],
                'name'             => $row['name'],
                'channel'          => $row['channel'],
                'status'           => $row['status'],
                'total_recipients' => (int) $row['total_recipients'],
                'sent_count'       => (int) $row['sent_count'],
                'delivered_count'  => (int) $row['delivered_count'],
                'failed_count'     => (int) $row['failed_count'],
                'started_at'       => $row['started_at'],
            ];
        }

        return $result;
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function resolveTaskStatus(?string $lastRun, ?string $nextRun, int $interval, float $tolerance): string
    {
        if ($lastRun === null) {
            return $nextRun ? 'unknown' : 'stopped';
        }

        if ($nextRun === null) {
            return 'stopped';
        }

        $elapsed = time() - strtotime($lastRun . ' UTC');

        if ($elapsed > $interval * $tolerance) {
            return 'late';
        }

        return 'healthy';
    }

    private function getFailedAction(int $actionId): \ActionScheduler_Action
    {
        $store  = \ActionScheduler::store();
        $action = $store->fetch_action($actionId);

        if ($action instanceof \ActionScheduler_NullAction) {
            throw NotFoundException::entity('Action', (string) $actionId);
        }

        if ($action->get_group() !== 'wsms') {
            throw NotFoundException::entity('Action', (string) $actionId);
        }

        if ($store->get_status($actionId) !== \ActionScheduler_Store::STATUS_FAILED) {
            throw NotFoundException::entity('Failed action', (string) $actionId);
        }

        return $action;
    }

    private static function extractJobType(string $argsJson, string $hook): string
    {
        $args = json_decode($argsJson, true) ?: [];

        return $args[0]['type'] ?? $hook;
    }

    private function requireActionScheduler(): void
    {
        if (!class_exists(\ActionScheduler::class) || !\ActionScheduler::is_initialized()) {
            throw new \RuntimeException('Action Scheduler is not available.');
        }
    }

    private function asTable(string $name): string
    {
        return $this->db->wpdb()->prefix . 'actionscheduler_' . $name;
    }
}
