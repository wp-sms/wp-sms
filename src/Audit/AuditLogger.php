<?php

namespace WSms\Audit;

use WSms\Auth\SettingsRepository;
use WSms\Database\Connection;
use WSms\Enums\EventType;
use WSms\Enums\LogVerbosity;
use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Event\Events\AuthEvent;
use WSms\Support\GeoCountryResolver;
use WSms\Support\IpResolver;

defined('ABSPATH') || exit;

class AuditLogger
{
    private ?LogVerbosity $verbosity = null;
    private ?EventDispatcherInterface $eventDispatcher = null;

    public function __construct(
        private readonly Connection $db,
        private SettingsRepository $settingsRepo,
    ) {
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

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
            $data['ip_address']  = $this->getIpAddress();
            $data['geo_country'] = GeoCountryResolver::resolveRaw();
            $data['user_agent']  = isset($_SERVER['HTTP_USER_AGENT'])
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

        $this->db->insert(Connection::TABLE_AUTH_LOGS, $data);

        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch(new AuthEvent($event, $status, $userId, $meta));
        }
    }

    /**
     * Get audit log events with optional filtering.
     *
     * @return array{items: array, total: int}
     */
    public function getEvents(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $table = $this->db->table(Connection::TABLE_AUTH_LOGS);
        [$whereClause, $values] = $this->buildWhereClause($filters);

        $countSql = "SELECT COUNT(*) FROM {$table} {$whereClause}";
        $total = (int) $this->db->getVar($countSql, ...$values);

        $offset = ($page - 1) * $perPage;
        $querySql = "SELECT * FROM {$table} {$whereClause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $queryValues = array_merge($values, [$perPage, $offset]);

        $items = $this->db->getResults($querySql, ...$queryValues);

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Delete log entries matching the given filters, or all entries if no filters.
     */
    public function deleteAll(array $filters = []): int
    {
        $table = $this->db->table(Connection::TABLE_AUTH_LOGS);
        [$whereClause, $values] = $this->buildWhereClause($filters);

        $sql = "DELETE FROM {$table} {$whereClause}";

        return $this->db->query($sql, ...$values);
    }

    /**
     * Delete log entries older than the specified number of days.
     */
    public function deleteOlderThan(int $days): int
    {
        $table = $this->db->table(Connection::TABLE_AUTH_LOGS);

        return $this->db->query(
            "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days,
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
            $this->verbosity = LogVerbosity::tryFrom($this->settingsRepo->get('log_verbosity'))
                ?? LogVerbosity::Standard;
        }

        return $this->verbosity;
    }

    private function getIpAddress(): string
    {
        return IpResolver::resolve();
    }
}
