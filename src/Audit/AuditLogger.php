<?php

namespace WSms\Audit;

use WSms\Enums\EventType;
use WSms\Enums\LogVerbosity;
use WSms\Support\IpResolver;

defined('ABSPATH') || exit;

class AuditLogger
{
    private ?LogVerbosity $verbosity = null;

    /**
     * Log an authentication event.
     */
    public function log(
        EventType $event,
        string $status,
        ?int $userId = null,
        array $meta = [],
        ?string $channelId = null,
    ): void {
        global $wpdb;

        $verbosity = $this->getVerbosity();

        // Auto-extract channel_id from meta when not explicitly provided.
        if ($channelId === null && isset($meta['channel'])) {
            $channelId = $meta['channel'];
        }

        $data = [
            'event'   => $event->value,
            'status'  => $status,
            'user_id' => $userId,
        ];

        if ($channelId !== null) {
            $data['channel_id'] = $channelId;
        }

        if ($verbosity !== LogVerbosity::Minimal) {
            $data['ip_address'] = $this->getIpAddress();
            $data['user_agent'] = isset($_SERVER['HTTP_USER_AGENT'])
                ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 500)
                : null;
        }

        if ($verbosity === LogVerbosity::Verbose && !empty($meta)) {
            $data['meta'] = wp_json_encode($meta);
        }

        $data = apply_filters('wsms_audit_log_entry', $data, $event, $userId);

        if ($data === null) {
            return;
        }

        $wpdb->insert(
            $wpdb->prefix . 'wsms_auth_logs',
            $data,
        );

        do_action('wsms_audit_log_written', $data, $event, $userId);
    }

    /**
     * Get audit log events with optional filtering.
     *
     * @return array{items: array, total: int}
     */
    public function getEvents(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_auth_logs';
        [$whereClause, $values] = $this->buildWhereClause($filters);

        $countSql = "SELECT COUNT(*) FROM {$table} {$whereClause}";
        $total = !empty($values)
            ? (int) $wpdb->get_var($wpdb->prepare($countSql, ...$values))
            : (int) $wpdb->get_var($countSql);

        $offset = ($page - 1) * $perPage;
        $querySql = "SELECT * FROM {$table} {$whereClause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $queryValues = array_merge($values, [$perPage, $offset]);

        $items = $wpdb->get_results($wpdb->prepare($querySql, ...$queryValues));

        return [
            'items' => $items ?: [],
            'total' => $total,
        ];
    }

    /**
     * Delete log entries matching the given filters, or all entries if no filters.
     */
    public function deleteAll(array $filters = []): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_auth_logs';
        [$whereClause, $values] = $this->buildWhereClause($filters);

        $sql = "DELETE FROM {$table} {$whereClause}";

        if (!empty($values)) {
            return (int) $wpdb->query($wpdb->prepare($sql, ...$values));
        }

        return (int) $wpdb->query($sql);
    }

    /**
     * Delete log entries older than the specified number of days.
     */
    public function deleteOlderThan(int $days): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_auth_logs';

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days,
            ),
        );
    }

    /**
     * @return array{0: string, 1: array}
     */
    private function buildWhereClause(array $filters): array
    {
        $where = [];
        $values = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $values[] = $filters['user_id'];
        }

        if (!empty($filters['event'])) {
            $where[] = 'event = %s';
            $values[] = $filters['event'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $values[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $values[] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        return [$whereClause, $values];
    }

    private function getVerbosity(): LogVerbosity
    {
        if ($this->verbosity === null) {
            $settings = get_option('wsms_auth_settings', []);
            $level = $settings['log_verbosity'] ?? 'standard';
            $this->verbosity = LogVerbosity::tryFrom($level) ?? LogVerbosity::Standard;
        }

        return $this->verbosity;
    }

    private function getIpAddress(): string
    {
        return IpResolver::resolve();
    }
}
