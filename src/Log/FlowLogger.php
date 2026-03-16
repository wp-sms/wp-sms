<?php

namespace WSms\Log;

use WSms\Dependencies\Psr\Log\LoggerInterface;

defined('ABSPATH') || exit;

class FlowLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function appendStepLog(string $executionId, array $stepLog): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_flow_executions';

        // Atomic append using JSON_ARRAY_APPEND to avoid read-modify-write race
        // condition when parallel branches log concurrently.
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET step_logs = JSON_ARRAY_APPEND(COALESCE(step_logs, '[]'), '$', CAST(%s AS JSON)) WHERE id = %s",
            wp_json_encode($stepLog),
            $executionId,
        ));
    }

    public function logStepStart(string $executionId, string $nodeId, string $type, array $input = []): void
    {
        $this->appendStepLog($executionId, [
            'node_id' => $nodeId,
            'type'    => $type,
            'status'  => 'started',
            'input'   => $input,
            'at'      => gmdate('Y-m-d\TH:i:s\Z'),
        ]);

        $this->logger->debug("Flow step started: {$nodeId} ({$type})", [
            'execution_id' => $executionId,
        ]);
    }

    public function logStepComplete(string $executionId, string $nodeId, string $type, array $output = []): void
    {
        $this->appendStepLog($executionId, [
            'node_id' => $nodeId,
            'type'    => $type,
            'status'  => 'completed',
            'output'  => $output,
            'at'      => gmdate('Y-m-d\TH:i:s\Z'),
        ]);

        $this->logger->debug("Flow step completed: {$nodeId} ({$type})", [
            'execution_id' => $executionId,
        ]);
    }

    public function logStepError(string $executionId, string $nodeId, string $type, string $error): void
    {
        $this->appendStepLog($executionId, [
            'node_id' => $nodeId,
            'type'    => $type,
            'status'  => 'failed',
            'error'   => $error,
            'at'      => gmdate('Y-m-d\TH:i:s\Z'),
        ]);

        $this->logger->error("Flow step failed: {$nodeId} ({$type}): {$error}", [
            'execution_id' => $executionId,
        ]);
    }
}
