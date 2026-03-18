<?php

namespace WSms\Campaign;

defined('ABSPATH') || exit;

class CampaignRepository
{
    public function save(Campaign $campaign): string
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_campaigns';

        $data = [
            'id'                => $campaign->getId(),
            'name'              => $campaign->getName(),
            'channel'           => $campaign->getChannel(),
            'gateway_id'        => $campaign->getGatewayId(),
            'status'            => $campaign->getStatus(),
            'subject'           => $campaign->getSubject(),
            'body'              => $campaign->getBody(),
            'audience'          => wp_json_encode($campaign->getAudience()),
            'compliance'        => $campaign->getCompliance() ? wp_json_encode($campaign->getCompliance()) : null,
            'send_at'           => $campaign->getSendAt()?->format('Y-m-d H:i:s'),
            'timezone'          => $campaign->getTimezone(),
            'recurrence'        => $campaign->getRecurrence() ? wp_json_encode($campaign->getRecurrence()) : null,
            'quiet_hours'       => $campaign->getQuietHours() ? wp_json_encode($campaign->getQuietHours()) : null,
            'parent_id'         => $campaign->getParentId(),
            'total_recipients'  => $campaign->getTotalRecipients(),
            'sent_count'        => $campaign->getSentCount(),
            'delivered_count'   => $campaign->getDeliveredCount(),
            'failed_count'      => $campaign->getFailedCount(),
            'skipped_count'     => $campaign->getSkippedCount(),
            'total_cost'        => $campaign->getTotalCost(),
            'last_processed_id' => $campaign->getLastProcessedId(),
            'created_by'        => $campaign->getCreatedBy(),
            'started_at'        => $campaign->getStartedAt()?->format('Y-m-d H:i:s'),
            'completed_at'      => $campaign->getCompletedAt()?->format('Y-m-d H:i:s'),
            'cancelled_at'      => $campaign->getCancelledAt()?->format('Y-m-d H:i:s'),
        ];

        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id = %s", $campaign->getId()));

        if ($existing) {
            unset($data['id']);
            $data['updated_at'] = current_time('mysql');
            $wpdb->update($table, $data, ['id' => $campaign->getId()]);
        } else {
            $data['created_at'] = current_time('mysql');
            $data['updated_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
        }

        return $campaign->getId();
    }

    public function find(string $id): ?Campaign
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_campaigns';

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %s", $id), ARRAY_A);

        return $row ? Campaign::fromArray($row) : null;
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_campaigns';

        [$where, $params] = $this->buildWhereClause($filters, $wpdb);

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return array_map(fn($row) => Campaign::fromArray($row), $rows ?: []);
    }

    public function count(array $filters = []): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_campaigns';

        [$where, $params] = $this->buildWhereClause($filters, $wpdb);

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

        if (empty($params)) {
            return (int) $wpdb->get_var($sql);
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    public function delete(string $id): bool
    {
        global $wpdb;
        return (bool) $wpdb->delete($wpdb->prefix . 'wsms_campaigns', ['id' => $id]);
    }

    public function updateStatus(string $id, string $status, ?array $extra = null): bool
    {
        global $wpdb;

        $data = ['status' => $status, 'updated_at' => current_time('mysql')];
        if ($extra) {
            $data = array_merge($data, $extra);
        }

        return (bool) $wpdb->update($wpdb->prefix . 'wsms_campaigns', $data, ['id' => $id]);
    }

    public function incrementCounters(string $id, string $field, int $amount = 1): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_campaigns';

        $allowed = ['sent_count', 'delivered_count', 'failed_count', 'skipped_count'];
        if (!in_array($field, $allowed)) {
            return false;
        }

        return (bool) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET {$field} = {$field} + %d, updated_at = %s WHERE id = %s",
                $amount,
                current_time('mysql'),
                $id,
            ),
        );
    }

    public function incrementCost(string $id, float $amount): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_campaigns';

        return (bool) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET total_cost = total_cost + %f, updated_at = %s WHERE id = %s",
                $amount,
                current_time('mysql'),
                $id,
            ),
        );
    }

    public function updateLastProcessedId(string $id, string $lastId): bool
    {
        global $wpdb;
        return (bool) $wpdb->update(
            $wpdb->prefix . 'wsms_campaigns',
            ['last_processed_id' => $lastId, 'updated_at' => current_time('mysql')],
            ['id' => $id],
        );
    }

    public function findDueForSending(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_campaigns';
        $now = gmdate('Y-m-d H:i:s');
        $staleThreshold = gmdate('Y-m-d H:i:s', strtotime('-24 hours'));

        // Mark stale scheduled campaigns as failed
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = 'failed', updated_at = %s WHERE status = 'scheduled' AND send_at < %s",
                current_time('mysql'),
                $staleThreshold,
            ),
        );

        // Return campaigns due for sending
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = 'scheduled' AND send_at <= %s ORDER BY send_at ASC",
                $now,
            ),
            ARRAY_A,
        );

        return array_map(fn($row) => Campaign::fromArray($row), $rows ?: []);
    }

    /** @return array{string, array} */
    private function buildWhereClause(array $filters, object $wpdb): array
    {
        $where = '1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $where .= ' AND status = %s';
            $params[] = $filters['status'];
        }
        if (!empty($filters['channel'])) {
            $where .= ' AND channel = %s';
            $params[] = $filters['channel'];
        }

        return [$where, $params];
    }
}
