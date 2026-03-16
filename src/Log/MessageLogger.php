<?php

namespace WSms\Log;

use WSms\Dependencies\Symfony\Component\Uid\Ulid;
use WSms\Log\Contracts\MessageLoggerInterface;

defined('ABSPATH') || exit;

class MessageLogger implements MessageLoggerInterface
{
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
    ): string {
        global $wpdb;

        $id = (string) new Ulid();
        $now = current_time('mysql');

        $wpdb->insert($wpdb->prefix . 'wsms_message_logs', [
            'id'            => $id,
            'execution_id'  => $executionId,
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

    public function updateStatus(string $logId, string $status, ?string $providerId = null): void
    {
        global $wpdb;

        $data = ['status' => $status];
        if ($providerId !== null) {
            $data['provider_id'] = $providerId;
        }
        if ($status === 'delivered') {
            $data['delivered_at'] = current_time('mysql');
        }

        $wpdb->update($wpdb->prefix . 'wsms_message_logs', $data, ['id' => $logId]);
    }

    public function findByExecution(string $executionId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_message_logs';

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE execution_id = %s ORDER BY created_at ASC", $executionId),
            ARRAY_A
        ) ?: [];
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_message_logs';

        [$where, $params] = $this->buildWhereClause($filters, $wpdb);

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];
    }

    public function count(array $filters = []): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_message_logs';

        [$where, $params] = $this->buildWhereClause($filters, $wpdb);

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

        if (empty($params)) {
            return (int) $wpdb->get_var($sql);
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    public function deleteOlderThan(int $days): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_message_logs';

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days,
            ),
        );
    }

    /** @return array{string, array} */
    private function buildWhereClause(array $filters, object $wpdb): array
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
            $params[] = '%' . $wpdb->esc_like($filters['recipient']) . '%';
        }
        if (!empty($filters['gateway_id'])) {
            $where .= ' AND gateway_id = %s';
            $params[] = $filters['gateway_id'];
        }

        return [$where, $params];
    }
}
