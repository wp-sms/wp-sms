<?php

namespace WSms\Queue\Job;

use WSms\Queue\Contracts\JobInterface;

defined('ABSPATH') || exit;

class MigrationBatchJob implements JobInterface
{
    public function __construct(
        private readonly string $migratorId,
        private readonly string $stepId,
        private readonly ?string $cursor = null,
        private readonly int $batchSize = 500,
    ) {
    }

    public function getType(): string
    {
        return 'migration_batch';
    }

    public function getPayload(): array
    {
        return [
            'migrator_id' => $this->migratorId,
            'step_id'     => $this->stepId,
            'cursor'      => $this->cursor,
            'batch_size'  => $this->batchSize,
        ];
    }

    public function getMaxRetries(): int
    {
        return 3;
    }

    public function getRetryBackoff(): int
    {
        return 120;
    }
}
