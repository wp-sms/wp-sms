<?php

namespace WSms\Flow\Storage;

use WSms\Dependencies\Symfony\Component\Uid\Ulid;
use WSms\Enums\ExecutionStatus;

defined('ABSPATH') || exit;

class FlowExecutionRepository
{
    public function create(string $flowId, array $triggerData): string
    {
        global $wpdb;

        $id = (string) new Ulid();

        $wpdb->insert($wpdb->prefix . 'wsms_flow_executions', [
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
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_flow_executions';

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %s", $id), ARRAY_A) ?: null;
    }

    public function updateStatus(string $id, string $status): void
    {
        global $wpdb;
        $data = ['status' => $status];
        $now = gmdate('Y-m-d H:i:s');

        $terminal = [ExecutionStatus::Completed->value, ExecutionStatus::Failed->value, ExecutionStatus::Cancelled->value];
        if (in_array($status, $terminal, true)) {
            $data['completed_at'] = $now;
        }

        $wpdb->update($wpdb->prefix . 'wsms_flow_executions', $data, ['id' => $id]);
    }

    public function setError(string $id, string $error): void
    {
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'wsms_flow_executions', [
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
        global $wpdb;

        $wpdb->update($wpdb->prefix . 'wsms_flow_executions', [
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
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_flow_executions';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s AND wait_event_type = %s",
                ExecutionStatus::Waiting->value,
                $eventType,
            ),
            ARRAY_A,
        ) ?: [];
    }

    public function clearWaitState(string $id): void
    {
        global $wpdb;

        $wpdb->update($wpdb->prefix . 'wsms_flow_executions', [
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
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_flow_executions';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE flow_id = %s ORDER BY started_at DESC LIMIT %d OFFSET %d",
                $flowId,
                $limit,
                $offset,
            ),
            ARRAY_A,
        ) ?: [];
    }

    public function countByFlow(string $flowId): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_flow_executions';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE flow_id = %s",
            $flowId,
        ));
    }

    public function cleanupExpiredWaits(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_flow_executions';
        $now = gmdate('Y-m-d H:i:s');

        $cancelled = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = %s, completed_at = %s
             WHERE status = %s AND wait_timeout_at < %s AND wait_timeout_action = 'cancel'",
            ExecutionStatus::Cancelled->value,
            $now,
            ExecutionStatus::Waiting->value,
            $now,
        ));

        return (int) $cancelled;
    }
}
