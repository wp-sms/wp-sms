<?php

namespace WSms\Queue\Job;

use WSms\Queue\Contracts\JobInterface;

defined('ABSPATH') || exit;

class EvaluateTriggerJob implements JobInterface
{
    public function __construct(
        private readonly string $triggerType,
        private readonly array $payload,
    ) {
    }

    public function getType(): string
    {
        return 'evaluate_trigger';
    }

    public function getPayload(): array
    {
        return [
            'trigger_type' => $this->triggerType,
            'payload'      => $this->payload,
        ];
    }

    public function getMaxRetries(): int
    {
        return 1;
    }

    public function getRetryBackoff(): int
    {
        return 30;
    }
}
