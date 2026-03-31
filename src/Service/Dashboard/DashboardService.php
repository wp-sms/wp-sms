<?php

namespace WSms\Service\Dashboard;

use WSms\Auth\SettingsRepository;
use WSms\Database\Connection;
use WSms\Enums\EventType;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\System\SystemHealthService;

defined('ABSPATH') || exit;

class DashboardService
{
    private const CACHE_KEY = 'wsms_dashboard_%dd';
    private const CACHE_TTL_SECONDS = 300; // 5 minutes

    private const LOGIN_SUCCESS_EVENTS = [
        EventType::LoginSuccess,
        EventType::MagicLinkVerified,
        EventType::SocialLoginSuccess,
    ];

    private const LOGIN_FAILURE_EVENTS = [
        EventType::LoginFailure,
        EventType::SocialLoginFailure,
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly SystemHealthService $health,
        private readonly SettingsRepository $authSettings,
        private readonly GatewayRegistry $gatewayRegistry,
    ) {
    }

    public function getSummary(int $rangeDays = 30): array
    {
        $rangeDays = max(7, min(90, $rangeDays));

        $cacheKey = sprintf(self::CACHE_KEY, $rangeDays);
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $authEnabled = (bool) $this->authSettings->get('auth_enabled', false);
        $configured = $this->gatewayRegistry->getConfigured();
        $gatewaysConfigured = count($configured) > 0;
        $deliveryTrackingAvailable = $this->hasDeliveryTracking($configured);

        $messaging = $this->getMessagingStats($rangeDays, $deliveryTrackingAvailable);
        $channels = $this->getChannelBreakdown($rangeDays, $deliveryTrackingAvailable);
        $contacts = $this->getContactStats($rangeDays);
        $flows = $this->getFlowStats($rangeDays);
        $healthData = $this->health->getHealthData();
        $gateways = $this->buildGatewayStatus($configured, $healthData);
        $auth = $authEnabled ? $this->getAuthStats($rangeDays) : ['enabled' => false];
        $systemHealth = $this->buildSystemHealthSummary($healthData);

        $summary = [
            'range'         => $rangeDays,
            'feature_state' => [
                'auth_enabled'              => $authEnabled,
                'gateways_configured'       => $gatewaysConfigured,
                'delivery_tracking_available' => $deliveryTrackingAvailable,
                'has_contacts'              => $contacts['total'] > 0,
                'has_campaigns'             => $messaging['active_campaigns'] > 0 || $messaging['completed_campaigns'] > 0,
            ],
            'messaging'     => $messaging,
            'channels'      => $channels,
            'contacts'      => $contacts,
            'flows'         => $flows,
            'gateways'      => $gateways,
            'auth'          => $auth,
            'system_health' => $systemHealth,
            'generated_at'  => gmdate('c'),
        ];

        set_transient($cacheKey, $summary, self::CACHE_TTL_SECONDS);

        return $summary;
    }

    private function getMessagingStats(int $rangeDays, bool $deliveryTracking): array
    {
        $table = $this->db->table(Connection::TABLE_MESSAGE_LOGS);

        // Current period — count both 'sent' and 'delivered' separately
        $current = $this->db->getRow(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('sent', 'delivered') THEN 1 ELSE 0 END) AS messages_sent,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS messages_delivered,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS messages_failed,
                COALESCE(SUM(cost), 0) AS total_cost
            FROM {$table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $rangeDays,
        );

        // Previous period
        $previous = $this->db->getRow(
            "SELECT COUNT(*) AS prev_total
            FROM {$table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
              AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $rangeDays * 2,
            $rangeDays,
        );

        $total = (int) ($current['total'] ?? 0);
        $sent = (int) ($current['messages_sent'] ?? 0);
        $delivered = (int) ($current['messages_delivered'] ?? 0);
        $failed = (int) ($current['messages_failed'] ?? 0);
        $prevTotal = (int) ($previous['prev_total'] ?? 0);
        $totalCost = round((float) ($current['total_cost'] ?? 0), 2);

        // When delivery tracking is available: rate = delivered / (sent+delivered+failed)
        // When NOT available: rate = (sent+delivered) / (sent+delivered+failed) — 'sent' counts as success
        $rateNumerator = $deliveryTracking ? $delivered : $sent;
        $rateDenominator = $sent + $failed;
        $successRate = $rateDenominator > 0 ? round(($rateNumerator / $rateDenominator) * 100, 1) : null;

        // Campaign stats
        $campaigns = $this->getCampaignStats($rangeDays);

        return [
            'messages_sent'        => $total,
            'messages_sent_delta'  => self::computeDelta($total, $prevTotal),
            'success_rate'         => $successRate,
            'success_rate_insight'  => self::deliveryInsight($successRate),
            'success_rate_status'  => self::deliveryStatus($successRate),
            'messages_failed'      => $failed,
            'total_cost'           => $totalCost,
            'active_campaigns'     => $campaigns['active_count'],
            'completed_campaigns'  => $campaigns['completed_count'],
            'top_active_campaigns' => $campaigns['top_active'],
        ];
    }

    private function getChannelBreakdown(int $rangeDays, bool $deliveryTracking): array
    {
        $table = $this->db->table(Connection::TABLE_MESSAGE_LOGS);

        $rows = $this->db->getResults(
            "SELECT
                channel,
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('sent', 'delivered') THEN 1 ELSE 0 END) AS sent_or_delivered,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
                COALESCE(SUM(cost), 0) AS cost
            FROM {$table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY channel
            ORDER BY COUNT(*) DESC",
            $rangeDays,
        );

        return array_map(function (array $row) use ($deliveryTracking) {
            $total = (int) $row['total'];
            $sentOrDelivered = (int) $row['sent_or_delivered'];
            $delivered = (int) $row['delivered'];
            $failed = (int) $row['failed'];

            $rateNumerator = $deliveryTracking ? $delivered : $sentOrDelivered;
            $rateDenominator = $sentOrDelivered + $failed;

            return [
                'channel'      => $row['channel'],
                'sent'         => $total,
                'successful'   => $deliveryTracking ? $delivered : $sentOrDelivered,
                'failed'       => $failed,
                'success_rate' => $rateDenominator > 0 ? round(($rateNumerator / $rateDenominator) * 100, 1) : null,
                'cost'         => round((float) $row['cost'], 2),
            ];
        }, $rows);
    }

    private function getFlowStats(int $rangeDays): array
    {
        $flowTable = $this->db->table(Connection::TABLE_FLOWS);
        $execTable = $this->db->table(Connection::TABLE_FLOW_EXECUTIONS);

        // Flow counts by status
        $flowCounts = $this->db->getResults(
            "SELECT status, COUNT(*) AS count FROM {$flowTable} GROUP BY status",
        );

        $flows = ['active' => 0, 'paused' => 0, 'draft' => 0];
        foreach ($flowCounts as $row) {
            $flows[$row['status']] = (int) $row['count'];
        }

        // Recent execution stats
        $execCounts = $this->db->getResults(
            "SELECT status, COUNT(*) AS count
            FROM {$execTable}
            WHERE started_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY status",
            $rangeDays,
        );

        $executions = ['completed' => 0, 'failed' => 0, 'running' => 0];
        foreach ($execCounts as $row) {
            if (isset($executions[$row['status']])) {
                $executions[$row['status']] = (int) $row['count'];
            }
        }

        return [
            'active'               => $flows['active'],
            'paused'               => $flows['paused'],
            'draft'                => $flows['draft'],
            'executions_completed' => $executions['completed'],
            'executions_failed'    => $executions['failed'],
        ];
    }

    /**
     * @param \WSms\Messaging\Contracts\GatewayInterface[] $configured
     */
    private function hasDeliveryTracking(array $configured): bool
    {
        foreach ($configured as $gw) {
            if ($gw->getFeatures()['delivery_receipt'] ?? false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \WSms\Messaging\Contracts\GatewayInterface[] $configured
     */
    private function buildGatewayStatus(array $configured, array $healthData): array
    {
        $messagingChecks = $healthData['checks']['messaging'] ?? [];

        // Build a set of gateway IDs that have credential failures
        $unhealthyGateways = [];
        foreach ($messagingChecks as $check) {
            if ($check['status'] === 'fail' && str_contains($check['id'], 'gateway_credentials_')) {
                $gwId = str_replace('gateway_credentials_', '', $check['id']);
                $unhealthyGateways[$gwId] = true;
            }
        }

        $result = [];
        foreach ($configured as $gw) {
            $result[] = [
                'id'       => $gw->getId(),
                'name'     => $gw->getName(),
                'channels' => $gw->getSupportedChannels(),
                'healthy'  => !isset($unhealthyGateways[$gw->getId()]),
            ];
        }

        return $result;
    }

    private function getCampaignStats(int $rangeDays): array
    {
        $table = $this->db->table(Connection::TABLE_CAMPAIGNS);

        $counts = $this->db->getRow(
            "SELECT
                SUM(CASE WHEN status IN ('sending', 'scheduled', 'paused') THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN status = 'sent' AND completed_at >= DATE_SUB(NOW(), INTERVAL %d DAY) THEN 1 ELSE 0 END) AS completed_count
            FROM {$table}",
            $rangeDays,
        );

        $topActive = $this->db->getResults(
            "SELECT id, name, channel, status, total_recipients, sent_count, delivered_count, failed_count
            FROM {$table}
            WHERE status IN ('sending', 'scheduled', 'paused')
            ORDER BY started_at DESC, created_at DESC
            LIMIT 3",
        );

        return [
            'active_count'    => (int) ($counts['active_count'] ?? 0),
            'completed_count' => (int) ($counts['completed_count'] ?? 0),
            'top_active'      => $topActive,
        ];
    }

    private function getContactStats(int $rangeDays): array
    {
        $table = $this->db->table(Connection::TABLE_CONTACTS);

        $stats = $this->db->getRow(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'subscribed' THEN 1 ELSE 0 END) AS subscribed,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) THEN 1 ELSE 0 END) AS new_in_period,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                          AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY) THEN 1 ELSE 0 END) AS prev_new_in_period
            FROM {$table}",
            $rangeDays,
            $rangeDays * 2,
            $rangeDays,
        );

        $newInPeriod = (int) ($stats['new_in_period'] ?? 0);
        $prevNewInPeriod = (int) ($stats['prev_new_in_period'] ?? 0);

        return [
            'total'        => (int) ($stats['total'] ?? 0),
            'subscribed'   => (int) ($stats['subscribed'] ?? 0),
            'new_in_period' => $newInPeriod,
            'growth_delta' => self::computeDelta($newInPeriod, $prevNewInPeriod),
        ];
    }

    private function getAuthStats(int $rangeDays): array
    {
        $logTable = $this->db->table(Connection::TABLE_AUTH_LOGS);
        $usersTable = $this->db->wpdb()->users;
        $successIn = self::eventIn(self::LOGIN_SUCCESS_EVENTS);
        $failureIn = self::eventIn(self::LOGIN_FAILURE_EVENTS);

        $logins = $this->db->getRow(
            "SELECT
                SUM(CASE WHEN event IN ({$successIn}) THEN 1 ELSE 0 END) AS successful_logins,
                SUM(CASE WHEN event IN ({$failureIn}) THEN 1 ELSE 0 END) AS failed_logins,
                SUM(CASE WHEN event = %s THEN 1 ELSE 0 END) AS accounts_locked,
                SUM(CASE WHEN event = %s THEN 1 ELSE 0 END) AS accounts_suspended
            FROM {$logTable}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            EventType::AccountLocked->value,
            EventType::AccountSuspended->value,
            $rangeDays,
        );

        $registrations = (int) $this->db->getVar(
            "SELECT COUNT(*) FROM {$usersTable}
            WHERE user_registered >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $rangeDays,
        );

        $successfulLogins = (int) ($logins['successful_logins'] ?? 0);
        $failedLogins = (int) ($logins['failed_logins'] ?? 0);
        $totalLogins = $successfulLogins + $failedLogins;
        $successRate = $totalLogins > 0 ? round(($successfulLogins / $totalLogins) * 100, 1) : null;

        return [
            'enabled'              => true,
            'total_logins'         => $totalLogins,
            'login_success_rate'   => $successRate,
            'login_success_status' => self::loginSuccessStatus($successRate),
            'registrations'        => $registrations,
            'failed_logins'        => $failedLogins,
            'accounts_locked'      => (int) ($logins['accounts_locked'] ?? 0),
            'accounts_suspended'   => (int) ($logins['accounts_suspended'] ?? 0),
        ];
    }

    private function buildSystemHealthSummary(array $healthData): array
    {
        $overall = $healthData['overall_status'];

        $result = [
            'level'            => $overall['level'],
            'summary'          => $overall['summary'],
            'section_statuses' => $overall['section_statuses'],
            'top_issue'        => null,
        ];

        if ($overall['level'] !== 'healthy') {
            $result['top_issue'] = $this->findTopIssue($healthData['checks']);
        }

        return $result;
    }

    private function findTopIssue(array $checksBySection): ?array
    {
        $severityOrder = ['fail', 'warn'];

        foreach ($severityOrder as $targetStatus) {
            foreach ($checksBySection as $section => $checks) {
                foreach ($checks as $check) {
                    if ($check['status'] === $targetStatus) {
                        return [
                            'section'     => $section,
                            'label'       => $check['label'],
                            'status'      => $check['status'],
                            'description' => $check['message'],
                        ];
                    }
                }
            }
        }

        return null;
    }

    private static function computeDelta(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private static function deliveryInsight(?float $rate): ?string
    {
        if ($rate === null) {
            return null;
        }

        return match (true) {
            $rate >= 95 => __('Excellent', 'wp-sms'),
            $rate >= 85 => __('Good', 'wp-sms'),
            $rate >= 70 => __('Below target', 'wp-sms'),
            default     => __('Investigate', 'wp-sms'),
        };
    }

    private static function deliveryStatus(?float $rate): ?string
    {
        if ($rate === null) {
            return null;
        }

        return match (true) {
            $rate >= 85 => 'success',
            $rate >= 70 => 'warning',
            default     => 'destructive',
        };
    }

    private static function loginSuccessStatus(?float $rate): ?string
    {
        if ($rate === null) {
            return null;
        }

        return match (true) {
            $rate >= 90 => 'success',
            $rate >= 70 => 'warning',
            default     => 'destructive',
        };
    }

    private static function eventIn(array $events): string
    {
        return implode(',', array_map(
            fn(EventType $e) => "'" . $e->value . "'",
            $events,
        ));
    }
}
