<?php

namespace WSms\Log;

use WSms\Database\Connection;
use WSms\Dependencies\Symfony\Component\Uid\Ulid;
use WSms\Log\Contracts\MessageLoggerInterface;

defined('ABSPATH') || exit;

class MessageLogger implements MessageLoggerInterface
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function logSend(
        string $gatewayId,
        string $channel,
        string $recipient,
        string $body,
        string $status,
        ?string $executionId = null,
        ?string $subject = null,
        ?string $providerId = null,
        ?string $error = null,
        ?float $cost = null,
        string $type = 'transactional',
        ?string $campaignId = null,
    ): string {
        $id = (string) new Ulid();
        $now = current_time('mysql');

        $this->db->insert(Connection::TABLE_MESSAGE_LOGS, [
            'id'            => $id,
            'execution_id'  => $executionId,
            'campaign_id'   => $campaignId,
            'gateway_id'    => $gatewayId,
            'channel'       => $channel,
            'type'          => $type,
            'recipient'     => $recipient,
            'subject'       => $subject,
            'body_preview'  => mb_substr($body, 0, 500),
            'status'        => $status,
            'provider_id'   => $providerId,
            'error'         => $error,
            'cost'          => $cost,
            'sent_at'       => $status === 'sent' ? $now : null,
            'created_at'    => $now,
        ]);

        return $id;
    }

    public function updateStatus(string $logId, string $status, ?string $error = null, ?string $providerId = null): void
    {
        $data = ['status' => $status];
        if ($providerId !== null) {
            $data['provider_id'] = $providerId;
        }
        if ($error !== null) {
            $data['error'] = $error;
        }
        if ($status === 'sent') {
            $data['sent_at'] = current_time('mysql');
        }
        if ($status === 'delivered') {
            $data['delivered_at'] = current_time('mysql');
        }

        $this->db->update(Connection::TABLE_MESSAGE_LOGS, $data, ['id' => $logId]);
    }

    public function findByProviderId(string $gatewayId, string $providerId): ?array
    {
        $table = $this->db->table(Connection::TABLE_MESSAGE_LOGS);

        return $this->db->getRow(
            "SELECT id, campaign_id FROM {$table} WHERE gateway_id = %s AND provider_id = %s ORDER BY created_at DESC LIMIT 1",
            $gatewayId,
            $providerId,
        );
    }

    public function findByExecution(string $executionId): array
    {
        $table = $this->db->table(Connection::TABLE_MESSAGE_LOGS);

        return $this->db->getResults(
            "SELECT * FROM {$table} WHERE execution_id = %s ORDER BY created_at ASC",
            $executionId,
        );
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $table = $this->db->table(Connection::TABLE_MESSAGE_LOGS);

        [$where, $params] = $this->buildWhereClause($filters);

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->getResults($sql, ...$params);
    }

    public function count(array $filters = []): int
    {
        $table = $this->db->table(Connection::TABLE_MESSAGE_LOGS);

        [$where, $params] = $this->buildWhereClause($filters);

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

        return (int) $this->db->getVar($sql, ...$params);
    }

    public function deleteOlderThan(int $days): int
    {
        $table = $this->db->table(Connection::TABLE_MESSAGE_LOGS);

        return $this->db->query(
            "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days,
        );
    }

    public function getCampaignStats(string $campaignId): array
    {
        $table = $this->db->table(Connection::TABLE_MESSAGE_LOGS);

        $row = $this->db->getRow(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued,
                SUM(COALESCE(cost, 0)) as total_cost
            FROM {$table}
            WHERE campaign_id = %s",
            $campaignId,
        );

        return [
            'total'      => (int) ($row['total'] ?? 0),
            'delivered'  => (int) ($row['delivered'] ?? 0),
            'failed'     => (int) ($row['failed'] ?? 0),
            'sent'       => (int) ($row['sent'] ?? 0),
            'queued'     => (int) ($row['queued'] ?? 0),
            'total_cost' => (float) ($row['total_cost'] ?? 0),
        ];
    }

    public function findByRecipients(array $recipients, int $limit = 20, int $offset = 0): array
    {
        $recipients = array_filter($recipients);
        if (empty($recipients)) {
            return [];
        }

        $table = $this->db->table(Connection::TABLE_MESSAGE_LOGS);

        $placeholders = implode(', ', array_fill(0, count($recipients), '%s'));
        $params = array_values($recipients);
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->getResults(
            "SELECT id, status, channel, gateway_id, body_preview, sent_at, created_at
             FROM {$table}
             WHERE recipient IN ({$placeholders})
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            ...$params,
        );
    }

    public function findSentRecipients(string $campaignId, array $addresses): array
    {
        if (empty($addresses)) {
            return [];
        }

        $t = $this->db->table(Connection::TABLE_MESSAGE_LOGS);
        $ph = implode(',', array_fill(0, count($addresses), '%s'));
        $params = array_merge([$campaignId], $addresses);

        $rows = $this->db->getCol(
            "SELECT DISTINCT recipient FROM {$t} WHERE campaign_id = %s AND recipient IN ({$ph})",
            ...$params,
        );

        /** @var array<string, int> */
        return array_flip($rows);
    }

    /** @return array{string, array} */
    private function buildWhereClause(array $filters): array
    {
        $where = '1=1';
        $params = [];

        if (!empty($filters['channel'])) {
            $where .= ' AND channel = %s';
            $params[] = $filters['channel'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND status = %s';
            $params[] = $filters['status'];
        }
        if (!empty($filters['recipient'])) {
            $where .= ' AND recipient LIKE %s';
            $params[] = '%' . $this->db->escLike($filters['recipient']) . '%';
        }
        if (!empty($filters['gateway_id'])) {
            $where .= ' AND gateway_id = %s';
            $params[] = $filters['gateway_id'];
        }
        if (!empty($filters['campaign_id'])) {
            $where .= ' AND campaign_id = %s';
            $params[] = $filters['campaign_id'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND created_at >= %s';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND created_at <= %s';
            $params[] = $filters['date_to'];
        }

        return [$where, $params];
    }
}
