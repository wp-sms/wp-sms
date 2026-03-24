<?php

namespace WSms\Campaign;

use WSms\Database\Connection;
use WSms\Exception\NotFoundException;

defined('ABSPATH') || exit;

class CampaignRepository
{
    public function __construct(private readonly Connection $db) {}

    public function save(Campaign $campaign): string
    {
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

        $table = $this->db->table(Connection::TABLE_CAMPAIGNS);
        $existing = $this->db->getVar("SELECT id FROM {$table} WHERE id = %s", $campaign->getId());

        if ($existing) {
            unset($data['id']);
            $data['updated_at'] = current_time('mysql');
            $this->db->update(Connection::TABLE_CAMPAIGNS, $data, ['id' => $campaign->getId()]);
        } else {
            $data['created_at'] = current_time('mysql');
            $data['updated_at'] = current_time('mysql');
            $this->db->insert(Connection::TABLE_CAMPAIGNS, $data);
        }

        return $campaign->getId();
    }

    public function find(string $id): ?Campaign
    {
        $table = $this->db->table(Connection::TABLE_CAMPAIGNS);
        $row = $this->db->getRow("SELECT * FROM {$table} WHERE id = %s", $id);

        return $row ? Campaign::fromArray($row) : null;
    }

    public function findOrFail(string $id): Campaign
    {
        $campaign = $this->find($id);

        if ($campaign === null) {
            throw NotFoundException::entity('Campaign', $id);
        }

        return $campaign;
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $table = $this->db->table(Connection::TABLE_CAMPAIGNS);
        [$where, $params] = $this->buildWhereClause($filters);

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->db->getResults($sql, ...$params);

        return array_map(fn($row) => Campaign::fromArray($row), $rows);
    }

    public function count(array $filters = []): int
    {
        $table = $this->db->table(Connection::TABLE_CAMPAIGNS);
        [$where, $params] = $this->buildWhereClause($filters);

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

        return (int) $this->db->getVar($sql, ...$params);
    }

    public function delete(string $id): bool
    {
        return (bool) $this->db->delete(Connection::TABLE_CAMPAIGNS, ['id' => $id]);
    }

    public function updateStatus(string $id, string $status, ?array $extra = null): bool
    {
        $data = ['status' => $status, 'updated_at' => current_time('mysql')];
        if ($extra) {
            $data = array_merge($data, $extra);
        }

        return (bool) $this->db->update(Connection::TABLE_CAMPAIGNS, $data, ['id' => $id]);
    }

    public function incrementCounters(string $id, string $field, int $amount = 1): bool
    {
        $table = $this->db->table(Connection::TABLE_CAMPAIGNS);

        $allowed = ['sent_count', 'delivered_count', 'failed_count', 'skipped_count'];
        if (!in_array($field, $allowed)) {
            return false;
        }

        return (bool) $this->db->query(
            "UPDATE {$table} SET {$field} = {$field} + %d, updated_at = %s WHERE id = %s",
            $amount,
            current_time('mysql'),
            $id,
        );
    }

    public function incrementCost(string $id, float $amount): bool
    {
        $table = $this->db->table(Connection::TABLE_CAMPAIGNS);

        return (bool) $this->db->query(
            "UPDATE {$table} SET total_cost = total_cost + %f, updated_at = %s WHERE id = %s",
            $amount,
            current_time('mysql'),
            $id,
        );
    }

    public function updateLastProcessedId(string $id, string $lastId): bool
    {
        return (bool) $this->db->update(
            Connection::TABLE_CAMPAIGNS,
            ['last_processed_id' => $lastId, 'updated_at' => current_time('mysql')],
            ['id' => $id],
        );
    }

    public function findDueForSending(): array
    {
        $table = $this->db->table(Connection::TABLE_CAMPAIGNS);
        $now = gmdate('Y-m-d H:i:s');
        $staleThreshold = gmdate('Y-m-d H:i:s', strtotime('-24 hours'));

        // Mark stale scheduled campaigns as failed
        $this->db->query(
            "UPDATE {$table} SET status = 'failed', updated_at = %s WHERE status = 'scheduled' AND send_at < %s",
            current_time('mysql'),
            $staleThreshold,
        );

        // Return campaigns due for sending
        $rows = $this->db->getResults(
            "SELECT * FROM {$table} WHERE status = 'scheduled' AND send_at <= %s ORDER BY send_at ASC",
            $now,
        );

        return array_map(fn($row) => Campaign::fromArray($row), $rows);
    }

    /** @return array{string, array} */
    private function buildWhereClause(array $filters): array
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
