<?php

namespace WSms\Queue\Job;

use WSms\Queue\Contracts\JobInterface;

defined('ABSPATH') || exit;

class ExecuteFlowStepJob implements JobInterface
{
    public function __construct(
        private readonly string $executionId,
        private readonly array $node,
        private readonly array $payload,
    ) {
    }

    public function getType(): string
    {
        return 'execute_flow_step';
    }

    public function getPayload(): array
    {
        return [
            'execution_id' => $this->executionId,
            'node'         => $this->node,
            'payload'      => $this->payload,
        ];
    }

    public function getMaxRetries(): int
    {
        return 2;
    }

    public function getRetryBackoff(): int
    {
        return 30;
    }
}
