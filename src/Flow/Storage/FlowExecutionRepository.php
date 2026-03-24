<?php

namespace WSms\Flow\Storage;

use WSms\Database\Connection;
use WSms\Dependencies\Symfony\Component\Uid\Ulid;
use WSms\Enums\ExecutionStatus;

defined('ABSPATH') || exit;

class FlowExecutionRepository
{
    public function __construct(private readonly Connection $db) {}

    public function create(string $flowId, array $triggerData): string
    {
        $id = (string) new Ulid();

        $this->db->insert(Connection::TABLE_FLOW_EXECUTIONS, [
            'id'           => $id,
            'flow_id'      => $flowId,
            'trigger_data' => wp_json_encode($triggerData),
            'status'       => ExecutionStatus::Pending->value,
            'step_logs'    => '[]',
            'started_at'   => gmdate('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    public function find(string $id): ?array
    {
        $table = $this->db->table(Connection::TABLE_FLOW_EXECUTIONS);

        return $this->db->getRow("SELECT * FROM {$table} WHERE id = %s", $id);
    }

    public function updateStatus(string $id, string $status): void
    {
        $data = ['status' => $status];
        $now = gmdate('Y-m-d H:i:s');

        $terminal = [ExecutionStatus::Completed->value, ExecutionStatus::Failed->value, ExecutionStatus::Cancelled->value];
        if (in_array($status, $terminal, true)) {
            $data['completed_at'] = $now;
        }

        $this->db->update(Connection::TABLE_FLOW_EXECUTIONS, $data, ['id' => $id]);
    }

    public function setError(string $id, string $error): void
    {
        $this->db->update(Connection::TABLE_FLOW_EXECUTIONS, [
            'error'        => $error,
            'status'       => ExecutionStatus::Failed->value,
            'completed_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function setWaiting(
        string $id,
        string $eventType,
        string $matchExpr,
        string $nodeId,
        array $payload,
        int $timeout,
        string $timeoutAction,
    ): void {
        $this->db->update(Connection::TABLE_FLOW_EXECUTIONS, [
            'status'              => ExecutionStatus::Waiting->value,
            'wait_event_type'     => $eventType,
            'wait_match_expr'     => $matchExpr,
            'wait_node_id'        => $nodeId,
            'wait_payload'        => wp_json_encode($payload),
            'wait_timeout_at'     => gmdate('Y-m-d H:i:s', time() + $timeout),
            'wait_timeout_action' => $timeoutAction,
        ], ['id' => $id]);
    }

    public function findWaitingFor(string $eventType): array
    {
        $table = $this->db->table(Connection::TABLE_FLOW_EXECUTIONS);

        return $this->db->getResults(
            "SELECT * FROM {$table} WHERE status = %s AND wait_event_type = %s",
            ExecutionStatus::Waiting->value,
            $eventType,
        );
    }

    public function clearWaitState(string $id): void
    {
        $this->db->update(Connection::TABLE_FLOW_EXECUTIONS, [
            'status'              => ExecutionStatus::Running->value,
            'wait_event_type'     => null,
            'wait_match_expr'     => null,
            'wait_node_id'        => null,
            'wait_payload'        => null,
            'wait_timeout_at'     => null,
            'wait_timeout_action' => null,
        ], ['id' => $id]);
    }

    public function findByFlow(string $flowId, int $limit = 50, int $offset = 0): array
    {
        $table = $this->db->table(Connection::TABLE_FLOW_EXECUTIONS);

        return $this->db->getResults(
            "SELECT * FROM {$table} WHERE flow_id = %s ORDER BY started_at DESC LIMIT %d OFFSET %d",
            $flowId,
            $limit,
            $offset,
        );
    }

    public function countByFlow(string $flowId): int
    {
        $table = $this->db->table(Connection::TABLE_FLOW_EXECUTIONS);

        return (int) $this->db->getVar(
            "SELECT COUNT(*) FROM {$table} WHERE flow_id = %s",
            $flowId,
        );
    }

    public function cleanupExpiredWaits(): int
    {
        $table = $this->db->table(Connection::TABLE_FLOW_EXECUTIONS);
        $now = gmdate('Y-m-d H:i:s');

        return $this->db->query(
            "UPDATE {$table} SET status = %s, completed_at = %s
             WHERE status = %s AND wait_timeout_at < %s AND wait_timeout_action = 'cancel'",
            ExecutionStatus::Cancelled->value,
            $now,
            ExecutionStatus::Waiting->value,
            $now,
        );
    }
}
