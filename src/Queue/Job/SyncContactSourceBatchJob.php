<?php

namespace WSms\Queue\Job;

use WSms\Queue\Contracts\JobInterface;

defined('ABSPATH') || exit;

class SyncContactSourceBatchJob implements JobInterface
{
    public function __construct(
        private readonly string $sourceType,
        private readonly int $batchSize = 100,
        private readonly ?int $afterId = null,
    ) {
    }

    public function getType(): string
    {
        return 'sync_contact_source_batch';
    }

    public function getPayload(): array
    {
        return [
            'source_type' => $this->sourceType,
            'batch_size'  => $this->batchSize,
            'after_id'    => $this->afterId,
        ];
    }

    public function getMaxRetries(): int
    {
        return 3;
    }

    public function getRetryBackoff(): int
    {
        return 60;
    }
}
